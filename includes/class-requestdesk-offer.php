<?php
/**
 * RequestDesk Offers
 *
 * Registers the rd_offer post type: one vendor deal for the site's audience
 * (a listener discount, free months, a reserved seat), the referral link behind
 * it, where it is in review, how prominently it is shown, and the revenue-share
 * terms behind it. Headless only, built like RequestDesk_Event: an admin screen,
 * no front-end URL, no archive. The consuming site (Talk Commerce's Astro front)
 * reads the /headless/offers routes and renders the hub itself.
 *
 * WHY THIS EXISTS. Vendors pitch "a deal for your listeners" by email and the
 * pitches went nowhere. An offer needs more than a link on a page: whether it
 * was reviewed, whether it is featured, when it ends, what share the site earns
 * and how many people clicked through, so the share can be reconciled against
 * what the vendor pays.
 *
 * TWO CURATION AXES.
 *   stage     inbox -> reviewing -> approved -> expired. Only "approved" is
 *             live. The stage drives post_status (approved = publish, anything
 *             else = draft), so an offer nobody approved cannot reach the site
 *             through a stray Publish click.
 *   placement featured | standard | unlisted. Where an approved offer shows:
 *             featured on top, standard in the grid, unlisted on no list but
 *             still reachable by its own page and its /go link (an offer made
 *             for one episode's audience).
 *
 * Expiry is derived on every read from the expires date, the same rule the
 * events module uses for upcoming/past: a stored flag is a flag somebody
 * forgets to flip. A daily cron also moves passed offers to stage "expired" so
 * the admin list tells the truth, but the public routes never depend on it.
 *
 * TRACKED LINKS. Every public link is /go/<go_slug>. This class resolves that
 * path itself (it does not need the CC-only QR Redirect module), counts the
 * click, and sends a 302 to the referral URL. The referral URL is never
 * returned by the public routes, so the counted path is the only path.
 *
 * @package RequestDesk
 * @since 2.67.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Offer {

    const POST_TYPE    = 'rd_offer';
    const TAXONOMY     = 'offer_category';
    const META_PREFIX  = '_rd_offer_';
    const NONCE_ACTION = 'requestdesk_offer_save';
    const NONCE_FIELD  = 'requestdesk_offer_nonce';
    const CRON_HOOK    = 'requestdesk_offer_expire';
    const GO_PREFIX    = 'go';

    /** Lifetime click count, and a per-day map (Y-m-d => count) kept for DAILY_KEEP days. */
    const CLICKS_META = '_rd_offer_clicks';
    const DAILY_META  = '_rd_offer_clicks_daily';
    const DAILY_KEEP  = 400;

    public function __construct() {
        // Self-instantiated at require time (see the bottom of this file), so
        // these init hooks at priority 0 are added before WordPress fires init.
        // The redirect has to run at 0: QR Redirect, where it is loaded, also
        // answers /go at 0, and an offer slug must win over its default
        // fallback. Hooks at one priority run in the order they were added, and
        // this file is required before the site-module files.
        add_action('init', array($this, 'register_cpt'), 0);
        add_action('init', array($this, 'maybe_redirect'), 0);

        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . self::POST_TYPE, array($this, 'save_meta'), 10, 2);
        add_filter('wp_insert_post_data', array($this, 'status_from_stage'), 10, 3);
        // Classic editor for offers. The block editor saves the post over REST
        // first and the meta boxes second, so the stage rule would first see
        // the old stage and the editor would show a status that then flips.
        add_filter('use_block_editor_for_post_type', array($this, 'classic_editor'), 10, 2);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', array($this, 'admin_columns'));
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'admin_column_content'), 10, 2);
        add_action('restrict_manage_posts', array($this, 'stage_filter_dropdown'));
        add_action('admin_notices', array($this, 'live_state_notice'));
        add_action('pre_get_posts', array($this, 'admin_list_query'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        add_action(self::CRON_HOOK, array(__CLASS__, 'expire_passed_offers'));
        add_action('wp_loaded', array(__CLASS__, 'schedule_cron'));
    }

    /* ------------------------------------------------------------------ */
    /* Fields                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Every editable field, in the order the meta box shows them. One table
     * drives the meta box, the save handler, the REST write route and the API
     * shape, so a field cannot be rendered without being saved.
     *
     * 'private' => true marks business fields that never leave wp-admin and
     * the key-protected admin route.
     */
    public static function fields() {
        return array(
            // What the audience sees
            'vendor_name' => array('section' => 'listing', 'type' => 'text', 'label' => 'Vendor',
                'placeholder' => 'Oneseat Club'),
            'vendor_url' => array('section' => 'listing', 'type' => 'url', 'label' => 'Vendor website',
                'description' => 'Shown as plain text context only. Every button goes through the tracked link below.'),
            'logo' => array('section' => 'listing', 'type' => 'url', 'label' => 'Logo',
                'description' => 'A full image URL. Falls back to the featured image.'),
            'headline' => array('section' => 'listing', 'type' => 'text', 'label' => 'Deal in one line',
                'placeholder' => '6 months free, then $19 a month',
                'description' => 'The line on the offer card.'),
            'pitch' => array('section' => 'listing', 'type' => 'textarea', 'label' => 'What it is',
                'description' => 'Two or three sentences about the product. The main editor above holds the full offer page.'),
            'audience' => array('section' => 'listing', 'type' => 'text', 'label' => 'Who it is for',
                'placeholder' => 'US freelancers who serve Shopify brands'),
            'deal' => array('section' => 'listing', 'type' => 'textarea', 'label' => 'What the audience gets',
                'description' => 'The terms of the deal as the vendor states them.'),
            'price_after' => array('section' => 'listing', 'type' => 'text', 'label' => 'Price after the deal',
                'placeholder' => '$19 a month'),
            'code' => array('section' => 'listing', 'type' => 'text', 'label' => 'Code',
                'description' => 'A promo code to show on the card. Leave empty when the link carries it.'),
            'cta_text' => array('section' => 'listing', 'type' => 'text', 'label' => 'Button text',
                'placeholder' => 'Claim the offer'),

            // The link
            'referral_url' => array('section' => 'link', 'type' => 'url', 'label' => 'Referral URL', 'private' => true,
                'description' => 'Required before an offer can go live. Never shown on the site: visitors reach it only through the tracked /go link.'),
            'go_slug' => array('section' => 'link', 'type' => 'slug', 'label' => 'Tracked link',
                'description' => 'The /go/<this> path. Leave empty to use the offer\'s slug.'),

            // Curation
            'stage' => array('section' => 'curation', 'type' => 'select', 'label' => 'Stage', 'default' => 'inbox',
                'options' => array(
                    'inbox'     => 'Inbox: arrived, not looked at',
                    'reviewing' => 'Reviewing: vetting the vendor or the terms',
                    'approved'  => 'Approved: live on the site',
                    'expired'   => 'Expired: ended or withdrawn',
                ),
                'description' => 'Only Approved is live. The stage sets Published or Draft on save, so the Publish button alone does not take an offer live.'),
            'placement' => array('section' => 'curation', 'type' => 'select', 'label' => 'Placement', 'default' => 'standard',
                'options' => array(
                    'featured' => 'Featured: top of the offers page',
                    'standard' => 'Standard: in the offers grid',
                    'unlisted' => 'Unlisted: own page and /go link only',
                )),
            'expires' => array('section' => 'curation', 'type' => 'date', 'label' => 'Ends',
                'description' => 'Leave empty for an offer with no end date. The offer leaves the site the day after this date.'),
            'cap' => array('section' => 'curation', 'type' => 'text', 'label' => 'Limit', 'placeholder' => '20 seats',
                'description' => 'Shown on the card when set.'),

            // Business, never public
            'share_percent' => array('section' => 'business', 'type' => 'number', 'label' => 'Revenue share %', 'private' => true),
            'share_terms' => array('section' => 'business', 'type' => 'textarea', 'label' => 'Revenue share terms', 'private' => true,
                'description' => 'What was agreed: the share, how it is paid, how it is reported. Empty means no share is agreed yet.'),
            'contact_name' => array('section' => 'business', 'type' => 'text', 'label' => 'Vendor contact', 'private' => true),
            'contact_email' => array('section' => 'business', 'type' => 'email', 'label' => 'Contact email', 'private' => true),
            'source_thread' => array('section' => 'business', 'type' => 'text', 'label' => 'Gmail thread id', 'private' => true,
                'description' => 'The pitch email this offer came from.'),
            'hubspot_company_id' => array('section' => 'business', 'type' => 'text', 'label' => 'HubSpot company id', 'private' => true),
            'notes' => array('section' => 'business', 'type' => 'textarea', 'label' => 'Internal notes', 'private' => true),
        );
    }

    public static function sections() {
        return array(
            'curation' => 'Curation',
            'listing'  => 'What the audience sees',
            'link'     => 'Tracked link',
            'business' => 'Business (never shown on the site)',
        );
    }

    public static function get($post_id, $key) {
        return (string) get_post_meta($post_id, self::META_PREFIX . $key, true);
    }

    /* ------------------------------------------------------------------ */
    /* Registration                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Admin-only on every install, like rd_video: no URL, no archive. An
     * install that never uses offers gets an empty Offers screen and nothing
     * else; the /go handler answers only for an approved offer's slug.
     */
    public function register_cpt() {
        register_post_type(self::POST_TYPE, array(
            'labels' => array(
                'name'               => 'Offers',
                'singular_name'      => 'Offer',
                'menu_name'          => 'Offers',
                'add_new'            => 'Add New',
                'add_new_item'       => 'Add New Offer',
                'edit_item'          => 'Edit Offer',
                'new_item'           => 'New Offer',
                'search_items'       => 'Search Offers',
                'not_found'          => 'No offers found',
                'not_found_in_trash' => 'No offers found in Trash',
                'all_items'          => 'All Offers',
            ),
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => true,
            'has_archive'   => false,
            'rewrite'       => false,
            'supports'      => array('title', 'editor', 'thumbnail', 'page-attributes'),
            'menu_icon'     => 'dashicons-tag',
            'menu_position' => 28,
            'show_in_rest'  => true,
        ));

        register_taxonomy(self::TAXONOMY, self::POST_TYPE, array(
            'labels' => array(
                'name'          => 'Offer Categories',
                'singular_name' => 'Offer Category',
                'menu_name'     => 'Categories',
                'add_new_item'  => 'Add New Category',
            ),
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'hierarchical'      => true,
            'rewrite'           => false,
            'show_in_rest'      => true,
        ));
    }

    public static function schedule_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Rules                                                              */
    /* ------------------------------------------------------------------ */

    /** True when the offer has an end date and it is before today (UTC). */
    public static function is_expired($post_id) {
        $expires = self::get($post_id, 'expires');
        return $expires !== '' && $expires < gmdate('Y-m-d');
    }

    public static function go_slug($post) {
        $slug = self::get($post->ID, 'go_slug');
        return $slug !== '' ? $slug : $post->post_name;
    }

    /**
     * Why an approved offer is not live, or an empty list when it is.
     * Shown in the admin list and returned by the admin route, so an offer
     * never drops off the site without a stated reason.
     */
    public static function problems($post) {
        $problems = array();
        if (self::get($post->ID, 'referral_url') === '') {
            $problems[] = 'no referral URL';
        }
        if (self::is_expired($post->ID)) {
            $problems[] = 'end date has passed';
        }
        return $problems;
    }

    /**
     * Stage decides status. Runs on every insert and update (editor, REST,
     * wp_insert_post), so no path can publish an offer that is not approved,
     * or one approved with no referral URL to send people to.
     */
    public function classic_editor($use, $post_type) {
        return $post_type === self::POST_TYPE ? false : $use;
    }

    public function status_from_stage($data, $postarr, $unsanitized = array()) {
        // The raw array keeps the rd_offer_* keys the REST route and the cron
        // pass in; the sanitized one is not guaranteed to.
        if (is_array($unsanitized) && $unsanitized) {
            $postarr = array_merge($postarr, $unsanitized);
        }
        if ($data['post_type'] !== self::POST_TYPE) {
            return $data;
        }
        if (in_array($data['post_status'], array('trash', 'auto-draft', 'inherit'), true)) {
            return $data;
        }

        $id    = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
        $field = self::META_PREFIX . 'stage';
        $ref   = self::META_PREFIX . 'referral_url';

        // The editor posts the new values with the save; anything else (REST,
        // cron) has written the meta before calling wp_update_post.
        $stage = isset($_POST[$field]) ? sanitize_key(wp_unslash($_POST[$field])) : ($id ? self::get($id, 'stage') : '');
        $url   = isset($_POST[$ref]) ? trim((string) wp_unslash($_POST[$ref])) : ($id ? self::get($id, 'referral_url') : '');
        if (isset($postarr['rd_offer_stage'])) {
            $stage = $postarr['rd_offer_stage'];
        }
        if (isset($postarr['rd_offer_referral_url'])) {
            $url = $postarr['rd_offer_referral_url'];
        }

        $data['post_status'] = ($stage === 'approved' && $url !== '') ? 'publish' : 'draft';

        // WordPress leaves a draft's slug empty until it is published, but an
        // offer is found by slug (the update route, the /go link, the admin
        // route) for its whole life in review. Give it one now, unique as if
        // published, so it does not change on approval either.
        if ($data['post_name'] === '' && $data['post_title'] !== '') {
            $data['post_name'] = wp_unique_post_slug(sanitize_title($data['post_title']), $id, 'publish',
                self::POST_TYPE, (int) $data['post_parent']);
        }
        return $data;
    }

    /**
     * Daily: an approved offer whose end date has passed moves to stage
     * "expired" and back to draft. The public routes already hide it by date;
     * this keeps the admin list and the stage honest.
     */
    public static function expire_passed_offers() {
        $posts = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array('key' => self::META_PREFIX . 'expires', 'value' => gmdate('Y-m-d'), 'compare' => '<', 'type' => 'CHAR'),
                array('key' => self::META_PREFIX . 'expires', 'value' => '', 'compare' => '!='),
            ),
        ));
        $moved = array();
        foreach ($posts as $post) {
            update_post_meta($post->ID, self::META_PREFIX . 'stage', 'expired');
            wp_update_post(array('ID' => $post->ID, 'rd_offer_stage' => 'expired'));
            $moved[] = $post->post_name;
        }
        return $moved;
    }

    /* ------------------------------------------------------------------ */
    /* Editor                                                             */
    /* ------------------------------------------------------------------ */

    public function add_meta_boxes() {
        add_meta_box('requestdesk_offer_details', 'Offer Details', array($this, 'render_meta_box'),
            self::POST_TYPE, 'normal', 'high');
        add_meta_box('requestdesk_offer_clicks', 'Clicks', array($this, 'render_clicks_box'),
            self::POST_TYPE, 'side', 'default');
    }

    public function render_meta_box($post) {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $fields = self::fields();
        foreach (self::sections() as $section => $section_label) {
            echo '<h3 style="margin:1.5em 0 0;">' . esc_html($section_label) . '</h3>';
            echo '<table class="form-table" role="presentation">';
            foreach ($fields as $key => $field) {
                if ($field['section'] === $section) {
                    $this->render_field($post, $key, $field);
                }
            }
            echo '</table>';
        }
    }

    private function render_field($post, $key, $field) {
        $name  = self::META_PREFIX . $key;
        $id    = 'rd_offer_' . $key;
        $value = self::get($post->ID, $key);
        if ($value === '' && isset($field['default'])) {
            $value = $field['default'];
        }
        $placeholder = isset($field['placeholder']) ? ' placeholder="' . esc_attr($field['placeholder']) . '"' : '';

        echo '<tr><th><label for="' . esc_attr($id) . '">' . esc_html($field['label']) . '</label></th><td>';

        switch ($field['type']) {
            case 'textarea':
                echo '<textarea class="large-text" rows="3" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"' . $placeholder . '>'
                    . esc_textarea($value) . '</textarea>';
                break;
            case 'select':
                echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '">';
                foreach ($field['options'] as $option => $option_label) {
                    echo '<option value="' . esc_attr($option) . '"' . selected($value, $option, false) . '>'
                        . esc_html($option_label) . '</option>';
                }
                echo '</select>';
                break;
            case 'date':
                echo '<input type="date" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
                break;
            case 'number':
                echo '<input type="number" step="0.1" min="0" max="100" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
                break;
            case 'slug':
                echo '<code>' . esc_html(home_url('/' . self::GO_PREFIX . '/')) . '</code><input type="text" id="' . esc_attr($id) . '" name="'
                    . esc_attr($name) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($post->post_name) . '" />';
                break;
            default:
                echo '<input type="text" class="regular-text" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="'
                    . esc_attr($value) . '"' . $placeholder . ' />';
        }

        if (!empty($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
        echo '</td></tr>';
    }

    public function render_clicks_box($post) {
        $total = (int) get_post_meta($post->ID, self::CLICKS_META, true);
        echo '<p><strong>' . number_format_i18n($total) . '</strong> clicks in total</p>';
        echo '<p>' . number_format_i18n(self::clicks_since($post->ID, 30)) . ' in the last 30 days</p>';
        echo '<p class="description">Counted on <code>/' . esc_html(self::GO_PREFIX . '/' . self::go_slug($post)) . '</code>. Crawlers and link previews are not counted.</p>';
    }

    public function save_meta($post_id, $post) {
        if (!isset($_POST[self::NONCE_FIELD])
            || !wp_verify_nonce($_POST[self::NONCE_FIELD], self::NONCE_ACTION)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $values = array();
        foreach (self::fields() as $key => $field) {
            $raw = isset($_POST[self::META_PREFIX . $key]) ? wp_unslash($_POST[self::META_PREFIX . $key]) : null;
            $values[$key] = $raw;
        }
        self::save_values($post_id, $values);
    }

    /** Shared by the editor and the REST write route. Only keys present are written. */
    public static function save_values($post_id, array $values) {
        foreach (self::fields() as $key => $field) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            update_post_meta($post_id, self::META_PREFIX . $key, self::sanitize_value($field, $values[$key]));
        }
    }

    public static function sanitize_value($field, $raw) {
        switch ($field['type']) {
            case 'date':
                $raw = trim((string) $raw);
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                    return $raw;
                }
                return '';
            case 'url':
                return esc_url_raw(trim((string) $raw));
            case 'email':
                return sanitize_email((string) $raw);
            case 'number':
                $raw = trim((string) $raw);
                return is_numeric($raw) ? (string) max(0, min(100, (float) $raw)) : '';
            case 'slug':
                return sanitize_title((string) $raw);
            case 'textarea':
                return sanitize_textarea_field((string) $raw);
            case 'select':
                $raw = (string) $raw;
                return array_key_exists($raw, $field['options']) ? $raw : (isset($field['default']) ? $field['default'] : '');
            default:
                return sanitize_text_field((string) $raw);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Admin list                                                         */
    /* ------------------------------------------------------------------ */

    public function admin_columns($columns) {
        $new = array();
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['rd_offer_stage']     = 'Stage';
                $new['rd_offer_placement'] = 'Placement';
                $new['rd_offer_clicks']    = 'Clicks (30 days / total)';
                $new['rd_offer_expires']   = 'Ends';
                $new['rd_offer_share']     = 'Share';
            }
        }
        unset($new['date']);
        return $new;
    }

    public function admin_column_content($column, $post_id) {
        $post = get_post($post_id);
        switch ($column) {
            case 'rd_offer_stage':
                $stage = self::get($post_id, 'stage') ?: 'inbox';
                echo esc_html(ucfirst($stage));
                if ($stage === 'approved' && ($problems = self::problems($post))) {
                    // Approved but not live. Say why where the editor looks,
                    // rather than letting it vanish from the site in silence.
                    echo '<br><span style="color:#b32d2e;">not live: ' . esc_html(implode(', ', $problems)) . '</span>';
                }
                return;
            case 'rd_offer_placement':
                echo esc_html(ucfirst(self::get($post_id, 'placement') ?: 'standard'));
                return;
            case 'rd_offer_clicks':
                echo esc_html(number_format_i18n(self::clicks_since($post_id, 30)) . ' / '
                    . number_format_i18n((int) get_post_meta($post_id, self::CLICKS_META, true)));
                return;
            case 'rd_offer_expires':
                $expires = self::get($post_id, 'expires');
                echo esc_html($expires === '' ? '—' : $expires);
                return;
            case 'rd_offer_share':
                $pct = self::get($post_id, 'share_percent');
                $terms = self::get($post_id, 'share_terms');
                if ($pct === '' && $terms === '') {
                    echo '<span style="color:#b32d2e;">none agreed</span>';
                } else {
                    echo esc_html($pct !== '' ? $pct . '%' : 'see terms');
                }
                return;
        }
    }

    /**
     * On the edit screen, say whether this offer is live and why. WordPress's
     * own "Post draft updated" / "Post published" message does not explain
     * that the stage, not the button, decided it.
     */
    public function live_state_notice() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->base !== 'post' || $screen->post_type !== self::POST_TYPE || empty($_GET['post'])) {
            return;
        }
        $post = get_post((int) $_GET['post']);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return;
        }
        $d = self::describe($post);
        if ($d['live']) {
            $where = $d['listed'] ? 'listed on the offers page' : 'unlisted (its own page and link only)';
            $msg = 'Live, ' . $where . '. Tracked link: ' . $d['go_url'];
            $class = 'notice-success';
        } else {
            $stage = $d['meta']['stage'] ?: 'inbox';
            $why = $stage === 'approved' ? implode(', ', self::problems($post)) : 'stage is ' . ucfirst($stage) . ', and only Approved is live';
            $msg = 'Not live: ' . $why . '.';
            $class = 'notice-warning';
        }
        echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($msg) . '</p></div>';
    }

    public function stage_filter_dropdown($post_type) {
        if ($post_type !== self::POST_TYPE) {
            return;
        }
        $current = isset($_GET['rd_offer_stage']) ? sanitize_key($_GET['rd_offer_stage']) : '';
        echo '<select name="rd_offer_stage"><option value="">All stages</option>';
        foreach (self::fields()['stage']['options'] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($current, $value, false) . '>'
                . esc_html(ucfirst($value)) . '</option>';
        }
        echo '</select>';
    }

    /** Stage filter, and featured-first then manual order by default. */
    public function admin_list_query($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== self::POST_TYPE) {
            return;
        }
        $stage = isset($_GET['rd_offer_stage']) ? sanitize_key($_GET['rd_offer_stage']) : '';
        if ($stage !== '') {
            $query->set('meta_query', array(array('key' => self::META_PREFIX . 'stage', 'value' => $stage)));
        }
        if (!$query->get('orderby')) {
            $query->set('orderby', array('menu_order' => 'ASC', 'date' => 'DESC'));
        }
    }

    /* ------------------------------------------------------------------ */
    /* Tracked redirect: /go/<go_slug>                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Answer /go/<slug> for a live offer. Anything else (no match, a draft, an
     * expired offer, a bare /go) falls through untouched to QR Redirect where
     * it is loaded, or to WordPress.
     *
     * 302 plus no-store, never a 301: a cached 301 would skip the counter on
     * every later click and keep sending people to a referral URL after it is
     * changed or the offer ends.
     */
    public function maybe_redirect() {
        if (is_admin() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) {
            return;
        }
        if ((defined('REST_REQUEST') && REST_REQUEST) || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
            return;
        }

        $slug = self::request_slug();
        if ($slug === null) {
            return;
        }
        $post = self::find_live_by_go_slug($slug);
        if (!$post) {
            return;
        }

        if (!self::is_bot()) {
            self::count_click($post->ID);
        }

        $target = self::add_utm(self::get($post->ID, 'referral_url'), $slug);

        nocache_headers();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex, nofollow', true);
        // wp_redirect, not wp_safe_redirect: the referral URL is an external
        // host by design, and it can only come from an editor-saved offer.
        wp_redirect($target, 302);
        exit;
    }

    /** The slug from /go/<slug>, or null when this is not such a request. */
    private static function request_slug() {
        if (empty($_SERVER['REQUEST_URI'])) {
            return null;
        }
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }
        $home_path = parse_url(home_url('/'), PHP_URL_PATH);
        if (is_string($home_path) && $home_path !== '' && $home_path !== '/' && strpos($path, $home_path) === 0) {
            $path = substr($path, strlen($home_path) - 1);
        }
        $parts = explode('/', trim($path, '/'));
        if (count($parts) !== 2 || strtolower($parts[0]) !== self::GO_PREFIX) {
            return null;
        }
        $slug = sanitize_title($parts[1]);
        return $slug === '' ? null : $slug;
    }

    /** A published, unexpired offer whose tracked link is $slug. */
    private static function find_live_by_go_slug($slug) {
        $by_meta = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_key'       => self::META_PREFIX . 'go_slug',
            'meta_value'     => $slug,
        ));
        $post = $by_meta ? $by_meta[0] : null;
        if (!$post) {
            // Empty go_slug means the post slug is the link.
            $by_name = get_posts(array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'name'           => $slug,
            ));
            if ($by_name && self::get($by_name[0]->ID, 'go_slug') === '') {
                $post = $by_name[0];
            }
        }
        if (!$post || self::problems($post)) {
            return null;
        }
        return $post;
    }

    private static function is_bot() {
        if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'HEAD') {
            return true;
        }
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        if ($ua === '') {
            return true;
        }
        return (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|curl|wget|python-requests|httpclient|headless/i', $ua);
    }

    private static function count_click($post_id) {
        update_post_meta($post_id, self::CLICKS_META, (int) get_post_meta($post_id, self::CLICKS_META, true) + 1);

        $daily = get_post_meta($post_id, self::DAILY_META, true);
        if (!is_array($daily)) {
            $daily = array();
        }
        $today = gmdate('Y-m-d');
        $daily[$today] = (isset($daily[$today]) ? (int) $daily[$today] : 0) + 1;
        if (count($daily) > self::DAILY_KEEP) {
            ksort($daily);
            $daily = array_slice($daily, -self::DAILY_KEEP, null, true);
        }
        update_post_meta($post_id, self::DAILY_META, $daily);
    }

    public static function clicks_since($post_id, $days) {
        $daily = get_post_meta($post_id, self::DAILY_META, true);
        if (!is_array($daily)) {
            return 0;
        }
        $from = gmdate('Y-m-d', time() - ($days - 1) * DAY_IN_SECONDS);
        $sum = 0;
        foreach ($daily as $day => $n) {
            if ($day >= $from) {
                $sum += (int) $n;
            }
        }
        return $sum;
    }

    /**
     * Attribution the vendor's analytics can see. Params already on the
     * referral URL win: a vendor-issued utm_source is part of how they credit
     * the referral, and overwriting it could cost the share.
     */
    private static function add_utm($url, $slug) {
        $args = array(
            'utm_source'   => apply_filters('requestdesk_offer_utm_source', sanitize_title(get_bloginfo('name'))),
            'utm_medium'   => 'offer',
            'utm_campaign' => 'offers',
            'utm_content'  => $slug,
        );
        $existing = array();
        $query    = parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            parse_str($query, $existing);
        }
        foreach (array_keys($existing) as $have) {
            unset($args[$have]);
        }
        return empty($args) ? $url : add_query_arg($args, $url);
    }

    /* ------------------------------------------------------------------ */
    /* REST                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * /headless/offers and /headless/offers/<slug>: what the site renders.
     * Same API key as the other headless routes; the Astro front already holds
     * it. Business fields and the referral URL are never in these responses.
     *
     * /offers (GET, POST): the admin view and the write route for the inbox
     * sweep and the MCP. Every stage, every field, plus the reasons an offer is
     * not live.
     */
    public function register_rest_routes() {
        $auth = array($this, 'verify_api_key');
        register_rest_route('requestdesk/v1', '/headless/offers', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_public_list'),
            'permission_callback' => $auth,
            'args'                => array('category' => array('required' => false, 'type' => 'string')),
        ));
        register_rest_route('requestdesk/v1', '/headless/offers/(?P<slug>[a-zA-Z0-9-]+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_public_one'),
            'permission_callback' => $auth,
        ));
        register_rest_route('requestdesk/v1', '/offers', array(
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'rest_admin_list'),
                'permission_callback' => $auth,
                'args'                => array(
                    'stage' => array('required' => false, 'type' => 'string'),
                    'slug'  => array('required' => false, 'type' => 'string'),
                ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'rest_upsert'),
                'permission_callback' => $auth,
                'args'                => array(
                    'slug'       => array('required' => false, 'type' => 'string', 'description' => 'Update the offer with this slug; omit to create.'),
                    'title'      => array('required' => false, 'type' => 'string'),
                    'content'    => array('required' => false, 'type' => 'string'),
                    'meta'       => array('required' => false, 'type' => 'object', 'description' => 'Fields keyed as in RequestDesk_Offer::fields(). Only keys sent are written.'),
                    'categories' => array('required' => false, 'type' => 'array', 'description' => 'Category names; missing ones are created.'),
                    'menu_order' => array('required' => false, 'type' => 'integer'),
                    'dry_run'    => array('required' => false, 'type' => 'boolean', 'default' => false),
                ),
            ),
        ));
    }

    public function verify_api_key($request) {
        $settings = get_option('requestdesk_settings', array());
        $api_key  = isset($settings['api_key']) ? $settings['api_key'] : '';
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'RequestDesk API key not configured', array('status' => 401));
        }
        $provided = $request->get_header('X-RequestDesk-API-Key');
        if (empty($provided) || !hash_equals($api_key, (string) $provided)) {
            return new WP_Error('invalid_api_key', 'Invalid API key', array('status' => 401));
        }
        return true;
    }

    /** Live and listed offers: featured first, then manual order, then newest. */
    public function rest_public_list($request) {
        $posts = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => array('menu_order' => 'ASC', 'date' => 'DESC'),
        ));
        $category = sanitize_title((string) $request->get_param('category'));

        $offers = array();
        foreach ($posts as $post) {
            if (self::problems($post) || self::get($post->ID, 'placement') === 'unlisted') {
                continue;
            }
            $offer = self::format_for_api($post, false);
            if ($category !== '' && !in_array($category, wp_list_pluck($offer['categories'], 'slug'), true)) {
                continue;
            }
            $offers[] = $offer;
        }
        // Stable partition: featured keeps its manual order on top.
        $featured = array_values(array_filter($offers, function ($o) { return $o['featured']; }));
        $rest     = array_values(array_filter($offers, function ($o) { return !$o['featured']; }));
        $offers   = array_merge($featured, $rest);

        $terms = get_terms(array('taxonomy' => self::TAXONOMY, 'hide_empty' => false));
        $categories = array();
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                $categories[] = array('slug' => $t->slug, 'name' => $t->name);
            }
        }

        return new WP_REST_Response(array(
            'offers'     => $offers,
            'count'      => count($offers),
            'categories' => $categories,
        ), 200);
    }

    /** One live offer, unlisted included (its own page is how it is reached). */
    public function rest_public_one($request) {
        $posts = get_posts(array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'name'           => sanitize_title($request->get_param('slug')),
        ));
        if (!$posts || self::problems($posts[0])) {
            return new WP_Error('offer_not_found', 'No live offer with that slug.', array('status' => 404));
        }
        return new WP_REST_Response(array('offer' => self::format_for_api($posts[0], true)), 200);
    }

    public function rest_admin_list($request) {
        $slug = (string) $request->get_param('slug');
        $args = array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
        );
        if ($slug !== '') {
            $args['name'] = sanitize_title($slug);
        }
        $stage = sanitize_key((string) $request->get_param('stage'));
        if ($stage !== '') {
            $args['meta_query'] = array(array('key' => self::META_PREFIX . 'stage', 'value' => $stage));
        }
        $offers = array_map(array(__CLASS__, 'describe'), get_posts($args));
        return new WP_REST_Response(array('offers' => $offers, 'count' => count($offers)), 200);
    }

    /** Everything about one offer, for the MCP and the inbox sweep. */
    public static function describe($post) {
        $meta = array();
        foreach (array_keys(self::fields()) as $key) {
            $meta[$key] = self::get($post->ID, $key);
        }
        $live = $post->post_status === 'publish' && !self::problems($post);
        return array(
            'id'          => (int) $post->ID,
            'slug'        => $post->post_name,
            'title'       => $post->post_title,
            'status'      => $post->post_status,
            'live'        => $live,
            'listed'      => $live && $meta['placement'] !== 'unlisted',
            'problems'    => $meta['stage'] === 'approved' ? self::problems($post) : array(),
            'go_url'      => home_url('/' . self::GO_PREFIX . '/' . self::go_slug($post)),
            'clicks'      => (int) get_post_meta($post->ID, self::CLICKS_META, true),
            'clicks_30d'  => self::clicks_since($post->ID, 30),
            'categories'  => wp_get_post_terms($post->ID, self::TAXONOMY, array('fields' => 'names')),
            'menu_order'  => (int) $post->menu_order,
            'meta'        => $meta,
            'edit_url'    => admin_url('post.php?post=' . $post->ID . '&action=edit'),
        );
    }

    /**
     * Create, or update by slug. Unknown meta keys are refused rather than
     * dropped, so a typo in the sweep fails loudly instead of losing a field.
     * The stage the caller sends decides status, through status_from_stage().
     */
    public function rest_upsert($request) {
        $meta = (array) $request->get_param('meta');
        $unknown = array_diff(array_keys($meta), array_keys(self::fields()));
        if ($unknown) {
            return new WP_Error('unknown_fields', 'Unknown offer fields: ' . implode(', ', $unknown), array('status' => 400));
        }

        $slug = (string) $request->get_param('slug');
        $post = null;
        if ($slug !== '') {
            $found = get_posts(array(
                'post_type'      => self::POST_TYPE,
                'post_status'    => array('publish', 'draft', 'pending', 'private'),
                'posts_per_page' => 1,
                'name'           => sanitize_title($slug),
            ));
            if (!$found) {
                return new WP_Error('offer_not_found', 'No offer with slug "' . $slug . '". Omit slug to create one.', array('status' => 404));
            }
            $post = $found[0];
        } elseif ((string) $request->get_param('title') === '') {
            return new WP_Error('title_required', 'A new offer needs a title.', array('status' => 400));
        }

        if ($request->get_param('dry_run')) {
            return new WP_REST_Response(array('dry_run' => true, 'action' => $post ? 'update' : 'create', 'slug' => $post ? $post->post_name : null, 'meta' => $meta), 200);
        }

        $stage = array_key_exists('stage', $meta)
            ? self::sanitize_value(self::fields()['stage'], $meta['stage'])
            : ($post ? (self::get($post->ID, 'stage') ?: 'inbox') : 'inbox');
        $url = array_key_exists('referral_url', $meta)
            ? self::sanitize_value(self::fields()['referral_url'], $meta['referral_url'])
            : ($post ? self::get($post->ID, 'referral_url') : '');

        $postarr = array(
            'post_type'             => self::POST_TYPE,
            'rd_offer_stage'        => $stage,
            'rd_offer_referral_url' => $url,
        );
        if ($post) {
            $postarr['ID'] = $post->ID;
        }
        foreach (array('title' => 'post_title', 'content' => 'post_content') as $param => $field) {
            if ($request->get_param($param) !== null) {
                $postarr[$field] = (string) $request->get_param($param);
            }
        }
        if ($request->get_param('menu_order') !== null) {
            $postarr['menu_order'] = (int) $request->get_param('menu_order');
        }
        if (!$post) {
            $postarr['post_status'] = 'draft';
        }

        $id = $post ? wp_update_post($postarr, true) : wp_insert_post($postarr, true);
        if (is_wp_error($id)) {
            return new WP_Error('offer_save_failed', $id->get_error_message(), array('status' => 500));
        }
        if (!array_key_exists('stage', $meta) && !$post) {
            $meta['stage'] = 'inbox';
        }
        self::save_values($id, $meta);

        $categories = $request->get_param('categories');
        if (is_array($categories)) {
            wp_set_object_terms($id, array_map('sanitize_text_field', $categories), self::TAXONOMY);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'action'  => $post ? 'updated' : 'created',
            'offer'   => self::describe(get_post($id)),
        ), $post ? 200 : 201);
    }

    /**
     * The public shape. No referral URL and no business fields: the site
     * links to goSlug, which is where clicks are counted.
     */
    public static function format_for_api($post, $include_content = true) {
        $id = $post->ID;
        $public = array();
        foreach (self::fields() as $key => $field) {
            if (empty($field['private'])) {
                $public[$key] = self::get($id, $key);
            }
        }
        $placement = $public['placement'] ?: 'standard';
        $thumb = get_the_post_thumbnail_url($id, 'full');

        $categories = array();
        $terms = wp_get_post_terms($id, self::TAXONOMY);
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                $categories[] = array('slug' => $t->slug, 'name' => $t->name);
            }
        }

        $data = array(
            'id'          => $id,
            'slug'        => $post->post_name,
            'title'       => $post->post_title,
            'vendor'      => array(
                'name' => $public['vendor_name'] ?: $post->post_title,
                'url'  => $public['vendor_url'],
                'logo' => $public['logo'] ?: ($thumb ? $thumb : ''),
            ),
            'headline'    => $public['headline'],
            'pitch'       => $public['pitch'],
            'audience'    => $public['audience'],
            'deal'        => $public['deal'],
            'priceAfter'  => $public['price_after'],
            'code'        => $public['code'],
            'ctaText'     => $public['cta_text'] ?: 'Claim the offer',
            'goSlug'      => self::go_slug($post),
            'placement'   => $placement,
            'featured'    => $placement === 'featured',
            'expires'     => $public['expires'] ?: null,
            'cap'         => $public['cap'],
            'categories'  => $categories,
            'featuredImage' => $thumb ? $thumb : null,
            'modifiedAt'  => get_the_modified_date('c', $post),
        );
        if ($include_content) {
            $data['content'] = apply_filters('the_content', $post->post_content);
        }
        return $data;
    }
}

// Self-instantiated at require time, like RequestDesk_QR_Redirect: the /go
// handler must hook init at priority 0, which a class built inside
// requestdesk_init() (itself on init at 10) can never do. Do not add this
// class to the $aeo_classes list.
new RequestDesk_Offer();
