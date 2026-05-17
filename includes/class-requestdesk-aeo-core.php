<?php
/**
 * RequestDesk AEO Core Class
 *
 * Main orchestrator for Answer Engine Optimization, AI Optimization, and Generative Engine Optimization
 */

class RequestDesk_AEO_Core {

    private $analyzer;
    private $schema_generator;
    private $freshness_tracker;
    private $citation_tracker;

    public function __construct() {
        // Hook into WordPress actions
        add_action('wp_head', array($this, 'output_schema_markup'));
        add_action('save_post', array($this, 'handle_post_save'), 10, 2);
        add_action('publish_post', array($this, 'handle_post_publish'), 10, 2);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));

        // Admin hooks
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_requestdesk_analyze_content', array($this, 'ajax_analyze_content'));
        add_action('wp_ajax_requestdesk_optimize_content', array($this, 'ajax_optimize_content'));

        // REST API hooks
        add_action('rest_api_init', array($this, 'register_aeo_endpoints'));
    }

    /**
     * Initialize component classes
     */
    public function init_components() {
        if (!$this->analyzer) {
            $this->analyzer = new RequestDesk_Content_Analyzer();
        }
        if (!$this->schema_generator) {
            $this->schema_generator = new RequestDesk_Schema_Generator();
        }
        if (!$this->freshness_tracker) {
            $this->freshness_tracker = new RequestDesk_Freshness_Tracker();
        }
        if (!$this->citation_tracker) {
            $this->citation_tracker = new RequestDesk_Citation_Tracker();
        }
    }

    /**
     * Handle post save events
     */
    public function handle_post_save($post_id, $post) {
        // Skip for revisions and auto-saves
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        // Only process posts and pages
        if (!in_array($post->post_type, array('post', 'page'))) {
            return;
        }

        $settings = get_option('requestdesk_aeo_settings', array());

        // Check if AEO is enabled
        if (!($settings['enabled'] ?? true)) {
            return;
        }

        // Check if auto-optimization on update is enabled
        if ($post->post_status === 'publish' && ($settings['auto_optimize_on_update'] ?? false)) {
            wp_schedule_single_event(time() + 10, 'requestdesk_process_aeo_optimization', array($post_id));
        }
    }

    /**
     * Handle post publish events
     */
    public function handle_post_publish($post_id, $post) {
        $settings = get_option('requestdesk_aeo_settings', array());

        // Check if AEO is enabled and auto-optimization on publish is enabled
        if (($settings['enabled'] ?? true) && ($settings['auto_optimize_on_publish'] ?? true)) {
            wp_schedule_single_event(time() + 5, 'requestdesk_process_aeo_optimization', array($post_id));
        }
    }

    /**
     * Process AEO optimization for a post
     */
    public function optimize_post($post_id, $force = false) {
        $this->init_components();

        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid_post', 'Post not found');
        }

        $settings = get_option('requestdesk_aeo_settings', array());
        $min_length = $settings['min_content_length'] ?? 300;

        // Check minimum content length
        if (strlen(strip_tags($post->post_content)) < $min_length && !$force) {
            return new WP_Error('content_too_short', 'Content too short for optimization');
        }

        // Get or create AEO data record
        $aeo_data = $this->get_aeo_data($post_id);

        // Update optimization status
        $this->update_aeo_data($post_id, array(
            'optimization_status' => 'processing',
            'last_analyzed' => current_time('mysql')
        ));

        $results = array(
            'post_id' => $post_id,
            'optimization_score' => 0,
            'improvements' => array(),
            'warnings' => array(),
            'data' => array()
        );

        try {
            // Analyze content
            $analysis = $this->analyzer->analyze_content($post);
            $results['data']['analysis'] = $analysis;

            // Extract Q&A pairs if enabled
            if ($settings['extract_qa_pairs'] ?? true) {
                $qa_pairs = $this->analyzer->extract_qa_pairs($post->post_content);
                $results['data']['qa_pairs'] = $qa_pairs;
                $results['improvements'][] = count($qa_pairs) . ' Q&A pairs extracted';
            }

            // Generate FAQ schema if enabled
            if ($settings['generate_faq_schema'] ?? true) {
                $faq_schema = $this->schema_generator->generate_faq_schema($post, $qa_pairs ?? array());
                $results['data']['faq_schema'] = $faq_schema;
                if (!empty($faq_schema)) {
                    $results['improvements'][] = 'FAQ schema markup generated';
                }
            }

            // Track freshness if enabled
            if ($settings['monitor_freshness'] ?? true) {
                $freshness_score = $this->freshness_tracker->calculate_freshness_score($post);
                $results['data']['freshness_score'] = $freshness_score;
                $results['improvements'][] = 'Content freshness tracked';
            }

            // Extract citation statistics if enabled
            if ($settings['track_citations'] ?? true) {
                $citation_stats = $this->citation_tracker->extract_statistics($post->post_content);
                $results['data']['citation_stats'] = $citation_stats;
                if (!empty($citation_stats)) {
                    $results['improvements'][] = count($citation_stats) . ' citation-ready statistics found';
                }
            }

            // Calculate overall optimization score
            $results['optimization_score'] = $this->calculate_optimization_score($results['data']);

            // Save results to database
            $this->update_aeo_data($post_id, array(
                'aeo_score' => $results['optimization_score'],
                'ai_questions' => json_encode($qa_pairs ?? array()),
                'faq_data' => json_encode($faq_schema ?? array()),
                'citation_stats' => json_encode($citation_stats ?? array()),
                'optimization_status' => 'completed',
                'updated_at' => current_time('mysql')
            ));

            // Update post meta for quick access
            update_post_meta($post_id, '_requestdesk_aeo_score', $results['optimization_score']);
            update_post_meta($post_id, '_requestdesk_aeo_last_update', current_time('timestamp'));

        } catch (Exception $e) {
            $results['warnings'][] = 'Optimization error: ' . $e->getMessage();

            $this->update_aeo_data($post_id, array(
                'optimization_status' => 'failed',
                'updated_at' => current_time('mysql')
            ));
        }

        return $results;
    }

    /**
     * Calculate optimization score based on analysis data
     */
    private function calculate_optimization_score($data) {
        $score = 0;
        $max_score = 100;

        // Q&A pairs (25 points)
        if (!empty($data['qa_pairs'])) {
            $qa_count = count($data['qa_pairs']);
            $score += min(25, $qa_count * 5); // 5 points per Q&A pair, max 25
        }

        // FAQ schema (20 points)
        if (!empty($data['faq_schema'])) {
            $score += 20;
        }

        // Citation statistics (20 points)
        if (!empty($data['citation_stats'])) {
            $stat_count = count($data['citation_stats']);
            $score += min(20, $stat_count * 4); // 4 points per statistic, max 20
        }

        // Content freshness (15 points)
        if (isset($data['freshness_score'])) {
            // freshness_score is an array with overall_score key
            $freshness_value = is_array($data['freshness_score'])
                ? ($data['freshness_score']['overall_score'] ?? 0)
                : $data['freshness_score'];
            $score += ($freshness_value / 100) * 15;
        }

        // Content analysis (20 points)
        if (!empty($data['analysis'])) {
            $analysis = $data['analysis'];

            // Question headings
            if ($analysis['question_headings'] > 0) {
                $score += 8;
            }

            // Content structure
            if ($analysis['has_clear_structure']) {
                $score += 7;
            }

            // Word count bonus
            if ($analysis['word_count'] > 500) {
                $score += 5;
            }
        }

        return min($max_score, round($score));
    }

    /**
     * Get AEO data for a post
     */
    public function get_aeo_data($post_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'requestdesk_aeo_data';

        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d",
            $post_id
        ), ARRAY_A);

        if (!$data) {
            // Create new record
            $wpdb->insert(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'content_type' => get_post_type($post_id),
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s')
            );

            return $this->get_aeo_data($post_id);
        }

        // Decode JSON fields with null safety
        $data['ai_questions'] = !empty($data['ai_questions']) ? json_decode($data['ai_questions'], true) : array();
        $data['faq_data'] = !empty($data['faq_data']) ? json_decode($data['faq_data'], true) : array();
        $data['citation_stats'] = !empty($data['citation_stats']) ? json_decode($data['citation_stats'], true) : array();

        return $data;
    }

    /**
     * Update AEO data for a post
     */
    public function update_aeo_data($post_id, $data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'requestdesk_aeo_data';

        $wpdb->update(
            $table_name,
            $data,
            array('post_id' => $post_id),
            null,
            array('%d')
        );
    }

    /**
     * Output schema markup in head
     */
    public function output_schema_markup() {
        // CC-FULL-02: emit ProfessionalService + OfferCatalog on the home
        // page so AI engines have an explicit entity for what the site
        // sells. Additive and isolated: its own ld+json block, runs before
        // (and independent of) the single/page FAQ logic below so it is
        // unaffected by the early return and cannot regress existing schema.
        if (is_front_page()) {
            if (!$this->schema_generator) {
                $this->schema_generator = new RequestDesk_Schema_Generator();
            }
            $ps_schema = $this->schema_generator->generate_professional_service_schema();
            if (!empty($ps_schema)) {
                echo '<script type="application/ld+json">';
                echo json_encode($ps_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                echo '</script>' . "\n";
            }
        }

        if (!is_single() && !is_page()) {
            return;
        }

        $post_id = get_queried_object_id();
        $aeo_data = $this->get_aeo_data($post_id);

        if (!empty($aeo_data['faq_data'])) {
            echo '<script type="application/ld+json">';
            echo json_encode($aeo_data['faq_data'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            echo '</script>' . "\n";
        }
    }

    /**
     * Register AEO REST API endpoints
     */
    public function register_aeo_endpoints() {
        // Get AEO data endpoint
        register_rest_route('requestdesk/v1', '/aeo-data/(?P<post_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_aeo_data'),
            'permission_callback' => array($this, 'check_aeo_permissions'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'type' => 'integer'
                )
            )
        ));

        // Optimize content endpoint
        register_rest_route('requestdesk/v1', '/optimize-content', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_optimize_content'),
            'permission_callback' => array($this, 'check_aeo_permissions'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'type' => 'integer'
                ),
                'force' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false
                )
            )
        ));
    }

    /**
     * REST endpoint to get AEO data
     */
    public function rest_get_aeo_data($request) {
        $post_id = $request->get_param('post_id');
        $aeo_data = $this->get_aeo_data($post_id);

        return new WP_REST_Response($aeo_data, 200);
    }

    /**
     * REST endpoint to optimize content
     */
    public function rest_optimize_content($request) {
        $post_id = $request->get_param('post_id');
        $force = $request->get_param('force');

        $result = $this->optimize_post($post_id, $force);

        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * Check permissions for AEO operations
     */
    public function check_aeo_permissions($request) {
        // For now, require edit_posts capability
        // In production, you might want to tie this to the RequestDesk API key system
        return current_user_can('edit_posts');
    }

    /**
     * AJAX handler for content analysis
     */
    public function ajax_analyze_content() {
        check_ajax_referer('requestdesk_aeo_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }

        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);

        if (!$post) {
            wp_send_json_error('Post not found');
        }

        $this->init_components();
        $analysis = $this->analyzer->analyze_content($post);

        wp_send_json_success($analysis);
    }

    /**
     * AJAX handler for content optimization
     */
    public function ajax_optimize_content() {
        check_ajax_referer('requestdesk_aeo_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }

        $post_id = intval($_POST['post_id']);
        $force = !empty($_POST['force']);

        $result = $this->optimize_post($post_id, $force);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            wp_enqueue_script(
                'requestdesk-aeo-admin',
                REQUESTDESK_PLUGIN_URL . 'assets/js/aeo-admin.js',
                array('jquery'),
                REQUESTDESK_VERSION,
                true
            );

            wp_localize_script('requestdesk-aeo-admin', 'requestdesk_aeo', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('requestdesk_aeo_nonce'),
                'post_id' => get_the_ID()
            ));
        }
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        // Add any frontend scripts needed for AEO functionality
    }
}

// Schedule the optimization processing action - only after WordPress is initialized
add_action('init', function() {
    add_action('requestdesk_process_aeo_optimization', function($post_id) {
        $aeo_core = new RequestDesk_AEO_Core();
        $aeo_core->optimize_post($post_id);
    });
});