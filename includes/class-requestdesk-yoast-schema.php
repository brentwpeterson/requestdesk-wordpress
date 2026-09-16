<?php
/**
 * RequestDesk Yoast SEO schema compatibility
 *
 * On a site that runs Yoast SEO (free or Premium), Yoast prints one
 * application/ld+json block holding a connected @graph (WebPage, WebSite,
 * Organization, Article, BreadcrumbList ...). The connector used to print its
 * own standalone ld+json blocks next to it from wp_head: FAQPage on posts and
 * pages, ProfessionalService on the front page, Article on case studies. That
 * leaves a second, disconnected schema graph on the page (t2373).
 *
 * With Yoast active the connector always works inside Yoast's single graph:
 *
 *   - FAQPage is added INTO Yoast's graph as its own node through the
 *     `wpseo_schema_graph_pieces` filter, @id "<permalink>#requestdesk-faq",
 *     linked to Yoast's WebPage node with isPartOf. Skipped when the post
 *     already carries a Yoast FAQ block, because Yoast then marks the WebPage
 *     itself as FAQPage and a second FAQPage would be a duplicate.
 *   - Case study Article (site-module installs only) is added the same way,
 *     @id "<permalink>#requestdesk-case-study", when Yoast is not already
 *     printing an Article for that post type. When Yoast IS printing one, the
 *     case study's `about` and `review` are merged into Yoast's Article.
 *
 * Who wins where both describe the same thing is a setting,
 * requestdesk_aeo_settings[yoast_mode]:
 *
 *   'requestdesk' (default, 2.47.0) RequestDesk wins. The site's Organization
 *       and WebSite nodes carry RequestDesk's name, description, logo and
 *       social profiles; on site-module installs the Organization also carries
 *       the ProfessionalService type and service catalog; the case study's
 *       about / review replace Yoast's. Meta tags follow the same rule, see
 *       RequestDesk_Yoast_Meta.
 *   'yoast' Yoast wins. RequestDesk only adds nodes Yoast does not have
 *       (the FAQ, the case study Article) and never changes Yoast's values.
 *       This is the 2.46.0 behavior.
 *
 * Deferral only happens on a request where Yoast actually built its graph
 * (the pieces filter fired before the connector's wp_head output ran; Yoast
 * prints at wp_head priority 1, the connector at 10). If Yoast's schema output
 * is switched off for a request, nothing is printed by Yoast, so the
 * connector's standalone blocks are printed as before and nothing is lost.
 *
 * When Yoast is not active none of the filters here ever fire and every
 * should_skip_standalone() check returns false, so output is unchanged.
 *
 * @package RequestDesk
 * @since 2.46.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Yoast_Schema {

    /** @var bool Hooks registered for this request. */
    private static $registered = false;

    /** @var bool Yoast built its schema graph on this request. */
    private static $graph_built = false;

    /**
     * Register the Yoast filters once per request.
     */
    public static function register() {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_filter('wpseo_schema_graph_pieces', array(__CLASS__, 'add_graph_pieces'), 11, 2);
        add_filter('wpseo_schema_article', array(__CLASS__, 'merge_case_study_into_article'), 20, 2);
        add_filter('wpseo_schema_organization', array(__CLASS__, 'apply_organization'), 20, 2);
        add_filter('wpseo_schema_website', array(__CLASS__, 'apply_website'), 20, 2);
    }

    /**
     * Whether Yoast SEO (free or Premium) is loaded.
     *
     * Same test as RequestDesk_SEO_Core::detect_yoast(), evaluated at call
     * time rather than at plugin load, because the connector loads before
     * wordpress-seo alphabetically.
     */
    public static function is_yoast_active() {
        return defined('WPSEO_VERSION') || class_exists('WPSEO_Options');
    }

    /**
     * Who wins when RequestDesk and Yoast both describe the same thing.
     *
     * @return string 'requestdesk' (default) or 'yoast'.
     */
    public static function mode() {
        $settings = get_option('requestdesk_aeo_settings', array());
        if (is_array($settings) && isset($settings['yoast_mode']) && $settings['yoast_mode'] === 'yoast') {
            return 'yoast';
        }
        return 'requestdesk';
    }

    /**
     * Whether RequestDesk's values replace Yoast's on this request.
     */
    public static function requestdesk_wins() {
        return self::is_yoast_active() && self::mode() === 'requestdesk';
    }

    /**
     * Whether the connector's standalone wp_head ld+json output should be
     * skipped on this request: Yoast is active AND it built its graph.
     */
    public static function should_skip_standalone() {
        return self::$graph_built && self::is_yoast_active();
    }

    /**
     * wpseo_schema_graph_pieces callback.
     *
     * @param array  $pieces  Yoast schema pieces.
     * @param object $context Yoast Meta_Tags_Context.
     * @return array
     */
    public static function add_graph_pieces($pieces, $context) {
        if (!is_array($pieces)) {
            return $pieces;
        }

        // Yoast is building a graph for this request.
        self::$graph_built = true;

        if (!class_exists('Yoast\\WP\\SEO\\Generators\\Schema\\Abstract_Schema_Piece')) {
            // Yoast too old for the pieces API. Report loudly and leave the
            // standalone output in place rather than dropping the schema.
            self::$graph_built = false;
            error_log('RequestDesk: Yoast schema piece API not found; printing standalone schema instead.');
            return $pieces;
        }

        require_once REQUESTDESK_PLUGIN_DIR . 'includes/class-requestdesk-yoast-schema-piece.php';

        $pieces[] = new RequestDesk_Yoast_Schema_Piece('requestdesk_faq', array(__CLASS__, 'build_faq_node'));
        $pieces[] = new RequestDesk_Yoast_Schema_Piece('requestdesk_case_study', array(__CLASS__, 'build_case_study_node'));

        return $pieces;
    }

    /**
     * Build the FAQPage node for Yoast's graph, or null when none applies.
     *
     * Reads the same stored faq_data and applies the same question-name
     * cleanup as the standalone emitter, so the questions match exactly.
     *
     * @param object $context Yoast Meta_Tags_Context.
     * @return array|null
     */
    public static function build_faq_node($context) {
        if (!is_single() && !is_page()) {
            return null;
        }

        // A Yoast FAQ block already turns the WebPage node into an FAQPage.
        if (!empty($context->blocks['yoast/faq-block'])) {
            return null;
        }

        if (!class_exists('RequestDesk_AEO_Core')) {
            return null;
        }

        $post_id = get_queried_object_id();
        $aeo_core = new RequestDesk_AEO_Core(false);
        $faq = $aeo_core->get_clean_faq_schema($post_id);

        if (empty($faq) || !is_array($faq) || empty($faq['mainEntity'])) {
            return null;
        }

        $page_id = self::main_schema_id($context, $post_id);

        unset($faq['@context']);
        $node = array(
            '@type' => 'FAQPage',
            '@id' => $page_id . '#requestdesk-faq',
            'isPartOf' => array('@id' => $page_id),
        );
        foreach ($faq as $key => $value) {
            if (!array_key_exists($key, $node)) {
                $node[$key] = $value;
            }
        }

        return $node;
    }

    /**
     * Build the case study Article node for Yoast's graph, or null.
     *
     * @param object $context Yoast Meta_Tags_Context.
     * @return array|null
     */
    public static function build_case_study_node($context) {
        if (!class_exists('RequestDesk_Case_Study') || !is_singular('cc_case_study')) {
            return null;
        }

        // Yoast is already printing an Article for this post type; its about
        // and review are merged there by merge_case_study_into_article().
        if (!empty($context->has_article)) {
            return null;
        }

        $post_id = get_queried_object_id();
        $schema = RequestDesk_Case_Study::build_schema($post_id);
        if (empty($schema)) {
            return null;
        }

        $page_id = self::main_schema_id($context, $post_id);

        unset($schema['@context']);
        $schema['@id'] = $page_id . '#requestdesk-case-study';
        $schema['isPartOf'] = array('@id' => $page_id);
        $schema['mainEntityOfPage'] = array('@id' => $page_id);

        return $schema;
    }

    /**
     * wpseo_schema_article callback: when Yoast prints the Article for a case
     * study, carry over the connector's about / review. RequestDesk wins
     * replaces Yoast's values; Yoast wins only fills what Yoast left empty.
     *
     * @param array  $data    Yoast Article node.
     * @param object $context Yoast Meta_Tags_Context.
     * @return array
     */
    public static function merge_case_study_into_article($data, $context = null) {
        if (!is_array($data) || !self::is_yoast_active()) {
            return $data;
        }
        if (!class_exists('RequestDesk_Case_Study') || !is_singular('cc_case_study')) {
            return $data;
        }

        $wins = self::requestdesk_wins();
        $schema = RequestDesk_Case_Study::build_schema(get_queried_object_id());
        foreach (array('about', 'review') as $key) {
            if (!empty($schema[$key]) && ($wins || !isset($data[$key]))) {
                $data[$key] = $schema[$key];
            }
        }

        return $data;
    }

    /**
     * wpseo_schema_organization callback: RequestDesk's site identity replaces
     * Yoast's so a page describes the business one way.
     *
     * Name and description come from the WordPress site title and tagline (the
     * same source as RequestDesk's standalone Organization schema). Social
     * profiles are merged, RequestDesk's first. On site-module installs the
     * node also carries ProfessionalService and the service catalog that
     * used to print as a separate front-page block, so that block's content
     * is no longer lost, and no second organization entity exists.
     *
     * @param array  $data    Yoast Organization node.
     * @param object $context Yoast Meta_Tags_Context.
     * @return array
     */
    public static function apply_organization($data, $context = null) {
        if (!is_array($data) || !self::requestdesk_wins() || !class_exists('RequestDesk_Schema_Generator')) {
            return $data;
        }

        $generator = new RequestDesk_Schema_Generator();
        $org = $generator->generate_organization_schema();

        if (!empty($org['name'])) {
            $data['name'] = $org['name'];
        }
        if (!empty($org['description'])) {
            $data['description'] = $org['description'];
        }
        if (!empty($org['sameAs'])) {
            $existing = isset($data['sameAs']) ? (array) $data['sameAs'] : array();
            $data['sameAs'] = array_values(array_unique(array_merge($org['sameAs'], $existing)));
        }
        if (empty($data['logo']) && !empty($org['logo'])) {
            $data['logo'] = $org['logo'];
        }

        if (function_exists('requestdesk_is_cc_site') && requestdesk_is_cc_site()) {
            $service = $generator->generate_professional_service_schema();
            $types = isset($data['@type']) ? (array) $data['@type'] : array('Organization');
            if (!in_array('ProfessionalService', $types, true)) {
                $types[] = 'ProfessionalService';
            }
            $data['@type'] = array_values($types);
            foreach (array('knowsAbout', 'hasOfferCatalog') as $key) {
                if (!empty($service[$key])) {
                    $data[$key] = $service[$key];
                }
            }
        }

        return $data;
    }

    /**
     * wpseo_schema_website callback: RequestDesk's site name and tagline.
     *
     * @param array  $data    Yoast WebSite node.
     * @param object $context Yoast Meta_Tags_Context.
     * @return array
     */
    public static function apply_website($data, $context = null) {
        if (!is_array($data) || !self::requestdesk_wins()) {
            return $data;
        }
        $name = get_bloginfo('name');
        $description = get_bloginfo('description');
        if ($name !== '') {
            $data['name'] = $name;
        }
        if ($description !== '') {
            $data['description'] = $description;
        }
        return $data;
    }

    /**
     * Yoast's WebPage @id (the permalink), with the WordPress permalink as the
     * fallback for Yoast versions without main_schema_id.
     */
    private static function main_schema_id($context, $post_id) {
        if (is_object($context) && !empty($context->main_schema_id)) {
            return $context->main_schema_id;
        }
        return get_permalink($post_id);
    }
}
