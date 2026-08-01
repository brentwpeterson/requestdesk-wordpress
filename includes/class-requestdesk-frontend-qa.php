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
     * Enqueue frontend styles for Q&A display.
     *
     * Loads only when the page is going to render Q&A content. Previously
     * enqueued on every singular page regardless of whether the post had
     * AEO Q&A data (render-blocking dead weight on most pages). Now checks
     * that AEO is enabled site-wide AND that the post has ai_questions
     * registered before enqueueing.
     *
     * Tightened in v2.15.2.
     */
    public function enqueue_frontend_styles() {
        if (!is_single() && !is_page()) {
            return;
        }
        if (is_front_page()) {
            return;
        }

        $settings = get_option('requestdesk_aeo_settings', array());
        if (!($settings['auto_display_qa_frontend'] ?? false)) {
            return;
        }

        $show_qa = apply_filters('requestdesk_show_qa_on_template', true, get_queried_object_id());
        if (!$show_qa) {
            return;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }

        // Q&A lives in the requestdesk_aeo_data TABLE, not post meta. This guard
        // used to read get_post_meta($post_id, 'aeo_data') -- a key nothing ever
        // writes -- so it always returned early and this stylesheet never loaded,
        // while the_content still injected the markup. Result: unstyled Q&A blocks
        // on every post that had pairs. Leftover from the meta-to-table migration.
        //
        // Deliberately a direct read rather than RequestDesk_AEO_Core::get_aeo_data(),
        // which INSERTs a row when none exists -- that would write to the database
        // on every anonymous pageview.
        global $wpdb;

        $questions = $wpdb->get_var($wpdb->prepare(
            "SELECT ai_questions FROM {$wpdb->prefix}requestdesk_aeo_data WHERE post_id = %d",
            $post_id
        ));

        if (empty($questions)) {
            return;
        }

        $decoded = json_decode($questions, true);
        if (!is_array($decoded) || empty($decoded)) {
            return;
        }

        wp_enqueue_style(
            'requestdesk-frontend-qa',
            REQUESTDESK_PLUGIN_URL . 'assets/css/frontend-qa.css',
            array(),
            REQUESTDESK_VERSION
        );
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

        // Do not restate the article back to the reader.
        //
        // extract_qa_pairs() builds its pairs FROM this post's own body: a
        // question-form H2 becomes the question and the prose under it becomes
        // the answer. Appending those pairs as a "Frequently Asked Questions"
        // block puts the same words on the page twice, in the same order, a
        // few hundred pixels apart.
        //
        // Measured on contentcucumber.com 2026-08-01: of 60 published posts, 7
        // had both an appended block and question-form H2s, and 6 of those
        // repeated at least one question. On how-to-write-meta-tags-for-seo the
        // writer's H2 and the appended question were character-for-character
        // identical; on chatgpt-will-not-get-you-better-content all 5 were.
        // Brent: "we write blog posts with QA built in, we have to be aware of
        // this when automating the FAQ."
        //
        // A manual pair is different and still renders. Someone opened the AEO
        // meta box and wrote a standalone question for the box, which is a
        // deliberate act; extraction is a machine reading the article.
        //
        // The FAQPage schema is deliberately left alone. Its questions and
        // answers are still visible on the page -- in the body, where the
        // writer put them -- so the markup stays valid. Suppressing the schema
        // here is what WOULD break it, by describing content that is no longer
        // rendered.
        if ($this->pairs_are_extracted_from_body(get_the_ID())) {
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
     * True when this post's Q&A pairs were all read out of its own body, so
     * rendering them again would repeat content the reader has already passed.
     *
     * A pair carries source='manual' only when a human typed it into the AEO
     * meta box (see RequestDesk_AEO_Core::save_manual_qa_pairs). Everything
     * else arrives from extract_qa_pairs(), which parses this post's headings
     * and prose. So: any manual pair in the set means a human intended a
     * standalone FAQ block and it renders; a set with none is the article
     * talking to itself.
     *
     * Filterable, because a site may legitimately want the block anyway (a
     * long reference page where a summarised FAQ at the end earns its space):
     *     add_filter('requestdesk_suppress_extracted_qa', '__return_false');
     *
     * @param int $post_id
     * @return bool
     */
    protected function pairs_are_extracted_from_body($post_id) {
        if (!$post_id) {
            return false;
        }

        $pairs = $this->get_qa_pairs($post_id);
        if (!is_array($pairs)) {
            $pairs = array();
        }

        // No pairs at all: nothing to suppress, and render_qa_pairs() will
        // return an empty string on its own.
        if (empty($pairs)) {
            return false;
        }

        foreach ($pairs as $pair) {
            if (is_array($pair) && (($pair['source'] ?? '') === 'manual')) {
                return false;   // a human authored at least one; show the block
            }
        }

        return (bool) apply_filters('requestdesk_suppress_extracted_qa', true, $post_id, $pairs);
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
        // FAQPage JSON-LD schema is intentionally NOT emitted here. The
        // canonical FAQPage emitter is RequestDesk_AEO_Core::output_schema_markup
        // (hooked to wp_head, see class-requestdesk-aeo-core.php line 302),
        // which reads the same aeo_data['faq_data'] post meta and emits one
        // FAQPage script in the document head. Emitting a second script here
        // produced duplicate FAQPage on every page where Frontend QA was
        // enabled (caught by the contentcucumber.com 2026-05-09 SEO audit on
        // /contact/ and other pages with auto_display_qa_frontend = true).
        // Visible Q&A HTML above keeps Schema.org microdata
        // (itemtype Question/Answer) so AI crawlers and Google can still
        // extract the same data from the rendered DOM.
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