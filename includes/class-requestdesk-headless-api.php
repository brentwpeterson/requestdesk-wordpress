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
     * Register REST API routes for headless CMS
     */
    public function register_routes() {
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
    }

    /**
     * Get list of posts
     */
    public function get_posts($request) {
        try {
            $page = $request->get_param('page') ?: 1;
            $per_page = $request->get_param('per_page') ?: 10;
            $category = $request->get_param('category');
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
            foreach ($menu_locations as $location => $menu_id) {
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
    private function format_post($post, $include_full = true) {
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

        // SEO data
        $seo = $this->get_seo_data($post);

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

        // Include full content for single post requests
        if ($include_full) {
            $data['content'] = apply_filters('the_content', $content);
        }

        return $data;
    }

    /**
     * Format page data for API response
     */
    private function format_page($page, $include_full = true) {
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
                'post_type' => 'page',
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
        $seo = $this->get_seo_data($page);

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

        if ($include_full) {
            $data['content'] = apply_filters('the_content', $page->post_content);
            $data['children'] = $children;
        }

        return $data;
    }

    /**
     * Get SEO data from various SEO plugins or post meta
     */
    private function get_seo_data($post) {
        $post_id = $post->ID;
        $permalink = get_permalink($post_id);

        // Default title and description
        $title = $post->post_title . ' | ' . get_bloginfo('name');
        $description = get_the_excerpt($post);

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

        // Use first available title/description
        if (!empty($yoast_title)) {
            $title = $yoast_title;
        } elseif (!empty($rankmath_title)) {
            $title = $rankmath_title;
        } elseif (!empty($aioseo_title)) {
            $title = $aioseo_title;
        }

        if (!empty($yoast_desc)) {
            $description = $yoast_desc;
        } elseif (!empty($rankmath_desc)) {
            $description = $rankmath_desc;
        } elseif (!empty($aioseo_desc)) {
            $description = $aioseo_desc;
        }

        // Keyphrase
        $keyphrase = $yoast_keyphrase ?: $rankmath_keyphrase ?: '';

        // OG data (check various sources)
        $og_title = get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true)
            ?: get_post_meta($post_id, 'rank_math_facebook_title', true)
            ?: $title;

        $og_description = get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true)
            ?: get_post_meta($post_id, 'rank_math_facebook_description', true)
            ?: $description;

        $og_image = get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true)
            ?: get_post_meta($post_id, 'rank_math_facebook_image', true)
            ?: get_the_post_thumbnail_url($post_id, 'full');

        // Canonical URL
        $canonical = get_post_meta($post_id, '_yoast_wpseo_canonical', true)
            ?: get_post_meta($post_id, 'rank_math_canonical_url', true)
            ?: $permalink;

        // Robots meta
        $robots = 'index, follow';
        $noindex = get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true)
            ?: get_post_meta($post_id, 'rank_math_robots', true);
        if ($noindex === '1' || strpos($noindex, 'noindex') !== false) {
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

        // Use headless-specific API key if set, otherwise fall back to main API key
        $api_key = $headless_settings['api_key'] ?? '';
        if (empty($api_key)) {
            $api_key = $settings['api_key'] ?? '';
        }

        if (empty($api_key)) {
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
        if (empty($provided_key)) {
            $provided_key = $request->get_param('api_key');
        }

        if (empty($provided_key) || !hash_equals($api_key, $provided_key)) {
            return new WP_Error(
                'invalid_api_key',
                'Invalid API key',
                array('status' => 401)
            );
        }

        return true;
    }
}
