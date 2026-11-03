<?php
/**
 * RequestDesk Headless Sites
 *
 * One WordPress serving extra headless frontends. Each extra site is DATA in
 * the requestdesk_headless_sites option: its own content types, taxonomies,
 * custom fields, an optional author type, menu locations, canonical URL
 * patterns and a rebuild webhook. Nothing about any one site lives in code,
 * because the Connector is installed by customers we have never heard of.
 *
 * WHY. One install can render one site through its theme, and the native
 * post and page types belong to that site. Every other brand on the same
 * install gets its own types, registered here, so a frontend can only ever
 * receive the content of its own site: separation is a property of the data,
 * not a filter every page has to remember.
 *
 * Types and taxonomies are headless only, like rd_video: public => false,
 * no rewrite rules, no permalinks. The theme, the core sitemap and RSS never
 * see them. Content reaches a frontend through /headless/sites/{site}/...
 * (see RequestDesk_Headless_API).
 *
 * WordPress does not remember post types between requests, so registration
 * runs on every request from the saved option.
 *
 * @package RequestDesk
 * @since 2.65.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Headless_Sites {

    const OPTION         = 'requestdesk_headless_sites';
    const ERRORS_OPTION  = 'requestdesk_headless_sites_webhook_errors';
    const CRON_HOOK      = 'requestdesk_headless_site_rebuild';
    const META_PREFIX    = '_rd_';
    const REBUILD_DELAY  = 30; // seconds; a bulk edit inside this window fires one rebuild

    // Names a type slug cannot take: WordPress's own types, and the fixed
    // segments of /headless/sites/{site}/... that a type route would shadow.
    const RESERVED_TYPES = array(
        'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css',
        'customize_changeset', 'oembed_cache', 'user_request', 'wp_block',
        'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation',
        'wp_font_family', 'wp_font_face', 'action', 'author', 'order', 'theme',
        'site', 'menus', 'terms', 'authors', 'config',
    );

    const FIELD_KINDS = array('text', 'textarea', 'url', 'number', 'date', 'embed');

    private static $instance = null;

    public function __construct() {
        self::$instance = $this;

        // requestdesk_init() builds this class from inside 'init' (see the
        // same guard in RequestDesk_Video). A callback added to 'init' now
        // would never run, so register directly once init has started.
        if (did_action('init')) {
            $this->register_all();
        } else {
            add_action('init', array($this, 'register_all'), 5);
        }

        add_action('after_setup_theme', array($this, 'register_menu_locations'), 20);
        if (did_action('after_setup_theme')) {
            $this->register_menu_locations();
        }

        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_fields'), 10, 2);
        add_action('transition_post_status', array($this, 'on_status_change'), 10, 3);
        add_action('post_updated', array($this, 'on_post_updated'), 10, 3);
        add_action(self::CRON_HOOK, array(__CLASS__, 'fire_rebuild'));
        add_action('admin_notices', array($this, 'webhook_error_notices'));
        add_action('load-edit.php', array($this, 'add_site_views'));

        // Author term fields.
        foreach (self::all_author_taxonomies() as $tax) {
            add_action($tax . '_add_form_fields', array($this, 'author_fields_add'));
            add_action($tax . '_edit_form_fields', array($this, 'author_fields_edit'), 10, 2);
            add_action('created_' . $tax, array($this, 'save_author_fields'));
            add_action('edited_' . $tax, array($this, 'save_author_fields'));
        }

        // Keep other plugins on a shared install from touching these types.
        add_filter('pll_get_post_types', array($this, 'polylang_post_types'), 99, 2);
        add_filter('pll_get_taxonomies', array($this, 'polylang_taxonomies'), 99, 2);
        add_filter('wpseo_sitemap_exclude_post_type', array($this, 'yoast_exclude_type'), 10, 2);
        add_filter('wpseo_sitemap_exclude_taxonomy', array($this, 'yoast_exclude_taxonomy'), 10, 2);
        add_filter('wpseo_accessible_post_types', array($this, 'yoast_accessible_types'));
    }

    /* ------------------------------------------------------------------ */
    /* Config                                                              */
    /* ------------------------------------------------------------------ */

    /** Every configured site, keyed by site key. */
    public static function get_sites() {
        $sites = get_option(self::OPTION, array());
        return is_array($sites) ? $sites : array();
    }

    public static function get_site($key) {
        $sites = self::get_sites();
        return isset($sites[$key]) ? $sites[$key] : null;
    }

    /** The site config and type config for a post type slug, or null. */
    public static function find_type($post_type) {
        foreach (self::get_sites() as $site) {
            foreach ($site['types'] as $type) {
                if ($type['slug'] === $post_type) {
                    return array('site' => $site, 'type' => $type);
                }
            }
        }
        return null;
    }

    /** The URL an item lives at on its frontend, from its type's path pattern. */
    public static function item_url($post) {
        $found = $post ? self::find_type($post->post_type) : null;
        if (!$found) {
            return null;
        }
        $full = $post->post_name;
        if ($found['type']['behavior'] === 'page') {
            $full = trim((string) get_page_uri($post), '/');
        }
        $path = str_replace(array('{slug}', '{path}'), array($post->post_name, $full), $found['type']['path']);
        return $found['site']['frontend_url'] . '/' . ltrim($path, '/');
    }

    /** Every post type slug owned by a headless site. */
    public static function all_type_slugs() {
        $out = array();
        foreach (self::get_sites() as $site) {
            foreach ($site['types'] as $type) {
                $out[] = $type['slug'];
            }
        }
        return $out;
    }

    /** Every taxonomy slug owned by a headless site, author taxonomies included. */
    public static function all_taxonomy_slugs() {
        $out = array();
        foreach (self::get_sites() as $site) {
            foreach ($site['taxonomies'] as $tax) {
                $out[] = $tax['slug'];
            }
            if (!empty($site['authors'])) {
                $out[] = self::author_taxonomy($site);
            }
        }
        return $out;
    }

    public static function all_author_taxonomies() {
        $out = array();
        foreach (self::get_sites() as $site) {
            if (!empty($site['authors'])) {
                $out[] = self::author_taxonomy($site);
            }
        }
        return $out;
    }

    public static function author_taxonomy($site) {
        return $site['key'] . '_author';
    }

    /** Every nav menu location owned by a headless site. */
    public static function all_menu_locations() {
        $out = array();
        foreach (self::get_sites() as $site) {
            foreach ($site['menus'] as $menu) {
                $out[] = self::menu_location($site, $menu['slug']);
            }
        }
        return $out;
    }

    public static function menu_location($site, $slug) {
        return $site['key'] . '_' . $slug;
    }

    /** Taxonomy configs attached to a type. */
    public static function type_taxonomies($site, $type) {
        $out = array();
        foreach ($site['taxonomies'] as $tax) {
            if (in_array($type['slug'], $tax['types'], true)) {
                $out[] = $tax;
            }
        }
        return $out;
    }

    /** Meta key a field is stored under: its own meta_key, or _rd_{key}. */
    public static function field_meta_key($field) {
        return !empty($field['meta_key']) ? $field['meta_key'] : self::META_PREFIX . $field['key'];
    }

    /**
     * Validate and normalize a site config. Returns the clean config or a
     * WP_Error naming every problem. Nothing is guessed: a bad value is an
     * error, never silently replaced with a default.
     *
     * @param array      $input    the submitted config
     * @param array|null $existing the stored config for this key, if any
     */
    public static function validate_site($input, $existing = null) {
        $errors = array();
        if (!is_array($input)) {
            return new WP_Error('invalid_site', 'Site config must be an object.');
        }

        $key = isset($input['key']) ? (string) $input['key'] : '';
        if (!preg_match('/^[a-z][a-z0-9]{1,9}$/', $key)) {
            $errors[] = 'key must be 2-10 lowercase letters or digits, starting with a letter.';
        }

        $name = isset($input['name']) ? sanitize_text_field($input['name']) : '';
        if ($name === '') {
            $errors[] = 'name is required.';
        }

        $frontend = isset($input['frontend_url']) ? esc_url_raw(trim((string) $input['frontend_url'])) : '';
        if ($frontend === '' || !preg_match('#^https?://#', $frontend)) {
            $errors[] = 'frontend_url must be a full http(s) URL.';
        }

        $webhook = isset($input['webhook_url']) ? trim((string) $input['webhook_url']) : '';
        if ($webhook !== '' && !preg_match('#^https://#', $webhook)) {
            $errors[] = 'webhook_url must be an https URL or empty.';
        }

        $api_key = isset($input['api_key']) ? trim((string) $input['api_key']) : '';
        if ($api_key !== '' && strlen($api_key) < 24) {
            $errors[] = 'api_key must be at least 24 characters or empty.';
        }

        $clean = array(
            'key'          => $key,
            'name'         => $name,
            'frontend_url' => untrailingslashit($frontend),
            'description'  => isset($input['description']) ? sanitize_textarea_field($input['description']) : '',
            'logo'         => isset($input['logo']) ? esc_url_raw($input['logo']) : '',
            'og_image'     => isset($input['og_image']) ? esc_url_raw($input['og_image']) : '',
            'favicon'      => isset($input['favicon']) ? esc_url_raw($input['favicon']) : '',
            'webhook_url'  => $webhook,
            'api_key'      => $api_key,
            'authors'      => !empty($input['authors']),
            'types'        => array(),
            'taxonomies'   => array(),
            'menus'        => array(),
        );

        // Types.
        $types_in = isset($input['types']) && is_array($input['types']) ? $input['types'] : array();
        if (empty($types_in)) {
            $errors[] = 'at least one content type is required.';
        }
        $type_slugs = array();
        foreach ($types_in as $i => $t) {
            $label = 'types[' . $i . ']';
            $slug = isset($t['slug']) ? (string) $t['slug'] : '';
            if (!preg_match('/^[a-z][a-z0-9_]{1,19}$/', $slug)) {
                $errors[] = "$label.slug must be 2-20 characters: lowercase letters, digits, underscore.";
                continue;
            }
            if (in_array($slug, self::RESERVED_TYPES, true) || in_array($slug, $type_slugs, true)) {
                $errors[] = "$label.slug '$slug' is reserved or used twice.";
                continue;
            }
            $owner = self::find_type($slug);
            if (post_type_exists($slug) && (!$owner || $owner['site']['key'] !== $key)) {
                $errors[] = "$label.slug '$slug' is already registered by WordPress or another plugin or site.";
                continue;
            }
            $behavior = isset($t['behavior']) ? (string) $t['behavior'] : 'post';
            if (!in_array($behavior, array('post', 'page'), true)) {
                $errors[] = "$label.behavior must be post or page.";
            }
            $path = isset($t['path']) ? (string) $t['path'] : '/{slug}';
            if (strpos($path, '{slug}') === false && strpos($path, '{path}') === false) {
                $errors[] = "$label.path must contain {slug} or {path}.";
            }
            $plural   = isset($t['plural']) ? sanitize_text_field($t['plural']) : '';
            $singular = isset($t['singular']) ? sanitize_text_field($t['singular']) : '';
            if ($plural === '' || $singular === '') {
                $errors[] = "$label needs singular and plural labels.";
            }

            $fields = array();
            $field_keys = array();
            foreach ((isset($t['fields']) && is_array($t['fields']) ? $t['fields'] : array()) as $j => $f) {
                $fl = "$label.fields[$j]";
                $fkey = isset($f['key']) ? (string) $f['key'] : '';
                if (!preg_match('/^[a-z][a-z0-9_]{0,40}$/', $fkey) || in_array($fkey, $field_keys, true)) {
                    $errors[] = "$fl.key must be lowercase letters, digits, underscore, and unique in the type.";
                    continue;
                }
                $kind = isset($f['kind']) ? (string) $f['kind'] : 'text';
                if (!in_array($kind, self::FIELD_KINDS, true)) {
                    $errors[] = "$fl.kind must be one of " . implode(', ', self::FIELD_KINDS) . '.';
                }
                $meta_key = isset($f['meta_key']) ? (string) $f['meta_key'] : '';
                if ($meta_key !== '' && !preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $meta_key)) {
                    $errors[] = "$fl.meta_key has characters a meta key cannot hold.";
                }
                $field_keys[] = $fkey;
                $fields[] = array(
                    'key'      => $fkey,
                    'label'    => isset($f['label']) && $f['label'] !== '' ? sanitize_text_field($f['label']) : $fkey,
                    'kind'     => $kind,
                    'meta_key' => $meta_key,
                    'api'      => !isset($f['api']) || !empty($f['api']),
                    'root'     => !empty($f['root']),
                );
            }

            $type_slugs[] = $slug;
            $clean['types'][] = array(
                'slug'      => $slug,
                'singular'  => $singular,
                'plural'    => $plural,
                'behavior'  => $behavior,
                'path'      => $path,
                'menu_icon' => isset($t['menu_icon']) ? sanitize_html_class($t['menu_icon']) : '',
                'fields'    => $fields,
            );
        }

        // Taxonomies.
        $tax_slugs = array();
        foreach ((isset($input['taxonomies']) && is_array($input['taxonomies']) ? $input['taxonomies'] : array()) as $i => $x) {
            $label = 'taxonomies[' . $i . ']';
            $slug = isset($x['slug']) ? (string) $x['slug'] : '';
            if (!preg_match('/^[a-z][a-z0-9_]{1,31}$/', $slug) || in_array($slug, $tax_slugs, true)) {
                $errors[] = "$label.slug must be 2-32 characters: lowercase letters, digits, underscore, unique.";
                continue;
            }
            if ($slug === $key . '_author') {
                $errors[] = "$label.slug '$slug' is reserved for the author type.";
                continue;
            }
            $owned = in_array($slug, self::site_taxonomy_slugs(self::get_site($key)), true);
            if (taxonomy_exists($slug) && !$owned) {
                $errors[] = "$label.slug '$slug' is already registered.";
                continue;
            }
            $attached = isset($x['types']) && is_array($x['types']) ? array_values(array_intersect($x['types'], $type_slugs)) : array();
            if (empty($attached)) {
                $errors[] = "$label.types must name at least one of this site's types.";
            }
            $role = isset($x['role']) ? (string) $x['role'] : '';
            if (!in_array($role, array('', 'category', 'tag'), true)) {
                $errors[] = "$label.role must be category, tag, or empty.";
            }
            $tax_slugs[] = $slug;
            $clean['taxonomies'][] = array(
                'slug'         => $slug,
                'singular'     => isset($x['singular']) ? sanitize_text_field($x['singular']) : $slug,
                'plural'       => isset($x['plural']) ? sanitize_text_field($x['plural']) : $slug,
                'hierarchical' => !empty($x['hierarchical']),
                'role'         => $role,
                'types'        => $attached,
            );
        }
        foreach (array('category', 'tag') as $role) {
            $count = count(array_filter($clean['taxonomies'], function ($x) use ($role) { return $x['role'] === $role; }));
            if ($count > 1) {
                $errors[] = "only one taxonomy can have role '$role'.";
            }
        }

        // Menus.
        foreach ((isset($input['menus']) && is_array($input['menus']) ? $input['menus'] : array()) as $i => $m) {
            $slug = isset($m['slug']) ? (string) $m['slug'] : '';
            if (!preg_match('/^[a-z][a-z0-9_]{1,30}$/', $slug)) {
                $errors[] = "menus[$i].slug must be lowercase letters, digits, underscore.";
                continue;
            }
            $clean['menus'][] = array(
                'slug'  => $slug,
                'label' => isset($m['label']) && $m['label'] !== '' ? sanitize_text_field($m['label']) : $slug,
            );
        }

        // A type that holds content cannot be removed or renamed: every post
        // saved under the old slug would be orphaned with no admin screen.
        if ($existing) {
            foreach ($existing['types'] as $old) {
                if (!in_array($old['slug'], $type_slugs, true) && self::type_has_content($old['slug'])) {
                    $errors[] = "type '{$old['slug']}' holds content and cannot be removed or renamed. Move or delete its content first.";
                }
            }
        }

        if ($errors) {
            return new WP_Error('invalid_site', implode(' ', $errors), array('status' => 400, 'errors' => $errors));
        }
        return $clean;
    }

    private static function site_taxonomy_slugs($site) {
        if (!$site) {
            return array();
        }
        return array_map(function ($x) { return $x['slug']; }, $site['taxonomies']);
    }

    public static function type_has_content($slug) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->posts} WHERE post_type = %s LIMIT 1",
            $slug
        ));
    }

    /** Save a validated site. Returns the clean config or WP_Error. */
    public static function save_site($input) {
        $key = isset($input['key']) ? (string) $input['key'] : '';
        $existing = self::get_site($key);

        // An empty api_key or webhook_url on an update keeps the stored one,
        // so the admin form never has to echo a secret back into the page.
        if ($existing) {
            foreach (array('api_key', 'webhook_url') as $secret) {
                if (!isset($input[$secret]) || $input[$secret] === '') {
                    $input[$secret] = $existing[$secret];
                }
            }
        }

        $clean = self::validate_site($input, $existing);
        if (is_wp_error($clean)) {
            return $clean;
        }
        $sites = self::get_sites();
        $sites[$clean['key']] = $clean;
        update_option(self::OPTION, $sites, true);
        return $clean;
    }

    /** Delete a site. Refused while any of its types holds content. */
    public static function delete_site($key) {
        $site = self::get_site($key);
        if (!$site) {
            return new WP_Error('site_not_found', 'No headless site with key ' . $key, array('status' => 404));
        }
        foreach ($site['types'] as $type) {
            if (self::type_has_content($type['slug'])) {
                return new WP_Error('site_has_content', "Type '{$type['slug']}' holds content. Delete or move it before deleting the site.", array('status' => 409));
            }
        }
        $sites = self::get_sites();
        unset($sites[$key]);
        update_option(self::OPTION, $sites, true);
        return true;
    }

    /** Public view of a site config: everything except secrets. */
    public static function public_config($site) {
        $out = $site;
        unset($out['api_key'], $out['webhook_url']);
        $out['has_webhook'] = $site['webhook_url'] !== '';
        $out['has_api_key'] = $site['api_key'] !== '';
        if (!empty($site['authors'])) {
            $out['author_taxonomy'] = self::author_taxonomy($site);
        }
        $out['menu_locations'] = array();
        foreach ($site['menus'] as $menu) {
            $out['menu_locations'][] = self::menu_location($site, $menu['slug']);
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Registration                                                        */
    /* ------------------------------------------------------------------ */

    public function register_all() {
        foreach (self::get_sites() as $site) {
            foreach ($site['types'] as $type) {
                $this->register_type($site, $type);
            }
            foreach ($site['taxonomies'] as $tax) {
                $this->register_taxonomy($site, $tax);
            }
            if (!empty($site['authors'])) {
                $this->register_author_taxonomy($site);
            }
        }
    }

    private function register_type($site, $type) {
        $is_page  = $type['behavior'] === 'page';
        $plural   = $site['name'] . ' ' . $type['plural'];
        $singular = $type['singular'];

        $supports = array('title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields');
        if ($is_page) {
            $supports[] = 'page-attributes';
        } else {
            $supports[] = 'author';
        }

        register_post_type($type['slug'], array(
            'labels' => array(
                'name'               => $plural,
                'singular_name'      => $singular,
                'menu_name'          => $plural,
                'all_items'          => 'All ' . $type['plural'],
                'add_new'            => 'Add New',
                'add_new_item'       => 'Add New ' . $singular,
                'edit_item'          => 'Edit ' . $singular,
                'new_item'           => 'New ' . $singular,
                'view_item'          => 'View ' . $singular,
                'search_items'       => 'Search ' . $type['plural'],
                'not_found'          => 'No ' . strtolower($type['plural']) . ' found',
                'not_found_in_trash' => 'No ' . strtolower($type['plural']) . ' found in Trash',
            ),
            'description'         => $site['name'] . ' (headless site ' . $site['key'] . ')',
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_nav_menus'   => true,
            'show_in_rest'        => true,
            'hierarchical'        => $is_page,
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,
            'supports'            => $supports,
            'menu_icon'           => $type['menu_icon'] !== '' ? $type['menu_icon'] : ($is_page ? 'dashicons-admin-page' : 'dashicons-admin-post'),
            'menu_position'       => 27,
        ));

        foreach ($type['fields'] as $field) {
            register_post_meta($type['slug'], self::field_meta_key($field), array(
                'type'          => $field['kind'] === 'number' ? 'number' : 'string',
                'single'        => true,
                'show_in_rest'  => true,
                'auth_callback' => function () { return current_user_can('edit_posts'); },
            ));
        }
    }

    private function register_taxonomy($site, $tax) {
        register_taxonomy($tax['slug'], $tax['types'], array(
            'labels' => array(
                'name'          => $site['name'] . ' ' . $tax['plural'],
                'singular_name' => $tax['singular'],
                'menu_name'     => $tax['plural'],
                'all_items'     => 'All ' . $tax['plural'],
                'edit_item'     => 'Edit ' . $tax['singular'],
                'add_new_item'  => 'Add New ' . $tax['singular'],
                'search_items'  => 'Search ' . $tax['plural'],
            ),
            'public'            => false,
            'publicly_queryable'=> false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'hierarchical'      => $tax['hierarchical'],
            'rewrite'           => false,
            'query_var'         => false,
        ));
    }

    private function register_author_taxonomy($site) {
        $types = array();
        foreach ($site['types'] as $type) {
            if ($type['behavior'] === 'post') {
                $types[] = $type['slug'];
            }
        }
        register_taxonomy(self::author_taxonomy($site), $types, array(
            'labels' => array(
                'name'          => $site['name'] . ' Authors',
                'singular_name' => 'Author',
                'menu_name'     => 'Authors',
                'all_items'     => 'All Authors',
                'edit_item'     => 'Edit Author',
                'add_new_item'  => 'Add New Author',
                'search_items'  => 'Search Authors',
            ),
            'public'            => false,
            'publicly_queryable'=> false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'hierarchical'      => false,
            'rewrite'           => false,
            'query_var'         => false,
        ));
    }

    public function register_menu_locations() {
        $locations = array();
        foreach (self::get_sites() as $site) {
            foreach ($site['menus'] as $menu) {
                $locations[self::menu_location($site, $menu['slug'])] = $site['name'] . ': ' . $menu['label'];
            }
        }
        if ($locations) {
            register_nav_menus($locations);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Custom fields                                                       */
    /* ------------------------------------------------------------------ */

    public function add_meta_boxes() {
        foreach (self::get_sites() as $site) {
            foreach ($site['types'] as $type) {
                if (empty($type['fields'])) {
                    continue;
                }
                add_meta_box(
                    'rdhs_fields_' . $type['slug'],
                    $type['singular'] . ' Fields',
                    array($this, 'render_fields_box'),
                    $type['slug'],
                    'normal',
                    'high',
                    array('fields' => $type['fields'])
                );
            }
        }
    }

    public function render_fields_box($post, $box) {
        wp_nonce_field('rdhs_fields_save', 'rdhs_fields_nonce');
        echo '<table class="form-table">';
        foreach ($box['args']['fields'] as $field) {
            $name  = 'rdhs_field[' . $field['key'] . ']';
            $id    = 'rdhs_field_' . $field['key'];
            $value = get_post_meta($post->ID, self::field_meta_key($field), true);
            echo '<tr><th><label for="' . esc_attr($id) . '">' . esc_html($field['label']) . '</label></th><td>';
            if ($field['kind'] === 'textarea' || $field['kind'] === 'embed') {
                echo '<textarea class="large-text code" rows="4" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '">' . esc_textarea($value) . '</textarea>';
            } else {
                $html_type = array('url' => 'url', 'number' => 'number', 'date' => 'date')[$field['kind']] ?? 'text';
                echo '<input type="' . esc_attr($html_type) . '" class="regular-text" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . ($html_type === 'number' ? ' step="any"' : '') . ' />';
            }
            echo '<p class="description"><code>' . esc_html($field['key']) . '</code></p></td></tr>';
        }
        echo '</table>';
    }

    public static function sanitize_field_value($field, $raw) {
        $raw = is_string($raw) ? wp_unslash($raw) : $raw;
        switch ($field['kind']) {
            case 'url':
                return esc_url_raw(trim((string) $raw));
            case 'number':
                $raw = trim((string) $raw);
                return $raw === '' ? '' : (is_numeric($raw) ? $raw + 0 : '');
            case 'date':
                $raw = trim((string) $raw);
                return preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) ? substr($raw, 0, 10) : '';
            case 'textarea':
                return sanitize_textarea_field((string) $raw);
            case 'embed':
                // Player embed code. Same iframe allowance the publish route uses.
                return current_user_can('unfiltered_html') ? (string) $raw : wp_kses((string) $raw, array(
                    'iframe' => array('src' => true, 'width' => true, 'height' => true, 'frameborder' => true,
                                      'scrolling' => true, 'allow' => true, 'allowfullscreen' => true, 'title' => true,
                                      'loading' => true, 'style' => true),
                ));
            default:
                return sanitize_text_field((string) $raw);
        }
    }

    public function save_fields($post_id, $post) {
        if (!isset($_POST['rdhs_fields_nonce']) || !wp_verify_nonce($_POST['rdhs_fields_nonce'], 'rdhs_fields_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $found = self::find_type($post->post_type);
        if (!$found || !isset($_POST['rdhs_field']) || !is_array($_POST['rdhs_field'])) {
            return;
        }
        self::write_fields($post_id, $found['type'], $_POST['rdhs_field']);
    }

    /**
     * Write field values for a post. Unknown keys are refused, not ignored.
     * An empty value deletes the meta. Returns an array of unknown keys.
     */
    public static function write_fields($post_id, $type, $values) {
        $by_key = array();
        foreach ($type['fields'] as $field) {
            $by_key[$field['key']] = $field;
        }
        $unknown = array();
        foreach ($values as $k => $raw) {
            if (!isset($by_key[$k])) {
                $unknown[] = $k;
                continue;
            }
            $value = self::sanitize_field_value($by_key[$k], $raw);
            $meta_key = self::field_meta_key($by_key[$k]);
            if ($value === '' || $value === null) {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $value);
            }
        }
        return $unknown;
    }

    /** Field values for the API: ['fields' => [...], 'root' => [...]]. */
    public static function read_fields($post_id, $type) {
        $fields = array();
        $root = array();
        foreach ($type['fields'] as $field) {
            if (!$field['api']) {
                continue;
            }
            $value = get_post_meta($post_id, self::field_meta_key($field), true);
            if ($value === '' || $value === null) {
                $value = null;
            } elseif ($field['kind'] === 'number') {
                $value = $value + 0;
            }
            $fields[$field['key']] = $value;
            if ($field['root'] && $value !== null) {
                $root[$field['key']] = $value;
            }
        }
        return array('fields' => $fields, 'root' => $root);
    }

    /* ------------------------------------------------------------------ */
    /* Author terms                                                        */
    /* ------------------------------------------------------------------ */

    const AUTHOR_META = array(
        'avatar'   => 'Avatar URL',
        'title'    => 'Job title',
        'company'  => 'Company',
        'linkedin' => 'LinkedIn URL',
        'website'  => 'Website',
    );

    public function author_fields_add() {
        wp_nonce_field('rdhs_author_save', 'rdhs_author_nonce');
        foreach (self::AUTHOR_META as $k => $label) {
            echo '<div class="form-field"><label for="rdhs_author_' . esc_attr($k) . '">' . esc_html($label) . '</label>';
            echo '<input type="text" name="rdhs_author[' . esc_attr($k) . ']" id="rdhs_author_' . esc_attr($k) . '" value="" /></div>';
        }
    }

    public function author_fields_edit($term) {
        wp_nonce_field('rdhs_author_save', 'rdhs_author_nonce');
        foreach (self::AUTHOR_META as $k => $label) {
            $v = get_term_meta($term->term_id, 'rd_author_' . $k, true);
            echo '<tr class="form-field"><th scope="row"><label for="rdhs_author_' . esc_attr($k) . '">' . esc_html($label) . '</label></th>';
            echo '<td><input type="text" name="rdhs_author[' . esc_attr($k) . ']" id="rdhs_author_' . esc_attr($k) . '" value="' . esc_attr($v) . '" /></td></tr>';
        }
    }

    public function save_author_fields($term_id) {
        if (!isset($_POST['rdhs_author_nonce']) || !wp_verify_nonce($_POST['rdhs_author_nonce'], 'rdhs_author_save')) {
            return;
        }
        if (!current_user_can('manage_categories') || !isset($_POST['rdhs_author']) || !is_array($_POST['rdhs_author'])) {
            return;
        }
        self::write_author_meta($term_id, wp_unslash($_POST['rdhs_author']));
    }

    public static function write_author_meta($term_id, $values) {
        foreach (self::AUTHOR_META as $k => $label) {
            if (!array_key_exists($k, $values)) {
                continue;
            }
            $v = in_array($k, array('avatar', 'linkedin', 'website'), true)
                ? esc_url_raw(trim((string) $values[$k]))
                : sanitize_text_field((string) $values[$k]);
            if ($v === '') {
                delete_term_meta($term_id, 'rd_author_' . $k);
            } else {
                update_term_meta($term_id, 'rd_author_' . $k, $v);
            }
        }
    }

    public static function format_author_term($term) {
        $out = array(
            'id'          => $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description,
            'count'       => (int) $term->count,
        );
        foreach (array_keys(self::AUTHOR_META) as $k) {
            $v = get_term_meta($term->term_id, 'rd_author_' . $k, true);
            $out[$k] = $v !== '' ? $v : null;
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Rebuild webhook                                                     */
    /* ------------------------------------------------------------------ */

    public function on_status_change($new, $old, $post) {
        if ($new === $old || ($new !== 'publish' && $old !== 'publish')) {
            return;
        }
        $this->queue_rebuild_for($post);
    }

    public function on_post_updated($post_id, $after, $before) {
        // Status changes are handled above; this catches edits to live content.
        if ($after->post_status === 'publish' && $before->post_status === 'publish') {
            $this->queue_rebuild_for($after);
        }
    }

    private function queue_rebuild_for($post) {
        if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }
        $found = self::find_type($post->post_type);
        if (!$found) {
            return;
        }
        self::queue_rebuild($found['site']['key']);
    }

    /**
     * Schedule one rebuild a short time out. A second change inside the window
     * finds the event already scheduled, so a bulk edit fires once.
     */
    public static function queue_rebuild($site_key) {
        $site = self::get_site($site_key);
        if (!$site) {
            return false;
        }
        if ($site['webhook_url'] === '') {
            self::record_webhook_error($site_key, 0, 'No rebuild webhook is configured, so the frontend was not rebuilt after this change.');
            return false;
        }
        if (!wp_next_scheduled(self::CRON_HOOK, array($site_key))) {
            wp_schedule_single_event(time() + self::REBUILD_DELAY, self::CRON_HOOK, array($site_key));
        }
        return true;
    }

    /** Cron callback, also callable directly. Returns true or WP_Error. */
    public static function fire_rebuild($site_key) {
        $site = self::get_site($site_key);
        if (!$site || $site['webhook_url'] === '') {
            return new WP_Error('no_webhook', 'No webhook for site ' . $site_key);
        }
        $response = wp_remote_post($site['webhook_url'], array(
            'timeout' => 15,
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode(array('source' => 'requestdesk-connector', 'site' => $site_key, 'at' => gmdate('c'))),
        ));
        if (is_wp_error($response)) {
            self::record_webhook_error($site_key, 0, $response->get_error_message());
            error_log('RequestDesk: rebuild webhook for ' . $site_key . ' failed - ' . $response->get_error_message());
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            self::record_webhook_error($site_key, $code, substr((string) wp_remote_retrieve_body($response), 0, 300));
            error_log('RequestDesk: rebuild webhook for ' . $site_key . ' returned HTTP ' . $code);
            return new WP_Error('webhook_http', 'HTTP ' . $code);
        }
        $errors = get_option(self::ERRORS_OPTION, array());
        if (isset($errors[$site_key])) {
            unset($errors[$site_key]);
            update_option(self::ERRORS_OPTION, $errors, false);
        }
        update_option('requestdesk_headless_site_last_rebuild_' . $site_key, gmdate('c'), false);
        return true;
    }

    private static function record_webhook_error($site_key, $code, $message) {
        $errors = get_option(self::ERRORS_OPTION, array());
        $errors[$site_key] = array('code' => $code, 'message' => $message, 'at' => gmdate('c'));
        update_option(self::ERRORS_OPTION, $errors, false);
    }

    /** A failed rebuild stays on screen until a later rebuild succeeds. */
    public function webhook_error_notices() {
        if (!current_user_can('edit_posts')) {
            return;
        }
        $errors = get_option(self::ERRORS_OPTION, array());
        foreach ($errors as $key => $e) {
            $site = self::get_site($key);
            if (!$site) {
                continue;
            }
            echo '<div class="notice notice-error"><p><strong>' . esc_html($site['name']) . ' was not rebuilt.</strong> ';
            echo esc_html($e['code'] ? 'The webhook answered HTTP ' . $e['code'] . '. ' : '');
            echo esc_html($e['message']) . ' <em>(' . esc_html($e['at']) . ' UTC)</em> ';
            echo '<a href="' . esc_url(admin_url('admin.php?page=requestdesk-headless-sites&site=' . $key)) . '">Headless Sites settings</a></p></div>';
        }
    }

    /* ------------------------------------------------------------------ */
    /* Site switcher on the list screens                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Adds a jump-list to the All | Mine | Published row of every list screen
     * a site owns (this install's posts and pages, and every headless-site
     * type), so an editor moves between sites and types in one click.
     * Only when at least one headless site exists.
     */
    public function add_site_views() {
        if (!self::get_sites()) {
            return;
        }
        $screen_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : 'post';
        if (!in_array($screen_type, requestdesk_content_post_types(), true)) {
            return;
        }
        add_filter('views_edit-' . $screen_type, array($this, 'site_views'));
    }

    /** Every list screen, grouped by site: [site name => [type => label]]. */
    public static function site_type_groups() {
        $groups = array(
            get_bloginfo('name') => array(
                'post' => get_post_type_object('post')->labels->name,
                'page' => get_post_type_object('page')->labels->name,
            ),
        );
        foreach (self::get_sites() as $site) {
            foreach ($site['types'] as $type) {
                $groups[$site['name']][$type['slug']] = $type['plural'];
            }
        }
        return $groups;
    }

    public function site_views($views) {
        $current = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : 'post';
        $links = array();
        foreach (self::site_type_groups() as $site_name => $types) {
            $parts = array();
            foreach ($types as $slug => $label) {
                if (!post_type_exists($slug) || !current_user_can(get_post_type_object($slug)->cap->edit_posts)) {
                    continue;
                }
                $counts = (array) wp_count_posts($slug);
                unset($counts['trash'], $counts['auto-draft'], $counts['inherit']);
                $count = array_sum(array_map('intval', $counts));
                $text = esc_html($label) . ' <span class="count">(' . number_format_i18n($count) . ')</span>';
                $parts[] = $slug === $current
                    ? '<strong>' . $text . '</strong>'
                    : '<a href="' . esc_url(admin_url('edit.php?post_type=' . $slug)) . '">' . $text . '</a>';
            }
            if ($parts) {
                $links[] = '<span class="rdhs-site">' . esc_html($site_name) . ':</span> ' . implode(' &middot; ', $parts);
            }
        }
        if ($links) {
            // A view row is a flat list joined by " | "; keep the sites on
            // their own line so they read as a switcher, not as filters.
            $views['rdhs_sites'] = '<br /><span class="rdhs-sites">' . implode(' &nbsp;&nbsp;|&nbsp;&nbsp; ', $links) . '</span>';
        }
        return $views;
    }

    /* ------------------------------------------------------------------ */
    /* Other plugins on a shared install                                   */
    /* ------------------------------------------------------------------ */

    public function polylang_post_types($types, $is_settings) {
        foreach (self::all_type_slugs() as $slug) {
            unset($types[$slug]);
        }
        return $types;
    }

    public function polylang_taxonomies($taxonomies, $is_settings) {
        foreach (self::all_taxonomy_slugs() as $slug) {
            unset($taxonomies[$slug]);
        }
        return $taxonomies;
    }

    public function yoast_exclude_type($excluded, $post_type) {
        return in_array($post_type, self::all_type_slugs(), true) ? true : $excluded;
    }

    public function yoast_exclude_taxonomy($excluded, $taxonomy) {
        return in_array($taxonomy, self::all_taxonomy_slugs(), true) ? true : $excluded;
    }

    public function yoast_accessible_types($types) {
        foreach (self::all_type_slugs() as $slug) {
            unset($types[$slug]);
        }
        return $types;
    }
}

/**
 * Every post type the Connector's content features work on: post, page, and
 * every type owned by a headless site. Use this instead of array('post','page').
 */
function requestdesk_content_post_types() {
    $types = array('post', 'page');
    if (class_exists('RequestDesk_Headless_Sites')) {
        $types = array_merge($types, RequestDesk_Headless_Sites::all_type_slugs());
    }
    return $types;
}
