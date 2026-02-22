<?php
/**
 * RequestDesk Frontend Q&A Display
 *
 * Handles displaying Q&A pairs on the frontend
 *
 * @package RequestDesk
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Frontend_QA {

    public function __construct() {
        // Frontend hooks
        add_action('init', array($this, 'init_frontend_qa'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));

        // Shortcode
        add_shortcode('requestdesk_qa', array($this, 'qa_shortcode'));
        add_shortcode('requestdesk_qa_debug', array($this, 'qa_debug_shortcode'));

        // Auto-display hooks (configurable)
        add_filter('the_content', array($this, 'auto_append_qa_to_content'), 20);
    }

    /**
     * Initialize frontend Q&A functionality
     */
    public function init_frontend_qa() {
        // Add any initialization code here
    }

    /**
     * Enqueue frontend styles for Q&A display
     */
    public function enqueue_frontend_styles() {
        if (is_single() || is_page()) {
            wp_enqueue_style(
                'requestdesk-frontend-qa',
                REQUESTDESK_PLUGIN_URL . 'assets/css/frontend-qa.css',
                array(),
                REQUESTDESK_VERSION
            );
        }
    }

    /**
     * Shortcode to display Q&A pairs
     *
     * Usage: [requestdesk_qa post_id="123" show_confidence="false" title="Frequently Asked Questions"]
     */
    public function qa_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID(),
            'show_confidence' => 'false',
            'title' => 'Frequently Asked Questions',
            'show_title' => 'true',
            'max_pairs' => '0', // 0 = show all
            'min_confidence' => '0.5' // Only show pairs above this confidence
        ), $atts);

        return $this->render_qa_pairs(
            intval($atts['post_id']),
            array(
                'show_confidence' => ($atts['show_confidence'] === 'true'),
                'title' => $atts['title'],
                'show_title' => ($atts['show_title'] === 'true'),
                'max_pairs' => intval($atts['max_pairs']),
                'min_confidence' => floatval($atts['min_confidence'])
            )
        );
    }

    /**
     * Debug shortcode to show Q&A data and troubleshoot issues
     */
    public function qa_debug_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID()
        ), $atts);

        $post_id = intval($atts['post_id']);

        if (!$post_id) {
            return '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0;"><strong>RequestDesk Q&A Debug:</strong> No post ID found.</div>';
        }

        // Get AEO data
        $aeo_core = new RequestDesk_AEO_Core();
        $aeo_data = $aeo_core->get_aeo_data($post_id);
        $qa_pairs = $aeo_data['ai_questions'] ?? array();

        // Get settings
        $settings = get_option('requestdesk_aeo_settings', array());
        $auto_display = $settings['auto_display_qa_frontend'] ?? false;

        $debug_info = '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 10px 0; font-family: monospace; font-size: 12px;">';
        $debug_info .= '<strong>RequestDesk Q&A Debug Info:</strong><br>';
        $debug_info .= 'Post ID: ' . $post_id . '<br>';
        $debug_info .= 'Q&A Pairs Count: ' . count($qa_pairs) . '<br>';
        $debug_info .= 'Auto-Display Enabled: ' . ($auto_display ? 'Yes' : 'No') . '<br>';
        $debug_info .= 'Frontend Q&A Class: ' . (class_exists('RequestDesk_Frontend_QA') ? 'Loaded' : 'Missing') . '<br>';
        $debug_info .= 'AEO Core Class: ' . (class_exists('RequestDesk_AEO_Core') ? 'Loaded' : 'Missing') . '<br>';

        if (!empty($qa_pairs)) {
            $debug_info .= '<br><strong>Q&A Pairs Found:</strong><br>';
            foreach ($qa_pairs as $i => $qa) {
                $confidence = round(($qa['confidence'] ?? 0) * 100);
                $debug_info .= ($i + 1) . '. Q: ' . substr($qa['question'] ?? 'No question', 0, 100) . '... (Confidence: ' . $confidence . '%)<br>';
            }
        } else {
            $debug_info .= '<br><strong>No Q&A pairs found in database for this post.</strong><br>';
        }

        $debug_info .= '</div>';

        return $debug_info;
    }

    /**
     * Auto-append Q&A pairs to post content (if enabled in settings)
     */
    public function auto_append_qa_to_content($content) {
        // Only on single posts/pages, skip front page
        if (!is_single() && !is_page()) {
            return $content;
        }
        if (is_front_page()) {
            return $content;
        }

        // Check if auto-display is enabled
        $settings = get_option('requestdesk_aeo_settings', array());
        if (!($settings['auto_display_qa_frontend'] ?? false)) {
            return $content;
        }

        // Allow templates to override Q&A display
        $show_qa = apply_filters('requestdesk_show_qa_on_template', true, get_the_ID());
        if (!$show_qa) {
            return $content;
        }

        // Get Q&A pairs for current post
        $qa_html = $this->render_qa_pairs(get_the_ID(), array(
            'show_confidence' => false,
            'title' => $settings['qa_frontend_title'] ?? 'Frequently Asked Questions',
            'show_title' => true,
            'max_pairs' => intval($settings['qa_frontend_max_pairs'] ?? 0),
            'min_confidence' => floatval($settings['qa_frontend_min_confidence'] ?? 0.5)
        ));

        if (!empty($qa_html)) {
            $content .= $qa_html;
        }

        return $content;
    }

    /**
     * Render Q&A pairs HTML
     */
    public function render_qa_pairs($post_id, $options = array()) {
        if (!$post_id) {
            return '';
        }

        // Default options
        $options = wp_parse_args($options, array(
            'show_confidence' => false,
            'title' => 'Frequently Asked Questions',
            'show_title' => true,
            'max_pairs' => 0,
            'min_confidence' => 0.5
        ));

        // Get AEO data
        $aeo_core = new RequestDesk_AEO_Core();
        $aeo_data = $aeo_core->get_aeo_data($post_id);
        $qa_pairs = $aeo_data['ai_questions'] ?? array();

        if (empty($qa_pairs)) {
            return '';
        }

        // Filter by confidence
        if ($options['min_confidence'] > 0) {
            $qa_pairs = array_filter($qa_pairs, function($qa) use ($options) {
                return ($qa['confidence'] ?? 0) >= $options['min_confidence'];
            });
        }

        // Limit number of pairs
        if ($options['max_pairs'] > 0) {
            $qa_pairs = array_slice($qa_pairs, 0, $options['max_pairs']);
        }

        if (empty($qa_pairs)) {
            return '';
        }

        // Build HTML
        ob_start();
        ?>
        <div class="requestdesk-qa-section">
            <?php if ($options['show_title'] && !empty($options['title'])): ?>
                <h3 class="requestdesk-qa-title"><?php echo esc_html($options['title']); ?></h3>
            <?php endif; ?>

            <div class="requestdesk-qa-list">
                <?php foreach ($qa_pairs as $index => $qa): ?>
                    <div class="requestdesk-qa-item" itemscope itemtype="https://schema.org/Question">
                        <div class="requestdesk-qa-question" itemprop="name">
                            <strong><?php echo esc_html($qa['question']); ?></strong>
                            <?php if ($options['show_confidence'] && isset($qa['confidence'])): ?>
                                <span class="requestdesk-qa-confidence" style="opacity: 0.7; font-size: 0.8em;">
                                    (<?php echo round($qa['confidence'] * 100); ?>% confidence)
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="requestdesk-qa-answer" itemscope itemtype="https://schema.org/Answer" itemprop="acceptedAnswer">
                            <div itemprop="text">
                                <?php echo do_shortcode(wp_kses_post(wpautop($qa['answer']))); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php
        $faq_schema = array(
            '@context' => 'https://schema.org',
            '@type'    => 'FAQPage',
            'mainEntity' => array(),
        );
        foreach ($qa_pairs as $qa) {
            $faq_schema['mainEntity'][] = array(
                '@type' => 'Question',
                'name'  => $qa['question'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => strip_tags($qa['answer']),
                ),
            );
        }
        ?>
        <script type="application/ld+json">
        <?php echo wp_json_encode($faq_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Get Q&A pairs for a post (public method for theme integration)
     */
    public function get_qa_pairs($post_id) {
        if (!$post_id) {
            return array();
        }

        $aeo_core = new RequestDesk_AEO_Core();
        $aeo_data = $aeo_core->get_aeo_data($post_id);

        return $aeo_data['ai_questions'] ?? array();
    }

    /**
     * Check if post has Q&A pairs
     */
    public function post_has_qa_pairs($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        $qa_pairs = $this->get_qa_pairs($post_id);
        return !empty($qa_pairs);
    }
}

// Helper functions for theme integration
if (!function_exists('requestdesk_display_qa_pairs')) {
    /**
     * Display Q&A pairs in theme templates
     *
     * @param int $post_id Post ID (optional, defaults to current post)
     * @param array $options Display options
     */
    function requestdesk_display_qa_pairs($post_id = null, $options = array()) {
        $frontend_qa = new RequestDesk_Frontend_QA();

        if (!$post_id) {
            $post_id = get_the_ID();
        }

        echo $frontend_qa->render_qa_pairs($post_id, $options);
    }
}

if (!function_exists('requestdesk_get_qa_pairs')) {
    /**
     * Get Q&A pairs for theme integration
     *
     * @param int $post_id Post ID (optional, defaults to current post)
     * @return array Q&A pairs
     */
    function requestdesk_get_qa_pairs($post_id = null) {
        $frontend_qa = new RequestDesk_Frontend_QA();

        if (!$post_id) {
            $post_id = get_the_ID();
        }

        return $frontend_qa->get_qa_pairs($post_id);
    }
}

if (!function_exists('requestdesk_has_qa_pairs')) {
    /**
     * Check if post has Q&A pairs
     *
     * @param int $post_id Post ID (optional, defaults to current post)
     * @return bool Whether post has Q&A pairs
     */
    function requestdesk_has_qa_pairs($post_id = null) {
        $frontend_qa = new RequestDesk_Frontend_QA();
        return $frontend_qa->post_has_qa_pairs($post_id);
    }
}

if (!function_exists('requestdesk_disable_qa_for_template')) {
    /**
     * Disable Q&A display for specific template
     * Call this in your template file to prevent auto-display
     */
    function requestdesk_disable_qa_for_template() {
        add_filter('requestdesk_show_qa_on_template', '__return_false');
    }
}

if (!function_exists('requestdesk_enable_qa_for_template')) {
    /**
     * Re-enable Q&A display for specific template (if previously disabled)
     */
    function requestdesk_enable_qa_for_template() {
        remove_filter('requestdesk_show_qa_on_template', '__return_false');
    }
}