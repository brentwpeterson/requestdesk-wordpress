<?php
/**
 * RequestDesk Headless API Class
 *
 * Provides REST API endpoints for using WordPress as a headless CMS
 * Designed for Astro SSR frontends
 *
 * @since 2.6.0
 */

class RequestDesk_Headless_API {

    /**
     * REST API namespace
     */
    private $namespace = 'requestdesk/v1';

    /**
     * Atomically increment the headless API request counter.
     *
     * Uses a raw SQL UPDATE so the increment is safe under concurrent
     * requests (no get_option/update_option race condition).
     */
    public static function increment_request_count($request = null) {
        // The keyless /public/ routes are anonymous. Counting them lets any
        // visitor write to the options table on every call and inflate the
        // usage figure reported under the client's key.
        if ($request instanceof WP_REST_Request
            && strpos((string) $request->get_route(), '/requestdesk/v1/public/') === 0) {
            return;
        }
        global $wpdb;
        $wpdb->query(
            "UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = 'requestdesk_headless_api_count'"
        );
    }

    /**
     * Register REST API routes for headless CMS
     */
    public function register_routes() {
        // The "Enable Headless API" switch. It used to be written by the
        // settings page and read by nothing, so an admin could untick it, see
        // "saved", and leave all eight routes serving content.
        $headless_settings = get_option('requestdesk_headless_settings', array());
        if (isset($headless_settings['enabled']) && !$headless_settings['enabled']) {
            return;
        }

        // List posts
        register_rest_route($this->namespace, '/headless/posts', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_posts'),
            'permission_callback' => array($this, 'verify_api_key'),
            'args' => array(
                'page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1
                ),
                'per_page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 50
                ),
                'category' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Filter by category slug'
                ),
                'exclude_category' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Exclude posts in this category slug'
                ),
                'tag' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Filter by tag slug'
                ),
                'search' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Search posts'
                ),
                'orderby' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'date',
                    'enum' => array('date', 'modified', 'title')
                ),
                'order' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'DESC',
                    'enum' => array('ASC', 'DESC')
                )
            )
        ));

        // Single post by slug
        register_rest_route($this->namespace, '/headless/posts/(?P<slug>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_post'),
            'permission_callback' => array($this, 'verify_api_key'),
            'args' => array(
                'slug' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Post slug'
                )
            )
        ));

        // List pages
        register_rest_route($this->namespace, '/headless/pages', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_pages'),
            'permission_callback' => array($this, 'verify_api_key'),
            'args' => array(
                'page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1
                ),
                'per_page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 50,
                    'minimum' => 1,
                    'maximum' => 100
                ),
                'parent' => array(
                    'required' => false,
                    'type' => 'integer',
                    'description' => 'Filter by parent page ID (0 for top-level)'
                )
            )
        ));

        // Single page by slug
        register_rest_route($this->namespace, '/headless/pages/(?P<slug>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_page'),
            'permission_callback' => array($this, 'verify_api_key'),
            'args' => array(
                'slug' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Page slug'
                )
            )
        ));

        // Site metadata
        register_rest_route($this->namespace, '/headless/site', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_site'),
            'permission_callback' => array($this, 'verify_api_key')
        ));

        // Video library. See RequestDesk_Video.
        register_rest_route($this->namespace, '/headless/videos', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_videos'),
            'permission_callback' => array($this, 'verify_api_key'),
            'args' => array(
                'placement' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Filter by rd_video_placement term slug'
                ),
                'per_page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 20,
                    'minimum' => 1,
                    'maximum' => 50
                ),
                'orderby' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'menu_order',
                    'enum' => array('menu_order', 'date', 'title')
                ),
                'order' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'ASC',
                    'enum' => array('ASC', 'DESC')
                )
            )
        ));

        // Events. See RequestDesk_Event.
        register_rest_route($this->namespace, '/headless/events', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_events'),
            'permission_callback' => array($this, 'verify_api_key'),
            'args' => array(
                'when' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'all',
                    'enum' => array('all', 'upcoming', 'past')
                ),
                'per_page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 100,
                    'minimum' => 1,
                    'maximum' => 100
                )
            )
        ));

        register_rest_route($this->namespace, '/headless/events/(?P<slug>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_event'),
            'permission_callback' => array($this, 'verify_api_key'),
            'args' => array(
                'slug' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Event slug'
                )
            )
        ));

        // Events with no key. The list and the single-event body are the same
        // information the public /events/ pages already show: published events
        // only, each with a start date and a city (get_events and get_event leave
        // out any that lack either). A frontend reads them without storing a
        // secret, so a regenerated key cannot break it. Reads only; every write
        // route still needs the RequestDesk API key.
        register_rest_route($this->namespace, '/public/events', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_events'),
            'permission_callback' => '__return_true',
            'args' => array(
                'when' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'all',
                    'enum' => array('all', 'upcoming', 'past')
                ),
                'per_page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 100,
                    'minimum' => 1,
                    'maximum' => 100
                )
            )
        ));

        register_rest_route($this->namespace, '/public/events/(?P<slug>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_event'),
            'permission_callback' => '__return_true',
            'args' => array(
                'slug' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Event slug'
                )
            )
        ));

        if (class_exists('RequestDesk_Headless_Sites')) {
            $this->register_site_routes();
        }
    }

    /* ------------------------------------------------------------------ */
    /* Headless sites: extra frontends served from this install.           */
    /* See RequestDesk_Headless_Sites. Every route is scoped to one site,  */
    /* and a type or taxonomy outside that site answers 404.               */
    /* ------------------------------------------------------------------ */

    private function register_site_routes() {
        $site = '(?P<site>[a-z][a-z0-9]{1,9})';

        // Discovery: every site, its types, taxonomies and fields. No secrets.
        register_rest_route($this->namespace, '/headless/sites', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'site_discovery'),
                'permission_callback' => array($this, 'verify_any_site_key'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'site_config_save'),
                'permission_callback' => array($this, 'verify_main_key'),
            ),
        ));

        register_rest_route($this->namespace, "/headless/sites/$site/config", array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'site_config_get'),
                'permission_callback' => array($this, 'verify_main_key'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'site_config_delete'),
                'permission_callback' => array($this, 'verify_main_key'),
            ),
        ));

        register_rest_route($this->namespace, "/headless/sites/$site/rebuild", array(
            'methods' => 'POST',
            'callback' => array($this, 'site_rebuild'),
            'permission_callback' => array($this, 'verify_main_key'),
        ));

        register_rest_route($this->namespace, "/headless/sites/$site/site", array(
            'methods' => 'GET',
            'callback' => array($this, 'site_meta'),
            'permission_callback' => array($this, 'verify_site_key'),
        ));

        register_rest_route($this->namespace, "/headless/sites/$site/menus", array(
            'methods' => 'GET',
            'callback' => array($this, 'site_menus'),
            'permission_callback' => array($this, 'verify_site_key'),
        ));

        register_rest_route($this->namespace, "/headless/sites/$site/terms/(?P<taxonomy>[a-z][a-z0-9_]{1,31})", array(
            'methods' => 'GET',
            'callback' => array($this, 'site_terms'),
            'permission_callback' => array($this, 'verify_site_key'),
        ));

        register_rest_route($this->namespace, "/headless/sites/$site/authors", array(
            'methods' => 'GET',
            'callback' => array($this, 'site_authors'),
            'permission_callback' => array($this, 'verify_site_key'),
        ));

        register_rest_route($this->namespace, "/headless/sites/$site/authors/(?P<slug>[a-zA-Z0-9_-]+)", array(
            'methods' => 'GET',
            'callback' => array($this, 'site_author'),
            'permission_callback' => array($this, 'verify_site_key'),
        ));

        register_rest_route($this->namespace, "/headless/sites/$site/(?P<type>[a-z][a-z0-9_]{1,19})", array(
            'methods' => 'GET',
            'callback' => array($this, 'site_items'),
            'permission_callback' => array($this, 'verify_site_key'),
            'args' => array(
                'page'     => array('type' => 'integer', 'default' => 1, 'minimum' => 1),
                'per_page' => array('type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100),
                'orderby'  => array('type' => 'string', 'default' => 'date', 'enum' => array('date', 'modified', 'title', 'menu_order')),
                'order'    => array('type' => 'string', 'default' => 'DESC', 'enum' => array('ASC', 'DESC')),
                'search'   => array('type' => 'string'),
                'taxonomy' => array('type' => 'string', 'description' => 'Taxonomy slug for term / exclude_term'),
                'term'     => array('type' => 'string', 'description' => 'Only items in this term slug'),
                'exclude_term' => array('type' => 'string', 'description' => 'Comma list of term slugs to leave out'),
                'author'   => array('type' => 'string', 'description' => 'Author term slug'),
                'field'    => array('type' => 'string', 'description' => 'Custom field key to match'),
                'value'    => array('type' => 'string', 'description' => 'Value the field must equal'),
                'parent'   => array('type' => 'integer', 'description' => 'Page-like types: parent id, 0 for top level'),
            ),
        ));

        register_rest_route($this->namespace, "/headless/sites/$site/(?P<type>[a-z][a-z0-9_]{1,19})/(?P<slug>[a-zA-Z0-9_-]+)", array(
            'methods' => 'GET',
            'callback' => array($this, 'site_item'),
            'permission_callback' => array($this, 'verify_site_key'),
        ));
    }

    /** The site named in the route, or a 404. */
    private function route_site($request) {
        $site = RequestDesk_Headless_Sites::get_site((string) $request->get_param('site'));
        if (!$site) {
            return new WP_Error('site_not_found', 'No headless site with key ' . $request->get_param('site'), array('status' => 404));
        }
        return $site;
    }

    /** The type config of the route's type, only if it belongs to the site. */
    private function route_type($site, $slug) {
        foreach ($site['types'] as $type) {
            if ($type['slug'] === $slug) {
                return $type;
            }
        }
        return new WP_Error('type_not_found', "Site {$site['key']} has no type $slug", array('status' => 404));
    }

    private function site_context($site, $type) {
        return array('site' => $site, 'type' => $type);
    }

    public function site_discovery($request) {
        self::increment_request_count($request);
        $only = $this->key_site_scope($request);
        $sites = array();
        foreach (RequestDesk_Headless_Sites::get_sites() as $key => $site) {
            if ($only !== null && $only !== $key) {
                continue;
            }
            $sites[] = RequestDesk_Headless_Sites::public_config($site);
        }
        return rest_ensure_response(array('success' => true, 'sites' => $sites));
    }

    public function site_config_get($request) {
        $site = $this->route_site($request);
        if (is_wp_error($site)) {
            return $site;
        }
        return rest_ensure_response(array('success' => true, 'site' => RequestDesk_Headless_Sites::public_config($site)));
    }

    /** Create or replace one site. Body is the site config. Main key only. */
    public function site_config_save($request) {
        $input = $request->get_json_params();
        $result = RequestDesk_Headless_Sites::save_site(is_array($input) ? $input : array());
        if (is_wp_error($result)) {
            return $result;
        }
        // Types registered from the old config are still in memory for this
        // request; the next request registers the new ones.
        return rest_ensure_response(array('success' => true, 'site' => RequestDesk_Headless_Sites::public_config($result)));
    }

    public function site_config_delete($request) {
        $result = RequestDesk_Headless_Sites::delete_site((string) $request->get_param('site'));
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response(array('success' => true, 'deleted' => $request->get_param('site')));
    }

    /** Fire the rebuild webhook now and report the answer. */
    public function site_rebuild($request) {
        $site = $this->route_site($request);
        if (is_wp_error($site)) {
            return $site;
        }
        $result = RequestDesk_Headless_Sites::fire_rebuild($site['key']);
        if (is_wp_error($result)) {
            return new WP_Error('rebuild_failed', $result->get_error_message(), array('status' => 502));
        }
        return rest_ensure_response(array('success' => true, 'site' => $site['key'], 'rebuilt_at' => gmdate('c')));
    }

    public function site_meta($request) {
        self::increment_request_count($request);
        $site = $this->route_site($request);
        if (is_wp_error($site)) {
            return $site;
        }
        $types = array();
        foreach ($site['types'] as $type) {
            $count = wp_count_posts($type['slug']);
            $types[] = array(
                'slug'     => $type['slug'],
                'singular' => $type['singular'],
                'plural'   => $type['plural'],
                'behavior' => $type['behavior'],
                'path'     => $type['path'],
                'published'=> isset($count->publish) ? (int) $count->publish : 0,
            );
        }
        $taxonomies = array();
        foreach ($site['taxonomies'] as $tax) {
            $taxonomies[] = array(
                'slug'  => $tax['slug'],
                'plural'=> $tax['plural'],
                'role'  => $tax['role'],
                'hierarchical' => $tax['hierarchical'],
                'terms' => $this->terms_for($tax['slug']),
            );
        }
        return rest_ensure_response(array(
            'success' => true,
            'site' => array(
                'key'         => $site['key'],
                'name'        => $site['name'],
                'description' => $site['description'],
                'url'         => $site['frontend_url'],
                'logo'        => $site['logo'] !== '' ? $site['logo'] : null,
                'og_image'    => $site['og_image'] !== '' ? $site['og_image'] : null,
                'favicon'     => $site['favicon'] !== '' ? $site['favicon'] : null,
                'language'    => get_locale(),
                'timezone'    => wp_timezone_string(),
                'types'       => $types,
                'taxonomies'  => $taxonomies,
                'menus'       => $this->menus_for_locations($this->site_locations($site)),
                'plugin_version' => REQUESTDESK_VERSION,
            ),
        ));
    }

    public function site_menus($request) {
        self::increment_request_count($request);
        $site = $this->route_site($request);
        if (is_wp_error($site)) {
            return $site;
        }
        return rest_ensure_response(array('success' => true, 'menus' => $this->menus_for_locations($this->site_locations($site))));
    }

    /** location key as the site names it => real location. */
    private function site_locations($site) {
        $out = array();
        foreach ($site['menus'] as $menu) {
            $out[$menu['slug']] = RequestDesk_Headless_Sites::menu_location($site, $menu['slug']);
        }
        return $out;
    }

    private function menus_for_locations($locations) {
        $assigned = get_nav_menu_locations();
        $menus = array();
        foreach ($locations as $name => $location) {
            $menus[$name] = array();
            if (empty($assigned[$location])) {
                continue;
            }
            $items = wp_get_nav_menu_items($assigned[$location]);
            foreach ((array) $items as $item) {
                $menus[$name][] = array(
                    'id'     => (int) $item->ID,
                    'parent' => (int) $item->menu_item_parent,
                    'title'  => $item->title,
                    'url'    => $this->menu_item_url($item),
                    'target' => $item->target ?: '_self',
                );
            }
        }
        return $menus;
    }

    /**
     * A menu item pointing at a headless-site item has no WordPress URL
     * (the types have no permalinks), so resolve it to the frontend URL.
     */
    private function menu_item_url($item) {
        if ($item->type === 'post_type' && !empty($item->object_id)) {
            $post = get_post((int) $item->object_id);
            if ($post) {
                $found = RequestDesk_Headless_Sites::find_type($post->post_type);
                if ($found) {
                    return $this->frontend_url($post, $found['site'], $found['type']);
                }
            }
        }
        return $item->url;
    }

    private function terms_for($taxonomy) {
        $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name'));
        $out = array();
        if (is_wp_error($terms)) {
            return $out;
        }
        foreach ($terms as $term) {
            $out[] = array(
                'id'          => $term->term_id,
                'name'        => $term->name,
                'slug'        => $term->slug,
                'description' => $term->description,
                'parent'      => (int) $term->parent,
                'count'       => (int) $term->count,
            );
        }
        return $out;
    }

    public function site_terms($request) {
        self::increment_request_count($request);
        $site = $this->route_site($request);
        if (is_wp_error($site)) {
            return $site;
        }
        $slug = (string) $request->get_param('taxonomy');
        if (!in_array($slug, array_map(function ($x) { return $x['slug']; }, $site['taxonomies']), true)) {
            return new WP_Error('taxonomy_not_found', "Site {$site['key']} has no taxonomy $slug", array('status' => 404));
        }
        return rest_ensure_response(array('success' => true, 'taxonomy' => $slug, 'terms' => $this->terms_for($slug)));
    }

    private function author_tax_or_error($site) {
        if (empty($site['authors'])) {
            return new WP_Error('authors_disabled', "Site {$site['key']} has no author type", array('status' => 404));
        }
        return RequestDesk_Headless_Sites::author_taxonomy($site);
    }

    public function site_authors($request) {
        self::increment_request_count($request);
        $site = $this->route_site($request);
        if (is_wp_error($site)) {
            return $site;
        }
        $tax = $this->author_tax_or_error($site);
        if (is_wp_error($tax)) {
            return $tax;
        }
        $terms = get_terms(array('taxonomy' => $tax, 'hide_empty' => false, 'orderby' => 'name'));
        $authors = array();
        foreach (is_wp_error($terms) ? array() : $terms as $term) {
            $authors[] = RequestDesk_Headless_Sites::format_author_term($term);
        }
        return rest_ensure_response(array('success' => true, 'authors' => $authors));
    }

    public function site_author($request) {
        self::increment_request_count($request);
        $site = $this->route_site($request);
        if (is_wp_error($site)) {
            return $site;
        }
        $tax = $this->author_tax_or_error($site);
        if (is_wp_error($tax)) {
            return $tax;
        }
        $term = get_term_by('slug', sanitize_title($request->get_param('slug')), $tax);
        if (!$term) {
            return new WP_Error('author_not_found', 'Author not found: ' . $request->get_param('slug'), array('status' => 404));
        }
        return rest_ensure_response(array('success' => true, 'author' => RequestDesk_Headless_Sites::format_author_term($term)));
    }

    public function site_items($request) {
        self::increment_request_count($request);
        $site = $this->route_site($request);
        if (is_wp_error($site)) {
            return $site;
        }
        $type = $this->route_type($site, (string) $request->get_param('type'));
        if (is_wp_error($type)) {
            return $type;
        }

        $page     = (int) ($request->get_param('page') ?: 1);
        $per_page = (int) ($request->get_param('per_page') ?: 10);
        $orderby  = $request->get_param('orderby') ?: ($type['behavior'] === 'page' ? 'menu_order' : 'date');
        $order    = $request->get_param('order') ?: ($type['behavior'] === 'page' ? 'ASC' : 'DESC');

        $args = array(
            'post_type'      => $type['slug'],
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => $orderby,
            'order'          => $order,
        );

        if ($request->get_param('search')) {
            $args['s'] = sanitize_text_field($request->get_param('search'));
        }
        if ($request->get_param('parent') !== null && $type['behavior'] === 'page') {
            $args['post_parent'] = (int) $request->get_param('parent');
        }

        $tax_query = array();
        $term = $request->get_param('term');
        $exclude = $request->get_param('exclude_term');
        if ($term || $exclude) {
            $tax_slug = (string) $request->get_param('taxonomy');
            $own = RequestDesk_Headless_Sites::type_taxonomies($site, $type);
            if ($tax_slug === '') {
                // No taxonomy named: use the type's category-role taxonomy.
                foreach ($own as $x) {
                    if ($x['role'] === 'category') {
                        $tax_slug = $x['slug'];
                    }
                }
            }
            if (!in_array($tax_slug, array_map(function ($x) { return $x['slug']; }, $own), true)) {
                return new WP_Error('taxonomy_not_found', "Type {$type['slug']} has no taxonomy " . ($tax_slug ?: '(none named)'), array('status' => 400));
            }
            if ($term) {
                $tax_query[] = array('taxonomy' => $tax_slug, 'field' => 'slug', 'terms' => sanitize_title($term));
            }
            if ($exclude) {
                $tax_query[] = array(
                    'taxonomy' => $tax_slug,
                    'field'    => 'slug',
                    'terms'    => array_map('sanitize_title', explode(',', $exclude)),
                    'operator' => 'NOT IN',
                );
            }
        }
        if ($request->get_param('author')) {
            $author_tax = $this->author_tax_or_error($site);
            if (is_wp_error($author_tax)) {
                return $author_tax;
            }
            $tax_query[] = array('taxonomy' => $author_tax, 'field' => 'slug', 'terms' => sanitize_title($request->get_param('author')));
        }
        if ($tax_query) {
            $args['tax_query'] = array_merge(array('relation' => 'AND'), $tax_query);
        }

        $field = $request->get_param('field');
        if ($field !== null && $field !== '') {
            $match = null;
            foreach ($type['fields'] as $f) {
                if ($f['key'] === $field) {
                    $match = $f;
                }
            }
            if (!$match) {
                return new WP_Error('field_not_found', "Type {$type['slug']} has no field $field", array('status' => 400));
            }
            $args['meta_query'] = array(array(
                'key'   => RequestDesk_Headless_Sites::field_meta_key($match),
                'value' => (string) $request->get_param('value'),
            ));
        }

        $query = new WP_Query($args);
        $ctx = $this->site_context($site, $type);
        $items = array();
        foreach ($query->posts as $post) {
            $items[] = $type['behavior'] === 'page'
                ? $this->format_page($post, false, $ctx)
                : $this->format_post($post, false, $ctx);
        }
        $total = (int) $query->found_posts;
        $total_pages = (int) ceil($total / $per_page);

        return rest_ensure_response(array(
            'success' => true,
            'site'    => $site['key'],
            'type'    => $type['slug'],
            'items'   => $items,
            'pagination' => array(
                'page'         => $page,
                'per_page'     => $per_page,
                'total'        => $total,
                'total_pages'  => $total_pages,
                'has_next'     => $page < $total_pages,
                'has_previous' => $page > 1,
            ),
        ));
    }

    public function site_item($request) {
        self::increment_request_count($request);
        $site = $this->route_site($request);
        if (is_wp_error($site)) {
            return $site;
        }
        $type = $this->route_type($site, (string) $request->get_param('type'));
        if (is_wp_error($type)) {
            return $type;
        }
        $slug = sanitize_title($request->get_param('slug'));
        $query = new WP_Query(array(
            'post_type'      => $type['slug'],
            'post_status'    => 'publish',
            'name'           => $slug,
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ));
        if (empty($query->posts)) {
            return new WP_Error('item_not_found', "No {$type['singular']} with slug $slug", array('status' => 404));
        }
        $ctx = $this->site_context($site, $type);
        $post = $query->posts[0];
        return rest_ensure_response(array(
            'success' => true,
            'site'    => $site['key'],
            'type'    => $type['slug'],
            'item'    => $type['behavior'] === 'page' ? $this->format_page($post, true, $ctx) : $this->format_post($post, true, $ctx),
        ));
    }

    /** The URL an item lives at on its frontend, from the type's path pattern. */
    private function frontend_url($post, $site, $type) {
        return RequestDesk_Headless_Sites::item_url($post);
    }

    /** Extra fields a headless-site item carries: terms, author, custom fields. */
    private function site_item_extras($post, $ctx, &$data) {
        $site = $ctx['site'];
        $type = $ctx['type'];

        $terms = array();
        foreach (RequestDesk_Headless_Sites::type_taxonomies($site, $type) as $tax) {
            $list = array();
            $assigned = get_the_terms($post->ID, $tax['slug']);
            foreach (is_array($assigned) ? $assigned : array() as $t) {
                $list[] = array('id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug);
            }
            $terms[$tax['slug']] = $list;
            // Role aliases keep a frontend written for posts working unchanged.
            if ($tax['role'] === 'category') {
                $data['categories'] = $list;
            } elseif ($tax['role'] === 'tag') {
                $data['tags'] = $list;
            }
        }
        $data['terms'] = $terms;

        if (!empty($site['authors']) && $type['behavior'] === 'post') {
            $assigned = get_the_terms($post->ID, RequestDesk_Headless_Sites::author_taxonomy($site));
            $authors = array();
            foreach (is_array($assigned) ? $assigned : array() as $t) {
                $authors[] = RequestDesk_Headless_Sites::format_author_term($t);
            }
            $data['authors'] = $authors;
            if ($authors) {
                $data['author'] = array(
                    'name'   => $authors[0]['name'],
                    'slug'   => $authors[0]['slug'],
                    'avatar' => $authors[0]['avatar'],
                );
            }
        }

        $fields = RequestDesk_Headless_Sites::read_fields($post->ID, $type);
        $data['fields'] = $fields['fields'];
        foreach ($fields['root'] as $k => $v) {
            if (!array_key_exists($k, $data)) {
                $data[$k] = $v;
            }
        }
        $data['url'] = $this->frontend_url($post, $site, $type);
    }

    /** Which site a per-site key is scoped to; null for the main or headless key. */
    private function key_site_scope($request) {
        $provided = $this->provided_key($request);
        if ($provided === '') {
            return null;
        }
        foreach (RequestDesk_Headless_Sites::get_sites() as $key => $site) {
            if ($site['api_key'] !== '' && hash_equals($site['api_key'], $provided)) {
                return $key;
            }
        }
        return null;
    }

    private function provided_key($request) {
        $key = $request->get_header('X-RequestDesk-API-Key');
        if (empty($key)) {
            $key = $request->get_header('X-WP-Headless-Key');
        }
        if (empty($key)) {
            $auth = (string) $request->get_header('Authorization');
            $key = strpos($auth, 'Bearer ') === 0 ? substr($auth, 7) : $auth;
        }
        return (string) $key;
    }

    /** Route for one site: the headless key, the main key, or that site's own key. */
    public function verify_site_key($request) {
        $site = RequestDesk_Headless_Sites::get_site((string) $request->get_param('site'));
        if ($site && $site['api_key'] !== '' && hash_equals($site['api_key'], $this->provided_key($request))) {
            return true;
        }
        return $this->verify_api_key($request);
    }

    /** Discovery: any accepted key; a per-site key sees only its own site. */
    public function verify_any_site_key($request) {
        if ($this->key_site_scope($request) !== null) {
            return true;
        }
        return $this->verify_api_key($request);
    }

    /** Config writes: the main RequestDesk key only, like every write route. */
    public function verify_main_key($request) {
        $settings = get_option('requestdesk_settings', array());
        $main = (string) ($settings['api_key'] ?? '');
        if ($main === '') {
            return new WP_Error('no_api_key', 'RequestDesk API key not configured', array('status' => 401));
        }
        $provided = (string) $request->get_header('X-RequestDesk-API-Key');
        if ($provided === '' || !hash_equals($main, $provided)) {
            return new WP_Error('invalid_api_key', 'Invalid API key', array('status' => 401));
        }
        return true;
    }

    /**
     * Videos, optionally filtered to one placement.
     *
     * Returns an empty list rather than a 404 when the placement term does not
     * exist. A page asking for a placement nobody has tagged yet is a normal
     * state, not an error, and the Astro side renders nothing for an empty
     * array. Erroring here would turn "no videos yet" into a broken page.
     *
     * Default order is menu_order ASC so the running order is editorial: drag
     * to reorder in the admin list, no deploy. Date order is available but is
     * rarely what a curated strip on a page wants.
     */
    public function get_videos($request) {
        self::increment_request_count();
        try {
            if (!class_exists('RequestDesk_Video')) {
                return new WP_Error(
                    'videos_unavailable',
                    'Video module is not enabled on this site.',
                    array('status' => 501)
                );
            }

            $placement = $request->get_param('placement');
            $orderby   = $request->get_param('orderby') ?: 'menu_order';

            $args = array(
                'post_type'      => RequestDesk_Video::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => $request->get_param('per_page') ?: 20,
                'orderby'        => $orderby === 'menu_order' ? array('menu_order' => 'ASC', 'date' => 'DESC') : $orderby,
                'order'          => $request->get_param('order') ?: 'ASC',
                'no_found_rows'  => true,
            );

            if (!empty($placement)) {
                $args['tax_query'] = array(array(
                    'taxonomy' => RequestDesk_Video::TAXONOMY,
                    'field'    => 'slug',
                    'terms'    => sanitize_title($placement),
                ));
            }

            $query  = new WP_Query($args);
            $videos = array();
            foreach ($query->posts as $post) {
                $formatted = RequestDesk_Video::format_for_api($post);
                // Drop rows with no id. A draft saved before the id was pasted
                // must not reach the site as an empty player.
                if ($formatted !== null) {
                    $videos[] = $formatted;
                }
            }

            return rest_ensure_response(array(
                'videos'    => $videos,
                'count'     => count($videos),
                'placement' => $placement ?: null,
            ));
        } catch (Throwable $e) {
            return new WP_Error('videos_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Short edge-cache lifetime for the open event routes (/public/events...).
     * Without a lifetime, the CDN in front of the site kept serving an old list
     * long after an event was added (2026-10-04: four events cached while the
     * site held thirteen), so a change made through the MCP did not reach a
     * frontend that reads this list. Sixty seconds keeps the routes cheap and
     * the lag short.
     * The key-protected /headless/ routes are not marked public.
     */
    private function public_cache($response, $request) {
        if (strpos((string) $request->get_route(), '/requestdesk/v1/public/') === 0
            && $response instanceof WP_HTTP_Response) {
            $response->header('Cache-Control', 'public, max-age=60, s-maxage=60');
            $response->header('Surrogate-Control', 'max-age=60');
        }
        return $response;
    }

    /**
     * Published events, upcoming soonest first, then past most recent first.
     *
     * The list leaves out each event's rendered body to stay small; the single
     * route carries it. `upcoming` is computed per request from end_date, so
     * `when=upcoming` is always today's answer rather than whatever was true at
     * the last save.
     */
    public function get_events($request) {
        self::increment_request_count($request);
        try {
            if (!class_exists('RequestDesk_Event')) {
                return new WP_Error(
                    'events_unavailable',
                    'Event module is not enabled on this site.',
                    array('status' => 501)
                );
            }

            $when     = $request->get_param('when') ?: 'all';
            $per_page = $request->get_param('per_page') ?: 100;

            $query = new WP_Query(array(
                'post_type'      => RequestDesk_Event::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'no_found_rows'  => true,
            ));

            $events = array();
            foreach ($query->posts as $post) {
                $formatted = RequestDesk_Event::format_for_api($post, false);
                if ($formatted === null) {
                    continue;
                }
                if ($when === 'upcoming' && !$formatted['upcoming']) {
                    continue;
                }
                if ($when === 'past' && $formatted['upcoming']) {
                    continue;
                }
                $events[] = $formatted;
            }

            $events = array_slice(RequestDesk_Event::sort_for_api($events), 0, $per_page);

            return $this->public_cache(rest_ensure_response(array(
                'events' => $events,
                'count'  => count($events),
                'when'   => $when,
            )), $request);
        } catch (Throwable $e) {
            return new WP_Error('events_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * One published event by slug, with its rendered body. An event missing its
     * start date or city answers 404, the same as one that does not exist,
     * because the list route leaves it out too.
     */
    public function get_event($request) {
        self::increment_request_count($request);
        try {
            if (!class_exists('RequestDesk_Event')) {
                return new WP_Error(
                    'events_unavailable',
                    'Event module is not enabled on this site.',
                    array('status' => 501)
                );
            }

            $slug  = sanitize_title($request->get_param('slug'));
            $query = new WP_Query(array(
                'post_type'      => RequestDesk_Event::POST_TYPE,
                'post_status'    => 'publish',
                'name'           => $slug,
                'posts_per_page' => 1,
                'no_found_rows'  => true,
            ));

            $formatted = empty($query->posts) ? null : RequestDesk_Event::format_for_api($query->posts[0], true);
            if ($formatted === null) {
                return new WP_Error(
                    'event_not_found',
                    'Event not found with slug: ' . $slug,
                    array('status' => 404)
                );
            }

            return $this->public_cache(rest_ensure_response(array('event' => $formatted)), $request);
        } catch (Throwable $e) {
            return new WP_Error('event_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Get list of posts
     */
    public function get_posts($request) {
        self::increment_request_count();
        try {
            $page = $request->get_param('page') ?: 1;
            $per_page = $request->get_param('per_page') ?: 10;
            $category = $request->get_param('category');
            $exclude_category = $request->get_param('exclude_category');
            $tag = $request->get_param('tag');
            $search = $request->get_param('search');
            $orderby = $request->get_param('orderby') ?: 'date';
            $order = $request->get_param('order') ?: 'DESC';

            $args = array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => $per_page,
                'paged' => $page,
                'orderby' => $orderby,
                'order' => $order,
                'no_found_rows' => false
            );

            // Category filter
            if (!empty($category)) {
                $args['category_name'] = sanitize_text_field($category);
            }

            // Exclude category filter
            if (!empty($exclude_category)) {
                $exclude_term = get_category_by_slug(sanitize_text_field($exclude_category));
                if ($exclude_term) {
                    $args['category__not_in'] = array($exclude_term->term_id);
                }
            }

            // Tag filter
            if (!empty($tag)) {
                $args['tag'] = sanitize_text_field($tag);
            }

            // Search filter
            if (!empty($search)) {
                $args['s'] = sanitize_text_field($search);
            }

            $query = new WP_Query($args);
            $posts = array();

            foreach ($query->posts as $post) {
                $posts[] = $this->format_post($post, false);
            }

            $total = $query->found_posts;
            $total_pages = ceil($total / $per_page);

            return new WP_REST_Response(array(
                'success' => true,
                'posts' => $posts,
                'pagination' => array(
                    'page' => (int) $page,
                    'per_page' => (int) $per_page,
                    'total' => (int) $total,
                    'total_pages' => (int) $total_pages,
                    'has_next' => $page < $total_pages,
                    'has_previous' => $page > 1
                )
            ), 200);

        } catch (Exception $e) {
            return new WP_Error(
                'headless_posts_error',
                'Failed to fetch posts: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get single post by slug
     */
    public function get_post($request) {
        self::increment_request_count();
        try {
            $slug = sanitize_text_field($request->get_param('slug'));

            $args = array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'name' => $slug,
                'posts_per_page' => 1
            );

            $query = new WP_Query($args);

            if (empty($query->posts)) {
                return new WP_Error(
                    'post_not_found',
                    'Post not found with slug: ' . $slug,
                    array('status' => 404)
                );
            }

            $post = $query->posts[0];

            return new WP_REST_Response(array(
                'success' => true,
                'post' => $this->format_post($post, true)
            ), 200);

        } catch (Exception $e) {
            return new WP_Error(
                'headless_post_error',
                'Failed to fetch post: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get list of pages
     */
    public function get_pages($request) {
        self::increment_request_count();
        try {
            $page = $request->get_param('page') ?: 1;
            $per_page = $request->get_param('per_page') ?: 50;
            $parent = $request->get_param('parent');

            $args = array(
                'post_type' => 'page',
                'post_status' => 'publish',
                'posts_per_page' => $per_page,
                'paged' => $page,
                'orderby' => 'menu_order',
                'order' => 'ASC',
                'no_found_rows' => false
            );

            // Parent filter
            if ($parent !== null) {
                $args['post_parent'] = (int) $parent;
            }

            $query = new WP_Query($args);
            $pages = array();

            foreach ($query->posts as $page_post) {
                $pages[] = $this->format_page($page_post, false);
            }

            $total = $query->found_posts;
            $total_pages = ceil($total / $per_page);

            return new WP_REST_Response(array(
                'success' => true,
                'pages' => $pages,
                'pagination' => array(
                    'page' => (int) $page,
                    'per_page' => (int) $per_page,
                    'total' => (int) $total,
                    'total_pages' => (int) $total_pages,
                    'has_next' => $page < $total_pages,
                    'has_previous' => $page > 1
                )
            ), 200);

        } catch (Exception $e) {
            return new WP_Error(
                'headless_pages_error',
                'Failed to fetch pages: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get single page by slug
     */
    public function get_page($request) {
        self::increment_request_count();
        try {
            $slug = sanitize_text_field($request->get_param('slug'));

            $args = array(
                'post_type' => 'page',
                'post_status' => 'publish',
                'name' => $slug,
                'posts_per_page' => 1
            );

            $query = new WP_Query($args);

            if (empty($query->posts)) {
                return new WP_Error(
                    'page_not_found',
                    'Page not found with slug: ' . $slug,
                    array('status' => 404)
                );
            }

            $page = $query->posts[0];

            return new WP_REST_Response(array(
                'success' => true,
                'page' => $this->format_page($page, true)
            ), 200);

        } catch (Exception $e) {
            return new WP_Error(
                'headless_page_error',
                'Failed to fetch page: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get site metadata
     */
    public function get_site($request) {
        self::increment_request_count();
        try {
            // Get all categories with post counts
            $categories = get_categories(array(
                'hide_empty' => true,
                'orderby' => 'count',
                'order' => 'DESC'
            ));

            $category_list = array();
            foreach ($categories as $cat) {
                $category_list[] = array(
                    'id' => $cat->term_id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'description' => $cat->description,
                    'count' => $cat->count
                );
            }

            // Get navigation menus if registered
            $menus = array();
            $menu_locations = get_nav_menu_locations();
            $site_locations = class_exists('RequestDesk_Headless_Sites')
                ? RequestDesk_Headless_Sites::all_menu_locations() : array();
            foreach ($menu_locations as $location => $menu_id) {
                // A headless site's menus belong to that site's /menus route.
                if (in_array($location, $site_locations, true)) {
                    continue;
                }
                if ($menu_id) {
                    $menu_items = wp_get_nav_menu_items($menu_id);
                    if ($menu_items) {
                        $menus[$location] = array();
                        foreach ($menu_items as $item) {
                            $menus[$location][] = array(
                                'title' => $item->title,
                                'url' => $item->url,
                                'target' => $item->target ?: '_self'
                            );
                        }
                    }
                }
            }

            // Get site icon/logo
            $custom_logo_id = get_theme_mod('custom_logo');
            $logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : null;
            $favicon_id = get_option('site_icon');
            $favicon_url = $favicon_id ? wp_get_attachment_image_url($favicon_id, 'full') : null;

            return new WP_REST_Response(array(
                'success' => true,
                'site' => array(
                    'name' => get_bloginfo('name'),
                    'description' => get_bloginfo('description'),
                    'url' => home_url(),
                    'language' => get_locale(),
                    'timezone' => wp_timezone_string(),
                    'date_format' => get_option('date_format'),
                    'time_format' => get_option('time_format'),
                    'logo' => $logo_url,
                    'favicon' => $favicon_url,
                    'categories' => $category_list,
                    'menus' => $menus,
                    'plugin_version' => REQUESTDESK_VERSION,
                    'wordpress_version' => get_bloginfo('version')
                )
            ), 200);

        } catch (Exception $e) {
            return new WP_Error(
                'headless_site_error',
                'Failed to fetch site metadata: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Format post data for API response
     */
    private function format_post($post, $include_full = true, $ctx = null) {
        $post_id = $post->ID;

        // Author data
        $author_id = $post->post_author;
        $author = array(
            'name' => get_the_author_meta('display_name', $author_id),
            'slug' => get_the_author_meta('user_nicename', $author_id),
            'avatar' => get_avatar_url($author_id, array('size' => 96))
        );

        // Categories
        $categories = array();
        $post_categories = wp_get_post_categories($post_id, array('fields' => 'all'));
        foreach ($post_categories as $cat) {
            $categories[] = array(
                'id' => $cat->term_id,
                'name' => $cat->name,
                'slug' => $cat->slug
            );
        }

        // Tags
        $tags = array();
        $post_tags = wp_get_post_tags($post_id);
        foreach ($post_tags as $tag) {
            $tags[] = array(
                'id' => $tag->term_id,
                'name' => $tag->name,
                'slug' => $tag->slug
            );
        }

        // Featured image
        $featured_image = null;
        $featured_image_alt = null;
        $featured_image_id = get_post_thumbnail_id($post_id);
        if ($featured_image_id) {
            $featured_image = get_the_post_thumbnail_url($post_id, 'full');
            $featured_image_alt = get_post_meta($featured_image_id, '_wp_attachment_image_alt', true);
        }

        // Word count and reading time
        $content = $post->post_content;
        $word_count = str_word_count(strip_tags($content));
        $reading_time = ceil($word_count / 200);

        // SEO data. A headless-site item's canonical is its frontend URL.
        $seo = $this->get_seo_data($post, $ctx ? $this->frontend_url($post, $ctx['site'], $ctx['type']) : null);

        $data = array(
            'id' => $post_id,
            'slug' => $post->post_name,
            'title' => $post->post_title,
            'excerpt' => get_the_excerpt($post),
            'featured_image' => $featured_image,
            'featured_image_alt' => $featured_image_alt,
            'published_at' => date('c', strtotime($post->post_date)),
            'modified_at' => date('c', strtotime($post->post_modified)),
            'author' => $author,
            'categories' => $categories,
            'tags' => $tags,
            'word_count' => $word_count,
            'reading_time_minutes' => $reading_time,
            'seo' => $seo
        );

        if ($ctx) {
            // Terms, author terms and custom fields come from the site config.
            // Native category/tag lists do not apply to a headless-site type.
            $data['categories'] = array();
            $data['tags'] = array();
            $this->site_item_extras($post, $ctx, $data);
        } else {
            // Podcast episode fields. Added only when a value exists, so the
            // response for an ordinary post is unchanged.
            $data = array_merge($data, $this->get_podcast_data($post_id));
        }

        // Include full content for single post requests
        if ($include_full) {
            $data['content'] = apply_filters('the_content', $content);
        }

        return $data;
    }

    /**
     * Podcast episode fields for a post.
     *
     * audio_embed / audio_url: the Podcaster plugin keeps the player's embed code in
     * cmb_thst_audio_embed_code and the source url in cmb_thst_audio_embed. The Astro
     * front end has always asked for `audio_embed`; this is the first time it is sent.
     *
     * guest / duration_seconds: no plugin on the site stores these. They read the
     * _requestdesk_* meta keys below, which stay empty until something fills them, so
     * a post without them returns neither field.
     */
    private function get_podcast_data($post_id) {
        $out = array();

        $embed = (string) get_post_meta($post_id, 'cmb_thst_audio_embed_code', true);
        if ($embed !== '') {
            $out['audio_embed'] = $embed;
        }

        $audio_url = (string) get_post_meta($post_id, 'cmb_thst_audio_embed', true);
        if ($audio_url !== '') {
            $out['audio_url'] = esc_url_raw($audio_url);
        }

        $guest = array(
            'name'    => (string) get_post_meta($post_id, '_requestdesk_guest_name', true),
            'company' => (string) get_post_meta($post_id, '_requestdesk_guest_company', true),
            'title'   => (string) get_post_meta($post_id, '_requestdesk_guest_title', true),
            'linkedin' => (string) get_post_meta($post_id, '_requestdesk_guest_linkedin', true),
        );
        $guest = array_filter($guest, 'strlen');
        if (!empty($guest)) {
            $out['guest'] = $guest;
        }

        $duration = (int) get_post_meta($post_id, '_requestdesk_duration_seconds', true);
        if ($duration > 0) {
            $out['duration_seconds'] = $duration;
        }

        // The RequestDesk episode record this post was matched to.
        $rd_id = (string) get_post_meta($post_id, '_requestdesk_episode_id', true);
        if ($rd_id !== '') {
            $out['requestdesk_episode_id'] = $rd_id;
        }

        return $out;
    }

    /**
     * Format page data for API response
     */
    private function format_page($page, $include_full = true, $ctx = null) {
        $page_id = $page->ID;

        // Featured image
        $featured_image = null;
        $featured_image_alt = null;
        $featured_image_id = get_post_thumbnail_id($page_id);
        if ($featured_image_id) {
            $featured_image = get_the_post_thumbnail_url($page_id, 'full');
            $featured_image_alt = get_post_meta($featured_image_id, '_wp_attachment_image_alt', true);
        }

        // Parent info
        $parent = array(
            'id' => $page->post_parent,
            'slug' => null,
            'title' => null
        );
        if ($page->post_parent > 0) {
            $parent_page = get_post($page->post_parent);
            if ($parent_page) {
                $parent['slug'] = $parent_page->post_name;
                $parent['title'] = $parent_page->post_title;
            }
        }

        // Children
        $children = array();
        if ($include_full) {
            $child_pages = get_children(array(
                'post_parent' => $page_id,
                'post_type' => $page->post_type,
                'post_status' => 'publish',
                'orderby' => 'menu_order',
                'order' => 'ASC'
            ));
            foreach ($child_pages as $child) {
                $children[] = array(
                    'id' => $child->ID,
                    'slug' => $child->post_name,
                    'title' => $child->post_title,
                    'menu_order' => $child->menu_order
                );
            }
        }

        // SEO data
        $seo = $this->get_seo_data($page, $ctx ? $this->frontend_url($page, $ctx['site'], $ctx['type']) : null);

        $data = array(
            'id' => $page_id,
            'slug' => $page->post_name,
            'title' => $page->post_title,
            'excerpt' => get_the_excerpt($page),
            'featured_image' => $featured_image,
            'featured_image_alt' => $featured_image_alt,
            'published_at' => date('c', strtotime($page->post_date)),
            'modified_at' => date('c', strtotime($page->post_modified)),
            'parent' => $parent,
            'menu_order' => $page->menu_order,
            'template' => get_page_template_slug($page_id) ?: 'default',
            'seo' => $seo
        );

        if ($ctx) {
            $this->site_item_extras($page, $ctx, $data);
        }

        if ($include_full) {
            $data['content'] = apply_filters('the_content', $page->post_content);
            $data['children'] = $children;
        }

        return $data;
    }

    /**
     * Read one _requestdesk_* SEO value.
     *
     * Values imported from Yoast by RequestDesk_Yoast_Importer can still carry
     * Yoast-style %%variables%%, so run them through Yoast's replacement when
     * Yoast is present. With Yoast gone, a stored %%variable%% would render
     * literally, so drop it rather than ship the token to the front end.
     */
    private function seo_meta_value($post_id, $meta_key) {
        $value = (string) get_post_meta($post_id, $meta_key, true);
        if ($value === '') {
            return '';
        }
        if (strpos($value, '%%') !== false) {
            if (function_exists('wpseo_replace_vars')) {
                $value = wpseo_replace_vars($value, get_post($post_id));
            } else {
                $value = trim(preg_replace('/%%[^%]+%%/', '', $value));
            }
        }
        return $value;
    }

    /**
     * Get SEO data from various SEO plugins or post meta
     */
    private function get_seo_data($post, $frontend_url = null) {
        $post_id = $post->ID;
        $permalink = $frontend_url ?: get_permalink($post_id);

        // Default title and description. A headless-site item is titled with
        // its own site's name, not this install's.
        $found = $frontend_url && class_exists('RequestDesk_Headless_Sites')
            ? RequestDesk_Headless_Sites::find_type($post->post_type) : null;
        $title = $post->post_title . ' | ' . ($found ? $found['site']['name'] : get_bloginfo('name'));
        $description = get_the_excerpt($post);

        // RequestDesk's own values, which WIN over every SEO plugin below.
        // This mirrors RequestDesk_Yoast_Meta, which already makes these
        // values beat Yoast in the tags Yoast prints on a rendered site. A
        // headless front end never runs those filters, so the precedence has
        // to be applied here too or the two paths disagree about the same post.
        $rd_title = $this->seo_meta_value($post_id, '_requestdesk_seo_title');
        $rd_desc = $this->seo_meta_value($post_id, '_requestdesk_seo_description');
        $rd_keyphrase = $this->seo_meta_value($post_id, '_requestdesk_focus_keyphrase');

        // Check for Yoast SEO
        $yoast_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
        $yoast_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        $yoast_keyphrase = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);

        // Check for RankMath
        $rankmath_title = get_post_meta($post_id, 'rank_math_title', true);
        $rankmath_desc = get_post_meta($post_id, 'rank_math_description', true);
        $rankmath_keyphrase = get_post_meta($post_id, 'rank_math_focus_keyword', true);

        // Check for All in One SEO
        $aioseo_title = get_post_meta($post_id, '_aioseo_title', true);
        $aioseo_desc = get_post_meta($post_id, '_aioseo_description', true);

        // Use first available title/description. RequestDesk first.
        if (!empty($rd_title)) {
            $title = $rd_title;
        } elseif (!empty($yoast_title)) {
            $title = $yoast_title;
        } elseif (!empty($rankmath_title)) {
            $title = $rankmath_title;
        } elseif (!empty($aioseo_title)) {
            $title = $aioseo_title;
        }

        if (!empty($rd_desc)) {
            $description = $rd_desc;
        } elseif (!empty($yoast_desc)) {
            $description = $yoast_desc;
        } elseif (!empty($rankmath_desc)) {
            $description = $rankmath_desc;
        } elseif (!empty($aioseo_desc)) {
            $description = $aioseo_desc;
        }

        // Keyphrase
        $keyphrase = $rd_keyphrase ?: $yoast_keyphrase ?: $rankmath_keyphrase ?: '';

        // OG data (check various sources)
        $og_title = $this->seo_meta_value($post_id, '_requestdesk_og_title')
            ?: get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true)
            ?: get_post_meta($post_id, 'rank_math_facebook_title', true)
            ?: $title;

        $og_description = $this->seo_meta_value($post_id, '_requestdesk_og_description')
            ?: get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true)
            ?: get_post_meta($post_id, 'rank_math_facebook_description', true)
            ?: $description;

        $og_image = get_post_meta($post_id, '_requestdesk_og_image', true)
            ?: get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true)
            ?: get_post_meta($post_id, 'rank_math_facebook_image', true)
            ?: get_the_post_thumbnail_url($post_id, 'full')
            ?: ($found && $found['site']['og_image'] !== '' ? $found['site']['og_image'] : false);

        // Canonical URL
        $canonical = get_post_meta($post_id, '_requestdesk_canonical_url', true)
            ?: get_post_meta($post_id, '_yoast_wpseo_canonical', true)
            ?: get_post_meta($post_id, 'rank_math_canonical_url', true)
            ?: $permalink;

        // Robots meta
        // RequestDesk can ADD a noindex, and never lifts one another plugin
        // set -- the same one-way rule RequestDesk_Yoast_Meta applies.
        $robots = 'index, follow';
        $noindex = get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true)
            ?: get_post_meta($post_id, 'rank_math_robots', true);
        $rd_noindex = get_post_meta($post_id, '_requestdesk_noindex', true);
        if ($noindex === '1' || strpos((string) $noindex, 'noindex') !== false
            || $rd_noindex === '1' || $rd_noindex === 'on') {
            $robots = 'noindex, follow';
        }

        return array(
            'title' => $title,
            'description' => $description,
            'canonical_url' => $canonical,
            'og_title' => $og_title,
            'og_description' => $og_description,
            'og_image' => $og_image,
            'robots' => $robots,
            'focus_keyphrase' => $keyphrase
        );
    }

    /**
     * Verify API key for authentication
     * Uses dedicated headless API key, falls back to main RequestDesk key
     */
    public function verify_api_key($request) {
        $settings = get_option('requestdesk_settings', array());
        $headless_settings = get_option('requestdesk_headless_settings', array());

        // Accept the headless key or the main RequestDesk key. The main key already
        // authorizes every write route (/publish, /events), so refusing it on these
        // read-only routes protected nothing. It did break tooling that holds only
        // the main key: on Talk Commerce, where a separate headless key is set,
        // the post-event check could create events but not read them back.
        $accepted_keys = array_values(array_filter(array(
            (string) ($headless_settings['api_key'] ?? ''),
            (string) ($settings['api_key'] ?? ''),
        ), 'strlen'));

        if (empty($accepted_keys)) {
            return new WP_Error(
                'no_api_key',
                'Headless API key not configured. Go to Settings > RequestDesk > Headless API to set one.',
                array('status' => 401)
            );
        }

        // Check multiple header formats for flexibility
        $provided_key = $request->get_header('X-RequestDesk-API-Key');
        if (empty($provided_key)) {
            $provided_key = $request->get_header('X-WP-Headless-Key');
        }
        if (empty($provided_key)) {
            $provided_key = $request->get_header('Authorization');
            // Handle "Bearer <key>" format
            if (!empty($provided_key) && strpos($provided_key, 'Bearer ') === 0) {
                $provided_key = substr($provided_key, 7);
            }
        }
        // Header only. A key in the query string lands in access logs and Referer.

        if (!empty($provided_key)) {
            foreach ($accepted_keys as $accepted_key) {
                if (hash_equals($accepted_key, (string) $provided_key)) {
                    return true;
                }
            }
        }

        return new WP_Error(
            'invalid_api_key',
            'Invalid API key',
            array('status' => 401)
        );
    }
}
