<?php
/**
 * RequestDesk Video Library
 *
 * Registers the rd_video post type: a YouTube video, its id, and the pages it
 * belongs on. Headless only. Nothing here renders on the WordPress front end,
 * because the consumer is the Astro site, so the CPT deliberately has no
 * rewrite rules, no permalinks and no templates. That is the whole difference
 * between this and RequestDesk_Partner, which does render and therefore carries
 * a lot of machinery this class does not need.
 *
 * WHY THIS EXISTS. Video ids were hardcoded in arrays on individual Astro
 * pages. Four pages held copies of the same iframe and the same ids, so adding
 * a video meant a code change and a ten minute container deploy, and the copies
 * drifted. Placement is a taxonomy here so a page asks for a term and gets
 * whatever is currently tagged with it, with no deploy involved.
 *
 * @package RequestDesk
 * @since 2.43.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Video {

    const POST_TYPE = 'rd_video';
    const TAXONOMY  = 'rd_video_placement';

    // YouTube's own hosts, used only to preview a video inside wp-admin. These
    // point at YouTube rather than at any RequestDesk service, so they carry no
    // vendor coupling for an install that is not ours.
    const YT_THUMB_BASE = 'https://i.ytimg.com/vi/'; // hardcode-ok: YouTube thumbnail CDN
    const YT_WATCH_BASE = 'https://www.youtube.com/watch?v='; // hardcode-ok: YouTube watch page

    public function __construct() {
        add_action('init', array($this, 'register_cpt'));
        add_action('init', array($this, 'register_taxonomy'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . self::POST_TYPE, array($this, 'save_meta'), 10, 2);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', array($this, 'admin_columns'));
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'admin_column_content'), 10, 2);
    }

    /**
     * Pull the 11-character id out of whatever the user pasted.
     *
     * Editors paste watch URLs, share URLs and embed URLs, and YouTube answers
     * a malformed embed with a silently broken player rather than an error. So
     * normalise on the way in and store only the id. Returns '' when nothing
     * id-shaped is found, which the caller treats as "leave it empty" rather
     * than storing garbage.
     */
    public static function normalize_video_id($raw) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        // Already a bare id.
        if (preg_match('#^[A-Za-z0-9_-]{11}$#', $raw)) {
            return $raw;
        }

        // watch?v=ID, youtu.be/ID, /embed/ID, /shorts/ID, /live/ID
        $patterns = array(
            '#[?&]v=([A-Za-z0-9_-]{11})#',
            '#youtu\.be/([A-Za-z0-9_-]{11})#',
            '#/embed/([A-Za-z0-9_-]{11})#',
            '#/shorts/([A-Za-z0-9_-]{11})#',
            '#/live/([A-Za-z0-9_-]{11})#',
        );
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $raw, $m)) {
                return $m[1];
            }
        }

        return '';
    }

    /**
     * Register the rd_video post type.
     *
     * public => false with show_ui => true gives the editor a normal admin
     * screen while keeping the CPT off the WordPress front end entirely. There
     * is no /rd_video/ URL to leak, index, or 404.
     */
    public function register_cpt() {
        $labels = array(
            'name'               => 'Videos',
            'singular_name'      => 'Video',
            'menu_name'          => 'Videos',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Video',
            'edit_item'          => 'Edit Video',
            'new_item'           => 'New Video',
            'view_item'          => 'View Video',
            'search_items'       => 'Search Videos',
            'not_found'          => 'No videos found',
            'not_found_in_trash' => 'No videos found in Trash',
            'all_items'          => 'All Videos',
        );

        register_post_type(self::POST_TYPE, array(
            'labels'        => $labels,
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => true,
            'has_archive'   => false,
            'rewrite'       => false,
            'supports'      => array('title', 'excerpt', 'thumbnail', 'page-attributes'),
            'menu_icon'     => 'dashicons-video-alt3',
            'menu_position' => 26,
            'show_in_rest'  => true,
        ));
    }

    /**
     * Placement taxonomy. One term per surface that shows videos.
     *
     * Hierarchical so it behaves like categories in the editor: checkboxes,
     * not a free-text tag field. Placement terms are a controlled vocabulary
     * that Astro pages hardcode a reference to, so a typo creating a brand new
     * term silently would mean a page quietly rendering nothing.
     */
    public function register_taxonomy() {
        register_taxonomy(self::TAXONOMY, array(self::POST_TYPE), array(
            'labels' => array(
                'name'          => 'Placements',
                'singular_name' => 'Placement',
                'menu_name'     => 'Placements',
                'all_items'     => 'All Placements',
                'edit_item'     => 'Edit Placement',
                'add_new_item'  => 'Add New Placement',
                'search_items'  => 'Search Placements',
            ),
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'hierarchical'      => true,
            'rewrite'           => false,
            'show_in_rest'      => true,
        ));
    }

    public function add_meta_boxes() {
        add_meta_box(
            'requestdesk_video_details',
            'Video Details',
            array($this, 'render_meta_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {
        wp_nonce_field('requestdesk_video_save', 'requestdesk_video_nonce');

        $video_id = get_post_meta($post->ID, '_requestdesk_video_id', true);
        $meta     = get_post_meta($post->ID, '_requestdesk_video_meta', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="requestdesk_video_id">YouTube Video</label></th>
                <td>
                    <input type="text" class="regular-text" id="requestdesk_video_id"
                           name="_requestdesk_video_id"
                           value="<?php echo esc_attr($video_id); ?>"
                           placeholder="dQw4w9WgXcQ or a full YouTube URL" />
                    <p class="description">
                        Paste the video id or any YouTube URL. The id is pulled out and saved on its own.
                    </p>
                    <?php if ($video_id) : ?>
                        <p style="margin-top:10px;">
                            <img src="<?php echo esc_url(self::YT_THUMB_BASE . $video_id . '/mqdefault.jpg'); ?>"
                                 alt="" style="max-width:240px;height:auto;border-radius:6px;" />
                        </p>
                        <p class="description">
                            <a href="<?php echo esc_url(self::YT_WATCH_BASE . $video_id); ?>"
                               target="_blank" rel="noopener">Open on YouTube</a>
                            &middot; id <code><?php echo esc_html($video_id); ?></code>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="requestdesk_video_meta">Caption line</label></th>
                <td>
                    <input type="text" class="regular-text" id="requestdesk_video_meta"
                           name="_requestdesk_video_meta"
                           value="<?php echo esc_attr($meta); ?>"
                           placeholder="Recorded at Shoptoberfest" />
                    <p class="description">
                        Optional small line under the title. Guest, event, or show.
                    </p>
                </td>
            </tr>
        </table>
        <p class="description">
            The <strong>title</strong> above is what appears under the video. Use
            <strong>Placements</strong> in the sidebar to choose which pages show it.
        </p>
        <?php
    }

    public function save_meta($post_id, $post) {
        if (!isset($_POST['requestdesk_video_nonce'])
            || !wp_verify_nonce($_POST['requestdesk_video_nonce'], 'requestdesk_video_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['_requestdesk_video_id'])) {
            $normalized = self::normalize_video_id(wp_unslash($_POST['_requestdesk_video_id']));
            update_post_meta($post_id, '_requestdesk_video_id', $normalized);
        }

        if (isset($_POST['_requestdesk_video_meta'])) {
            update_post_meta(
                $post_id,
                '_requestdesk_video_meta',
                sanitize_text_field(wp_unslash($_POST['_requestdesk_video_meta']))
            );
        }
    }

    /**
     * Thumbnail and id in the list table, so a wrong or empty video is obvious
     * at a glance instead of requiring a click into each post.
     */
    public function admin_columns($columns) {
        $new = array();
        foreach ($columns as $key => $label) {
            if ($key === 'title') {
                $new['rd_video_thumb'] = 'Video';
            }
            $new[$key] = $label;
        }
        return $new;
    }

    public function admin_column_content($column, $post_id) {
        if ($column !== 'rd_video_thumb') {
            return;
        }
        $video_id = get_post_meta($post_id, '_requestdesk_video_id', true);
        if (!$video_id) {
            echo '<span style="color:#b32d2e;">missing id</span>';
            return;
        }
        printf(
            '<img src="%s" alt="" style="width:80px;height:auto;border-radius:3px;" />',
            esc_url(self::YT_THUMB_BASE . $video_id . '/default.jpg')
        );
    }

    /**
     * Shape one video for the headless API.
     *
     * Videos with no id are dropped by the caller rather than returned with an
     * empty string, so a half-filled draft cannot render an empty player.
     */
    public static function format_for_api($post) {
        $video_id = get_post_meta($post->ID, '_requestdesk_video_id', true);
        if (empty($video_id)) {
            return null;
        }

        $placements = wp_get_post_terms($post->ID, self::TAXONOMY, array('fields' => 'slugs'));

        return array(
            'id'         => $post->ID,
            'videoId'    => $video_id,
            'title'      => get_the_title($post),
            'meta'       => get_post_meta($post->ID, '_requestdesk_video_meta', true),
            'excerpt'    => $post->post_excerpt,
            'placements' => is_wp_error($placements) ? array() : $placements,
            'menuOrder'  => (int) $post->menu_order,
            'date'       => get_the_date('c', $post),
        );
    }
}
