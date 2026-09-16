<?php
/**
 * RequestDesk wins over Yoast SEO for head meta tags
 *
 * With Yoast SEO active and requestdesk_aeo_settings[yoast_mode] set to
 * 'requestdesk' (the default), the per-post SEO values RequestDesk stores
 * replace Yoast's in the tags Yoast prints. Yoast still prints each tag exactly
 * once, so there are no duplicates, and Yoast keeps covering everything
 * RequestDesk has no value for (archives, the home page, fields left empty).
 *
 * Order for each tag on a single post, page or custom post:
 *
 *   title             _requestdesk_seo_title   -> Yoast
 *   meta description  _requestdesk_seo_description -> Yoast -> excerpt
 *   canonical, og:url _requestdesk_canonical_url -> Yoast
 *   robots            _requestdesk_noindex / _requestdesk_nofollow add a
 *                     restriction; RequestDesk never lifts a noindex another
 *                     plugin or the theme set
 *   og:title          _requestdesk_og_title -> RequestDesk SEO title -> Yoast
 *   og:description    _requestdesk_og_description -> RequestDesk description
 *                     -> Yoast -> excerpt
 *   og:image          _requestdesk_og_image -> Yoast
 *   twitter:*         _requestdesk_twitter_title / _description / _image -> Yoast
 *
 * Stored values may carry Yoast-style %%variables%% (they were imported from
 * Yoast by RequestDesk_Yoast_Importer), so they go through Yoast's own
 * variable replacement.
 *
 * With yoast_mode 'yoast', or with Yoast inactive, nothing here changes output.
 *
 * @package RequestDesk
 * @since 2.47.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Yoast_Meta {

    /** @var bool */
    private static $registered = false;

    /**
     * Register the Yoast filters once per request.
     */
    public static function register() {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_filter('wpseo_title', array(__CLASS__, 'filter_title'), 20);
        add_filter('wpseo_metadesc', array(__CLASS__, 'filter_description'), 20);
        add_filter('wpseo_canonical', array(__CLASS__, 'filter_canonical'), 20);
        add_filter('wpseo_opengraph_url', array(__CLASS__, 'filter_canonical'), 20);
        add_filter('wpseo_robots_array', array(__CLASS__, 'filter_robots'), 20);
        add_filter('wpseo_opengraph_title', array(__CLASS__, 'filter_og_title'), 20);
        add_filter('wpseo_opengraph_desc', array(__CLASS__, 'filter_og_description'), 20);
        add_filter('wpseo_opengraph_image', array(__CLASS__, 'filter_og_image'), 20);
        add_action('wpseo_add_opengraph_additional_images', array(__CLASS__, 'add_og_image'), 20);
        add_filter('wpseo_twitter_title', array(__CLASS__, 'filter_twitter_title'), 20);
        add_filter('wpseo_twitter_description', array(__CLASS__, 'filter_twitter_description'), 20);
        add_filter('wpseo_twitter_image', array(__CLASS__, 'filter_twitter_image'), 20);
    }

    /**
     * The post whose values apply, or 0 when RequestDesk does not win here.
     */
    private static function post_id() {
        if (!class_exists('RequestDesk_Yoast_Schema') || !RequestDesk_Yoast_Schema::requestdesk_wins()) {
            return 0;
        }
        if (!is_singular()) {
            return 0;
        }
        return (int) get_queried_object_id();
    }

    /**
     * A stored RequestDesk value with Yoast variables replaced, or ''.
     */
    private static function value($post_id, $key) {
        $raw = get_post_meta($post_id, $key, true);
        if (!is_string($raw) || trim($raw) === '') {
            return '';
        }
        if (strpos($raw, '%%') !== false && function_exists('wpseo_replace_vars')) {
            $raw = wpseo_replace_vars($raw, get_post($post_id));
        }
        return trim(wp_strip_all_tags($raw));
    }

    /**
     * Same excerpt fallback RequestDesk_SEO_Core uses.
     */
    private static function excerpt($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return '';
        }
        $text = $post->post_excerpt;
        if ($text === '') {
            $text = wp_trim_words(strip_shortcodes(wp_strip_all_tags($post->post_content)), 25, '...');
        }
        return trim(wp_strip_all_tags($text));
    }

    private static function is_empty($value) {
        return !is_string($value) || trim($value) === '';
    }

    public static function filter_title($title) {
        $post_id = self::post_id();
        if (!$post_id) {
            return $title;
        }
        $value = self::value($post_id, '_requestdesk_seo_title');
        return $value !== '' ? $value : $title;
    }

    public static function filter_description($description) {
        $post_id = self::post_id();
        if (!$post_id) {
            return $description;
        }
        $value = self::value($post_id, '_requestdesk_seo_description');
        if ($value !== '') {
            return $value;
        }
        if (!self::is_empty($description)) {
            return $description;
        }
        $excerpt = self::excerpt($post_id);
        return $excerpt !== '' ? $excerpt : $description;
    }

    public static function filter_canonical($canonical) {
        $post_id = self::post_id();
        if (!$post_id) {
            return $canonical;
        }
        $value = get_post_meta($post_id, '_requestdesk_canonical_url', true);
        return (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) ? $value : $canonical;
    }

    public static function filter_robots($robots) {
        $post_id = self::post_id();
        if (!$post_id || !is_array($robots)) {
            return $robots;
        }
        if (get_post_meta($post_id, '_requestdesk_noindex', true)) {
            $robots['index'] = 'noindex';
        }
        if (get_post_meta($post_id, '_requestdesk_nofollow', true)) {
            $robots['follow'] = 'nofollow';
        }
        return $robots;
    }

    public static function filter_og_title($title) {
        $post_id = self::post_id();
        if (!$post_id) {
            return $title;
        }
        foreach (array('_requestdesk_og_title', '_requestdesk_seo_title') as $key) {
            $value = self::value($post_id, $key);
            if ($value !== '') {
                return $value;
            }
        }
        return $title;
    }

    public static function filter_og_description($description) {
        $post_id = self::post_id();
        if (!$post_id) {
            return $description;
        }
        foreach (array('_requestdesk_og_description', '_requestdesk_seo_description') as $key) {
            $value = self::value($post_id, $key);
            if ($value !== '') {
                return $value;
            }
        }
        if (!self::is_empty($description)) {
            return $description;
        }
        $excerpt = self::excerpt($post_id);
        return $excerpt !== '' ? $excerpt : $description;
    }

    private static function og_image_url($post_id) {
        $value = get_post_meta($post_id, '_requestdesk_og_image', true);
        return (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) ? $value : '';
    }

    /**
     * Replace the image Yoast chose. Yoast runs this per image, so only the
     * first one is replaced; the rest are left alone.
     */
    public static function filter_og_image($url) {
        static $done = false;
        $post_id = self::post_id();
        if (!$post_id || $done) {
            return $url;
        }
        $value = self::og_image_url($post_id);
        if ($value === '') {
            return $url;
        }
        $done = true;
        return $value;
    }

    /**
     * When Yoast found no image of its own, add RequestDesk's.
     *
     * @param object $container Yoast Images helper container.
     */
    public static function add_og_image($container) {
        $post_id = self::post_id();
        if (!$post_id || !is_object($container)) {
            return;
        }
        $value = self::og_image_url($post_id);
        if ($value === '') {
            return;
        }
        if (method_exists($container, 'has_images') && $container->has_images()) {
            return;
        }
        if (method_exists($container, 'add_image_by_url')) {
            $container->add_image_by_url($value);
        }
    }

    public static function filter_twitter_title($title) {
        $post_id = self::post_id();
        if (!$post_id) {
            return $title;
        }
        $value = self::value($post_id, '_requestdesk_twitter_title');
        return $value !== '' ? $value : $title;
    }

    public static function filter_twitter_description($description) {
        $post_id = self::post_id();
        if (!$post_id) {
            return $description;
        }
        $value = self::value($post_id, '_requestdesk_twitter_description');
        return $value !== '' ? $value : $description;
    }

    public static function filter_twitter_image($url) {
        $post_id = self::post_id();
        if (!$post_id) {
            return $url;
        }
        $value = get_post_meta($post_id, '_requestdesk_twitter_image', true);
        return (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) ? $value : $url;
    }
}
