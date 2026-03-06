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
                    'required' => true,
                    'type' => 'string'
                ),
                'status' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'draft',
                    'enum' => array('draft', 'publish', 'private')
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

            // Build WP_Query arguments
            $args = array(
                'post_type' => 'post',
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
            $title = sanitize_text_field($request->get_param('title'));
            $content = wp_kses_post($request->get_param('content'));
            $status = sanitize_text_field($request->get_param('status')) ?: 'draft';
            $ticket_id = sanitize_text_field($request->get_param('ticket_id'));
            $agent_id = sanitize_text_field($request->get_param('agent_id'));
            $featured_image = esc_url_raw($request->get_param('featured_image'));
            $excerpt = sanitize_textarea_field($request->get_param('excerpt'));
            $categories = $request->get_param('categories') ?: array();
            $tags = $request->get_param('tags') ?: array();
            $post_id = sanitize_text_field($request->get_param('post_id'));

            $is_update = !empty($post_id);

            $author = absint($request->get_param('author'));

            // Prepare post data
            $post_data = array(
                'post_title' => $title,
                'post_content' => $content,
                'post_status' => $status,
                'post_type' => 'post'
            );

            // Set author if provided and valid
            if ($author > 0 && get_user_by('id', $author)) {
                $post_data['post_author'] = $author;
            }

            // Add excerpt if provided
            if (!empty($excerpt)) {
                $post_data['post_excerpt'] = $excerpt;
            }

            if ($is_update) {
                // Update existing post
                $post_data['ID'] = $post_id;
                $result = wp_update_post($post_data);

                if (is_wp_error($result) || $result === 0) {
                    throw new Exception('Failed to update post: ' . ($is_wp_error($result) ? $result->get_error_message() : 'Post not found'));
                }
            } else {
                // Create new post
                $post_id = wp_insert_post($post_data);

                if (is_wp_error($post_id)) {
                    throw new Exception('Failed to create post: ' . $post_id->get_error_message());
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

            // Add metadata for tracking
            if ($ticket_id) {
                update_post_meta($post_id, '_requestdesk_ticket_id', $ticket_id);
            }
            if ($agent_id) {
                update_post_meta($post_id, '_requestdesk_agent_id', $agent_id);
            }

            // Log successful publish
            $this->log_sync('publish', 1, 'success', '', $ticket_id, $post_id, $agent_id);

            return new WP_REST_Response(array(
                'success' => true,
                'post_id' => $post_id,
                'post_url' => get_permalink($post_id),
                'edit_url' => get_edit_post_link($post_id, 'raw'),
                'featured_image_set' => !empty($featured_image),
                'categories_set' => count($category_ids ?? []),
                'tags_set' => count($tag_names ?? [])
            ), 201);

        } catch (Exception $e) {
            $this->log_sync('publish', 0, 'error', $e->getMessage(), $ticket_id, null, $agent_id);

            return new WP_Error(
                'publish_error',
                'Failed to publish content: ' . $e->getMessage(),
                array('status' => 500)
            );
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

            // Prepare file array
            $file_array = array(
                'name' => basename($image_url),
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