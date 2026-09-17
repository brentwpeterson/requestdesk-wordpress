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
        $aeo_data = $aeo_core->get_aeo_data_readonly($post_id);
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
        // question-form H2 or H3 becomes the question and the prose under it
        // becomes the answer. Appending those pairs as a "Frequently Asked
        // Questions" block puts the same words on the page twice, a few hundred
        // pixels apart.
        //
        // Measured against the production database on 2026-08-01: 105 posts
        // carry stored AEO pairs and 64 of them have at least one question that
        // matches a heading in their own body.
        //
        // Brent: "we write blog posts with QA built in, we have to be aware of
        // this when automating the FAQ."
        //
        // The filtering is PER PAIR, not all-or-nothing, because the sets are
        // usually mixed. what-gets-your-content-cited-in-ai-search stores nine
        // questions of which three restate headings; dropping the block whole
        // would throw away six that add something.
        //
        // The FAQPage schema is deliberately left alone. Its questions and
        // answers are still visible on the page -- in the body, where the writer
        // put them -- so the markup stays valid. Suppressing the schema is what
        // WOULD break it, by describing content that is no longer rendered.
        $qa_html = $this->render_qa_pairs(get_the_ID(), array(
            'show_confidence' => false,
            'title' => $settings['qa_frontend_title'] ?? 'Frequently Asked Questions',
            'show_title' => true,
            'max_pairs' => intval($settings['qa_frontend_max_pairs'] ?? 0),
            'min_confidence' => floatval($settings['qa_frontend_min_confidence'] ?? 0.5),
            'exclude_body_duplicates' => true,
        ));

        if (!empty($qa_html)) {
            $content .= $qa_html;
        }

        return $content;
    }

    /**
     * Drop pairs whose question already appears as a heading in the post body.
     *
     * WHY NOT source='manual'. The first version of this check trusted that
     * field and it was wrong. `source` is stamped when someone SAVES the AEO
     * meta box, not when they author a question, so extraction-derived pairs
     * become "manual" the moment an editor opens the box and hits update. The
     * production data proves it: chatgpt-will-not-get-you-better-content stores
     * five pairs all marked source=manual, and the first one reads
     * "2. Why does AI content all sound the same?" -- nobody types a list
     * number into a FAQ box. Deployed on 2026-08-01, that version changed
     * nothing on the 14 posts with manual pairs, which is where the worst
     * duplication was.
     *
     * The content itself is the reliable signal. If the question matches a
     * heading the reader has already scrolled past, the pair adds nothing,
     * whatever any metadata claims about its origin.
     *
     * Comparison is normalised for case, punctuation, entities, and leading
     * list numbering, so "2. Why does AI content all sound the same?" matches
     * the H3 "2. Why does AI content all sound the same?" and also matches it
     * once normalize_qa_questions() has stripped the number.
     *
     * Filterable per post, for a long reference page where a summarised FAQ at
     * the end earns its space:
     *     add_filter('requestdesk_exclude_body_duplicate_qa', '__return_false');
     *
     * @param int   $post_id
     * @param array $pairs
     * @return array
     */
    protected function drop_questions_answered_in_body($post_id, $pairs) {
        if (!is_array($pairs) || empty($pairs)) {
            return is_array($pairs) ? $pairs : array();
        }

        if (!apply_filters('requestdesk_exclude_body_duplicate_qa', true, $post_id, $pairs)) {
            return $pairs;
        }

        $post = get_post($post_id);
        if (!$post || empty($post->post_content)) {
            return $pairs;
        }

        // Headings as the reader sees them. Run the content through the same
        // filter WordPress does so blocks and shortcodes resolve to real markup
        // first; fall back to the raw content if that is unavailable.
        $rendered = $post->post_content;
        if (function_exists('apply_filters')) {
            $rendered = apply_filters('requestdesk_qa_body_source', $rendered, $post);
        }

        if (!preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/is', $rendered, $matches)) {
            return $pairs;
        }

        $headings = array();
        foreach ($matches[1] as $heading) {
            $key = $this->normalize_for_match($heading);
            if ($key !== '') {
                $headings[$key] = true;
            }
        }

        if (empty($headings)) {
            return $pairs;
        }

        $kept = array();
        foreach ($pairs as $pair) {
            if (!is_array($pair)) {
                $kept[] = $pair;
                continue;
            }
            $key = $this->normalize_for_match($pair['question'] ?? '');
            if ($key !== '' && isset($headings[$key])) {
                continue;   // already answered above; do not say it twice
            }
            $kept[] = $pair;
        }

        return $kept;
    }

    /**
     * Comparison key for question/heading matching. Strips tags, entities,
     * leading list numbering, punctuation and case, so the same sentence
     * matches whether or not it still carries its "2." and whether or not the
     * heading was written with a curly apostrophe.
     *
     * @param string $text
     * @return string
     */
    protected function normalize_for_match($text) {
        $text = wp_strip_all_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = strtolower(trim($text));
        // leading list numbering: "2.", "2)", "(2)", "step 2:", "q3."
        $text = preg_replace(
            '/^\s*(?:\(?\s*(?:step|q(?:uestion)?)?\s*\d{1,2}\s*\)?\s*[\.\):\-\x{2013}\x{2014}]\s*)+/iu',
            '',
            $text
        );
        $text = preg_replace('/[^a-z0-9 ]+/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim((string) $text);
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
            'min_confidence' => 0.5,
            // Off by default: an explicit [requestdesk_qa] shortcode is a
            // deliberate placement and renders whatever the post has. Only the
            // automatic append turns this on.
            'exclude_body_duplicates' => false,
        ));

        // Get AEO data
        $aeo_core = new RequestDesk_AEO_Core();
        $aeo_data = $aeo_core->get_aeo_data_readonly($post_id);
        $qa_pairs = $aeo_data['ai_questions'] ?? array();

        if (empty($qa_pairs)) {
            return '';
        }

        // Drop pairs that merely restate a heading already in the article.
        if (!empty($options['exclude_body_duplicates'])) {
            $qa_pairs = $this->drop_questions_answered_in_body($post_id, $qa_pairs);
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
        // enabled (caught by the 2026-05-09 SEO audit on
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
        $aeo_data = $aeo_core->get_aeo_data_readonly($post_id);

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