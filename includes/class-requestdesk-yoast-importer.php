<?php
/**
 * RequestDesk Yoast SEO Importer
 *
 * Imports SEO data from Yoast SEO to RequestDesk, allowing users
 * to migrate their SEO settings and phase out Yoast.
 *
 * @package RequestDesk
 * @since 2.5.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Yoast_Importer {

    /**
     * Yoast meta key mappings to RequestDesk
     */
    private $meta_key_map = array(
        // Core SEO
        '_yoast_wpseo_title' => '_requestdesk_seo_title',
        '_yoast_wpseo_metadesc' => '_requestdesk_seo_description',
        '_yoast_wpseo_focuskw' => '_requestdesk_focus_keyphrase',
        '_yoast_wpseo_canonical' => '_requestdesk_canonical_url',

        // Robots
        '_yoast_wpseo_meta-robots-noindex' => '_requestdesk_noindex',
        '_yoast_wpseo_meta-robots-nofollow' => '_requestdesk_nofollow',

        // Open Graph
        '_yoast_wpseo_opengraph-title' => '_requestdesk_og_title',
        '_yoast_wpseo_opengraph-description' => '_requestdesk_og_description',
        '_yoast_wpseo_opengraph-image' => '_requestdesk_og_image',

        // Twitter
        '_yoast_wpseo_twitter-title' => '_requestdesk_twitter_title',
        '_yoast_wpseo_twitter-description' => '_requestdesk_twitter_description',
        '_yoast_wpseo_twitter-image' => '_requestdesk_twitter_image',
    );

    /**
     * Constructor
     */
    public function __construct() {
        // Register AJAX handlers
        add_action('wp_ajax_requestdesk_yoast_import_preview', array($this, 'ajax_import_preview'));
        add_action('wp_ajax_requestdesk_yoast_import_execute', array($this, 'ajax_import_execute'));
        add_action('wp_ajax_requestdesk_yoast_import_status', array($this, 'ajax_import_status'));
    }

    /**
     * Check if Yoast data exists
     */
    public function has_yoast_data() {
        global $wpdb;

        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key LIKE '_yoast_wpseo_%'
             AND meta_value != ''"
        );

        return $count > 0;
    }

    /**
     * Get count of posts with Yoast data
     */
    public function get_yoast_data_count() {
        global $wpdb;

        $count = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
             WHERE meta_key LIKE '_yoast_wpseo_%'
             AND meta_value != ''"
        );

        return (int) $count;
    }

    /**
     * Get preview of data to be imported
     */
    public function get_import_preview($limit = 10) {
        global $wpdb;

        // Get unique post IDs with Yoast data
        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key LIKE '_yoast_wpseo_%%'
                 AND meta_value != ''
                 LIMIT %d",
                $limit
            )
        );

        $preview = array();

        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) continue;

            $yoast_data = $this->get_yoast_data_for_post($post_id);
            $existing_rd_data = $this->get_requestdesk_data_for_post($post_id);

            $preview[] = array(
                'post_id' => $post_id,
                'post_title' => $post->post_title,
                'post_type' => $post->post_type,
                'yoast_data' => $yoast_data,
                'has_existing_rd_data' => !empty(array_filter($existing_rd_data)),
                'existing_rd_data' => $existing_rd_data,
            );
        }

        return $preview;
    }

    /**
     * Get Yoast data for a specific post
     */
    public function get_yoast_data_for_post($post_id) {
        $data = array();

        foreach ($this->meta_key_map as $yoast_key => $rd_key) {
            $value = get_post_meta($post_id, $yoast_key, true);
            if (!empty($value)) {
                $data[$yoast_key] = $value;
            }
        }

        return $data;
    }

    /**
     * Get RequestDesk SEO data for a specific post
     */
    public function get_requestdesk_data_for_post($post_id) {
        $data = array();

        foreach ($this->meta_key_map as $yoast_key => $rd_key) {
            $value = get_post_meta($post_id, $rd_key, true);
            if (!empty($value)) {
                $data[$rd_key] = $value;
            }
        }

        return $data;
    }

    /**
     * Import Yoast data for a single post
     */
    public function import_post($post_id, $overwrite = false) {
        $yoast_data = $this->get_yoast_data_for_post($post_id);
        $imported = 0;
        $skipped = 0;

        foreach ($this->meta_key_map as $yoast_key => $rd_key) {
            if (!isset($yoast_data[$yoast_key])) {
                continue;
            }

            $yoast_value = $yoast_data[$yoast_key];
            $existing_value = get_post_meta($post_id, $rd_key, true);

            // Skip if RequestDesk already has data and we're not overwriting
            if (!empty($existing_value) && !$overwrite) {
                $skipped++;
                continue;
            }

            // Handle special cases
            $value = $this->transform_value($yoast_key, $yoast_value);

            if (!empty($value)) {
                update_post_meta($post_id, $rd_key, $value);
                $imported++;
            }
        }

        return array(
            'post_id' => $post_id,
            'imported' => $imported,
            'skipped' => $skipped,
        );
    }

    /**
     * Transform Yoast value to RequestDesk format if needed
     */
    private function transform_value($yoast_key, $value) {
        // Handle noindex/nofollow (Yoast uses '1' or '2', we use '1' or empty)
        if (in_array($yoast_key, array('_yoast_wpseo_meta-robots-noindex', '_yoast_wpseo_meta-robots-nofollow'))) {
            return ($value === '1' || $value === 1) ? '1' : '';
        }

        // Handle image URLs (Yoast sometimes stores attachment IDs)
        if (strpos($yoast_key, '-image') !== false) {
            if (is_numeric($value)) {
                $image_url = wp_get_attachment_url($value);
                return $image_url ?: '';
            }
            return esc_url_raw($value);
        }

        // Handle title/description (Yoast uses template variables)
        if (in_array($yoast_key, array('_yoast_wpseo_title', '_yoast_wpseo_metadesc'))) {
            // Convert common Yoast template variables to RequestDesk format
            $value = str_replace(
                array('%%title%%', '%%sitename%%', '%%sep%%', '%%excerpt%%', '%%primary_category%%'),
                array('%%title%%', '%%sitename%%', '%%sep%%', '%%excerpt%%', '%%category%%'),
                $value
            );
            return sanitize_text_field($value);
        }

        // Default: sanitize as text
        return sanitize_text_field($value);
    }

    /**
     * Run batch import
     */
    public function run_batch_import($batch_size = 50, $offset = 0, $overwrite = false) {
        global $wpdb;

        // Get batch of post IDs with Yoast data
        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key LIKE '_yoast_wpseo_%%'
                 AND meta_value != ''
                 ORDER BY post_id ASC
                 LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            )
        );

        $results = array(
            'processed' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => array(),
        );

        foreach ($post_ids as $post_id) {
            try {
                $result = $this->import_post($post_id, $overwrite);
                $results['processed']++;
                $results['imported'] += $result['imported'];
                $results['skipped'] += $result['skipped'];
            } catch (Exception $e) {
                $results['errors'][] = array(
                    'post_id' => $post_id,
                    'error' => $e->getMessage(),
                );
            }
        }

        return $results;
    }

    /**
     * Get total number of posts to import
     */
    public function get_total_posts() {
        return $this->get_yoast_data_count();
    }

    /**
     * AJAX: Get import preview
     */
    public function ajax_import_preview() {
        check_ajax_referer('requestdesk_yoast_import', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $preview = $this->get_import_preview(20);
        $total = $this->get_total_posts();

        wp_send_json_success(array(
            'preview' => $preview,
            'total' => $total,
            'has_yoast_data' => $this->has_yoast_data(),
        ));
    }

    /**
     * AJAX: Execute import batch
     */
    public function ajax_import_execute() {
        check_ajax_referer('requestdesk_yoast_import', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === 'true';

        $results = $this->run_batch_import($batch_size, $offset, $overwrite);
        $total = $this->get_total_posts();

        wp_send_json_success(array(
            'results' => $results,
            'offset' => $offset + $batch_size,
            'total' => $total,
            'complete' => ($offset + $batch_size) >= $total,
        ));
    }

    /**
     * AJAX: Get import status
     */
    public function ajax_import_status() {
        check_ajax_referer('requestdesk_yoast_import', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(array(
            'has_yoast_data' => $this->has_yoast_data(),
            'total_yoast_posts' => $this->get_total_posts(),
            'yoast_active' => defined('WPSEO_VERSION'),
            'yoast_version' => defined('WPSEO_VERSION') ? WPSEO_VERSION : null,
        ));
    }

    /**
     * Get summary of what will be imported
     */
    public function get_import_summary() {
        global $wpdb;

        $summary = array();

        foreach ($this->meta_key_map as $yoast_key => $rd_key) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta}
                     WHERE meta_key = %s AND meta_value != ''",
                    $yoast_key
                )
            );

            if ($count > 0) {
                $summary[$yoast_key] = array(
                    'yoast_key' => $yoast_key,
                    'requestdesk_key' => $rd_key,
                    'count' => (int) $count,
                    'label' => $this->get_field_label($yoast_key),
                );
            }
        }

        return $summary;
    }

    /**
     * Get human-readable label for a meta key
     */
    private function get_field_label($key) {
        $labels = array(
            '_yoast_wpseo_title' => 'SEO Title',
            '_yoast_wpseo_metadesc' => 'Meta Description',
            '_yoast_wpseo_focuskw' => 'Focus Keyphrase',
            '_yoast_wpseo_canonical' => 'Canonical URL',
            '_yoast_wpseo_meta-robots-noindex' => 'No Index',
            '_yoast_wpseo_meta-robots-nofollow' => 'No Follow',
            '_yoast_wpseo_opengraph-title' => 'Open Graph Title',
            '_yoast_wpseo_opengraph-description' => 'Open Graph Description',
            '_yoast_wpseo_opengraph-image' => 'Open Graph Image',
            '_yoast_wpseo_twitter-title' => 'Twitter Title',
            '_yoast_wpseo_twitter-description' => 'Twitter Description',
            '_yoast_wpseo_twitter-image' => 'Twitter Image',
        );

        return $labels[$key] ?? $key;
    }
}
