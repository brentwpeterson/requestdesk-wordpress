<?php
/**
 * Glossary terms (cc_glossary): one public post per term, living under the
 * glossary hub page at /aeo-glossary/<term>/.
 *
 * Content Cucumber only. The file is in $cc_only_files, so a client install
 * never grows a /aeo-glossary/ URL it has no use for (the 2026-08-03 empty
 * case-study archive on Talk Commerce is why that list exists).
 *
 * The hub is an ordinary WordPress page with the slug aeo-glossary, rendered
 * by the theme. The post type has no archive, so the hub is the only listing
 * and its URL is not shadowed by an archive rewrite.
 *
 * The definition is the post body and the excerpt is the one-line definition
 * shown on the hub. Everything else is a field in fields(): one table drives
 * the meta box, the save handler and the /glossary API route, so a field
 * cannot be shown without being saved or saved without being returned.
 *
 * @since 2.69.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Glossary {

    const POST_TYPE = 'cc_glossary';
    const TAXONOMY  = 'cc_glossary_topic';
    const SLUG      = 'aeo-glossary';
    const META_PREFIX = '_cc_glossary_';
    const NONCE     = 'cc_glossary_meta_nonce';

    public function __construct() {
        add_action('init', array($this, 'register'));
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post_' . self::POST_TYPE, array($this, 'save_meta_box'), 10, 2);
        // The Connector's content features (AEO meta boxes, Q&A, author and
        // image-alt routes) read requestdesk_content_post_types().
        add_filter('requestdesk_content_post_types', array(__CLASS__, 'add_content_type'));
    }

    public static function add_content_type($types) {
        $types[] = self::POST_TYPE;
        return array_values(array_unique($types));
    }

    /**
     * Every editable field, in the order the meta box shows them.
     * Types: text, textarea, lines (one item per line), date.
     */
    public static function fields() {
        return array(
            'aliases' => array('type' => 'lines', 'label' => 'Also called',
                'description' => 'Other names a reader might search for. One per line.'),
            'why' => array('type' => 'textarea', 'label' => 'Why it matters'),
            'example' => array('type' => 'textarea', 'label' => 'Example',
                'description' => 'Shown labeled as illustrative. Never a client result.'),
            'check' => array('type' => 'textarea', 'label' => 'What to check'),
            'limitation' => array('type' => 'textarea', 'label' => 'What this does not tell you'),
            'sources' => array('type' => 'lines', 'label' => 'Sources',
                'description' => 'One per line: Title | https://example.com/page | Source type'),
            'evidence' => array('type' => 'text', 'label' => 'Evidence type',
                'placeholder' => 'Documented concept, Editorial practice, Proposal or Reporting definition'),
            'source_checked' => array('type' => 'date', 'label' => 'Sources checked on'),
            'related' => array('type' => 'lines', 'label' => 'Related terms',
                'description' => 'Slugs of other glossary terms. One per line.'),
        );
    }

    public function register() {
        register_post_type(self::POST_TYPE, array(
            'labels' => array(
                'name'               => 'Glossary',
                'singular_name'      => 'Glossary Term',
                'menu_name'          => 'Glossary',
                'add_new_item'       => 'Add New Glossary Term',
                'edit_item'          => 'Edit Glossary Term',
                'new_item'           => 'New Glossary Term',
                'view_item'          => 'View Glossary Term',
                'search_items'       => 'Search Glossary',
                'not_found'          => 'No glossary terms found',
                'not_found_in_trash' => 'No glossary terms found in Trash',
                'all_items'          => 'All Glossary Terms',
            ),
            'public'        => true,
            'has_archive'   => false,
            'rewrite'       => array('slug' => self::SLUG, 'with_front' => false),
            'supports'      => array('title', 'editor', 'excerpt', 'revisions'),
            'menu_icon'     => 'dashicons-book-alt',
            'menu_position' => 27,
            'show_in_rest'  => true,
        ));

        register_taxonomy(self::TAXONOMY, self::POST_TYPE, array(
            'labels' => array(
                'name'          => 'Glossary Topics',
                'singular_name' => 'Glossary Topic',
                'menu_name'     => 'Topics',
            ),
            'hierarchical'      => true,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => false,
        ));

        foreach (self::fields() as $key => $field) {
            register_post_meta(self::POST_TYPE, self::META_PREFIX . $key, array(
                'type'          => 'string',
                'single'        => true,
                'show_in_rest'  => true,
                'auth_callback' => function () { return current_user_can('edit_posts'); },
            ));
        }

        // A post-type rewrite that is registered after the rules were last
        // flushed returns 404 until someone saves Permalinks. Flush once per
        // version of this rule instead of asking anyone to remember that.
        if (get_option('cc_glossary_rewrite_version') !== self::SLUG . '-1') {
            flush_rewrite_rules(false);
            update_option('cc_glossary_rewrite_version', self::SLUG . '-1');
        }
    }

    // ---------------------------------------------------------------------
    // Reading and writing fields
    // ---------------------------------------------------------------------

    public static function get($post_id, $key) {
        return (string) get_post_meta($post_id, self::META_PREFIX . $key, true);
    }

    /** A 'lines' field as an array of trimmed, non-empty lines. */
    public static function get_lines($post_id, $key) {
        $lines = preg_split('/\r\n|\r|\n/', self::get($post_id, $key));
        return array_values(array_filter(array_map('trim', $lines), 'strlen'));
    }

    /** Sources parsed into title, url and type. A line with no valid URL is skipped. */
    public static function get_sources($post_id) {
        $out = array();
        foreach (self::get_lines($post_id, 'sources') as $line) {
            $parts = array_map('trim', explode('|', $line));
            $url = esc_url_raw($parts[1] ?? '');
            if ($url === '') {
                continue;
            }
            $out[] = array('title' => $parts[0], 'url' => $url, 'type' => $parts[2] ?? '');
        }
        return $out;
    }

    public static function sanitize_value($field, $raw) {
        if (is_array($raw)) {
            $raw = implode("\n", array_map('strval', $raw));
        }
        $raw = (string) $raw;
        switch ($field['type']) {
            case 'date':
                $raw = trim($raw);
                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? $raw : '';
            case 'lines':
                $lines = preg_split('/\r\n|\r|\n/', $raw);
                return implode("\n", array_filter(array_map(function ($l) {
                    return trim(sanitize_text_field($l));
                }, $lines), 'strlen'));
            case 'textarea':
                return trim(sanitize_textarea_field($raw));
            default:
                return trim(sanitize_text_field($raw));
        }
    }

    /** Write only the keys given. Unknown keys are ignored here; the API refuses them. */
    public static function save_values($post_id, $values) {
        $fields = self::fields();
        foreach ((array) $values as $key => $raw) {
            if (!isset($fields[$key])) {
                continue;
            }
            $value = self::sanitize_value($fields[$key], $raw);
            if ($value === '') {
                delete_post_meta($post_id, self::META_PREFIX . $key);
            } else {
                update_post_meta($post_id, self::META_PREFIX . $key, wp_slash($value));
            }
        }
    }

    /** One term as the API returns it. */
    public static function format_for_api($post) {
        $out = array(
            'id'         => $post->ID,
            'slug'       => $post->post_name,
            'title'      => $post->post_title,
            'status'     => $post->post_status,
            'permalink'  => get_permalink($post),
            'definition' => $post->post_content,
            'excerpt'    => $post->post_excerpt,
            'topics'     => wp_get_object_terms($post->ID, self::TAXONOMY, array('fields' => 'slugs')),
        );
        foreach (array_keys(self::fields()) as $key) {
            $out[$key] = self::get($post->ID, $key);
        }
        return $out;
    }

    // ---------------------------------------------------------------------
    // Admin meta box
    // ---------------------------------------------------------------------

    public function add_meta_box() {
        add_meta_box('cc_glossary_fields', 'Glossary Term Details', array($this, 'render_meta_box'), self::POST_TYPE, 'normal', 'high');
    }

    public function render_meta_box($post) {
        wp_nonce_field(self::NONCE, self::NONCE);
        echo '<p style="color:#646970">The main editor holds the definition. The excerpt is the one-line definition shown on the glossary page.</p>';
        echo '<table class="form-table" role="presentation">';
        foreach (self::fields() as $key => $field) {
            $id = 'cc_glossary_' . $key;
            $value = self::get($post->ID, $key);
            echo '<tr><th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($field['label']) . '</label></th><td>';
            if ($field['type'] === 'textarea' || $field['type'] === 'lines') {
                echo '<textarea class="large-text" rows="' . ($field['type'] === 'lines' ? 4 : 3) . '" id="' . esc_attr($id) . '" name="cc_glossary[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea>';
            } else {
                echo '<input class="regular-text" type="' . ($field['type'] === 'date' ? 'date' : 'text') . '" id="' . esc_attr($id) . '" name="cc_glossary[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" placeholder="' . esc_attr($field['placeholder'] ?? '') . '">';
            }
            if (!empty($field['description'])) {
                echo '<p class="description">' . esc_html($field['description']) . '</p>';
            }
            echo '</td></tr>';
        }
        echo '</table>';
    }

    public function save_meta_box($post_id, $post) {
        if (!isset($_POST[self::NONCE]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE])), self::NONCE)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $values = isset($_POST['cc_glossary']) && is_array($_POST['cc_glossary']) ? wp_unslash($_POST['cc_glossary']) : array();
        // Every field is in the form, so an emptied field is cleared.
        $all = array();
        foreach (array_keys(self::fields()) as $key) {
            $all[$key] = $values[$key] ?? '';
        }
        self::save_values($post_id, $all);
    }
}

new RequestDesk_Glossary();
