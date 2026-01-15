<?php
/**
 * RequestDesk SEO Core Class
 *
 * Handles traditional SEO features: meta titles, descriptions, canonical URLs,
 * robots meta, and Open Graph/Twitter Card support.
 *
 * This class provides Yoast-like SEO functionality to allow users to
 * phase out Yoast SEO and use RequestDesk as a complete SEO solution.
 *
 * @package RequestDesk
 * @since 2.5.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_SEO_Core {

    /**
     * SEO settings
     */
    private $settings;

    /**
     * Whether Yoast is active
     */
    private $yoast_active = false;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('requestdesk_seo_settings', $this->get_default_settings());
        $this->yoast_active = $this->detect_yoast();

        // Only initialize SEO output if enabled and Yoast is not active
        if ($this->is_seo_enabled()) {
            // Frontend meta tag output
            add_action('wp_head', array($this, 'output_meta_tags'), 1);
            add_action('wp_head', array($this, 'output_canonical'), 2);
            add_action('wp_head', array($this, 'output_robots_meta'), 3);
            add_action('wp_head', array($this, 'output_open_graph'), 4);
            add_action('wp_head', array($this, 'output_twitter_cards'), 5);

            // Filter document title
            add_filter('pre_get_document_title', array($this, 'filter_document_title'), 15);
            add_filter('document_title_parts', array($this, 'filter_document_title_parts'), 15);

            // Remove WordPress default canonical if we're outputting our own
            remove_action('wp_head', 'rel_canonical');
        }

        // Admin hooks (always register for settings)
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Get default SEO settings
     */
    public function get_default_settings() {
        return array(
            // General SEO
            'seo_enabled' => true,
            'disable_if_yoast_active' => true,
            'default_title_template' => '%%title%% %%sep%% %%sitename%%',
            'default_description_template' => '%%excerpt%%',
            'separator' => '-',

            // Social
            'og_enabled' => true,
            'twitter_enabled' => true,
            'default_og_image' => '',
            'twitter_site' => '',
            'twitter_card_type' => 'summary_large_image',

            // Robots
            'noindex_archives' => false,
            'noindex_author_archives' => true,
            'noindex_search_results' => true,
            'noindex_date_archives' => true,
        );
    }

    /**
     * Detect if Yoast SEO is active
     */
    public function detect_yoast() {
        return defined('WPSEO_VERSION') || class_exists('WPSEO_Options');
    }

    /**
     * Check if SEO features should be enabled
     */
    public function is_seo_enabled() {
        if (!($this->settings['seo_enabled'] ?? true)) {
            return false;
        }

        // Disable if Yoast is active and setting is enabled
        if ($this->yoast_active && ($this->settings['disable_if_yoast_active'] ?? true)) {
            return false;
        }

        return true;
    }

    /**
     * Register SEO settings
     */
    public function register_settings() {
        register_setting('requestdesk_seo_settings', 'requestdesk_seo_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
    }

    /**
     * Sanitize SEO settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        $sanitized['seo_enabled'] = !empty($input['seo_enabled']);
        $sanitized['disable_if_yoast_active'] = !empty($input['disable_if_yoast_active']);
        $sanitized['default_title_template'] = sanitize_text_field($input['default_title_template'] ?? '%%title%% %%sep%% %%sitename%%');
        $sanitized['default_description_template'] = sanitize_text_field($input['default_description_template'] ?? '%%excerpt%%');
        $sanitized['separator'] = sanitize_text_field($input['separator'] ?? '-');

        $sanitized['og_enabled'] = !empty($input['og_enabled']);
        $sanitized['twitter_enabled'] = !empty($input['twitter_enabled']);
        $sanitized['default_og_image'] = esc_url_raw($input['default_og_image'] ?? '');
        $sanitized['twitter_site'] = sanitize_text_field($input['twitter_site'] ?? '');
        $sanitized['twitter_card_type'] = in_array($input['twitter_card_type'] ?? '', array('summary', 'summary_large_image'))
            ? $input['twitter_card_type']
            : 'summary_large_image';

        $sanitized['noindex_archives'] = !empty($input['noindex_archives']);
        $sanitized['noindex_author_archives'] = !empty($input['noindex_author_archives']);
        $sanitized['noindex_search_results'] = !empty($input['noindex_search_results']);
        $sanitized['noindex_date_archives'] = !empty($input['noindex_date_archives']);

        return $sanitized;
    }

    /**
     * Output meta description tag
     */
    public function output_meta_tags() {
        if (!is_singular()) {
            return;
        }

        $post_id = get_queried_object_id();
        $description = $this->get_meta_description($post_id);

        if (!empty($description)) {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }
    }

    /**
     * Get meta description for a post
     */
    public function get_meta_description($post_id) {
        // First check for custom meta description
        $description = get_post_meta($post_id, '_requestdesk_seo_description', true);

        // Fall back to excerpt if no custom description
        if (empty($description)) {
            $post = get_post($post_id);
            if ($post) {
                $description = $post->post_excerpt;

                // If no excerpt, generate from content
                if (empty($description)) {
                    $description = wp_trim_words(strip_shortcodes(strip_tags($post->post_content)), 25, '...');
                }
            }
        }

        return $description;
    }

    /**
     * Get SEO title for a post
     */
    public function get_seo_title($post_id) {
        // First check for custom SEO title
        $title = get_post_meta($post_id, '_requestdesk_seo_title', true);

        // Fall back to post title if no custom title
        if (empty($title)) {
            $post = get_post($post_id);
            if ($post) {
                $title = $post->post_title;
            }
        }

        return $title;
    }

    /**
     * Filter document title
     */
    public function filter_document_title($title) {
        if (!is_singular()) {
            return $title;
        }

        $post_id = get_queried_object_id();
        $custom_title = get_post_meta($post_id, '_requestdesk_seo_title', true);

        if (!empty($custom_title)) {
            return $this->process_title_template($custom_title, $post_id);
        }

        return $title;
    }

    /**
     * Filter document title parts
     */
    public function filter_document_title_parts($title_parts) {
        if (!is_singular()) {
            return $title_parts;
        }

        $post_id = get_queried_object_id();
        $custom_title = get_post_meta($post_id, '_requestdesk_seo_title', true);

        if (!empty($custom_title)) {
            $title_parts['title'] = $custom_title;
        }

        return $title_parts;
    }

    /**
     * Process title template variables
     */
    public function process_title_template($template, $post_id = null) {
        $post = $post_id ? get_post($post_id) : get_post();
        $separator = $this->settings['separator'] ?? '-';

        $replacements = array(
            '%%title%%' => $post ? $post->post_title : '',
            '%%sitename%%' => get_bloginfo('name'),
            '%%sep%%' => $separator,
            '%%excerpt%%' => $post ? wp_trim_words($post->post_excerpt ?: strip_tags($post->post_content), 25) : '',
            '%%date%%' => $post ? get_the_date('', $post) : '',
            '%%author%%' => $post ? get_the_author_meta('display_name', $post->post_author) : '',
            '%%category%%' => $this->get_primary_category($post_id),
        );

        $title = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Clean up multiple separators or spaces
        $title = preg_replace('/\s+/', ' ', $title);
        $title = trim($title, ' ' . $separator);

        return $title;
    }

    /**
     * Get primary category for a post
     */
    private function get_primary_category($post_id) {
        $categories = get_the_category($post_id);
        if (!empty($categories)) {
            return $categories[0]->name;
        }
        return '';
    }

    /**
     * Output canonical URL
     */
    public function output_canonical() {
        if (!is_singular()) {
            // Output canonical for archives if needed
            echo '<link rel="canonical" href="' . esc_url($this->get_current_url()) . '">' . "\n";
            return;
        }

        $post_id = get_queried_object_id();
        $canonical = get_post_meta($post_id, '_requestdesk_canonical_url', true);

        // Default to permalink if no custom canonical
        if (empty($canonical)) {
            $canonical = get_permalink($post_id);
        }

        if (!empty($canonical)) {
            echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
        }
    }

    /**
     * Get current URL
     */
    private function get_current_url() {
        global $wp;
        return home_url(add_query_arg(array(), $wp->request));
    }

    /**
     * Output robots meta tags
     */
    public function output_robots_meta() {
        $robots = array();

        // Check for per-post settings
        if (is_singular()) {
            $post_id = get_queried_object_id();

            if (get_post_meta($post_id, '_requestdesk_noindex', true)) {
                $robots[] = 'noindex';
            }

            if (get_post_meta($post_id, '_requestdesk_nofollow', true)) {
                $robots[] = 'nofollow';
            }
        }

        // Check for archive settings
        if (is_search() && ($this->settings['noindex_search_results'] ?? true)) {
            $robots[] = 'noindex';
        }

        if (is_author() && ($this->settings['noindex_author_archives'] ?? true)) {
            $robots[] = 'noindex';
        }

        if (is_date() && ($this->settings['noindex_date_archives'] ?? true)) {
            $robots[] = 'noindex';
        }

        if ((is_category() || is_tag()) && ($this->settings['noindex_archives'] ?? false)) {
            $robots[] = 'noindex';
        }

        // Output robots meta if we have directives
        if (!empty($robots)) {
            $robots = array_unique($robots);
            echo '<meta name="robots" content="' . esc_attr(implode(', ', $robots)) . '">' . "\n";
        }
    }

    /**
     * Output Open Graph meta tags
     */
    public function output_open_graph() {
        if (!($this->settings['og_enabled'] ?? true)) {
            return;
        }

        if (!is_singular()) {
            return;
        }

        $post_id = get_queried_object_id();
        $post = get_post($post_id);

        // og:type
        echo '<meta property="og:type" content="article">' . "\n";

        // og:title
        $og_title = get_post_meta($post_id, '_requestdesk_og_title', true);
        if (empty($og_title)) {
            $og_title = $this->get_seo_title($post_id);
        }
        if (!empty($og_title)) {
            echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";
        }

        // og:description
        $og_description = get_post_meta($post_id, '_requestdesk_og_description', true);
        if (empty($og_description)) {
            $og_description = $this->get_meta_description($post_id);
        }
        if (!empty($og_description)) {
            echo '<meta property="og:description" content="' . esc_attr($og_description) . '">' . "\n";
        }

        // og:url
        $canonical = get_post_meta($post_id, '_requestdesk_canonical_url', true);
        if (empty($canonical)) {
            $canonical = get_permalink($post_id);
        }
        echo '<meta property="og:url" content="' . esc_url($canonical) . '">' . "\n";

        // og:site_name
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";

        // og:image
        $og_image = get_post_meta($post_id, '_requestdesk_og_image', true);
        if (empty($og_image)) {
            // Fall back to featured image
            $og_image = get_the_post_thumbnail_url($post_id, 'large');
        }
        if (empty($og_image)) {
            // Fall back to default OG image
            $og_image = $this->settings['default_og_image'] ?? '';
        }
        if (!empty($og_image)) {
            echo '<meta property="og:image" content="' . esc_url($og_image) . '">' . "\n";
        }

        // Article-specific meta
        if ($post) {
            echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c', $post)) . '">' . "\n";
            echo '<meta property="article:modified_time" content="' . esc_attr(get_the_modified_date('c', $post)) . '">' . "\n";
        }
    }

    /**
     * Output Twitter Card meta tags
     */
    public function output_twitter_cards() {
        if (!($this->settings['twitter_enabled'] ?? true)) {
            return;
        }

        if (!is_singular()) {
            return;
        }

        $post_id = get_queried_object_id();

        // twitter:card
        $card_type = $this->settings['twitter_card_type'] ?? 'summary_large_image';
        echo '<meta name="twitter:card" content="' . esc_attr($card_type) . '">' . "\n";

        // twitter:site
        $twitter_site = $this->settings['twitter_site'] ?? '';
        if (!empty($twitter_site)) {
            echo '<meta name="twitter:site" content="' . esc_attr($twitter_site) . '">' . "\n";
        }

        // twitter:title
        $twitter_title = get_post_meta($post_id, '_requestdesk_twitter_title', true);
        if (empty($twitter_title)) {
            $twitter_title = $this->get_seo_title($post_id);
        }
        if (!empty($twitter_title)) {
            echo '<meta name="twitter:title" content="' . esc_attr($twitter_title) . '">' . "\n";
        }

        // twitter:description
        $twitter_description = get_post_meta($post_id, '_requestdesk_twitter_description', true);
        if (empty($twitter_description)) {
            $twitter_description = $this->get_meta_description($post_id);
        }
        if (!empty($twitter_description)) {
            echo '<meta name="twitter:description" content="' . esc_attr($twitter_description) . '">' . "\n";
        }

        // twitter:image
        $twitter_image = get_post_meta($post_id, '_requestdesk_twitter_image', true);
        if (empty($twitter_image)) {
            // Fall back to featured image
            $twitter_image = get_the_post_thumbnail_url($post_id, 'large');
        }
        if (empty($twitter_image)) {
            // Fall back to default OG image
            $twitter_image = $this->settings['default_og_image'] ?? '';
        }
        if (!empty($twitter_image)) {
            echo '<meta name="twitter:image" content="' . esc_url($twitter_image) . '">' . "\n";
        }
    }

    /**
     * Get focus keyphrase for a post
     */
    public function get_focus_keyphrase($post_id) {
        return get_post_meta($post_id, '_requestdesk_focus_keyphrase', true);
    }

    /**
     * Analyze focus keyphrase usage in content
     */
    public function analyze_keyphrase($post_id, $keyphrase = null) {
        if (empty($keyphrase)) {
            $keyphrase = $this->get_focus_keyphrase($post_id);
        }

        if (empty($keyphrase)) {
            return array(
                'score' => 0,
                'keyphrase' => '',
                'checks' => array()
            );
        }

        $post = get_post($post_id);
        if (!$post) {
            return array(
                'score' => 0,
                'keyphrase' => $keyphrase,
                'checks' => array()
            );
        }

        $keyphrase_lower = strtolower($keyphrase);
        $title = strtolower($post->post_title);
        $content = strtolower(strip_tags($post->post_content));
        $meta_description = strtolower($this->get_meta_description($post_id));
        $seo_title = strtolower($this->get_seo_title($post_id));

        $checks = array();
        $score = 0;

        // Check in SEO title (20 points)
        $in_seo_title = strpos($seo_title, $keyphrase_lower) !== false;
        $checks['seo_title'] = array(
            'passed' => $in_seo_title,
            'message' => $in_seo_title
                ? 'Focus keyphrase appears in SEO title'
                : 'Focus keyphrase does not appear in SEO title',
            'weight' => 20
        );
        if ($in_seo_title) $score += 20;

        // Check in post title (15 points)
        $in_title = strpos($title, $keyphrase_lower) !== false;
        $checks['title'] = array(
            'passed' => $in_title,
            'message' => $in_title
                ? 'Focus keyphrase appears in post title'
                : 'Focus keyphrase does not appear in post title',
            'weight' => 15
        );
        if ($in_title) $score += 15;

        // Check in meta description (15 points)
        $in_meta = strpos($meta_description, $keyphrase_lower) !== false;
        $checks['meta_description'] = array(
            'passed' => $in_meta,
            'message' => $in_meta
                ? 'Focus keyphrase appears in meta description'
                : 'Focus keyphrase does not appear in meta description',
            'weight' => 15
        );
        if ($in_meta) $score += 15;

        // Check in first paragraph (15 points)
        $paragraphs = explode("\n\n", $content);
        $first_para = isset($paragraphs[0]) ? $paragraphs[0] : '';
        $in_first_para = strpos($first_para, $keyphrase_lower) !== false;
        $checks['first_paragraph'] = array(
            'passed' => $in_first_para,
            'message' => $in_first_para
                ? 'Focus keyphrase appears in the first paragraph'
                : 'Focus keyphrase does not appear in the first paragraph',
            'weight' => 15
        );
        if ($in_first_para) $score += 15;

        // Check in headings (15 points)
        preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $post->post_content, $headings);
        $in_headings = false;
        if (!empty($headings[1])) {
            foreach ($headings[1] as $heading) {
                if (strpos(strtolower(strip_tags($heading)), $keyphrase_lower) !== false) {
                    $in_headings = true;
                    break;
                }
            }
        }
        $checks['headings'] = array(
            'passed' => $in_headings,
            'message' => $in_headings
                ? 'Focus keyphrase appears in a heading'
                : 'Focus keyphrase does not appear in any headings',
            'weight' => 15
        );
        if ($in_headings) $score += 15;

        // Check keyphrase density (10 points)
        $word_count = str_word_count($content);
        $keyphrase_count = substr_count($content, $keyphrase_lower);
        $density = $word_count > 0 ? ($keyphrase_count / $word_count) * 100 : 0;
        $good_density = $density >= 0.5 && $density <= 3;
        $checks['density'] = array(
            'passed' => $good_density,
            'message' => $good_density
                ? sprintf('Focus keyphrase density is %.1f%% (good)', $density)
                : sprintf('Focus keyphrase density is %.1f%% (aim for 0.5-3%%)', $density),
            'weight' => 10,
            'value' => $density
        );
        if ($good_density) $score += 10;

        // Check URL/slug (10 points)
        $slug = $post->post_name;
        $keyphrase_slug = sanitize_title($keyphrase);
        $in_slug = strpos($slug, $keyphrase_slug) !== false || strpos($slug, str_replace('-', '', $keyphrase_slug)) !== false;
        $checks['url'] = array(
            'passed' => $in_slug,
            'message' => $in_slug
                ? 'Focus keyphrase appears in the URL'
                : 'Focus keyphrase does not appear in the URL',
            'weight' => 10
        );
        if ($in_slug) $score += 10;

        return array(
            'score' => $score,
            'keyphrase' => $keyphrase,
            'checks' => $checks,
            'density' => $density,
            'occurrences' => $keyphrase_count
        );
    }

    /**
     * Check if Yoast is active (public method for other classes)
     */
    public function is_yoast_active() {
        return $this->yoast_active;
    }

    /**
     * Get settings (public method for other classes)
     */
    public function get_settings() {
        return $this->settings;
    }
}
