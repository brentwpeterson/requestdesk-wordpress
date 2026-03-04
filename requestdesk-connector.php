<?php
/**
 * Plugin Name: RequestDesk Connector
 * Plugin URI: https://requestdesk.ai
 * Description: Connects RequestDesk.ai to WordPress for publishing content with secure API key authentication and AEO/AIO/GEO optimization
 * Version: 2.7.0
 * Author: RequestDesk Team
 * License: GPL v2 or later
 * Text Domain: requestdesk-connector
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('REQUESTDESK_VERSION', '2.7.0');
define('REQUESTDESK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('REQUESTDESK_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load plugin files with error handling
$plugin_files = array(
    'includes/class-requestdesk-api.php',
    'includes/class-requestdesk-headless-api.php',
    'includes/class-requestdesk-post-handler.php',
    'includes/class-requestdesk-push.php',
    'admin/settings-page.php',
    'admin/headless-settings-page.php',
    'includes/class-requestdesk-aeo-core.php',
    'includes/class-requestdesk-content-analyzer.php',
    'includes/class-requestdesk-content-detector.php', // NEW: AI-First Schema Detection
    'includes/class-requestdesk-schema-generator.php',
    'includes/class-requestdesk-freshness-tracker.php',
    'includes/class-requestdesk-citation-tracker.php',
    'includes/class-requestdesk-claude-integration.php',
    'admin/aeo-settings-page.php',
    'admin/aeo-meta-boxes.php',
    'admin/aeo-bulk-optimizer.php',
    'admin/aeo-template-importer.php',
    'admin/aeo-action-instructions.php',
    'admin/aeo-template-enhanced.php',
    'admin/aeo-template-about.php',
    'includes/class-requestdesk-frontend-qa.php',
    'includes/class-requestdesk-child-grid.php',
    'includes/class-requestdesk-homepage-hero.php',
    'admin/homepage-hero-settings-page.php',
    'includes/class-requestdesk-stats-bar.php',
    'admin/stats-bar-settings-page.php',
    'includes/class-requestdesk-comparison-table.php',
    'includes/class-requestdesk-partner.php'
);

foreach ($plugin_files as $file) {
    $file_path = REQUESTDESK_PLUGIN_DIR . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        // Log missing file but don't stop execution
        if (function_exists('error_log')) {
            error_log('RequestDesk: Missing plugin file: ' . $file_path);
        }
    }
}

// Enqueue comparison table styles on frontend
if (function_exists('add_action')) {
    add_action('wp_enqueue_scripts', function () {
        wp_enqueue_style(
            'requestdesk-comparison-table',
            REQUESTDESK_PLUGIN_URL . 'assets/css/comparison-table.css',
            array(),
            REQUESTDESK_VERSION
        );
    });
}

// Register custom 5-minute cron schedule
if (function_exists('add_filter')) {
    add_filter('cron_schedules', 'requestdesk_add_cron_schedules');
}

function requestdesk_add_cron_schedules($schedules) {
    $schedules['every_five_minutes'] = array(
        'interval' => 300,
        'display'  => __('Every 5 Minutes'),
    );
    return $schedules;
}

// Initialize the plugin with safety checks
if (function_exists('add_action')) {
    add_action('init', 'requestdesk_init');
} else {
    // Fallback initialization if add_action isn't available
    if (function_exists('requestdesk_init')) {
        requestdesk_init();
    }
}

function requestdesk_init() {
    // Register REST API endpoints with safety check
    if (function_exists('add_action')) {
        add_action('rest_api_init', 'requestdesk_register_api_endpoints');

        // Add admin menu
        add_action('admin_menu', 'requestdesk_add_admin_menu');

        // Initialize AEO admin functionality
        add_action('admin_menu', 'requestdesk_aeo_add_admin_menu');
        add_action('add_meta_boxes', 'requestdesk_aeo_add_meta_boxes');

        // Initialize Headless API admin menu
        if (function_exists('requestdesk_headless_add_admin_menu')) {
            add_action('admin_menu', 'requestdesk_headless_add_admin_menu', 20);
        }

        // Schedule headless API usage sync (every 5 minutes)
        add_action('requestdesk_sync_headless_counts', 'requestdesk_sync_headless_counts_callback');
        if (!wp_next_scheduled('requestdesk_sync_headless_counts')) {
            wp_schedule_event(time(), 'every_five_minutes', 'requestdesk_sync_headless_counts');
        }
    }

    // Initialize push functionality with error handling
    if (class_exists('RequestDesk_Push')) {
        try {
            new RequestDesk_Push();
        } catch (Throwable $e) {
            if (function_exists('error_log')) {
                error_log('RequestDesk: Failed to initialize Push: ' . $e->getMessage());
            }
        }
    }

    // Initialize AEO functionality with error handling
    $aeo_classes = array(
        'RequestDesk_AEO_Core',
        'RequestDesk_Content_Analyzer',
        'RequestDesk_Schema_Generator',
        'RequestDesk_Freshness_Tracker',
        'RequestDesk_Citation_Tracker',
        'RequestDesk_Frontend_QA'
    );

    foreach ($aeo_classes as $class_name) {
        if (class_exists($class_name)) {
            try {
                new $class_name();
            } catch (Throwable $e) {
                if (function_exists('error_log')) {
                    error_log("RequestDesk: Failed to initialize $class_name: " . $e->getMessage());
                }
            }
        }
    }
}

/**
 * Register REST API endpoints
 */
function requestdesk_register_api_endpoints() {
    $api = new RequestDesk_API();
    $api->register_routes();

    // Register headless CMS endpoints for Astro/frontend use
    if (class_exists('RequestDesk_Headless_API')) {
        $headless_api = new RequestDesk_Headless_API();
        $headless_api->register_routes();
    }
}

/**
 * Add admin menu
 */
function requestdesk_add_admin_menu() {
    // Main menu page - AEO Analytics Dashboard
    add_menu_page(
        'RequestDesk AEO Dashboard',
        'RequestDesk',
        'manage_options',
        'requestdesk-aeo-analytics',
        'requestdesk_aeo_analytics_page',
        'dashicons-chart-area',
        30
    );

    // Combined Settings page (RequestDesk + AEO settings)
    add_submenu_page(
        'requestdesk-aeo-analytics',
        'RequestDesk Settings',
        'Settings',
        'manage_options',
        'requestdesk-settings',
        'requestdesk_combined_settings_page'
    );
}

/**
 * Combined Settings Page (RequestDesk + AEO Settings)
 */
function requestdesk_combined_settings_page() {
    ?>
    <div class="wrap">
        <h1>RequestDesk Settings</h1>

        <nav class="nav-tab-wrapper">
            <a href="#general" class="nav-tab nav-tab-active" onclick="openTab(event, 'general')">General Settings</a>
            <a href="#aeo" class="nav-tab" onclick="openTab(event, 'aeo')">AEO Settings</a>
            <a href="#homepage-hero" class="nav-tab" onclick="openTab(event, 'homepage-hero')">Homepage Hero</a>
            <a href="#stats-bar" class="nav-tab" onclick="openTab(event, 'stats-bar')">Stats Bar</a>
        </nav>

        <!-- General RequestDesk Settings Tab -->
        <div id="general" class="tab-content" style="display: block;">
            <?php
            // Include the original RequestDesk settings content
            ob_start();
            requestdesk_settings_page();
            $settings_content = ob_get_clean();

            // Remove the outer wrap div and h1 to avoid duplication
            $settings_content = preg_replace('/<div class="wrap">.*?<h1>.*?<\/h1>/s', '', $settings_content);
            $settings_content = preg_replace('/<\/div>\s*$/', '', $settings_content);

            echo $settings_content;
            ?>
        </div>

        <!-- AEO Settings Tab -->
        <div id="aeo" class="tab-content" style="display: none;">
            <?php
            // Include the AEO settings content
            ob_start();
            requestdesk_aeo_settings_page();
            $aeo_content = ob_get_clean();

            // Remove the outer wrap div and h1 to avoid duplication
            $aeo_content = preg_replace('/<div class="wrap">.*?<h1>.*?<\/h1>/s', '', $aeo_content);
            $aeo_content = preg_replace('/<\/div>\s*$/', '', $aeo_content);

            echo $aeo_content;
            ?>
        </div>

        <!-- Homepage Hero Settings Tab -->
        <div id="homepage-hero" class="tab-content" style="display: none;">
            <?php
            if (function_exists('requestdesk_homepage_hero_settings_page')) {
                requestdesk_homepage_hero_settings_page();
            }
            ?>
        </div>

        <!-- Stats Bar Settings Tab -->
        <div id="stats-bar" class="tab-content" style="display: none;">
            <?php
            if (function_exists('requestdesk_stats_bar_settings_page')) {
                requestdesk_stats_bar_settings_page();
            }
            ?>
        </div>
    </div>

    <style>
    .nav-tab-wrapper {
        margin-bottom: 20px;
    }
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }
    </style>

    <script>
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        tablinks = document.getElementsByClassName("nav-tab");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("nav-tab-active");
        }
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.classList.add("nav-tab-active");
    }
    </script>
    <?php
}

/**
 * Sync headless API request counts to RequestDesk.
 *
 * Reads the buffered count, POSTs it to the RequestDesk sync endpoint,
 * and on success subtracts the synced amount (not reset to 0) so requests
 * arriving during the sync are not lost.
 */
function requestdesk_sync_headless_counts_callback() {
    global $wpdb;

    // Read current buffered count
    $count = (int) get_option('requestdesk_headless_api_count', 0);
    if ($count <= 0) {
        return; // Nothing to sync
    }

    // Get the agent API key from headless settings (or main settings fallback)
    $headless_settings = get_option('requestdesk_headless_settings', array());
    $settings = get_option('requestdesk_settings', array());
    $api_key = !empty($headless_settings['api_key']) ? $headless_settings['api_key'] : ($settings['api_key'] ?? '');

    if (empty($api_key)) {
        error_log('RequestDesk: Cannot sync headless counts - no API key configured');
        return;
    }

    // Get RequestDesk API URL from settings (default to production)
    $api_url = !empty($headless_settings['requestdesk_url'])
        ? rtrim($headless_settings['requestdesk_url'], '/')
        : 'https://app.requestdesk.ai';

    $response = wp_remote_post($api_url . '/api/usage/headless-sync', array(
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ),
        'body'    => json_encode(array(
            'count'    => $count,
            'source'   => 'wordpress',
            'site_url' => home_url(),
        )),
        'timeout' => 15,
    ));

    if (is_wp_error($response)) {
        error_log('RequestDesk: Headless sync failed - ' . $response->get_error_message());
        return; // Keep buffer, retry next cron run
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        error_log('RequestDesk: Headless sync returned HTTP ' . $status_code);
        return; // Keep buffer, retry next cron run
    }

    // Success: subtract the synced amount (not reset to 0) for concurrent safety
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->options} SET option_value = GREATEST(option_value - %d, 0) WHERE option_name = 'requestdesk_headless_api_count'",
        $count
    ));
}

/**
 * Activation hook with safety checks
 */
if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, 'requestdesk_activate');
}

function requestdesk_activate() {
    // Create database table for sync logs if needed
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'requestdesk_sync_log';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        ticket_id varchar(255) NOT NULL,
        post_id bigint(20) NOT NULL,
        agent_id varchar(255) NOT NULL,
        sync_status varchar(50) DEFAULT 'success',
        sync_date datetime DEFAULT CURRENT_TIMESTAMP,
        error_message text,
        PRIMARY KEY (id),
        KEY ticket_id (ticket_id),
        KEY agent_id (agent_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Create AEO data table
    $aeo_table_name = $wpdb->prefix . 'requestdesk_aeo_data';

    $aeo_sql = "CREATE TABLE IF NOT EXISTS $aeo_table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        content_type varchar(20) DEFAULT 'post',
        aeo_score tinyint(3) DEFAULT 0,
        last_analyzed datetime DEFAULT CURRENT_TIMESTAMP,
        ai_questions longtext,
        faq_data longtext,
        citation_stats longtext,
        optimization_status varchar(20) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY post_id (post_id),
        KEY content_type (content_type),
        KEY optimization_status (optimization_status),
        KEY aeo_score (aeo_score)
    ) $charset_collate;";

    dbDelta($aeo_sql);

    // Set default options
    add_option('requestdesk_settings', array(
        'debug_mode' => false,
        'allowed_post_types' => array('post'),
        'default_post_status' => 'draft',
        'api_key' => '', // Must be configured by user for security
        'claude_api_key' => '',
        'claude_model' => 'claude-sonnet-4-5-20250929'
    ));

    // Set default AEO options
    add_option('requestdesk_aeo_settings', array(
        'enabled' => true,
        'auto_optimize_on_publish' => true,
        'auto_optimize_on_update' => false,
        'generate_faq_schema' => true,
        'extract_qa_pairs' => true,
        'track_citations' => true,
        'monitor_freshness' => true,
        'min_content_length' => 300,
        'qa_extraction_confidence' => 0.7,
        'freshness_alert_days' => 90
    ));

    // Set default Homepage Hero options
    add_option('requestdesk_homepage_hero_settings', array(
        'headline'              => 'Humans<br>Writing<br>Content',
        'form_heading'          => "Let's write your success story!",
        'seo_text'              => 'Humans in the loop. We believe AI should enhance human creativity, not replace it. Our approach: AI-powered content creation with human editors reviewing every piece. Executing with precision. Complete brand consistency across all platforms.',
        'hubspot_portal_id'     => '39487190',
        'hubspot_form_id'       => '3c945309-67c6-4812-ab65-c7280682e005',
        'hubspot_region'        => 'na1',
        'terminal_enabled'      => true,
        'terminal_sequences'    => array(
            array(
                array('text' => 'Write more, prompt less.', 'delay' => 500),
                array('text' => "\nNo AI slop.", 'delay' => 500),
                array('text' => "\nHumans in the loop. Always.", 'delay' => 1200),
            ),
            array(
                array('text' => '> content_engine.start()', 'delay' => 500),
                array('text' => "\nLoading brand voice...", 'delay' => 700),
                array('text' => "\nHuman writers standing by.", 'delay' => 900),
                array('text' => "\nReady.", 'delay' => 1200),
            ),
            array(
                array('text' => 'Blog posts. Landing pages.', 'delay' => 500),
                array('text' => "\nSocial. Email. SEO.", 'delay' => 500),
                array('text' => "\nAll written by real humans.", 'delay' => 1200),
            ),
        ),
        'terminal_type_speed_min' => 45,
        'terminal_type_speed_max' => 95,
        'hero_bg_color'           => '#000000',
        'headline_color'          => '#58c558',
        'max_width'               => 1200,
    ));

    // Set default Stats Bar options
    add_option('requestdesk_stats_bar_settings', array(
        'stats' => array(
            array('value' => '60,000 +', 'label' => 'Projects Delivered', 'icon' => ''),
            array('value' => '55 Million +', 'label' => 'Words Written', 'icon' => ''),
            array('value' => '4.9/5', 'label' => 'Average Project Rating', 'icon' => ''),
        ),
        'bg_color'    => '#000000',
        'value_color' => '#FF8C00',
        'label_color' => '#ffffff',
        'max_width'   => 1200,
        'columns'     => 3,
    ));

    // Initialize headless API request counter
    add_option('requestdesk_headless_api_count', 0);

    // Flush rewrite rules for REST API
    flush_rewrite_rules();

    // Mark activation as complete to enable auto-updater safely
    update_option('requestdesk_activation_complete', true);
}

/**
 * Deactivation hook with safety checks
 */
if (function_exists('register_deactivation_hook')) {
    register_deactivation_hook(__FILE__, 'requestdesk_deactivate');
}

function requestdesk_deactivate() {
    wp_clear_scheduled_hook('requestdesk_sync_headless_counts');
    flush_rewrite_rules();
}

/**
 * AJAX handler for testing Claude API connection with safety checks
 */
if (function_exists('add_action')) {
    add_action('wp_ajax_test_claude_connection', 'requestdesk_test_claude_connection');
}

function requestdesk_test_claude_connection() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'test_claude_connection')) {
        wp_die('Invalid nonce');
    }

    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    $api_key = sanitize_text_field($_POST['api_key']);

    if (empty($api_key)) {
        wp_send_json_error(array('message' => 'API key is required'));
        return;
    }

    // Temporarily store the API key for testing
    $original_settings = get_option('requestdesk_settings', array());
    $test_settings = $original_settings;
    $test_settings['claude_api_key'] = $api_key;
    update_option('requestdesk_settings', $test_settings);

    // Test the connection
    $claude = new RequestDesk_Claude_Integration();
    $result = $claude->test_connection();

    // Restore original settings
    update_option('requestdesk_settings', $original_settings);

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    } else {
        wp_send_json_success(array(
            'message' => 'Connection successful! Model: ' . $result['model'],
            'response' => $result['response']
        ));
    }
}

