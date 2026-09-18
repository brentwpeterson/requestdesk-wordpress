<?php

/**
 * RequestDesk API Class
 *
 * Handles all REST API endpoints for RequestDesk WordPress Connector
 */
class RequestDesk_API {

    /**
     * REST API namespace for all endpoints
     */
    private $namespace = 'requestdesk/v1';

    /**
     * Hosts whose <iframe> players survive a Connector publish.
     *
     * wp_kses_post() strips every iframe, and because Connector requests run
     * with no logged-in user WordPress strips them AGAIN on save (kses_init
     * adds wp_filter_post_kses when the user lacks unfiltered_html). That is
     * why audio-only Talk Commerce episode posts shipped with no Transistor
     * player (Brent, 2026-09-18). Only these hosts are allowed; any other
     * iframe is removed before kses runs.
     */
    private static $embed_hosts = array(
        'share.transistor.fm',        // hardcode-ok: public podcast player host, filterable via requestdesk_embed_hosts
        'www.youtube.com',            // hardcode-ok: public video player host, filterable via requestdesk_embed_hosts
        'youtube.com',                // hardcode-ok: public video player host, filterable via requestdesk_embed_hosts
        'www.youtube-nocookie.com',   // hardcode-ok: public video player host, filterable via requestdesk_embed_hosts
        'player.vimeo.com',           // hardcode-ok: public video player host, filterable via requestdesk_embed_hosts
    );

    /**
     * wp_kses_allowed_html filter: allow <iframe> in the 'post' context while a
     * Connector publish is running. Added and removed around publish_content.
     */
    public static function allow_embed_iframes($tags, $context) {
        if ($context === 'post') {
            $tags['iframe'] = array(
                'src' => true, 'width' => true, 'height' => true, 'title' => true,
                'frameborder' => true, 'scrolling' => true, 'seamless' => true,
                'loading' => true, 'allow' => true, 'allowfullscreen' => true,
                'style' => true, 'referrerpolicy' => true,
            );
        }
        return $tags;
    }

    /**
     * Remove any iframe whose src host is not in $embed_hosts.
     */
    private static function strip_foreign_iframes($html) {
        return preg_replace_callback('#<iframe\b[^>]*>.*?</iframe>#is', function ($m) {
            if (!preg_match('#\bsrc\s*=\s*["\']([^"\']+)["\']#i', $m[0], $src)) {
                return '';
            }
            $host = strtolower((string) wp_parse_url($src[1], PHP_URL_HOST));
            $hosts = (array) apply_filters('requestdesk_embed_hosts', self::$embed_hosts);
            return in_array($host, $hosts, true) ? $m[0] : '';
        }, $html);
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Test connection endpoint
        register_rest_route($this->namespace, '/test-connection', array(
            'methods' => 'GET',
            'callback' => array($this, 'test_connection'),
            'permission_callback' => array($this, 'verify_api_key')
        ));

        // Backward compatibility: old test endpoint
        register_rest_route($this->namespace, '/test', array(
            'methods' => 'GET',
            'callback' => array($this, 'test_connection'),
            'permission_callback' => array($this, 'verify_api_key')
        ));

        // Pull posts endpoint
        register_rest_route($this->namespace, '/pull-posts', array(
            'methods' => 'GET',
            'callback' => array($this, 'pull_posts_for_knowledge'),
            'permission_callback' => array($this, 'verify_api_key'),
            'args' => array(
                'per_page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 50,
                    'minimum' => 1,
                    'maximum' => 100
                ),
                'offset' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                    'minimum' => 0
                ),
                'modified_since' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'ISO date to get posts modified since (for incremental sync)'
                ),
                'include_content' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Include full post content'
                )
            )
        ));

        // Pull pages endpoint (NEW for v1.3.0)
        register_rest_route($this->namespace, '/pull-pages', array(
            'methods' => 'GET',
            'callback' => array($this, 'pull_pages_for_knowledge'),
            'permission_callback' => array($this, 'verify_api_key'),
            'args' => array(
                'per_page' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 50,
                    'minimum' => 1,
                    'maximum' => 100
                ),
                'offset' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0,
                    'minimum' => 0
                ),
                'modified_since' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'ISO date to get pages modified since (for incremental sync)'
                ),
                'include_content' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => true,
                    'description' => 'Include full page content'
                )
            )
        ));

        // Publish content endpoint
        register_rest_route($this->namespace, '/publish', array(
            'methods' => 'POST',
            'callback' => array($this, 'publish_content'),
            'permission_callback' => array($this, 'verify_api_key'),
            'args' => array(
                'title' => array(
                    'required' => true,
                    'type' => 'string'
                ),
                'content' => array(
                    'required' => false,
                    'type' => 'string'
                ),
                'status' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'draft',
                    'enum' => array('draft', 'publish', 'private', 'pending', 'future')
                ),
                'ticket_id' => array(
                    'required' => false,
                    'type' => 'string'
                ),
                'agent_id' => array(
                    'required' => false,
                    'type' => 'string'
                ),
                'featured_image' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'URL of the featured image to set for the post'
                ),
                'excerpt' => array(
                    'required' => false,
                    'type' => 'string'
                ),
                'categories' => array(
                    'required' => false,
                    'type' => 'array'
                ),
                'tags' => array(
                    'required' => false,
                    'type' => 'array'
                ),
                'post_id' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Post ID to update (if provided, updates existing post instead of creating new one)'
                ),
                'author' => array(
                    'required' => false,
                    'type' => 'integer',
                    'description' => 'WordPress user ID to set as the post author'
                ),
                'post_date' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Original publication date (Y-m-d H:i:s or ISO 8601). Accepted aliases: date, date_gmt.'
                ),
                'date' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Alias for post_date'
                ),
                'date_gmt' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Alias for post_date (interpreted as the publish date in any timezone)'
                ),
                'slug' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'URL slug to assign to the post'
                )
            )
        ));

        // Create or update one event (rd_event) by slug. /publish only writes
        // regular posts and cannot set a post type or the event fields.
        register_rest_route($this->namespace, '/events', array(
            'methods' => 'POST',
            'callback' => array($this, 'upsert_event'),
            'permission_callback' => array($this, 'verify_api_key'),
            'args' => array(
                'slug' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Event slug. An existing event with this slug is updated in place.'
                ),
                'title' => array(
                    'required' => true,
                    'type' => 'string'
                ),
                'content' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Page body (HTML). Omit to leave an existing body unchanged.'
                ),
                'status' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'draft',
                    'enum' => array('draft', 'publish', 'pending', 'private')
                ),
                'meta' => array(
                    'required' => false,
                    'type' => 'object',
                    'description' => 'Event fields keyed as in RequestDesk_Event::fields(). Only keys sent are written.'
                )
            )
        ));

        // Pull categories endpoint
        register_rest_route($this->namespace, '/pull-categories', array(
            'methods' => 'GET',
            'callback' => array($this, 'pull_categories'),
            'permission_callback' => array($this, 'verify_api_key')
        ));

        // Pull tags endpoint
        register_rest_route($this->namespace, '/pull-tags', array(
            'methods' => 'GET',
            'callback' => array($this, 'pull_tags'),
            'permission_callback' => array($this, 'verify_api_key')
        ));

        // NEW: Dedicated endpoint for updating featured images only
        register_rest_route($this->namespace, '/update-featured-image', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'update_featured_image'),
            'permission_callback' => array($this, 'verify_api_key'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'WordPress post ID to update'
                ),
                'featured_image_url' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'URL of the featured image to set'
                )
            )
        ));

        // Post identity lookup — used by Promote-to-Live to safely confirm a
        // target post exists at a given ID (and read its slug/status) BEFORE
        // updating it. Lives in the requestdesk/v1 namespace on purpose: the
        // theme locks down public wp/v2 on production, but allows this
        // namespace, so this works headlessly where wp/v2 would 401.
        register_rest_route($this->namespace, '/post-identity/(?P<post_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_post_identity'),
            'permission_callback' => array($this, 'verify_api_key'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'type' => 'integer'
                )
            )
        ));

    }

    /**
     * Return the identity of a post (existence, slug, status, link) so a remote
     * caller can verify it before pushing an update. API-key authed.
     */
    public function get_post_identity($request) {
        $post_id = (int) $request->get_param('post_id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'post') {
            return new WP_REST_Response(array(
                'exists' => false,
                'post_id' => $post_id,
            ), 200);
        }

        return new WP_REST_Response(array(
            'exists' => true,
            'post_id' => $post_id,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'title' => get_the_title($post),
            'link' => get_permalink($post),
        ), 200);
    }

    /**
     * Test connection endpoint
     */
    public function test_connection($request) {
        $settings = get_option('requestdesk_settings', array());

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Connection successful',
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => REQUESTDESK_VERSION,
            'site_url' => home_url(),
            'capabilities' => array(
                'posts' => true,
                'pages' => true,
                'publish' => true,
                'categories' => true,
                'tags' => true
            ),
            'site_info' => array(
                'name' => get_bloginfo('name'),
                'url' => home_url(),
                'version' => get_bloginfo('version'),
                'plugin_version' => REQUESTDESK_VERSION,
                'capabilities' => array(
                    'posts' => true,
                    'pages' => true,
                    'publish' => true,
                    'categories' => true,
                    'tags' => true
                )
            ),
            'settings' => array(
                'debug_mode' => $settings['debug_mode'] ?? false,
                'allowed_post_types' => $settings['allowed_post_types'] ?? array('post'),
                'default_post_status' => $settings['default_post_status'] ?? 'draft'
            )
        ), 200);
    }

    /**
     * Pull posts for RequestDesk knowledge chunks
     */
    public function pull_posts_for_knowledge($request) {
        try {
            $per_page = $request->get_param('per_page') ?: 50;
            $offset = $request->get_param('offset') ?: 0;
            $modified_since = $request->get_param('modified_since');
            $include_content = $request->get_param('include_content') === true || $request->get_param('include_content') === 'true';
            $post_status = $request->get_param('status') ?: 'publish';

            // Allow comma-separated statuses (e.g., "publish,pending,draft,future")
            $status_array = array_map('trim', explode(',', $post_status));
            $valid_statuses = array('publish', 'draft', 'pending', 'future', 'private');
            $status_array = array_intersect($status_array, $valid_statuses);
            if (empty($status_array)) {
                $status_array = array('publish');
            }

            // Build WP_Query arguments
            $args = array(
                'post_type' => 'post',
                'post_status' => count($status_array) === 1 ? $status_array[0] : $status_array,
                'posts_per_page' => $per_page,
                'offset' => $offset,
                'orderby' => 'modified',
                'order' => 'DESC',
                'no_found_rows' => false // We need total count
            );

            // Add date filter if specified
            if (!empty($modified_since)) {
                $args['date_query'] = array(
                    array(
                        'column' => 'post_modified',
                        'after' => $modified_since,
                        'inclusive' => true
                    )
                );
            }

            $query = new WP_Query($args);
            $posts = array();

            foreach ($query->posts as $post) {
                // Debug date processing
                $published_timestamp = strtotime($post->post_date);
                $modified_timestamp = strtotime($post->post_modified);

                // Get featured image URLs in different sizes (with safety checks)
                $featured_image_id = null;
                $featured_image_url = null;
                $featured_image_medium = null;
                $featured_image_thumbnail = null;

                if (function_exists('get_post_thumbnail_id') && function_exists('get_the_post_thumbnail_url')) {
                    $featured_image_id = get_post_thumbnail_id($post->ID);
                    if ($featured_image_id) {
                        $featured_image_url = get_the_post_thumbnail_url($post->ID, 'full');
                        $featured_image_medium = get_the_post_thumbnail_url($post->ID, 'medium');
                        $featured_image_thumbnail = get_the_post_thumbnail_url($post->ID, 'thumbnail');
                    }
                }

                $post_data = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'slug' => $post->post_name,
                    'url' => get_permalink($post->ID),
                    'excerpt' => function_exists('get_the_excerpt') ? get_the_excerpt($post) : '',
                    'published_date' => $published_timestamp ? date('c', $published_timestamp) : null,
                    'modified_date' => $modified_timestamp ? date('c', $modified_timestamp) : null,
                    'author' => get_the_author_meta('display_name', $post->post_author),
                    'categories' => wp_get_post_categories($post->ID, array('fields' => 'names')),
                    'tags' => wp_get_post_tags($post->ID, array('fields' => 'names')),
                    'word_count' => str_word_count(strip_tags($post->post_content)),
                    'featured_image_url' => $featured_image_url
                );

                // Add content if requested
                if ($include_content) {
                    $post_data['content'] = apply_filters('the_content', $post->post_content);
                }

                $posts[] = $post_data;
            }

            // Get site info
            $site_info = array(
                'name' => get_bloginfo('name'),
                'description' => get_bloginfo('description'),
                'url' => home_url(),
                'version' => get_bloginfo('version'),
                'language' => get_locale()
            );

            $total_posts = $query->found_posts;

            // Log the sync
            $this->log_sync('posts', count($posts), 'success');

            return new WP_REST_Response(array(
                'success' => true,
                'posts' => $posts,
                'site_info' => $site_info,
                'pagination' => array(
                    'per_page' => (int) $per_page,
                    'offset' => (int) $offset,
                    'total' => $total_posts,
                    'has_more' => ($offset + $per_page) < $total_posts
                )
            ), 200);

        } catch (Exception $e) {
            $this->log_sync('posts', 0, 'error', $e->getMessage());

            return new WP_Error(
                'pull_posts_error',
                'Failed to pull posts: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Pull pages for RequestDesk knowledge chunks (NEW in v1.3.0)
     */
    public function pull_pages_for_knowledge($request) {
        try {
            $per_page = $request->get_param('per_page') ?: 50;
            $offset = $request->get_param('offset') ?: 0;
            $modified_since = $request->get_param('modified_since');
            $include_content = $request->get_param('include_content') === true || $request->get_param('include_content') === 'true';

            // Build WP_Query arguments for pages
            $args = array(
                'post_type' => 'page',
                'post_status' => 'publish',
                'posts_per_page' => $per_page,
                'offset' => $offset,
                'orderby' => 'modified',
                'order' => 'DESC',
                'no_found_rows' => false // We need total count
            );

            // Add date filter if specified
            if (!empty($modified_since)) {
                $args['date_query'] = array(
                    array(
                        'column' => 'post_modified',
                        'after' => $modified_since,
                        'inclusive' => true
                    )
                );
            }

            $query = new WP_Query($args);
            $pages = array();

            foreach ($query->posts as $page) {
                $page_data = array(
                    'id' => $page->ID,
                    'title' => $page->post_title,
                    'slug' => $page->post_name,
                    'url' => get_permalink($page->ID),
                    'excerpt' => get_the_excerpt($page),
                    'published_date' => date('c', strtotime($page->post_date)),
                    'modified_date' => date('c', strtotime($page->post_modified)),
                    'author' => get_the_author_meta('display_name', $page->post_author),
                    'parent' => $page->post_parent,
                    'menu_order' => $page->menu_order,
                    'word_count' => str_word_count(strip_tags($page->post_content)),
                    'featured_image_url' => get_the_post_thumbnail_url($page->ID, 'full') ?: null
                );

                // Add content if requested
                if ($include_content) {
                    $page_data['content'] = apply_filters('the_content', $page->post_content);
                }

                $pages[] = $page_data;
            }

            // Get site info
            $site_info = array(
                'name' => get_bloginfo('name'),
                'description' => get_bloginfo('description'),
                'url' => home_url(),
                'version' => get_bloginfo('version'),
                'language' => get_locale()
            );

            $total_pages = $query->found_posts;

            // Log the sync
            $this->log_sync('pages', count($pages), 'success');

            return new WP_REST_Response(array(
                'success' => true,
                'pages' => $pages,
                'site_info' => $site_info,
                'pagination' => array(
                    'per_page' => (int) $per_page,
                    'offset' => (int) $offset,
                    'total' => $total_pages,
                    'has_more' => ($offset + $per_page) < $total_pages
                )
            ), 200);

        } catch (Exception $e) {
            $this->log_sync('pages', 0, 'error', $e->getMessage());

            return new WP_Error(
                'pull_pages_error',
                'Failed to pull pages: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Pull all categories for RequestDesk taxonomy sync
     */
    public function pull_categories($request) {
        try {
            $terms = get_terms(array(
                'taxonomy' => 'category',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
            ));

            if (is_wp_error($terms)) {
                return new WP_Error(
                    'pull_categories_error',
                    'Failed to get categories: ' . $terms->get_error_message(),
                    array('status' => 500)
                );
            }

            $categories = array();
            foreach ($terms as $term) {
                $categories[] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'description' => $term->description,
                    'parent' => $term->parent,
                    'count' => $term->count,
                );
            }

            $this->log_sync('categories', count($categories), 'success');

            return new WP_REST_Response(array(
                'success' => true,
                'categories' => $categories,
                'total' => count($categories),
            ), 200);

        } catch (Exception $e) {
            $this->log_sync('categories', 0, 'error', $e->getMessage());

            return new WP_Error(
                'pull_categories_error',
                'Failed to pull categories: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Pull all tags for RequestDesk taxonomy sync
     */
    public function pull_tags($request) {
        try {
            $terms = get_terms(array(
                'taxonomy' => 'post_tag',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
            ));

            if (is_wp_error($terms)) {
                return new WP_Error(
                    'pull_tags_error',
                    'Failed to get tags: ' . $terms->get_error_message(),
                    array('status' => 500)
                );
            }

            $tags = array();
            foreach ($terms as $term) {
                $tags[] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'description' => $term->description,
                    'count' => $term->count,
                );
            }

            $this->log_sync('tags', count($tags), 'success');

            return new WP_REST_Response(array(
                'success' => true,
                'tags' => $tags,
                'total' => count($tags),
            ), 200);

        } catch (Exception $e) {
            $this->log_sync('tags', 0, 'error', $e->getMessage());

            return new WP_Error(
                'pull_tags_error',
                'Failed to pull tags: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Publish content to WordPress
     */
    public function publish_content($request) {
        try {
            // Optional fields arrive as null when the caller leaves them out.
            // WordPress's sanitizers expect strings, and on PHP 8.1+ a null logs
            // a deprecation notice on every publish, so read them as strings.
            $str = function ($name) use ($request) {
                $value = $request->get_param($name);
                return is_scalar($value) ? (string) $value : '';
            };

            $title = sanitize_text_field($str('title'));
            // Allowlisted player iframes (Transistor, YouTube, Vimeo) must survive
            // both this kses pass and the one wp_insert_post runs on save.
            add_filter('wp_kses_allowed_html', array(__CLASS__, 'allow_embed_iframes'), 10, 2);
            $content = wp_kses_post(self::strip_foreign_iframes($str('content')));
            $status = sanitize_text_field($str('status')) ?: 'draft';
            $ticket_id = sanitize_text_field($str('ticket_id'));
            $agent_id = sanitize_text_field($str('agent_id'));
            $featured_image = esc_url_raw($str('featured_image'));
            $excerpt = sanitize_textarea_field($str('excerpt'));
            $categories = $request->get_param('categories') ?: array();
            $tags = $request->get_param('tags') ?: array();
            $post_id = sanitize_text_field($str('post_id'));

            $is_update = !empty($post_id);

            $author = absint($request->get_param('author'));
            $slug = sanitize_title($str('slug'));
            // Accept post_date, date, or date_gmt as the publish-date input (first non-empty wins).
            $post_date = sanitize_text_field($str('post_date'));
            if (empty($post_date)) {
                $post_date = sanitize_text_field($str('date'));
            }
            if (empty($post_date)) {
                $post_date = sanitize_text_field($str('date_gmt'));
            }

            // Track author resolution so we can echo the result in the response and log silent failures.
            $author_set = false;
            $author_failure_reason = null;

            // Prepare post data
            $post_data = array(
                'post_title' => $title,
                'post_status' => $status,
                'post_type' => 'post'
            );

            // Only set content if provided (allows metadata-only updates)
            if (!empty($content)) {
                $post_data['post_content'] = $content;
            }

            // Set slug if provided (preserves URL structure during migrations)
            if (!empty($slug)) {
                $post_data['post_name'] = $slug;
            }

            // Set original publication date if provided
            if (!empty($post_date)) {
                $post_data['post_date'] = $post_date;
                $post_data['post_date_gmt'] = get_gmt_from_date($post_date);
            }

            // Set author if provided and valid. Log silent failures so they stop being silent.
            if ($author > 0) {
                $user = get_user_by('id', $author);
                if ($user) {
                    $post_data['post_author'] = $author;
                    $author_set = true;
                } else {
                    $author_failure_reason = "User ID {$author} not found on this site";
                    error_log("[RequestDesk] publish_content: {$author_failure_reason}");
                }
            }

            // Add excerpt if provided
            if (!empty($excerpt)) {
                $post_data['post_excerpt'] = $excerpt;
            }

            if ($is_update) {
                // Update existing post
                $existing_post = get_post($post_id);

                if (!$existing_post) {
                    throw new Exception("No content found at ID {$post_id}; refusing to update.");
                }

                // PRESERVE THE EXISTING CONTENT TYPE.
                //
                // $post_data hardcodes post_type => 'post' for the create path.
                // Passing that into wp_update_post() coerces the existing row,
                // so updating a PAGE through this endpoint silently converted it
                // into a blog post: the permalink moved to /blog/<slug>/, the
                // canonical URL started returning 404, and the call still
                // reported success. That happened to two live service pages
                // (20924 /services/content-in-commerce/shopify-content-services/
                // and 20953 /hubspot-audit/) on 2026-07-21.
                //
                // An update must never change what kind of content something is.
                // Whatever the row already is, it stays.
                $post_data['post_type'] = $existing_post->post_type;

                $post_data['ID'] = $post_id;
                $result = wp_update_post($post_data, true);

                if (is_wp_error($result) || $result === 0) {
                    throw new Exception('Failed to update post: ' . (is_wp_error($result) ? $result->get_error_message() : 'Post not found'));
                }
            } else {
                // Check for duplicate before creating
                // Look for existing post with same slug or title (any status)
                $check_slug = !empty($slug) ? $slug : sanitize_title($title);
                $existing = get_posts(array(
                    'name' => $check_slug,
                    'post_type' => 'post',
                    'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
                    'numberposts' => 1,
                ));
                if (!empty($existing)) {
                    // Post with this slug already exists - update it instead of creating duplicate
                    $post_id = $existing[0]->ID;
                    $post_data['ID'] = $post_id;
                    $result = wp_update_post($post_data);
                    if (is_wp_error($result) || $result === 0) {
                        throw new Exception('Failed to update existing post: ' . (is_wp_error($result) ? $result->get_error_message() : 'Update failed'));
                    }
                    // Skip to featured image/category handling below
                } else {
                    // Create new post
                    $post_id = wp_insert_post($post_data);

                    if (is_wp_error($post_id)) {
                        throw new Exception('Failed to create post: ' . $post_id->get_error_message());
                    }
                }
            }

            // Handle featured image
            if (!empty($featured_image)) {
                $this->set_featured_image_from_url($post_id, $featured_image);
            }

            // Handle categories
            if (!empty($categories) && is_array($categories)) {
                $category_ids = array();
                foreach ($categories as $category_name) {
                    $category = get_category_by_slug(sanitize_title($category_name));
                    if (!$category) {
                        // Create category if it doesn't exist using wp_insert_term
                        $new_category = wp_insert_term(
                            sanitize_text_field($category_name),
                            'category'
                        );
                        if (!is_wp_error($new_category)) {
                            $category_ids[] = $new_category['term_id'];
                        }
                    } else {
                        $category_ids[] = $category->term_id;
                    }
                }
                if (!empty($category_ids)) {
                    wp_set_post_categories($post_id, $category_ids);
                }
            }

            // Handle tags
            if (!empty($tags) && is_array($tags)) {
                $tag_names = array_map('sanitize_text_field', $tags);
                wp_set_post_tags($post_id, $tag_names);
            }

            // Handle Polylang language assignment
            $language = sanitize_text_field($request->get_param('language'));
            $translation_of = absint($request->get_param('translation_of'));
            if (!empty($language) && function_exists('pll_set_post_language')) {
                pll_set_post_language($post_id, $language);

                // Link as translation of an existing post
                if ($translation_of > 0 && function_exists('pll_save_post_translations')) {
                    $existing_lang = pll_get_post_language($translation_of);
                    if ($existing_lang) {
                        // Build complete translations array with both languages
                        $translations = array(
                            $existing_lang => $translation_of,
                            $language => $post_id
                        );
                        pll_save_post_translations($translations);
                    }
                }
            }

            // Add metadata for tracking
            if ($ticket_id) {
                update_post_meta($post_id, '_requestdesk_ticket_id', $ticket_id);
            }
            if ($agent_id) {
                update_post_meta($post_id, '_requestdesk_agent_id', $agent_id);
            }

            // Verify post_author actually persisted. Some Magento-style replication, plugin
            // hooks, or default-author filters can override it after wp_insert_post.
            // If it did not stick, force-update once and re-check.
            $author_id_actual = null;
            if ($author_set) {
                $check_post = get_post($post_id);
                $author_id_actual = $check_post ? (int) $check_post->post_author : null;
                if ($author_id_actual !== $author) {
                    error_log("[RequestDesk] publish_content: post_author mismatch after save. expected={$author} actual={$author_id_actual}. Forcing update.");
                    wp_update_post(array('ID' => $post_id, 'post_author' => $author));
                    $check_post = get_post($post_id);
                    $author_id_actual = $check_post ? (int) $check_post->post_author : null;
                    if ($author_id_actual !== $author) {
                        $author_set = false;
                        $author_failure_reason = "post_author did not persist after force-update (still {$author_id_actual})";
                        error_log("[RequestDesk] publish_content: {$author_failure_reason}");
                    }
                }
            }

            // Verify post_date persisted similarly, since the stored value can be reformatted.
            $post_date_set = false;
            $post_date_actual = null;
            if (!empty($post_date)) {
                $check_post = isset($check_post) ? $check_post : get_post($post_id);
                $post_date_actual = $check_post ? $check_post->post_date : null;
                $post_date_set = !empty($post_date_actual);
            }

            // Log successful publish
            $this->log_sync('publish', 1, 'success', '', $ticket_id, $post_id, $agent_id);

            return new WP_REST_Response(array(
                'success' => true,
                'post_id' => $post_id,
                'post_type' => get_post_type($post_id),
                'post_url' => get_permalink($post_id),
                'edit_url' => get_edit_post_link($post_id, 'raw'),
                'featured_image_set' => !empty($featured_image),
                'categories_set' => count($category_ids ?? []),
                'tags_set' => count($tag_names ?? []),
                'author_set' => $author_set,
                'author_id' => $author_id_actual,
                'author_failure_reason' => $author_failure_reason,
                'post_date_set' => $post_date_set,
                'post_date' => $post_date_actual,
                // The status WordPress actually settled on, which is not always
                // the one that was asked for: 'future' with a missing or past
                // post_date is silently converted to 'publish'. Without this in
                // the response a caller cannot tell a SCHEDULED post from a
                // DRAFT or from one that just went live -- WP REST answers
                // rest_forbidden for the first two and post-identity is a
                // separate call. Two Talk Commerce posts published days early
                // on 2026-09-01 before anyone could see which had happened.
                'post_status' => get_post_status($post_id),
                'language' => !empty($language) ? $language : null,
                'translation_of' => $translation_of > 0 ? $translation_of : null
            ), 201);

        } catch (Exception $e) {
            $this->log_sync('publish', 0, 'error', $e->getMessage(), $ticket_id, null, $agent_id);

            return new WP_Error(
                'publish_error',
                'Failed to publish content: ' . $e->getMessage(),
                array('status' => 500)
            );
        } finally {
            remove_filter('wp_kses_allowed_html', array(__CLASS__, 'allow_embed_iframes'), 10);
        }
    }

    /**
     * Update featured image for existing post
     * Dedicated endpoint for EXISTING posts featured image updates
     */
    public function update_featured_image($request) {
        try {
            // Get parameters
            $post_id = sanitize_text_field($request->get_param('post_id'));
            $featured_image_url = esc_url_raw($request->get_param('featured_image_url'));

            // Validate post exists
            $post = get_post($post_id);
            if (!$post) {
                return new WP_Error(
                    'post_not_found',
                    'Post not found with ID: ' . $post_id,
                    array('status' => 404)
                );
            }

            // Set featured image using existing method
            $attachment_id = $this->set_featured_image_from_url($post_id, $featured_image_url);

            if ($attachment_id && !is_wp_error($attachment_id)) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Featured image updated successfully',
                    'post_id' => $post_id,
                    'attachment_id' => $attachment_id,
                    'post_url' => get_permalink($post_id)
                ), 200);
            } else {
                $error_message = is_wp_error($attachment_id) ? $attachment_id->get_error_message() : 'Failed to set featured image';

                return new WP_Error(
                    'featured_image_failed',
                    $error_message,
                    array('status' => 500)
                );
            }

        } catch (Exception $e) {
            return new WP_Error(
                'update_featured_image_error',
                'Failed to update featured image: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }


    /**
     * Set featured image from URL
     */
    private function set_featured_image_from_url($post_id, $image_url) {
        if (empty($image_url)) {
            return false;
        }

        // Include WordPress media functions
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        try {
            // Download image
            $temp_file = download_url($image_url);

            if (is_wp_error($temp_file)) {
                error_log('RequestDesk: Failed to download featured image: ' . $temp_file->get_error_message());
                return false;
            }

            // Prepare file array. basename() must run on the URL PATH only:
            // a signed CDN URL (e.g. a Canva export) carries a query string,
            // and basename() on the full URL yields a "filename" ending in
            // the signature, which media_handle_sideload rejects as an
            // invalid file type — silently, returning false.
            $url_path = parse_url($image_url, PHP_URL_PATH);
            $file_array = array(
                'name' => basename($url_path ?: $image_url),
                'tmp_name' => $temp_file
            );

            // Upload to media library
            $attachment_id = media_handle_sideload($file_array, $post_id);

            // Clean up temp file
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }

            if (is_wp_error($attachment_id)) {
                error_log('RequestDesk: Failed to create attachment: ' . $attachment_id->get_error_message());
                return false;
            }

            // Set as featured image
            set_post_thumbnail($post_id, $attachment_id);

            return $attachment_id;

        } catch (Exception $e) {
            error_log('RequestDesk: Exception setting featured image: ' . $e->getMessage());

            // Clean up temp file if it exists
            if (isset($temp_file) && file_exists($temp_file)) {
                unlink($temp_file);
            }

            return false;
        }
    }

    /**
     * Create or update one event by slug.
     *
     * Events live in the database, and a site's files and database reach
     * production by different routes (Content Cucumber deploys files only), so
     * an event built on a local copy never arrives on its own. This route is how
     * an event gets onto a live site without wp-admin: the same API key as
     * /publish, the same sanitizing as the editor (RequestDesk_Event::save_values),
     * and only the meta keys sent are written, so an update can touch one field.
     *
     * The response carries the event as the headless API returns it, so a
     * caller can confirm what the site will show rather than trusting "success".
     */
    public function upsert_event($request) {
        if (!class_exists('RequestDesk_Event') || !post_type_exists(RequestDesk_Event::POST_TYPE)) {
            return new WP_Error('events_unavailable', 'Event module is not enabled on this site.', array('status' => 501));
        }

        $slug  = sanitize_title((string) $request->get_param('slug'));
        $title = sanitize_text_field((string) $request->get_param('title'));
        if ($slug === '' || $title === '') {
            return new WP_Error('invalid_event', 'slug and title are required.', array('status' => 400));
        }

        $meta = $request->get_param('meta');
        if ($meta !== null && !is_array($meta)) {
            return new WP_Error('invalid_meta', 'meta must be an object of event fields.', array('status' => 400));
        }
        $unknown = array_diff(array_keys((array) $meta), array_keys(RequestDesk_Event::fields()));
        if (!empty($unknown)) {
            return new WP_Error('unknown_meta', 'Unknown event fields: ' . implode(', ', $unknown), array('status' => 400));
        }

        // Statuses listed explicitly, not 'any': this request has no logged-in
        // user, and WP_Query drops a non-public post from a by-name lookup unless
        // its status was asked for by name. With 'any', updating a draft missed
        // it and created a duplicate (caught testing on CC local).
        $existing = get_posts(array(
            'post_type'   => RequestDesk_Event::POST_TYPE,
            'name'        => $slug,
            'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
            'numberposts' => 1,
        ));

        $postarr = array(
            'post_type'   => RequestDesk_Event::POST_TYPE,
            'post_title'  => $title,
            'post_name'   => $slug,
            'post_status' => $request->get_param('status') ?: 'draft',
        );
        if ($request->get_param('content') !== null) {
            $postarr['post_content'] = wp_kses_post((string) $request->get_param('content'));
        }

        if ($existing) {
            $postarr['ID'] = $existing[0]->ID;
            $post_id = wp_update_post(wp_slash($postarr), true);
        } else {
            $post_id = wp_insert_post(wp_slash($postarr), true);
        }
        if (is_wp_error($post_id)) {
            return new WP_Error('event_save_failed', $post_id->get_error_message(), array('status' => 500));
        }

        if (!empty($meta)) {
            RequestDesk_Event::save_values($post_id, $meta);
        }

        $post = get_post($post_id);
        return rest_ensure_response(array(
            'success'   => true,
            'action'    => $existing ? 'updated' : 'created',
            'post_id'   => $post_id,
            'status'    => $post->post_status,
            'permalink' => get_permalink($post),
            // null when the event still lacks a start date or city; the site
            // leaves such an event out, so say so instead of reporting success alone.
            'event'     => RequestDesk_Event::format_for_api($post, true),
        ));
    }

    /**
     * Verify API key for authentication
     */
    public function verify_api_key($request) {
        $settings = get_option('requestdesk_settings', array());
        $api_key = $settings['api_key'] ?? '';

        if (empty($api_key)) {
            return new WP_Error(
                'no_api_key',
                'RequestDesk API key not configured',
                array('status' => 401)
            );
        }

        $provided_key = $request->get_header('X-RequestDesk-API-Key');
        if (empty($provided_key)) {
            $provided_key = $request->get_param('api_key');
        }

        if (empty($provided_key) || $provided_key !== $api_key) {
            return new WP_Error(
                'invalid_api_key',
                'Invalid API key',
                array('status' => 401)
            );
        }

        return true;
    }

    /**
     * Log sync activity
     */
    private function log_sync($operation, $count, $status, $error_message = '', $ticket_id = '', $post_id = null, $agent_id = '') {
        global $wpdb;

        $table_name = $wpdb->prefix . 'requestdesk_sync_log';

        $wpdb->insert(
            $table_name,
            array(
                'ticket_id' => $ticket_id ?: 'N/A',
                'post_id' => $post_id ?: 0,
                'agent_id' => $agent_id ?: 'N/A',
                'sync_status' => $status,
                'sync_date' => current_time('mysql'),
                'error_message' => $error_message
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s')
        );

        // Also log to WordPress error log if debug mode is enabled
        $settings = get_option('requestdesk_settings', array());
        if ($settings['debug_mode'] ?? false) {
            error_log("RequestDesk Sync - $operation: $count items, status: $status" .
                     ($error_message ? ", error: $error_message" : ""));
        }
    }
}