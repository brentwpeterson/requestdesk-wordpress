<?php
/**
 * RequestDesk Research Library CPT (cc_research)
 *
 * Content Cucumber's studies and reports: one post per study, trends report,
 * platform comparison, buyer's guide or benchmark. Replaces the hand-edited
 * /research/ pillar JSON page and the seven "research twin" Guide pages.
 *
 * Pieces:
 *   - cc_research post type, research_type + research_topic taxonomies
 *     (no term archives: filters are query vars on the one archive)
 *   - one field registry (fields()) that drives meta registration, the
 *     edit-screen meta box, the save handler, the importer and REST
 *   - Report JSON-LD (Dataset only when a public data file exists),
 *     CollectionPage + ItemList on the archive
 *   - JSON inbox importer (uploads/requestdesk/research/) and an upsert
 *     REST route, POST requestdesk/v1/research, on the main API key
 *   - routing switch: "staged" serves /research-library/<slug>/ with noindex
 *     while the old /research/ pages stay live; "live" takes over /research/
 *
 * Study numbers are a dated snapshot (snapshot_date + edition). Nothing here
 * calls the RequestDesk studies API at render time.
 *
 * Loaded only on Content Cucumber installs ($cc_only_files).
 *
 * @package RequestDesk
 * @since 2.69.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Research {

    const CPT            = 'cc_research';
    const TAX_TYPE       = 'research_type';
    const TAX_TOPIC      = 'research_topic';
    const ROUTING_OPTION = 'requestdesk_research_routing';
    const FLUSH_OPTION   = 'requestdesk_research_flush';
    const META_PREFIX    = '_cc_rs_';
    const HUBSPOT_PORTAL = '39487190';

    /** Band colors the theme knows how to paint. The theme maps keys to CSS. */
    const BAND_COLORS = array('ink', 'paper', 'cucumber', 'forest', 'lime', 'mist');

    /** Access modes for the download. */
    const ACCESS_MODES = array('open', 'inline_form', 'gate_page');

    /** Seed terms for research_type. */
    const TYPES = array(
        'study'               => 'Study',
        'trends-report'       => 'Trends Report',
        'platform-comparison' => 'Platform Comparison',
        'buyers-guide'        => "Buyer's Guide",
        'benchmark'           => 'Benchmark',
        'audit-checklist'     => 'Audit and Checklist',
    );

    public function __construct() {
        add_action('init', array($this, 'register_cpt'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('init', array($this, 'register_meta'));
        add_action('init', array($this, 'maybe_flush'), 99);
        add_action('admin_init', array($this, 'maybe_seed_types'));
        add_filter('term_link', array($this, 'term_link'), 10, 3);

        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . self::CPT, array($this, 'save_meta'), 10, 2);
        add_action('admin_notices', array($this, 'findings_notice'));

        add_action('pre_get_posts', array($this, 'archive_query'));
        add_filter('wp_robots', array($this, 'robots'));
        add_filter('wp_sitemaps_post_types', array($this, 'sitemap_post_types'));
        add_filter('wp_sitemaps_taxonomies', array($this, 'sitemap_taxonomies'));
        add_action('wp_head', array($this, 'output_schema'));

        // AEO Q&A, freshness, citations and the image-alt routes read this list
        // (same hook the glossary module uses). Manual Q&A survives auto-optimize.
        add_filter('requestdesk_content_post_types', array($this, 'aeo_post_types'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        add_action('admin_menu', array($this, 'add_admin_pages'));
        add_action('admin_post_requestdesk_research_routing', array($this, 'handle_routing'));
        add_action('admin_post_requestdesk_import_research', array($this, 'handle_import'));
    }

    // =========================================================================
    // ROUTING
    // =========================================================================

    /** 'staged' (default) or 'live'. */
    public static function routing() {
        return get_option(self::ROUTING_OPTION, 'staged') === 'live' ? 'live' : 'staged';
    }

    public static function is_live() {
        return self::routing() === 'live';
    }

    /** URL base for singles and the archive under the current routing. */
    public static function base() {
        return self::is_live() ? 'research' : 'research-library';
    }

    /** The archive URL, with optional filter args. */
    public static function archive_url($args = array()) {
        $url = home_url('/' . self::base() . '/');
        return $args ? add_query_arg($args, $url) : $url;
    }

    public function maybe_flush() {
        // First load of the module: record the default routing and register
        // /research-library/ rules once.
        if (get_option(self::ROUTING_OPTION) === false) {
            add_option(self::ROUTING_OPTION, 'staged', '', true);
            update_option(self::FLUSH_OPTION, 1, true);
        }
        if (get_option(self::FLUSH_OPTION)) {
            delete_option(self::FLUSH_OPTION);
            flush_rewrite_rules(false);
        }
    }

    // =========================================================================
    // REGISTRATION
    // =========================================================================
    public function register_cpt() {
        $live = self::is_live();
        register_post_type(self::CPT, array(
            'labels' => array(
                'name'               => 'Research',
                'singular_name'      => 'Research Item',
                'menu_name'          => 'Research',
                'add_new'            => 'Add New',
                'add_new_item'       => 'Add New Research Item',
                'edit_item'          => 'Edit Research Item',
                'new_item'           => 'New Research Item',
                'view_item'          => 'View Research Item',
                'search_items'       => 'Search Research',
                'not_found'          => 'No research found',
                'not_found_in_trash' => 'No research found in Trash',
                'all_items'          => 'All Research',
            ),
            'public'        => true,
            // Staged: no archive, so the existing /research/ page keeps serving.
            // Live: the archive takes /research/ and singles sit under it, which
            // is why the old research page and its seven child pages must be
            // drafted at cutover (their slugs are reused 1:1).
            'has_archive'   => $live ? 'research' : false,
            'rewrite'       => array('slug' => self::base(), 'with_front' => false),
            'supports'      => array('title', 'editor', 'thumbnail', 'excerpt', 'revisions'),
            'menu_icon'     => 'dashicons-chart-bar',
            'menu_position' => 27,
            'show_in_rest'  => true,
        ));
    }

    public function register_taxonomies() {
        // No rewrite: a term archive at /research/<term>/ would collide with
        // single slugs. Filtering is ?research_type=&research_topic= on the
        // one archive, so the query vars stay on.
        register_taxonomy(self::TAX_TYPE, self::CPT, array(
            'labels'            => array('name' => 'Research Types', 'singular_name' => 'Research Type', 'menu_name' => 'Types'),
            'hierarchical'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => false,
            'query_var'         => self::TAX_TYPE,
        ));
        register_taxonomy(self::TAX_TOPIC, self::CPT, array(
            'labels'            => array('name' => 'Research Topics', 'singular_name' => 'Research Topic', 'menu_name' => 'Topics'),
            'hierarchical'      => false,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => false,
            'query_var'         => self::TAX_TOPIC,
        ));
    }

    public function maybe_seed_types() {
        if (get_option('requestdesk_research_types_seeded')) return;
        foreach (self::TYPES as $slug => $name) {
            if (!term_exists($slug, self::TAX_TYPE)) {
                wp_insert_term($name, self::TAX_TYPE, array('slug' => $slug));
            }
        }
        update_option('requestdesk_research_types_seeded', 1, false);
    }

    /** Terms link to the filtered archive, never to a ?taxonomy= URL. */
    public function term_link($link, $term, $taxonomy) {
        if ($taxonomy !== self::TAX_TYPE && $taxonomy !== self::TAX_TOPIC) return $link;
        return self::archive_url(array($taxonomy => $term->slug));
    }

    // =========================================================================
    // FIELD REGISTRY
    // =========================================================================

    /**
     * Every field, keyed by its import/REST name. Meta key is META_PREFIX.key.
     * type: text | textarea | html | url | date | int | bool | select | lines | json
     * "lines" fields store an array parsed from one row per line, columns split
     * on " | " (see columns).
     */
    public static function fields() {
        return array(
            'edition'          => array('label' => 'Edition', 'type' => 'text', 'box' => 'study', 'help' => 'e.g. "Edition 1". Studies only.'),
            'snapshot_date'    => array('label' => 'Data as of (snapshot date)', 'type' => 'date', 'box' => 'study', 'help' => 'Required for studies. Every number on the page is as of this date.'),
            'study_slug'       => array('label' => 'RequestDesk study slug', 'type' => 'text', 'box' => 'study', 'help' => 'Slug in the RequestDesk studies collection, e.g. ai-content-study.'),
            'study_snapshot'   => array('label' => 'Frozen study summary (JSON)', 'type' => 'json', 'box' => 'study', 'help' => 'Output of study-db.py summary at the snapshot date. Stored, never fetched live.'),
            'methodology'      => array('label' => 'Methodology', 'type' => 'html', 'box' => 'study'),
            'sample_note'      => array('label' => 'Sample note', 'type' => 'textarea', 'box' => 'study', 'help' => 'Who the answers describe. Shown in the methodology block only, never on cards.'),

            'aeo_summary'      => array('label' => '3-sentence summary', 'type' => 'textarea', 'box' => 'summary', 'help' => 'Lifted verbatim by AI search. 1) What it is. 2) How it was made. 3) The headline finding as a percentage.'),
            'findings'         => array('label' => 'Key findings', 'type' => 'lines', 'columns' => array('value', 'label', 'note'), 'box' => 'summary', 'help' => 'One per line: value | label | note. Values are percentages (e.g. 62%), never a headline count.'),

            'access'           => array('label' => 'Access', 'type' => 'select', 'options' => self::ACCESS_MODES, 'box' => 'access', 'help' => 'open = direct PDF link. inline_form = HubSpot form on the page. gate_page = link to the no-nav gate page.'),
            'pdf'              => array('label' => 'PDF (attachment ID or URL)', 'type' => 'text', 'box' => 'access'),
            'pdf_pages'        => array('label' => 'PDF pages', 'type' => 'int', 'box' => 'access'),
            'hubspot_form_id'  => array('label' => 'HubSpot form ID', 'type' => 'text', 'box' => 'access', 'help' => 'Portal ' . self::HUBSPOT_PORTAL . '.'),
            'campaign_code'    => array('label' => 'Campaign code', 'type' => 'text', 'box' => 'access', 'help' => 'Defaults to the slug.'),
            'gate_url'         => array('label' => 'Gate page URL', 'type' => 'url', 'box' => 'access'),
            'dataset_url'      => array('label' => 'Public data file URL', 'type' => 'url', 'box' => 'access', 'help' => 'Only when the data itself is published. Adds Dataset schema.'),
            'dataset_license'  => array('label' => 'Data license URL', 'type' => 'url', 'box' => 'access'),

            'date_published'   => array('label' => 'Published', 'type' => 'date', 'box' => 'people'),
            'last_updated'     => array('label' => 'Last updated', 'type' => 'date', 'box' => 'people'),
            'authors'          => array('label' => 'Authors', 'type' => 'lines', 'columns' => array('name', 'title', 'url', 'role'), 'box' => 'people', 'help' => 'One per line: name | title | url | role (author or contributor).'),
            'sponsor_name'     => array('label' => 'Partner / sponsor', 'type' => 'text', 'box' => 'people', 'help' => 'Shown as "Made possible by".'),
            'sponsor_url'      => array('label' => 'Partner URL', 'type' => 'url', 'box' => 'people'),
            'sponsor_logo'     => array('label' => 'Partner logo (attachment ID or URL)', 'type' => 'text', 'box' => 'people'),
            'related_case_studies' => array('label' => 'Related case studies (slugs)', 'type' => 'lines', 'columns' => array('slug'), 'box' => 'people'),
            'related_links'    => array('label' => 'Related episodes and links', 'type' => 'lines', 'columns' => array('label', 'url'), 'box' => 'people', 'help' => 'One per line: label | url.'),
            'video_youtube_id' => array('label' => 'YouTube video ID', 'type' => 'text', 'box' => 'people'),

            'band_color'       => array('label' => 'Band color', 'type' => 'select', 'options' => self::BAND_COLORS, 'box' => 'display'),
            'listed'           => array('label' => 'Show on the library page', 'type' => 'bool', 'box' => 'display', 'default' => '1', 'help' => 'Unchecked: the page has a URL but no card.'),
            'pinned'           => array('label' => 'Pinned (shows first)', 'type' => 'bool', 'box' => 'display'),
            'display_priority' => array('label' => 'Display priority (lower = sooner)', 'type' => 'int', 'box' => 'display'),
            'seo_title'        => array('label' => 'SEO title', 'type' => 'text', 'box' => 'display'),
            'seo_description'  => array('label' => 'SEO description', 'type' => 'textarea', 'box' => 'display'),
        );
    }

    public function register_meta() {
        foreach (self::fields() as $key => $f) {
            $is_array = ($f['type'] === 'lines');
            $args = array(
                'single'        => true,
                // Numbers register as string: an empty priority is stored as ''.
                'type'          => $is_array ? 'array' : 'string',
                'auth_callback' => function () { return current_user_can('edit_posts'); },
                'show_in_rest'  => $is_array
                    ? array('schema' => array('type' => 'array', 'items' => array('type' => 'object', 'additionalProperties' => true)))
                    : true,
            );
            register_post_meta(self::CPT, self::META_PREFIX . $key, $args);
        }
    }

    /** Parse "a | b | c" lines into rows keyed by the field's columns. */
    private static function parse_lines($text, $columns) {
        $rows = array();
        foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = array_map('trim', explode('|', $line));
            $row = array();
            foreach ($columns as $i => $col) {
                $row[$col] = sanitize_text_field($parts[$i] ?? '');
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private static function format_lines($rows, $columns) {
        if (!is_array($rows)) return '';
        $out = array();
        foreach ($rows as $row) {
            $vals = array();
            foreach ($columns as $col) $vals[] = (string) ($row[$col] ?? '');
            $out[] = rtrim(implode(' | ', $vals), ' |');
        }
        return implode("\n", $out);
    }

    /** Sanitize one incoming value for a field (from the edit form, a file or REST). */
    private static function clean_value($f, $value) {
        switch ($f['type']) {
            case 'lines':
                if (is_string($value)) return self::parse_lines($value, $f['columns']);
                $rows = array();
                foreach ((array) $value as $row) {
                    if (is_string($row)) { $row = array($f['columns'][0] => $row); }
                    if (!is_array($row)) continue;
                    $clean = array();
                    foreach ($f['columns'] as $col) $clean[$col] = sanitize_text_field((string) ($row[$col] ?? ''));
                    if (implode('', $clean) !== '') $rows[] = $clean;
                }
                return $rows;
            case 'html':
                return wp_kses_post((string) $value);
            case 'textarea':
                return sanitize_textarea_field((string) $value);
            case 'url':
                return esc_url_raw((string) $value);
            case 'int':
                return ($value === '' || $value === null) ? '' : intval($value);
            case 'bool':
                return !empty($value) && $value !== '0' ? '1' : '0';
            case 'select':
                $value = sanitize_key((string) $value);
                return in_array($value, $f['options'], true) ? $value : '';
            case 'date':
                $value = sanitize_text_field((string) $value);
                return ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) ? $value : '';
            case 'json':
                if (is_array($value)) return wp_json_encode($value);
                $value = trim((string) $value);
                if ($value === '') return '';
                return json_decode($value, true) === null ? '' : $value;
            default:
                return sanitize_text_field((string) $value);
        }
    }

    // =========================================================================
    // META BOXES
    // =========================================================================
    public function add_meta_boxes() {
        $boxes = array(
            'summary' => 'Summary and key findings',
            'study'   => 'Study data (dated snapshot)',
            'access'  => 'Download and access',
            'people'  => 'People, partner and related work',
            'display' => 'Library display and SEO',
        );
        foreach ($boxes as $box => $title) {
            add_meta_box('cc_rs_' . $box, $title, array($this, 'render_box'), self::CPT, in_array($box, array('display'), true) ? 'side' : 'normal', 'high', array('box' => $box));
        }
    }

    public function render_box($post, $meta_box) {
        $box = $meta_box['args']['box'];
        if ($box === 'summary') {
            wp_nonce_field('cc_research_meta', 'cc_research_nonce');
        }
        foreach (self::fields() as $key => $f) {
            if ($f['box'] !== $box) continue;
            $name  = 'cc_rs[' . $key . ']';
            $value = get_post_meta($post->ID, self::META_PREFIX . $key, true);
            if ($value === '' && isset($f['default']) && $post->post_status === 'auto-draft') $value = $f['default'];
            echo '<p><label><strong>' . esc_html($f['label']) . '</strong><br>';
            switch ($f['type']) {
                case 'lines':
                    echo '<textarea name="' . esc_attr($name) . '" rows="4" style="width:100%;font-family:monospace">' . esc_textarea(self::format_lines($value, $f['columns'])) . '</textarea>';
                    break;
                case 'textarea':
                case 'json':
                case 'html':
                    echo '<textarea name="' . esc_attr($name) . '" rows="' . ($f['type'] === 'textarea' ? 3 : 6) . '" style="width:100%' . ($f['type'] === 'json' ? ';font-family:monospace' : '') . '">' . esc_textarea($value) . '</textarea>';
                    break;
                case 'bool':
                    echo '<input type="hidden" name="' . esc_attr($name) . '" value="0"><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . checked($value, '1', false) . '> yes';
                    break;
                case 'select':
                    echo '<select name="' . esc_attr($name) . '"><option value="">(choose)</option>';
                    foreach ($f['options'] as $opt) echo '<option value="' . esc_attr($opt) . '"' . selected($value, $opt, false) . '>' . esc_html($opt) . '</option>';
                    echo '</select>';
                    break;
                default:
                    $type = $f['type'] === 'date' ? 'date' : ($f['type'] === 'int' ? 'number' : ($f['type'] === 'url' ? 'url' : 'text'));
                    echo '<input type="' . $type . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" style="width:100%">';
            }
            echo '</label>';
            if (!empty($f['help'])) echo '<br><span class="description">' . esc_html($f['help']) . '</span>';
            echo '</p>';
        }
    }

    public function save_meta($post_id, $post) {
        if (!isset($_POST['cc_research_nonce']) || !wp_verify_nonce($_POST['cc_research_nonce'], 'cc_research_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $incoming = isset($_POST['cc_rs']) && is_array($_POST['cc_rs']) ? wp_unslash($_POST['cc_rs']) : array();
        self::write_fields($post_id, $incoming);
    }

    /** Write the given fields (only the keys present) and record problems. */
    public static function write_fields($post_id, $values) {
        $fields = self::fields();
        foreach ($values as $key => $value) {
            if (!isset($fields[$key])) continue;
            update_post_meta($post_id, self::META_PREFIX . $key, self::clean_value($fields[$key], $value));
        }
        update_post_meta($post_id, self::META_PREFIX . 'problems', self::problems($post_id));
    }

    /**
     * Pre-ship problems for one item. Shown as an admin notice; never blocks a
     * save. Findings must be percentages (study rule: no headline counts).
     */
    public static function problems($post_id) {
        $out = array();
        $findings = get_post_meta($post_id, self::META_PREFIX . 'findings', true);
        foreach ((array) $findings as $row) {
            $v = is_array($row) ? ($row['value'] ?? '') : '';
            if ($v !== '' && strpos($v, '%') === false) {
                $out[] = 'Finding "' . $v . '" is not a percentage. Report percentages, never a headline count.';
            }
        }
        if (has_term('study', self::TAX_TYPE, $post_id) && !get_post_meta($post_id, self::META_PREFIX . 'snapshot_date', true)) {
            $out[] = 'Studies need a snapshot date ("Data as of").';
        }
        $access = get_post_meta($post_id, self::META_PREFIX . 'access', true);
        if ($access === 'open' && !self::pdf_url($post_id)) $out[] = 'Access is "open" but no PDF resolves.';
        if ($access === 'inline_form' && !get_post_meta($post_id, self::META_PREFIX . 'hubspot_form_id', true)) $out[] = 'Access is "inline_form" but no HubSpot form ID is set.';
        if ($access === 'gate_page' && !get_post_meta($post_id, self::META_PREFIX . 'gate_url', true)) $out[] = 'Access is "gate_page" but no gate page URL is set.';
        return $out;
    }

    public function findings_notice() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== self::CPT || $screen->base !== 'post') return;
        global $post;
        if (!$post) return;
        $problems = get_post_meta($post->ID, self::META_PREFIX . 'problems', true);
        if (empty($problems) || !is_array($problems)) return;
        echo '<div class="notice notice-warning"><p><strong>Research pre-ship checks:</strong></p><ul style="list-style:disc;margin-left:20px">';
        foreach ($problems as $p) echo '<li>' . esc_html($p) . '</li>';
        echo '</ul></div>';
    }

    // =========================================================================
    // READ HELPERS FOR TEMPLATES
    // =========================================================================

    /** Resolve an attachment ID or URL field to a URL. */
    private static function media_url($ref) {
        if ($ref === '' || $ref === null) return '';
        if (is_numeric($ref)) return (string) wp_get_attachment_url(intval($ref));
        return esc_url_raw($ref);
    }

    public static function pdf_url($post_id) {
        return self::media_url(get_post_meta($post_id, self::META_PREFIX . 'pdf', true));
    }

    /**
     * Everything a template needs for one item, normalized. Themes read this
     * instead of raw meta so field changes stay inside the plugin.
     */
    public static function get($post_id) {
        $post_id = (int) $post_id;
        $m = function ($k) use ($post_id) { return get_post_meta($post_id, self::META_PREFIX . $k, true); };
        $types  = wp_get_post_terms($post_id, self::TAX_TYPE);
        $topics = wp_get_post_terms($post_id, self::TAX_TOPIC);
        $type   = (!is_wp_error($types) && $types) ? $types[0] : null;
        $access = $m('access') ?: 'open';
        $listed = $m('listed');

        $related_cs = array();
        foreach ((array) $m('related_case_studies') as $row) {
            $slug = is_array($row) ? ($row['slug'] ?? '') : '';
            $p = $slug ? get_page_by_path($slug, OBJECT, 'cc_case_study') : null;
            if ($p && $p->post_status === 'publish') {
                $related_cs[] = array('title' => get_the_title($p), 'url' => get_permalink($p), 'id' => $p->ID);
            }
        }
        $snapshot = $m('study_snapshot');

        return array(
            'id'             => $post_id,
            'title'          => get_the_title($post_id),
            'url'            => get_permalink($post_id),
            'excerpt'        => get_the_excerpt($post_id),
            'cover'          => get_the_post_thumbnail_url($post_id, 'large') ?: '',
            'cover_id'       => get_post_thumbnail_id($post_id),
            'type'           => $type ? array('slug' => $type->slug, 'name' => $type->name) : null,
            'topics'         => is_wp_error($topics) ? array() : array_map(function ($t) { return array('slug' => $t->slug, 'name' => $t->name); }, $topics),
            'year'           => substr((string) ($m('date_published') ?: get_the_date('Y-m-d', $post_id)), 0, 4),
            'date_published' => $m('date_published') ?: get_the_date('Y-m-d', $post_id),
            'last_updated'   => $m('last_updated') ?: get_the_modified_date('Y-m-d', $post_id),
            'edition'        => $m('edition'),
            'snapshot_date'  => $m('snapshot_date'),
            'study_slug'     => $m('study_slug'),
            'study_snapshot' => $snapshot ? json_decode($snapshot, true) : null,
            'methodology'    => $m('methodology'),
            'sample_note'    => $m('sample_note'),
            'aeo_summary'    => $m('aeo_summary'),
            'findings'       => is_array($m('findings')) ? $m('findings') : array(),
            'access'         => in_array($access, self::ACCESS_MODES, true) ? $access : 'open',
            'pdf_url'        => self::pdf_url($post_id),
            'pdf_pages'      => $m('pdf_pages'),
            'hubspot_portal' => self::HUBSPOT_PORTAL,
            'hubspot_form_id'=> $m('hubspot_form_id'),
            'campaign_code'  => $m('campaign_code') ?: get_post_field('post_name', $post_id),
            'gate_url'       => $m('gate_url'),
            'dataset_url'    => $m('dataset_url'),
            'authors'        => is_array($m('authors')) ? $m('authors') : array(),
            'sponsor'        => $m('sponsor_name') ? array(
                'name' => $m('sponsor_name'),
                'url'  => $m('sponsor_url'),
                'logo' => self::media_url($m('sponsor_logo')),
            ) : null,
            'related_case_studies' => $related_cs,
            'related_links'  => is_array($m('related_links')) ? $m('related_links') : array(),
            'video_youtube_id' => $m('video_youtube_id'),
            'band_color'     => in_array($m('band_color'), self::BAND_COLORS, true) ? $m('band_color') : '',
            'listed'         => $listed === '' ? true : $listed === '1',
            'pinned'         => $m('pinned') === '1',
            'seo_title'      => $m('seo_title'),
            'seo_description'=> $m('seo_description'),
        );
    }

    // =========================================================================
    // ARCHIVE QUERY, ROBOTS, SITEMAP
    // =========================================================================

    /** True on a filtered archive view (?research_type= or ?research_topic=). */
    public static function is_filtered_archive() {
        return is_post_type_archive(self::CPT) && (get_query_var(self::TAX_TYPE) || get_query_var(self::TAX_TOPIC));
    }

    public function archive_query($query) {
        if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive(self::CPT)) return;

        // Listed items only. A missing key counts as listed (the default).
        $query->set('meta_query', array(
            'relation' => 'AND',
            'listed' => array(
                'relation' => 'OR',
                array('key' => self::META_PREFIX . 'listed', 'value' => '1'),
                array('key' => self::META_PREFIX . 'listed', 'compare' => 'NOT EXISTS'),
            ),
        ));
        // Order is set in archive_orderby() below.
        $query->set('posts_per_page', 24);
        add_filter('posts_orderby', array($this, 'archive_orderby'), 10, 2);
    }

    /**
     * Pinned first, then display priority (lower first, empty last), then date.
     * Done in SQL on the joined meta so pagination counts stay correct.
     */
    public function archive_orderby($orderby, $query) {
        if (!$query->is_main_query() || !$query->is_post_type_archive(self::CPT)) return $orderby;
        remove_filter('posts_orderby', array($this, 'archive_orderby'), 10);
        global $wpdb;
        $pinned = "(SELECT pm.meta_value FROM {$wpdb->postmeta} pm WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key = '" . self::META_PREFIX . "pinned' LIMIT 1)";
        $prio   = "(SELECT pm.meta_value FROM {$wpdb->postmeta} pm WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key = '" . self::META_PREFIX . "display_priority' LIMIT 1)";
        return "COALESCE({$pinned}, '0') DESC, CASE WHEN COALESCE({$prio}, '') = '' THEN 1 ELSE 0 END ASC, CAST(COALESCE(NULLIF({$prio}, ''), '0') AS SIGNED) ASC, {$wpdb->posts}.post_date DESC";
    }

    public function robots($robots) {
        // Staged items are reviewable by URL but must not index.
        if (!self::is_live() && is_singular(self::CPT)) {
            $robots['noindex'] = true;
            $robots['follow']  = true;
            unset($robots['index']);
        }
        // Filtered and paged archive views: follow, don't index.
        if (self::is_filtered_archive()) {
            $robots['noindex'] = true;
            $robots['follow']  = true;
            unset($robots['index']);
        }
        return $robots;
    }

    public function sitemap_post_types($types) {
        if (!self::is_live()) unset($types[self::CPT]);
        return $types;
    }

    /** Term views are noindex filter URLs, never sitemap entries. */
    public function sitemap_taxonomies($taxonomies) {
        unset($taxonomies[self::TAX_TYPE], $taxonomies[self::TAX_TOPIC]);
        return $taxonomies;
    }

    public function aeo_post_types($types) {
        $types[] = self::CPT;
        return array_values(array_unique($types));
    }

    // =========================================================================
    // SCHEMA
    // =========================================================================
    public function output_schema() {
        if (is_singular(self::CPT)) {
            if (class_exists('RequestDesk_Yoast_Schema') && RequestDesk_Yoast_Schema::should_skip_standalone()) return;
            $schema = self::build_schema(get_queried_object_id());
            echo "\n<script type=\"application/ld+json\">" . wp_json_encode($schema, JSON_UNESCAPED_SLASHES) . "</script>\n";
            return;
        }
        if (is_post_type_archive(self::CPT) && !self::is_filtered_archive()) {
            echo "\n<script type=\"application/ld+json\">" . wp_json_encode(self::build_archive_schema(), JSON_UNESCAPED_SLASHES) . "</script>\n";
        }
    }

    /**
     * Report node for one item. Report is an Article subtype: honest for
     * research that was not peer reviewed (ScholarlyArticle would claim that).
     */
    public static function build_schema($post_id) {
        $d = self::get($post_id);
        $org = array('@type' => 'Organization', 'name' => get_bloginfo('name'), 'url' => home_url('/'));

        $authors = array();
        foreach ($d['authors'] as $a) {
            if (empty($a['name'])) continue;
            $authors[] = array_filter(array('@type' => 'Person', 'name' => $a['name'], 'jobTitle' => $a['title'] ?? '', 'url' => $a['url'] ?? ''));
        }
        $authors[] = $org;

        $schema = array(
            '@context'         => 'https://schema.org',
            '@type'            => 'Report',
            'headline'         => $d['title'],
            'name'             => $d['title'],
            'description'      => $d['aeo_summary'] ?: $d['excerpt'],
            'datePublished'    => $d['date_published'],
            'dateModified'     => $d['last_updated'],
            'author'           => $authors,
            'publisher'        => $org,
            'isAccessibleForFree' => true,
            'mainEntityOfPage' => $d['url'],
            'url'              => $d['url'],
            'inLanguage'       => 'en-US',
        );
        if ($d['type'])          $schema['genre'] = $d['type']['name'];
        if ($d['topics'])        $schema['keywords'] = implode(', ', wp_list_pluck($d['topics'], 'name'));
        if ($d['snapshot_date']) $schema['temporalCoverage'] = $d['snapshot_date'];
        if ($d['cover'])         $schema['image'] = $d['cover'];
        if ($d['sponsor'])       $schema['sponsor'] = array_filter(array('@type' => 'Organization', 'name' => $d['sponsor']['name'], 'url' => $d['sponsor']['url']));
        if ($d['pdf_url']) {
            $schema['associatedMedia'] = array_filter(array(
                '@type'          => 'MediaObject',
                'encodingFormat' => 'application/pdf',
                'contentUrl'     => $d['pdf_url'],
                'numberOfPages'  => $d['pdf_pages'] ? intval($d['pdf_pages']) : null,
            ));
        }
        if ($d['dataset_url']) {
            $schema['isBasedOn'] = array_filter(array(
                '@type'        => 'Dataset',
                'name'         => $d['title'] . ' data',
                'description'  => $d['sample_note'] ?: ($d['aeo_summary'] ?: $d['excerpt']),
                'creator'      => $org,
                'temporalCoverage' => $d['snapshot_date'] ?: null,
                'license'      => get_post_meta($post_id, self::META_PREFIX . 'dataset_license', true) ?: null,
                'distribution' => array('@type' => 'DataDownload', 'contentUrl' => $d['dataset_url']),
            ));
        }
        return $schema;
    }

    public static function build_archive_schema() {
        global $wp_query;
        $items = array();
        $pos = 1;
        foreach ((array) $wp_query->posts as $p) {
            $items[] = array('@type' => 'ListItem', 'position' => $pos++, 'url' => get_permalink($p), 'name' => get_the_title($p));
        }
        return array(
            '@context'    => 'https://schema.org',
            '@type'       => 'CollectionPage',
            'name'        => 'Studies and Research',
            'url'         => self::archive_url(),
            'isPartOf'    => array('@type' => 'WebSite', 'url' => home_url('/')),
            'mainEntity'  => array('@type' => 'ItemList', 'itemListElement' => $items),
        );
    }

    // =========================================================================
    // ADMIN: ROUTING SETTINGS + IMPORT
    // =========================================================================
    public function add_admin_pages() {
        $parent = 'edit.php?post_type=' . self::CPT;
        add_submenu_page($parent, 'Research Settings', 'Settings', 'manage_options', 'requestdesk-research-settings', array($this, 'render_settings_page'));
        add_submenu_page($parent, 'Import Research', 'Import', 'manage_options', 'requestdesk-research-import', array($this, 'render_import_page'));
    }

    public function render_settings_page() {
        $live = self::is_live();
        ?>
        <div class="wrap">
            <h1>Research Library Settings</h1>
            <?php if (isset($_GET['saved'])) echo '<div class="notice notice-success"><p>Routing saved. Rewrite rules were flushed.</p></div>'; ?>
            <p>Current routing: <strong><?php echo $live ? 'LIVE at /research/' : 'STAGED at /research-library/ (noindex)'; ?></strong></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="requestdesk_research_routing">
                <?php wp_nonce_field('requestdesk_research_routing', 'rs_routing_nonce'); ?>
                <p><label><input type="radio" name="routing" value="staged"<?php checked(!$live); ?>> <strong>Staged</strong>: items at /research-library/&lt;slug&gt;/, noindex, out of the sitemap, no archive. The existing /research/ page keeps serving.</label></p>
                <p><label><input type="radio" name="routing" value="live"<?php checked($live); ?>> <strong>Live</strong>: the library archive takes /research/ and items move to /research/&lt;slug&gt;/.</label></p>
                <div class="notice notice-warning inline"><p><strong>Before going live:</strong> every research item that reuses a /research/&lt;slug&gt;/ URL must be published, and the old "research" page and its child pages must be set to Draft. Going live first sends those URLs to the old pages or 404s.</p></div>
                <?php submit_button('Save routing'); ?>
            </form>
        </div>
        <?php
    }

    public function handle_routing() {
        if (!current_user_can('manage_options')) wp_die('Insufficient permissions');
        check_admin_referer('requestdesk_research_routing', 'rs_routing_nonce');
        $routing = (isset($_POST['routing']) && $_POST['routing'] === 'live') ? 'live' : 'staged';
        update_option(self::ROUTING_OPTION, $routing, true);
        // The CPT registered this request with the old slug. Flush on the next
        // request, after register_cpt() has run with the new routing.
        update_option(self::FLUSH_OPTION, 1, true);
        wp_redirect(admin_url('edit.php?post_type=' . self::CPT . '&page=requestdesk-research-settings&saved=1'));
        exit;
    }

    /**
     * Inbox: uploads/requestdesk/research/, one JSON object per file, upsert
     * by slug, assets/ beside it. Content storage, never inside the plugin
     * (it ships with the zip and is deleted on update; violation #290).
     */
    private function import_dir() {
        $uploads = wp_upload_dir();
        $default = trailingslashit($uploads['basedir']) . 'requestdesk/research/';
        return trailingslashit(apply_filters('requestdesk_research_import_dir', $default));
    }

    private function assets_dir() { return $this->import_dir() . 'assets/'; }

    private function pending_files() {
        $b = $this->import_dir();
        foreach (array($b, $b . 'imported/', $b . 'failed/', $b . 'assets/') as $d) {
            if (!is_dir($d)) wp_mkdir_p($d);
        }
        $files = glob($b . '*.json');
        if (!is_array($files)) return array();
        sort($files);
        $out = array();
        foreach ($files as $f) {
            $data = json_decode((string) file_get_contents($f), true);
            if (isset($data[0]) && is_array($data[0])) $data = $data[0];
            $out[] = array('path' => $f, 'entry' => is_array($data) ? $data : null);
        }
        return $out;
    }

    public function render_import_page() {
        $pending = $this->pending_files();
        ?>
        <div class="wrap">
            <h1>Import Research</h1>
            <?php
            foreach (array('created' => 'success', 'updated' => 'info', 'failed' => 'warning') as $k => $cls) {
                if (isset($_GET[$k])) echo '<div class="notice notice-' . $cls . '"><p><strong>' . intval($_GET[$k]) . '</strong> ' . $k . '.</p></div>';
            }
            if (!$pending) {
                echo '<p>Inbox empty. Drop one JSON file per item into <code>' . esc_html($this->import_dir()) . '</code>. Images and PDFs named in a file go in <code>assets/</code>. The same JSON can be sent to <code>POST /wp-json/requestdesk/v1/research</code>.</p>';
            } else {
                ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="requestdesk_import_research">
                    <?php wp_nonce_field('requestdesk_import_research', 'rs_import_nonce'); ?>
                    <table class="widefat striped"><thead><tr><th style="width:30px"></th><th>File</th><th>Slug</th><th>Title</th><th>Status</th></tr></thead><tbody>
                    <?php foreach ($pending as $it):
                        $e = $it['entry'];
                        $slug = is_array($e) ? ($e['slug'] ?? '') : '';
                        $existing = $slug ? get_page_by_path($slug, OBJECT, self::CPT) : null; ?>
                        <tr>
                            <td><input type="checkbox" name="files[]" value="<?php echo esc_attr(basename($it['path'])); ?>" checked></td>
                            <td><code><?php echo esc_html(basename($it['path'])); ?></code></td>
                            <td><code><?php echo esc_html($slug); ?></code></td>
                            <td><?php echo esc_html(is_array($e) ? ($e['title'] ?? '') : '(invalid JSON)'); ?></td>
                            <td><?php echo !is_array($e) ? 'Will fail' : ($existing ? 'Will update' : 'Will create'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                    <?php submit_button('Run import (selected)'); ?>
                </form>
                <?php
            }
            ?>
            <h2>JSON (one object per file)</h2>
            <pre style="background:#f6f7f7;padding:16px;overflow:auto;font-size:12px"><?php echo esc_html(self::example_json()); ?></pre>
        </div>
        <?php
    }

    public static function example_json() {
        return wp_json_encode(array(
            'slug' => 'b2b-commerce-trends', 'title' => 'B2B Commerce Trends 2026', 'status' => 'draft',
            'excerpt' => 'Card blurb.', 'content' => '<h2 id="intro">Intro</h2><p>Full text…</p>',
            'featured_image' => 'cover.jpg', 'type' => 'trends-report', 'topics' => array('B2B Commerce'),
            'aeo_summary' => '…', 'findings' => array(array('value' => '62%', 'label' => '…', 'note' => '')),
            'access' => 'inline_form', 'pdf' => 'report.pdf', 'pdf_pages' => 54, 'hubspot_form_id' => '…', 'gate_url' => '/b2b-commerce-trends-report/',
            'authors' => array(array('name' => 'Brent Peterson', 'title' => 'CEO, Content Cucumber', 'url' => '', 'role' => 'author')),
            'sponsor_name' => 'Shopware', 'sponsor_url' => 'https://www.shopware.com/', 'sponsor_logo' => 'shopware.svg',
            'band_color' => 'ink', 'listed' => true, 'pinned' => true, 'display_priority' => 0,
            'faq' => array(array('question' => '…', 'answer' => '…')),
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function handle_import() {
        if (!current_user_can('manage_options')) wp_die('Insufficient permissions');
        check_admin_referer('requestdesk_import_research', 'rs_import_nonce');
        $selected = array_map('sanitize_file_name', (array) ($_POST['files'] ?? array()));
        $counts = array('created' => 0, 'updated' => 0, 'failed' => 0);
        foreach ($this->pending_files() as $it) {
            if (!in_array(basename($it['path']), $selected, true)) continue;
            $res = is_array($it['entry']) ? $this->import_entry($it['entry']) : new WP_Error('bad_json', 'Invalid JSON');
            $ok = !is_wp_error($res);
            $counts[$ok ? $res['result'] : 'failed']++;
            if (!$ok) error_log('RequestDesk research import failed for ' . basename($it['path']) . ': ' . $res->get_error_message());
            @rename($it['path'], $this->import_dir() . ($ok ? 'imported/' : 'failed/') . date('Ymd-His') . '-' . basename($it['path']));
        }
        wp_redirect(admin_url('edit.php?post_type=' . self::CPT . '&page=requestdesk-research-import&' . http_build_query($counts)));
        exit;
    }

    /**
     * Create or update one item from an import entry. Shared by the inbox and
     * REST. Returns ['result' => created|updated, 'post_id' => int, 'problems' => []]
     * or WP_Error.
     */
    public function import_entry($e, $dry_run = false) {
        // REST imports never pass through admin_init, so seed the types here too.
        $this->maybe_seed_types();

        $slug  = sanitize_title($e['slug'] ?? '');
        $title = sanitize_text_field($e['title'] ?? '');
        if ($slug === '' || $title === '') return new WP_Error('missing_fields', 'slug and title are required', array('status' => 400));
        if (!empty($e['type']) && !term_exists(sanitize_title($e['type']), self::TAX_TYPE)) {
            return new WP_Error('unknown_type', 'Unknown research type: ' . $e['type'] . '. Known: ' . implode(', ', array_keys(self::TYPES)), array('status' => 400));
        }

        $existing = get_page_by_path($slug, OBJECT, self::CPT);
        if ($dry_run) {
            return array('result' => $existing ? 'updated' : 'created', 'post_id' => $existing ? $existing->ID : 0, 'dry_run' => true);
        }

        $status = in_array(($e['status'] ?? 'draft'), array('draft', 'publish', 'pending', 'private'), true) ? $e['status'] : 'draft';
        $postarr = array(
            'post_type'    => self::CPT,
            'post_status'  => $status,
            'post_title'   => $title,
            'post_name'    => $slug,
        );
        if (array_key_exists('excerpt', $e)) $postarr['post_excerpt'] = sanitize_textarea_field($e['excerpt']);
        if (array_key_exists('content', $e)) $postarr['post_content'] = wp_kses_post($e['content']);

        if ($existing) {
            $postarr['ID'] = $existing->ID;
            $post_id = wp_update_post(wp_slash($postarr), true);
            $result = 'updated';
        } else {
            $post_id = wp_insert_post(wp_slash($postarr), true);
            $result = 'created';
        }
        if (is_wp_error($post_id)) return $post_id;

        if (!empty($e['type']))   wp_set_object_terms($post_id, array(sanitize_title($e['type'])), self::TAX_TYPE, false);
        if (isset($e['topics']) && is_array($e['topics'])) wp_set_object_terms($post_id, array_map('sanitize_text_field', $e['topics']), self::TAX_TOPIC, false);

        // Media: featured image, PDF and sponsor logo resolve to attachments.
        if (!empty($e['featured_image'])) {
            $att = $this->resolve_media($e['featured_image'], $post_id);
            if ($att) set_post_thumbnail($post_id, $att);
        }
        foreach (array('pdf', 'sponsor_logo') as $mk) {
            if (!empty($e[$mk]) && !preg_match('#^https?://#i', (string) $e[$mk])) {
                $att = $this->resolve_media($e[$mk], $post_id);
                if ($att) $e[$mk] = (string) $att;
            } elseif (!empty($e[$mk])) {
                $att = attachment_url_to_postid($e[$mk]);
                if ($att) $e[$mk] = (string) $att;
            }
        }

        $fields = array_intersect_key($e, self::fields());
        if (isset($fields['study_snapshot']) && is_array($fields['study_snapshot'])) $fields['study_snapshot'] = wp_json_encode($fields['study_snapshot']);
        self::write_fields($post_id, $fields);

        // Body images: src="cc-asset:<file>" sideloaded from assets/.
        $content = get_post_field('post_content', $post_id);
        if ($content && strpos($content, 'cc-asset:') !== false) {
            $content = preg_replace_callback('/cc-asset:([A-Za-z0-9._-]+)/', function ($mm) use ($post_id) {
                $id = $this->resolve_media($mm[1], $post_id);
                return $id ? wp_get_attachment_url($id) : $mm[0];
            }, $content);
            wp_update_post(wp_slash(array('ID' => $post_id, 'post_content' => $content)));
        }

        // FAQ goes through the one AEO write path, never into research meta.
        $faq_result = null;
        if (!empty($e['faq']) && is_array($e['faq']) && class_exists('RequestDesk_AEO_Core')) {
            $aeo = new RequestDesk_AEO_Core(false);
            $faq_result = $aeo->set_qa_pairs($post_id, $e['faq'], 'replace');
            if (is_wp_error($faq_result)) return $faq_result;
        }

        return array(
            'result'   => $result,
            'post_id'  => $post_id,
            'url'      => get_permalink($post_id),
            'faq'      => $faq_result ? ($faq_result['qa_pairs_saved'] ?? 0) : 0,
            'problems' => get_post_meta($post_id, self::META_PREFIX . 'problems', true) ?: array(),
        );
    }

    /**
     * Attachment ID, existing upload URL, remote URL or a bare filename in
     * assets/. Sideloaded copies are deduped by a _cc_rs_src marker.
     */
    private function resolve_media($ref, $post_id = 0) {
        $ref = is_string($ref) ? trim($ref) : $ref;
        if (empty($ref)) return 0;
        if (is_numeric($ref)) return intval($ref);

        $existing = attachment_url_to_postid($ref);
        if (!$existing) $existing = attachment_url_to_postid(home_url($ref));
        if ($existing) return $existing;

        $marker = md5($ref);
        $dupe = get_posts(array('post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_cc_rs_src', 'meta_value' => $marker));
        if ($dupe) return $dupe[0];

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $att = 0;
        if (preg_match('#^https?://#i', $ref)) {
            $tmp = download_url($ref);
            if (!is_wp_error($tmp)) {
                $att = media_handle_sideload(array('name' => basename(parse_url($ref, PHP_URL_PATH)), 'tmp_name' => $tmp), $post_id);
                if (is_wp_error($att)) { @unlink($tmp); $att = 0; }
            }
        } else {
            $path = $this->assets_dir() . basename($ref);
            if (file_exists($path)) {
                $tmp = wp_tempnam(basename($ref));
                if ($tmp && copy($path, $tmp)) {
                    $att = media_handle_sideload(array('name' => basename($ref), 'tmp_name' => $tmp), $post_id);
                    if (is_wp_error($att)) { @unlink($tmp); $att = 0; }
                }
            }
        }
        if (!$att) return 0;
        update_post_meta($att, '_cc_rs_src', $marker);
        return $att;
    }

    // =========================================================================
    // REST
    // =========================================================================
    public function register_rest_routes() {
        register_rest_route('requestdesk/v1', '/research', array(
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'rest_list'),
                'permission_callback' => array($this, 'verify_api_key'),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'rest_upsert'),
                'permission_callback' => array($this, 'verify_api_key'),
                'args'                => array(
                    'slug'    => array('required' => true, 'type' => 'string'),
                    'title'   => array('required' => true, 'type' => 'string'),
                    'dry_run' => array('required' => false, 'type' => 'boolean', 'default' => false),
                ),
            ),
        ));
    }

    /** Main RequestDesk API key only: this route writes content. */
    public function verify_api_key($request) {
        $settings = get_option('requestdesk_settings', array());
        $key = isset($settings['api_key']) ? (string) $settings['api_key'] : '';
        if ($key === '') return new WP_Error('no_api_key', 'RequestDesk API key not configured', array('status' => 401));
        $provided = (string) $request->get_header('X-RequestDesk-API-Key');
        if ($provided === '' || !hash_equals($key, $provided)) return new WP_Error('invalid_api_key', 'Invalid API key', array('status' => 401));
        return true;
    }

    public function rest_list($request) {
        $posts = get_posts(array('post_type' => self::CPT, 'post_status' => array('publish', 'draft', 'pending', 'private'), 'posts_per_page' => 200, 'orderby' => 'date', 'order' => 'DESC'));
        $out = array();
        foreach ($posts as $p) {
            $d = self::get($p->ID);
            $out[] = array(
                'id' => $p->ID, 'slug' => $p->post_name, 'status' => $p->post_status, 'title' => $d['title'], 'url' => $d['url'],
                'type' => $d['type'] ? $d['type']['slug'] : null, 'listed' => $d['listed'], 'access' => $d['access'],
                'snapshot_date' => $d['snapshot_date'], 'problems' => get_post_meta($p->ID, self::META_PREFIX . 'problems', true) ?: array(),
            );
        }
        return new WP_REST_Response(array('routing' => self::routing(), 'count' => count($out), 'items' => $out), 200);
    }

    public function rest_upsert($request) {
        $entry = $request->get_json_params();
        if (!is_array($entry)) return new WP_Error('invalid_body', 'JSON object expected', array('status' => 400));
        $res = $this->import_entry($entry, (bool) $request->get_param('dry_run'));
        if (is_wp_error($res)) return $res;
        return new WP_REST_Response($res, $res['result'] === 'created' ? 201 : 200);
    }
}

new RequestDesk_Research();
