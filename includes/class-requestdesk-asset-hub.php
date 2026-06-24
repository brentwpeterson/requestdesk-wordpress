<?php
/**
 * RequestDesk Brand Asset Hub
 *
 * A grab-and-go library for brand assets (promo banners, logos, mascots,
 * backgrounds). A brand asset is just a WordPress Media Library attachment
 * flagged for the hub, so its hosted URL (wp-content/uploads/...) is already
 * stable and public — no new storage infrastructure.
 *
 * Each asset is served with three copy-paste options:
 *   - Download   : the direct file URL
 *   - Image URL  : the hosted src (for emails)
 *   - Embed code : <a href="LINK"><img src="URL" alt="ALT" ...></a>
 *                  (the affiliate / partner-banner pattern, links back to the
 *                   offer page)
 *
 * Front-end render via [requestdesk_brand_asset id="123"].
 *
 * @package RequestDesk
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Asset_Hub {

    const MENU_SLUG     = 'requestdesk-brand-assets';
    const FLAG_META     = '_rd_brand_asset';      // '1' marks an attachment as a hub asset
    const LINK_META     = '_rd_asset_link_url';   // landing/offer URL for the embed href
    const ROTATION_META  = '_rd_asset_in_rotation'; // '1' = include in the random ad rotation
    const PLACEMENT_META = '_rd_asset_placement';   // 'banner' | 'sidebar' | 'any'
    const SETTINGS_OPT    = 'requestdesk_ads_settings'; // auto-insert config
    const HIDE_META       = '_rd_hide_ads';          // per-post: '1' = no auto ads
    const SPONSORED_META  = '_rd_asset_sponsored';   // '1' = rel="sponsored nofollow"
    const CLICKS_META     = '_rd_ad_clicks';         // lifetime click counter
    const IMPRESSIONS_META = '_rd_ad_impressions';   // lifetime impression counter
    const DEMO_META       = '_rd_demo_ad';           // marks a bundled demo (slug), for dedup
    const NONCE           = 'requestdesk_asset_hub';

    /** Bundled demo ads shipped inside the plugin (assets/img/demo-ads/). */
    private function demo_ads() {
        return array(
            array(
                'slug'      => 'banner-1200x300',
                'file'      => 'demo-ad-banner-1200x300.png',
                'title'     => 'Demo Ad — Banner 1200x300',
                'alt'       => '1980s mullet teen demo banner, 1200 by 300',
                'placement' => 'banner',
            ),
            array(
                'slug'      => 'sidebar-300x600',
                'file'      => 'demo-ad-sidebar-300x600.png',
                'title'     => 'Demo Ad — Sidebar 300x600',
                'alt'       => '1980s mullet teen demo sidebar ad, 300 by 600',
                'placement' => 'sidebar',
            ),
        );
    }

    /** @var bool front-end rotator assets enqueued once per request */
    private $frontend_enqueued = false;

    public function __construct() {
        // Priority 11 (not the default 10): this module instantiates at file-load,
        // which is BEFORE the main RequestDesk menu is built (requestdesk_add_admin_menu,
        // also priority 10, is hooked later during init). Registering at 10 would fire
        // first — before the parent 'requestdesk-aeo-analytics' menu exists — which made
        // Brand Assets the first submenu (so the parent linked here) AND produced a
        // malformed /wp-admin/requestdesk-brand-assets URL (404). 11 guarantees the parent
        // exists first.
        add_action('admin_menu', array($this, 'register_menu'), 11);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_shortcode('requestdesk_brand_asset', array($this, 'render_shortcode'));

        // Random ad rotator
        add_shortcode('requestdesk_random_ad', array($this, 'render_random_ad'));
        add_action('widgets_init', array($this, 'register_widget'));

        // Auto-insert banner into blog post bodies
        add_filter('the_content', array($this, 'auto_insert_ad'));
        add_action('add_meta_boxes', array($this, 'add_ad_metabox'));
        add_action('save_post', array($this, 'save_ad_metabox'));

        // Ad tracking: click redirect (cache-proof) + impression beacon
        add_action('init', array($this, 'handle_ad_click'));
        add_action('wp_ajax_requestdesk_ad_impression',        array($this, 'ajax_impression'));
        add_action('wp_ajax_nopriv_requestdesk_ad_impression', array($this, 'ajax_impression'));

        // AJAX (admin-only, nonce-protected)
        add_action('wp_ajax_requestdesk_asset_mark',   array($this, 'ajax_mark'));
        add_action('wp_ajax_requestdesk_asset_save',   array($this, 'ajax_save'));
        add_action('wp_ajax_requestdesk_asset_remove', array($this, 'ajax_remove'));
        add_action('wp_ajax_requestdesk_load_demos',   array($this, 'ajax_load_demos'));
    }

    /* ---------------------------------------------------------------- Admin */

    public function register_menu() {
        add_submenu_page(
            'requestdesk-aeo-analytics',           // under the main RequestDesk menu
            'Brand Assets',
            'Brand Assets',
            'manage_options',
            self::MENU_SLUG,
            array($this, 'render_page')
        );
    }

    public function enqueue_assets($hook) {
        // Only load on the Brand Assets page itself.
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }
        wp_enqueue_media(); // wp.media uploader frame

        wp_enqueue_style(
            'requestdesk-asset-hub',
            REQUESTDESK_PLUGIN_URL . 'assets/css/asset-hub.css',
            array(),
            REQUESTDESK_VERSION
        );
        wp_enqueue_script(
            'requestdesk-asset-hub',
            REQUESTDESK_PLUGIN_URL . 'assets/js/asset-hub.js',
            array('jquery'),
            REQUESTDESK_VERSION,
            true
        );
        wp_localize_script('requestdesk-asset-hub', 'RD_ASSET_HUB', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE),
        ));
    }

    /**
     * Collect all attachments flagged as brand assets, newest first.
     */
    private function get_assets() {
        $ids = get_posts(array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'meta_key'       => self::FLAG_META,
            'meta_value'     => '1',
        ));
        return array_map(array($this, 'asset_payload'), $ids);
    }

    /**
     * Everything the admin card (and AJAX responses) need for one asset.
     */
    public function asset_payload($id) {
        $id    = (int) $id;
        $src   = wp_get_attachment_url($id);
        $meta  = wp_get_attachment_metadata($id);
        $w     = isset($meta['width'])  ? (int) $meta['width']  : 0;
        $h     = isset($meta['height']) ? (int) $meta['height'] : 0;
        $thumb = wp_get_attachment_image_url($id, 'medium');

        return array(
            'id'        => $id,
            'title'     => get_the_title($id),
            'src'       => $src,
            'thumb'     => $thumb ? $thumb : $src,
            'width'     => $w,
            'height'    => $h,
            'alt'         => get_post_meta($id, '_wp_attachment_image_alt', true),
            'link'        => get_post_meta($id, self::LINK_META, true),
            'in_rotation' => get_post_meta($id, self::ROTATION_META, true) === '1',
            'placement'   => $this->get_placement($id),
            'sponsored'   => get_post_meta($id, self::SPONSORED_META, true) === '1',
            'clicks'      => (int) get_post_meta($id, self::CLICKS_META, true),
            'impressions' => (int) get_post_meta($id, self::IMPRESSIONS_META, true),
            'embed'       => $this->build_embed($id),
            'edit_link'   => get_edit_post_link($id, 'raw'),
        );
    }

    /**
     * Build the copy-paste embed snippet for an asset.
     * Returns the linked <a><img></a> when a link URL is set, else the bare <img>.
     */
    public function build_embed($id) {
        $src  = wp_get_attachment_url($id);
        if (!$src) {
            return '';
        }
        $meta = wp_get_attachment_metadata($id);
        $w    = isset($meta['width'])  ? (int) $meta['width']  : 0;
        $h    = isset($meta['height']) ? (int) $meta['height'] : 0;
        $alt  = get_post_meta($id, '_wp_attachment_image_alt', true);
        $link = get_post_meta($id, self::LINK_META, true);

        $dims = $w ? sprintf(' width="%d" height="%d"', $w, $h) : '';
        $img  = sprintf(
            '<img src="%s" alt="%s"%s style="max-width:100%%;height:auto;border:0;">',
            esc_url($src),
            esc_attr($alt),
            $dims
        );

        if ($link) {
            return sprintf(
                '<a href="%s" target="_blank" rel="noopener">%s</a>',
                esc_url($link),
                $img
            );
        }
        return $img;
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save auto-insert settings if submitted.
        if (isset($_POST['requestdesk_save_ad_settings']) &&
            check_admin_referer('requestdesk_ad_settings', 'requestdesk_ad_settings_nonce')) {
            update_option(self::SETTINGS_OPT, array(
                'auto_insert' => !empty($_POST['rd_auto_insert']),
                'paragraph'   => max(1, (int) ($_POST['rd_paragraph'] ?? 3)),
            ));
            echo '<div class="notice notice-success"><p>Ad settings saved.</p></div>';
        }

        $settings = $this->get_ad_settings();
        $assets   = $this->get_assets();
        ?>
        <div class="wrap rd-asset-hub">
            <h1>
                Brand Assets
                <button type="button" class="page-title-action" id="rd-asset-add">Add Brand Asset</button>
                <button type="button" class="page-title-action" id="rd-asset-demos">Load demo ads</button>
            </h1>
            <p class="description">
                A grab-and-go home for promo banners, logos, and brand images. Each asset
                serves a stable hosted URL plus a copy-paste embed snippet for partner sites,
                guest posts, emails, and internal pages. Use the shortcode
                <code>[requestdesk_brand_asset id="ID"]</code> to drop a linked banner into any page or post.
            </p>

            <div class="card" style="max-width:680px;">
                <h2 style="margin-top:0;">Blog Post Ads</h2>
                <p class="description">
                    Auto-insert a random <strong>banner</strong> ad into single blog posts. Sidebar ads are
                    placed with the <em>RequestDesk: Random Ad</em> widget (Appearance &rarr; Widgets).
                    Only assets with <strong>Include in ad rotation</strong> checked appear.
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field('requestdesk_ad_settings', 'requestdesk_ad_settings_nonce'); ?>
                    <p>
                        <label>
                            <input type="checkbox" name="rd_auto_insert" value="1" <?php checked(!empty($settings['auto_insert'])); ?>>
                            Auto-insert a banner ad into blog posts
                        </label>
                    </p>
                    <p>
                        <label>
                            After paragraph
                            <input type="number" name="rd_paragraph" min="1" max="20" step="1"
                                   value="<?php echo esc_attr($settings['paragraph']); ?>" class="small-text">
                        </label>
                        <span class="description">(posts shorter than this many paragraphs are skipped)</span>
                    </p>
                    <p>
                        <input type="submit" name="requestdesk_save_ad_settings" class="button button-primary" value="Save Ad Settings">
                    </p>
                </form>
            </div>

            <div id="rd-asset-grid" class="rd-asset-grid">
                <?php if (empty($assets)) : ?>
                    <div class="rd-asset-empty" id="rd-asset-empty">
                        <p>No brand assets yet. Click <strong>Add Brand Asset</strong> to pull one in from the Media Library (or upload a new one).</p>
                    </div>
                <?php else : ?>
                    <?php foreach ($assets as $a) : ?>
                        <?php $this->render_card($a); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * One asset card. Also used as the AJAX template (kept in PHP so escaping
     * stays server-side).
     */
    public function render_card($a) {
        ?>
        <div class="rd-asset-card" data-id="<?php echo (int) $a['id']; ?>">
            <div class="rd-asset-thumb">
                <img src="<?php echo esc_url($a['thumb']); ?>" alt="<?php echo esc_attr($a['alt']); ?>">
            </div>
            <div class="rd-asset-body">
                <div class="rd-asset-title"><?php echo esc_html($a['title']); ?></div>
                <div class="rd-asset-dims">
                    <?php echo $a['width'] ? esc_html($a['width'] . ' × ' . $a['height'] . ' px') : '&nbsp;'; ?>
                </div>

                <?php
                $imp = isset($a['impressions']) ? (int) $a['impressions'] : 0;
                $clk = isset($a['clicks']) ? (int) $a['clicks'] : 0;
                $ctr = $imp > 0 ? round(($clk / $imp) * 100, 1) : 0;
                ?>
                <div class="rd-asset-stats" title="Lifetime since first shown">
                    <?php echo esc_html(number_format_i18n($imp)); ?> views ·
                    <?php echo esc_html(number_format_i18n($clk)); ?> clicks ·
                    <?php echo esc_html($ctr); ?>% CTR
                </div>

                <label class="rd-asset-field">
                    <span>Links to</span>
                    <input type="url" class="rd-asset-link regular-text" value="<?php echo esc_attr($a['link']); ?>"
                           placeholder="https://contentcucumber.com/content-refresh-at-scale">
                </label>
                <label class="rd-asset-field">
                    <span>Alt text</span>
                    <input type="text" class="rd-asset-alt regular-text" value="<?php echo esc_attr($a['alt']); ?>"
                           placeholder="Content Refresh at Scale">
                </label>

                <label class="rd-asset-rotation">
                    <input type="checkbox" class="rd-asset-rotate" <?php checked(!empty($a['in_rotation'])); ?>>
                    <span>Include in ad rotation</span>
                </label>

                <label class="rd-asset-rotation">
                    <input type="checkbox" class="rd-asset-sponsored" <?php checked(!empty($a['sponsored'])); ?>>
                    <span>Sponsored (rel="sponsored")</span>
                </label>

                <label class="rd-asset-field rd-asset-placement-field">
                    <span>Ad placement</span>
                    <select class="rd-asset-placement">
                        <?php
                        $placement = !empty($a['placement']) ? $a['placement'] : 'any';
                        $opts = array(
                            'any'     => 'Any slot (banner or sidebar)',
                            'banner'  => 'Banner only (in-content / leaderboard)',
                            'sidebar' => 'Sidebar only (vertical / square)',
                        );
                        foreach ($opts as $val => $label) {
                            printf(
                                '<option value="%s"%s>%s</option>',
                                esc_attr($val),
                                selected($placement, $val, false),
                                esc_html($label)
                            );
                        }
                        ?>
                    </select>
                </label>

                <p class="rd-asset-actions-edit">
                    <button type="button" class="button button-primary rd-asset-savebtn">Save</button>
                    <a href="#" class="rd-asset-remove" role="button">Remove from hub</a>
                    <span class="rd-asset-saved" aria-live="polite"></span>
                </p>

                <div class="rd-asset-copy">
                    <button type="button" class="button rd-copy" data-copy="<?php echo esc_attr($a['src']); ?>">Download</button>
                    <button type="button" class="button rd-copy" data-copy="<?php echo esc_attr($a['src']); ?>">Image URL</button>
                    <button type="button" class="button rd-copy rd-copy-embed" data-copy="<?php echo esc_attr($a['embed']); ?>">Embed code</button>
                    <button type="button" class="button rd-copy" data-copy="<?php echo esc_attr('[requestdesk_brand_asset id="' . (int) $a['id'] . '"]'); ?>">Shortcode</button>
                </div>
            </div>
        </div>
        <?php
    }

    /* ----------------------------------------------------------------- AJAX */

    private function guard() {
        if (!current_user_can('manage_options') ||
            !check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Not allowed'), 403);
        }
    }

    /** Mark an existing/just-uploaded attachment as a brand asset. */
    public function ajax_mark() {
        $this->guard();
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id || get_post_type($id) !== 'attachment') {
            wp_send_json_error(array('message' => 'Invalid attachment'), 400);
        }
        update_post_meta($id, self::FLAG_META, '1');

        ob_start();
        $this->render_card($this->asset_payload($id));
        wp_send_json_success(array('html' => ob_get_clean()));
    }

    /** Save the link URL + alt text for an asset. */
    public function ajax_save() {
        $this->guard();
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id || get_post_type($id) !== 'attachment') {
            wp_send_json_error(array('message' => 'Invalid attachment'), 400);
        }
        $link      = isset($_POST['link']) ? esc_url_raw(trim(wp_unslash($_POST['link']))) : '';
        $alt       = isset($_POST['alt'])  ? sanitize_text_field(wp_unslash($_POST['alt'])) : '';
        $rotation  = !empty($_POST['rotation']);
        $placement = isset($_POST['placement']) ? sanitize_key(wp_unslash($_POST['placement'])) : 'any';
        if (!in_array($placement, array('any', 'banner', 'sidebar'), true)) {
            $placement = 'any';
        }

        update_post_meta($id, self::LINK_META, $link);
        update_post_meta($id, '_wp_attachment_image_alt', $alt);
        update_post_meta($id, self::PLACEMENT_META, $placement);
        if ($rotation) {
            update_post_meta($id, self::ROTATION_META, '1');
        } else {
            delete_post_meta($id, self::ROTATION_META);
        }
        if (!empty($_POST['sponsored'])) {
            update_post_meta($id, self::SPONSORED_META, '1');
        } else {
            delete_post_meta($id, self::SPONSORED_META);
        }

        wp_send_json_success(array(
            'embed' => $this->build_embed($id),
        ));
    }

    /** Remove an asset from the hub (keeps the Media Library file). */
    public function ajax_remove() {
        $this->guard();
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id) {
            delete_post_meta($id, self::FLAG_META);
        }
        wp_send_json_success();
    }

    /**
     * Sideload the bundled demo ads into the Media Library, flag them in
     * rotation with their placement, and link them to the homepage. Idempotent:
     * a demo already imported (matched by DEMO_META slug) is skipped.
     */
    public function ajax_load_demos() {
        $this->guard();

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $dir   = REQUESTDESK_PLUGIN_DIR . 'assets/img/demo-ads/';
        $cards = array();

        foreach ($this->demo_ads() as $demo) {
            // Dedup by marker meta.
            $existing = get_posts(array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => self::DEMO_META,
                'meta_value'     => $demo['slug'],
            ));
            if (!empty($existing)) {
                $id = (int) $existing[0];
            } else {
                $path = $dir . $demo['file'];
                if (!file_exists($path)) {
                    continue;
                }
                $tmp = wp_tempnam($demo['file']);
                if (!$tmp || !@copy($path, $tmp)) {
                    @unlink($tmp);
                    continue;
                }
                $id = media_handle_sideload(
                    array('name' => $demo['file'], 'tmp_name' => $tmp),
                    0,
                    $demo['title']
                );
                if (is_wp_error($id)) {
                    @unlink($tmp);
                    continue;
                }
            }

            update_post_meta($id, self::DEMO_META, $demo['slug']);
            update_post_meta($id, self::FLAG_META, '1');
            update_post_meta($id, self::ROTATION_META, '1');
            update_post_meta($id, self::PLACEMENT_META, $demo['placement']);
            update_post_meta($id, '_wp_attachment_image_alt', $demo['alt']);
            if (!get_post_meta($id, self::LINK_META, true)) {
                update_post_meta($id, self::LINK_META, home_url('/'));
            }

            ob_start();
            $this->render_card($this->asset_payload($id));
            $cards[] = ob_get_clean();
        }

        wp_send_json_success(array('cards' => $cards));
    }

    /* ------------------------------------------------------------ Shortcode */

    /**
     * [requestdesk_brand_asset id="123" link="" class="" width=""]
     * Renders the linked banner. Optional atts override the stored link/width.
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id'    => 0,
            'link'  => '',
            'class' => '',
            'width' => '',
        ), $atts, 'requestdesk_brand_asset');

        $id = (int) $atts['id'];
        if (!$id) {
            return '';
        }
        $src = wp_get_attachment_url($id);
        if (!$src) {
            return '<!-- requestdesk_brand_asset: attachment not found -->';
        }

        $alt  = get_post_meta($id, '_wp_attachment_image_alt', true);
        $link = $atts['link'] !== '' ? $atts['link'] : get_post_meta($id, self::LINK_META, true);
        $meta = wp_get_attachment_metadata($id);
        $w    = $atts['width'] !== '' ? (int) $atts['width'] : (isset($meta['width']) ? (int) $meta['width'] : 0);
        $h    = (isset($meta['width']) && $w && $meta['width'] && isset($meta['height']))
                ? (int) round($w * ($meta['height'] / $meta['width']))
                : (isset($meta['height']) ? (int) $meta['height'] : 0);

        $class = trim('rd-brand-asset ' . sanitize_html_class($atts['class']));
        $dims  = $w ? sprintf(' width="%d" height="%d"', $w, $h) : '';
        $img   = sprintf(
            '<img src="%s" alt="%s"%s class="%s" style="max-width:100%%;height:auto;border:0;" loading="lazy">',
            esc_url($src),
            esc_attr($alt),
            $dims,
            esc_attr($class)
        );

        if ($link) {
            return sprintf(
                '<a href="%s" target="_blank" rel="noopener">%s</a>',
                esc_url($link),
                $img
            );
        }
        return $img;
    }

    /* --------------------------------------------------------- Ad rotator */

    /** Normalize an asset's stored placement to one of any|banner|sidebar. */
    private function get_placement($id) {
        $p = get_post_meta($id, self::PLACEMENT_META, true);
        return in_array($p, array('banner', 'sidebar'), true) ? $p : 'any';
    }

    /**
     * All assets in the rotation, bucketed by the slot they can fill.
     * An 'any' asset goes into both the banner and sidebar buckets so it can
     * fill either slot. 'all' holds every ad regardless of placement.
     * Returns ['banner'=>[html...], 'sidebar'=>[...], 'all'=>[...]].
     */
    private function get_rotation_pools() {
        $ids = get_posts(array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                'relation' => 'AND',
                array('key' => self::FLAG_META,     'value' => '1'),
                array('key' => self::ROTATION_META, 'value' => '1'),
            ),
        ));

        $pools = array('banner' => array(), 'sidebar' => array(), 'all' => array());
        foreach ($ids as $id) {
            if (!wp_get_attachment_url($id)) {
                continue;
            }
            $placement = $this->get_placement($id);
            $pools['all'][] = $this->build_ad_html($id, '');
            if ($placement === 'banner' || $placement === 'any') {
                $pools['banner'][] = $this->build_ad_html($id, 'banner');
            }
            if ($placement === 'sidebar' || $placement === 'any') {
                $pools['sidebar'][] = $this->build_ad_html($id, 'sidebar');
            }
        }
        return $pools;
    }

    /**
     * One ad's markup: linked banner, responsive, no lazy-load (above fold).
     * The link routes through the click endpoint (cache-proof tracking) and
     * carries the ad id + placement so clicks/impressions are attributable.
     *
     * @param int    $id        attachment id
     * @param string $placement banner|sidebar|'' — stamps UTM medium on click
     */
    private function build_ad_html($id, $placement = '') {
        $src = wp_get_attachment_url($id);
        if (!$src) {
            return '';
        }
        $meta = wp_get_attachment_metadata($id);
        $w    = isset($meta['width'])  ? (int) $meta['width']  : 0;
        $h    = isset($meta['height']) ? (int) $meta['height'] : 0;
        $alt  = get_post_meta($id, '_wp_attachment_image_alt', true);
        $link = get_post_meta($id, self::LINK_META, true);

        $dims = $w ? sprintf(' width="%d" height="%d"', $w, $h) : '';
        $img  = sprintf(
            '<img src="%s" alt="%s"%s style="max-width:100%%;height:auto;border:0;display:block;">',
            esc_url($src),
            esc_attr($alt),
            $dims
        );

        // Bare image with no destination — still trackable for impressions.
        if (!$link) {
            return sprintf('<span class="rd-ad-link" data-rd-ad-id="%d">%s</span>', (int) $id, $img);
        }

        $click_url = add_query_arg(
            array('rd_ad_click' => (int) $id, 'p' => $placement),
            home_url('/')
        );
        $rel = get_post_meta($id, self::SPONSORED_META, true) === '1'
            ? 'noopener sponsored nofollow'
            : 'noopener';

        return sprintf(
            '<a href="%s" data-rd-ad-id="%d" target="_blank" rel="%s" class="rd-ad-link">%s</a>',
            esc_url($click_url),
            (int) $id,
            esc_attr($rel),
            $img
        );
    }

    /**
     * Enqueue the front-end rotator script/style once, and hand it the pools.
     * The full eligible pools (bucketed by placement) are localized so the
     * random pick happens in the browser on every load — this survives
     * full-page caching (a server-side pick would get frozen into the cache).
     */
    private function enqueue_frontend() {
        if ($this->frontend_enqueued) {
            return;
        }
        wp_enqueue_style(
            'requestdesk-ad-rotator',
            REQUESTDESK_PLUGIN_URL . 'assets/css/ad-rotator.css',
            array(),
            REQUESTDESK_VERSION
        );
        wp_enqueue_script(
            'requestdesk-ad-rotator',
            REQUESTDESK_PLUGIN_URL . 'assets/js/ad-rotator.js',
            array(),
            REQUESTDESK_VERSION,
            true
        );
        wp_localize_script('requestdesk-ad-rotator', 'RD_ADS', array(
            'pools'   => $this->get_rotation_pools(),
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ));
        $this->frontend_enqueued = true;
    }

    /** Map a requested placement to the bucket key the JS reads. */
    private function pool_key($placement) {
        return in_array($placement, array('banner', 'sidebar'), true) ? $placement : 'all';
    }

    /**
     * Shared render for the shortcode and the widget. Emits a placeholder
     * the rotator JS fills with `count` random ads (from the placement's
     * bucket) on load.
     */
    public function render_ad_slot($count = 1, $class = '', $placement = 'all') {
        $key   = $this->pool_key($placement);
        $pools = $this->get_rotation_pools();
        $pool  = isset($pools[$key]) ? $pools[$key] : array();

        if (empty($pool)) {
            return '<!-- requestdesk_random_ad: no ads in rotation for placement "' . esc_attr($key) . '" -->';
        }
        $this->enqueue_frontend();

        $count = max(1, (int) $count);
        $class = trim('rd-ad-slot rd-ad-slot--' . $key . ' ' . sanitize_html_class($class));

        // First ad is rendered server-side as a no-JS fallback (the JS swaps in
        // a random pick on load). Index 0 keeps SSR deterministic for caches.
        $fallback = $pool[0];

        return sprintf(
            '<div class="%s" data-rd-ad="1" data-count="%d" data-placement="%s">%s</div>',
            esc_attr($class),
            $count,
            esc_attr($key),
            $fallback
        );
    }

    /**
     * [requestdesk_random_ad count="1" placement="banner|sidebar|all" class=""]
     */
    public function render_random_ad($atts) {
        $atts = shortcode_atts(array(
            'count'     => 1,
            'placement' => 'all',
            'class'     => '',
        ), $atts, 'requestdesk_random_ad');

        return $this->render_ad_slot($atts['count'], $atts['class'], $atts['placement']);
    }

    public function register_widget() {
        register_widget('RequestDesk_Random_Ad_Widget');
    }

    /* ------------------------------------------------ Auto-insert into posts */

    /** Auto-insert settings with defaults. */
    public function get_ad_settings() {
        $defaults = array(
            'auto_insert' => false, // off until Brent turns it on
            'paragraph'   => 3,     // insert after the Nth paragraph
        );
        $saved = get_option(self::SETTINGS_OPT, array());
        return wp_parse_args(is_array($saved) ? $saved : array(), $defaults);
    }

    /**
     * Inject a banner ad after the Nth paragraph of single blog posts.
     * Cache-safe: the slot is a placeholder the rotator JS fills client-side.
     */
    public function auto_insert_ad($content) {
        // Only the main post body on a single post, in the main loop. Never
        // feeds, pages, archives, admin, REST, or secondary queries.
        if (is_admin() || is_feed() || !is_singular('post') || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $settings = $this->get_ad_settings();
        if (empty($settings['auto_insert'])) {
            return $content;
        }
        if (get_post_meta(get_the_ID(), self::HIDE_META, true) === '1') {
            return $content;
        }

        $ad = do_shortcode('[requestdesk_random_ad placement="banner" class="rd-ad-incontent"]');
        if (!$ad || strpos($ad, 'data-rd-ad') === false) {
            return $content; // no banner ads in rotation — insert nothing
        }

        $n          = max(1, (int) $settings['paragraph']);
        $closing    = '</p>';
        $paragraphs = explode($closing, $content);

        // Not enough paragraphs to reach N — leave the post untouched.
        $real = 0;
        foreach ($paragraphs as $p) {
            if (trim($p) !== '') { $real++; }
        }
        if ($real <= $n) {
            return $content;
        }

        $out   = '';
        $count = 0;
        foreach ($paragraphs as $paragraph) {
            if (trim($paragraph) === '') {
                $out .= $paragraph;
                continue;
            }
            $out .= $paragraph . $closing;
            $count++;
            if ($count === $n) {
                $out .= $ad;
            }
        }
        return $out;
    }

    /* ------------------------------------------ Per-post "hide ads" meta box */

    public function add_ad_metabox() {
        add_meta_box(
            'requestdesk_ads_box',
            'RequestDesk Ads',
            array($this, 'render_ad_metabox'),
            'post',
            'side',
            'default'
        );
    }

    public function render_ad_metabox($post) {
        wp_nonce_field('requestdesk_ads_metabox', 'requestdesk_ads_metabox_nonce');
        $hidden = get_post_meta($post->ID, self::HIDE_META, true) === '1';
        ?>
        <label style="display:flex;gap:6px;align-items:flex-start;">
            <input type="checkbox" name="requestdesk_hide_ads" value="1" <?php checked($hidden); ?>>
            <span>Hide auto-inserted ads in this post</span>
        </label>
        <?php
    }

    public function save_ad_metabox($post_id) {
        if (!isset($_POST['requestdesk_ads_metabox_nonce']) ||
            !wp_verify_nonce($_POST['requestdesk_ads_metabox_nonce'], 'requestdesk_ads_metabox')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (!empty($_POST['requestdesk_hide_ads'])) {
            update_post_meta($post_id, self::HIDE_META, '1');
        } else {
            delete_post_meta($post_id, self::HIDE_META);
        }
    }

    /* -------------------------------------------------------- Ad tracking */

    /**
     * Cache-proof click tracking. The ad link points at /?rd_ad_click=ID; this
     * fires on init, increments the click counter, then 302-redirects to the
     * stored offer URL with UTM params stamped on (so GA sees it too).
     */
    public function handle_ad_click() {
        if (!isset($_GET['rd_ad_click'])) {
            return;
        }
        $id        = absint($_GET['rd_ad_click']);
        $placement = isset($_GET['p']) ? sanitize_key($_GET['p']) : '';

        $link = '';
        if ($id && get_post_type($id) === 'attachment'
            && get_post_meta($id, self::FLAG_META, true) === '1') {
            $clicks = (int) get_post_meta($id, self::CLICKS_META, true);
            update_post_meta($id, self::CLICKS_META, $clicks + 1);
            $link = get_post_meta($id, self::LINK_META, true);
        }

        // Only ever redirect to the admin-set offer URL (no open redirect).
        if (empty($link)) {
            $link = home_url('/');
        }
        $target = add_query_arg(array(
            'utm_source'   => 'contentcucumber',
            'utm_medium'   => $placement ? $placement . '_ad' : 'ad',
            'utm_campaign' => 'brand_assets',
            'utm_content'  => $id,
        ), $link);

        nocache_headers();
        wp_redirect(esc_url_raw($target), 302);
        exit;
    }

    /**
     * Impression beacon (admin-ajax, public). The rotator JS posts the ids of
     * ads it actually injected; we bump each one's impression counter.
     */
    public function ajax_impression() {
        $ids = isset($_POST['ids']) ? (array) $_POST['ids'] : array();
        foreach (array_slice($ids, 0, 12) as $raw) {
            $id = absint($raw);
            if ($id && get_post_meta($id, self::FLAG_META, true) === '1') {
                $imp = (int) get_post_meta($id, self::IMPRESSIONS_META, true);
                update_post_meta($id, self::IMPRESSIONS_META, $imp + 1);
            }
        }
        wp_die('', '', array('response' => 204));
    }
}

/**
 * Classic widget: drop "RequestDesk: Random Ad" into any widget area
 * (sidebar/footer) or the block widget editor as a legacy widget.
 */
class RequestDesk_Random_Ad_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'requestdesk_random_ad',
            'RequestDesk: Random Ad',
            array('description' => 'Shows a random ad from your Brand Assets rotation.')
        );
    }

    public function widget($args, $instance) {
        if (!function_exists('do_shortcode')) {
            return;
        }
        $count     = isset($instance['count']) ? max(1, (int) $instance['count']) : 1;
        $title     = isset($instance['title']) ? $instance['title'] : '';
        $placement = isset($instance['placement']) ? $instance['placement'] : 'sidebar';

        echo $args['before_widget'];
        if ($title !== '') {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }
        echo do_shortcode(sprintf(
            '[requestdesk_random_ad count="%d" placement="%s"]',
            $count,
            esc_attr($placement)
        ));
        echo $args['after_widget'];
    }

    public function form($instance) {
        $title     = isset($instance['title']) ? $instance['title'] : '';
        $count     = isset($instance['count']) ? (int) $instance['count'] : 1;
        $placement = isset($instance['placement']) ? $instance['placement'] : 'sidebar';
        $choices   = array(
            'sidebar' => 'Sidebar ads',
            'banner'  => 'Banner ads',
            'all'     => 'Any ad',
        );
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Title (optional):</label>
            <input class="widefat" type="text"
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('placement')); ?>">Pull from:</label>
            <select class="widefat"
                    id="<?php echo esc_attr($this->get_field_id('placement')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('placement')); ?>">
                <?php foreach ($choices as $val => $label) : ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected($placement, $val); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('count')); ?>">How many ads to show:</label>
            <input class="tiny-text" type="number" min="1" max="6" step="1"
                   id="<?php echo esc_attr($this->get_field_id('count')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('count')); ?>"
                   value="<?php echo esc_attr($count); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $placement = isset($new_instance['placement']) ? sanitize_key($new_instance['placement']) : 'sidebar';
        if (!in_array($placement, array('sidebar', 'banner', 'all'), true)) {
            $placement = 'sidebar';
        }
        return array(
            'title'     => sanitize_text_field($new_instance['title'] ?? ''),
            'count'     => max(1, (int) ($new_instance['count'] ?? 1)),
            'placement' => $placement,
        );
    }
}

// Initialize (CC-only — see requestdesk_is_cc_site() in main plugin file).
// Flip this guard to instantiate unconditionally if other connector sites
// should get the hub too.
if (function_exists('requestdesk_is_cc_site') && requestdesk_is_cc_site()) {
    new RequestDesk_Asset_Hub();
}
