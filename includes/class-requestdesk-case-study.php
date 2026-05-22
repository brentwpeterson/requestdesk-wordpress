<?php
/**
 * RequestDesk Case Study CPT
 *
 * Registers cc_case_study custom post type with six taxonomies, meta boxes for
 * Tier 1 + Tier 2 fields, schema.org JSON-LD output, and a reusable card
 * render method for archive/index pages.
 *
 * Taxonomies:
 *   - case_study_industry  (Gourmet Food, SaaS, Healthcare, ...)
 *   - case_study_platform  (Shopify, WordPress, HubSpot, Magento, ...)
 *   - case_study_service   (Blog Writing, SEO, Migration, ...)
 *   - case_study_outcome   (Traffic Growth, Revenue Lift, ...)
 *   - case_study_size      (SMB, Mid-market, Enterprise)
 *   - case_study_year      (2024, 2025, 2026)
 *
 * @package RequestDesk
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Case_Study {

    /**
     * Whether the Case Study Creator wizard is enabled for this site.
     * Controlled by the "Case Study Creator" checkbox on the RequestDesk
     * settings page. Read by the wizard menu registration so the menu only
     * appears after the admin opts in.
     */
    public static function wizard_enabled() {
        $settings = get_option('requestdesk_settings', array());
        return !empty($settings['enable_case_study_wizard']);
    }

    public function __construct() {
        add_action('init', array($this, 'register_cpt'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('init', array($this, 'register_work_type_rewrites'), 20);
        add_action('admin_init', array($this, 'maybe_run_data_migrations'));
        add_action('created_work_type', 'flush_rewrite_rules');
        add_action('delete_work_type', 'flush_rewrite_rules');
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_cc_case_study', array($this, 'save_meta'), 10, 2);
        add_action('wp_head', array($this, 'output_schema'));
        add_filter('manage_cc_case_study_posts_columns', array($this, 'admin_columns'));
        add_action('manage_cc_case_study_posts_custom_column', array($this, 'admin_column_content'), 10, 2);
        add_action('pre_get_posts', array($this, 'archive_sort_pinned'));

        // The JSON bulk-importer ships CC client data and stays CC-only even
        // when the CPT itself goes universal across all connector installs.
        if (function_exists('requestdesk_is_cc_site') && requestdesk_is_cc_site()) {
            add_action('admin_menu', array($this, 'add_import_page'));
            add_action('admin_post_requestdesk_import_case_studies', array($this, 'handle_import'));
        }
    }

    // =========================================================================
    // CPT REGISTRATION
    // =========================================================================
    public function register_cpt() {
        $labels = array(
            'name'               => 'Case Studies',
            'singular_name'      => 'Case Study',
            'menu_name'          => 'Case Studies',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Case Study',
            'edit_item'          => 'Edit Case Study',
            'new_item'           => 'New Case Study',
            'view_item'          => 'View Case Study',
            'search_items'       => 'Search Case Studies',
            'not_found'          => 'No case studies found',
            'not_found_in_trash' => 'No case studies found in Trash',
            'all_items'          => 'All Case Studies',
        );

        register_post_type('cc_case_study', array(
            'labels'        => $labels,
            'public'        => true,
            // Umbrella archive at /our-work/. Sub-views for each Work Type
            // (Case Study, Research, etc.) live at /our-work/<type>/ via the
            // custom rewrite rules in register_work_type_rewrites(). Old
            // /case-studies/* paths 301 in the theme functions.php.
            'has_archive'   => 'our-work',
            'rewrite'       => array('slug' => 'our-work', 'with_front' => false),
            'supports'      => array('title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'page-attributes'),
            'menu_icon'     => 'dashicons-awards',
            'menu_position' => 26,
            'show_in_rest'  => true,
        ));
    }

    // =========================================================================
    // TAXONOMIES (work_type umbrella + 6 filter taxonomies)
    // =========================================================================
    public function register_taxonomies() {
        // work_type is the umbrella taxonomy. Drives the sub-archives at
        // /our-work/case-studies/, /our-work/research/, etc. Rewrite is
        // disabled here; the custom rewrite rules in
        // register_work_type_rewrites() handle URL routing so that single
        // posts at /our-work/<post-slug>/ and term archives at
        // /our-work/<term-slug>/ do not collide.
        register_taxonomy('work_type', 'cc_case_study', array(
            'labels'            => array(
                'name'          => 'Work Types',
                'singular_name' => 'Work Type',
                'menu_name'     => 'Work Types',
            ),
            'hierarchical'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'show_in_menu'      => false,
            'rewrite'           => false,
        ));

        $taxonomies = array(
            'case_study_industry' => array('Industry', 'Industries', 'industry'),
            'case_study_platform' => array('Platform', 'Platforms', 'platform'),
            'case_study_service'  => array('Service Used', 'Services Used', 'service'),
            'case_study_outcome'  => array('Outcome', 'Outcomes', 'outcome'),
            'case_study_size'     => array('Company Size', 'Company Sizes', 'size'),
            'case_study_year'     => array('Year', 'Years', 'year'),
        );

        foreach ($taxonomies as $slug => $info) {
            register_taxonomy($slug, 'cc_case_study', array(
                'labels'            => array(
                    'name'          => $info[1],
                    'singular_name' => $info[0],
                    'menu_name'     => $info[1],
                ),
                'hierarchical'      => true,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'show_in_menu'      => false,
                'rewrite'           => array('slug' => 'our-work/' . $info[2]),
            ));
        }
    }

    // =========================================================================
    // CUSTOM REWRITE RULES for /our-work/<work-type-term>/
    // =========================================================================
    public function register_work_type_rewrites() {
        $terms = get_terms(array(
            'taxonomy'   => 'work_type',
            'hide_empty' => false,
            'fields'     => 'slugs',
        ));
        if (is_wp_error($terms) || empty($terms)) {
            // Sensible defaults during first activation, before the backfill
            // routine has seeded the canonical terms.
            $terms = array('case-studies', 'research');
        }
        $pattern = implode('|', array_map('preg_quote', $terms));

        add_rewrite_rule(
            '^our-work/(' . $pattern . ')/?$',
            'index.php?post_type=cc_case_study&work_type=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^our-work/(' . $pattern . ')/page/([0-9]+)/?$',
            'index.php?post_type=cc_case_study&work_type=$matches[1]&paged=$matches[2]',
            'top'
        );
    }

    // =========================================================================
    // ONE-TIME DATA MIGRATIONS (Work Type backfill, etc.)
    // =========================================================================
    public function maybe_run_data_migrations() {
        $current = (int) get_option('cc_case_study_data_version', 1);

        // v2: seed canonical Work Type terms + backfill existing posts as
        // Case Study. Runs once on plugin upgrade or first admin page load
        // after the work_type taxonomy is registered.
        if ($current < 2) {
            $this->migrate_seed_work_types_and_backfill();
            update_option('cc_case_study_data_version', 2);
            flush_rewrite_rules();
        }
    }

    private function migrate_seed_work_types_and_backfill() {
        $seeds = array(
            'case-studies' => 'Case Study',
            'research'     => 'Research',
        );
        $case_study_term_id = 0;
        foreach ($seeds as $slug => $name) {
            $existing = get_term_by('slug', $slug, 'work_type');
            if ($existing) {
                if ($slug === 'case-studies') $case_study_term_id = (int) $existing->term_id;
                continue;
            }
            $new = wp_insert_term($name, 'work_type', array('slug' => $slug));
            if (!is_wp_error($new) && $slug === 'case-studies') {
                $case_study_term_id = (int) $new['term_id'];
            }
        }
        if (!$case_study_term_id) return;

        $posts = get_posts(array(
            'post_type'      => 'cc_case_study',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));
        foreach ($posts as $pid) {
            $assigned = wp_get_object_terms($pid, 'work_type', array('fields' => 'ids'));
            if (!is_wp_error($assigned) && empty($assigned)) {
                wp_set_object_terms($pid, $case_study_term_id, 'work_type', false);
            }
        }
    }

    // =========================================================================
    // META BOXES
    // =========================================================================
    public function add_meta_boxes() {
        // Enqueue WP media library JS so the logo/photo pickers work.
        if (function_exists('wp_enqueue_media')) wp_enqueue_media();
        add_meta_box('cc_case_study_client', 'Client Details', array($this, 'render_client_box'), 'cc_case_study', 'normal', 'high');
        add_meta_box('cc_case_study_engagement', 'Engagement', array($this, 'render_engagement_box'), 'cc_case_study', 'normal', 'high');
        add_meta_box('cc_case_study_story', 'The Story (Challenge / Approach / Results)', array($this, 'render_story_box'), 'cc_case_study', 'normal', 'high');
        add_meta_box('cc_case_study_stats', 'Stats Grid (Tier 2)', array($this, 'render_stats_box'), 'cc_case_study', 'normal', 'default');
        add_meta_box('cc_case_study_quote', 'Testimonial Quote', array($this, 'render_quote_box'), 'cc_case_study', 'normal', 'default');
        add_meta_box('cc_case_study_aeo', 'AEO Quick-Answer (3-Sentence Summary)', array($this, 'render_aeo_box'), 'cc_case_study', 'normal', 'default');
        add_meta_box('cc_case_study_team', 'Team', array($this, 'render_team_box'), 'cc_case_study', 'side', 'default');
        add_meta_box('cc_case_study_display', 'Display Options', array($this, 'render_display_box'), 'cc_case_study', 'side', 'default');
        add_meta_box('cc_case_study_seo', 'SEO Override', array($this, 'render_seo_box'), 'cc_case_study', 'normal', 'low');
    }

    public function render_client_box($post) {
        wp_nonce_field('cc_case_study_meta', 'cc_case_study_nonce');
        $client_name = get_post_meta($post->ID, '_cc_cs_client_name', true);
        $client_url  = get_post_meta($post->ID, '_cc_cs_client_url', true);
        $client_logo = get_post_meta($post->ID, '_cc_cs_client_logo', true);
        // Logo can be stored as an attachment ID (preferred) or a URL (legacy/import)
        $logo_url = '';
        if (is_numeric($client_logo) && intval($client_logo) > 0) {
            $logo_url = wp_get_attachment_url(intval($client_logo));
        } elseif (!empty($client_logo)) {
            $logo_url = $client_logo; // legacy string URL
        }
        ?>
        <p><label><strong>Client Name</strong><br>
            <input type="text" name="cc_cs_client_name" value="<?php echo esc_attr($client_name); ?>" style="width:100%"></label></p>
        <p><label><strong>Client Website URL</strong><br>
            <input type="url" name="cc_cs_client_url" value="<?php echo esc_attr($client_url); ?>" style="width:100%" placeholder="https://"></label></p>
        <p><strong>Client Logo</strong></p>
        <input type="hidden" name="cc_cs_client_logo" id="cc_cs_client_logo" value="<?php echo esc_attr($client_logo); ?>">
        <div id="cc_cs_client_logo_preview" style="margin-bottom:8px;">
            <?php if ($logo_url) : ?>
                <img src="<?php echo esc_url($logo_url); ?>" style="max-width:200px;height:auto;border:1px solid #e0e0e0;padding:4px;background:#fff;">
            <?php endif; ?>
        </div>
        <button type="button" class="button" id="cc_cs_client_logo_btn">Select Logo</button>
        <button type="button" class="button" id="cc_cs_client_logo_remove"<?php echo !$logo_url ? ' style="display:none;"' : ''; ?>>Remove</button>
        <script>
        jQuery(function($){
            var f;
            $('#cc_cs_client_logo_btn').on('click', function(e){
                e.preventDefault();
                f = wp.media({ title: 'Select Client Logo', multiple: false });
                f.on('select', function(){
                    var att = f.state().get('selection').first().toJSON();
                    $('#cc_cs_client_logo').val(att.id);
                    $('#cc_cs_client_logo_preview').html('<img src="' + att.url + '" style="max-width:200px;height:auto;border:1px solid #e0e0e0;padding:4px;background:#fff;">');
                    $('#cc_cs_client_logo_remove').show();
                });
                f.open();
            });
            $('#cc_cs_client_logo_remove').on('click', function(e){
                e.preventDefault();
                $('#cc_cs_client_logo').val('');
                $('#cc_cs_client_logo_preview').html('');
                $(this).hide();
            });
        });
        </script>
        <p class="description">Featured Image (right sidebar) is the case study hero. The client logo above appears in the meta header next to the client name.</p>
        <?php
    }

    public function render_engagement_box($post) {
        $length     = get_post_meta($post->ID, '_cc_cs_length', true);
        $type       = get_post_meta($post->ID, '_cc_cs_type', true);
        $started    = get_post_meta($post->ID, '_cc_cs_date_started', true);
        $published  = get_post_meta($post->ID, '_cc_cs_date_published', true);
        $verified   = get_post_meta($post->ID, '_cc_cs_last_verified', true);
        $type_options = array('Retainer', 'One-off project', 'Migration recovery', 'Case study as a service', 'Other');
        ?>
        <p><label><strong>Engagement Length</strong><br>
            <input type="text" name="cc_cs_length" value="<?php echo esc_attr($length); ?>" style="width:100%" placeholder="e.g. Ongoing, 6 months, 12-week sprint"></label></p>
        <p><label><strong>Engagement Type</strong><br>
            <select name="cc_cs_type" style="width:100%">
                <option value="">Select...</option>
                <?php foreach ($type_options as $opt) : ?>
                    <option value="<?php echo esc_attr($opt); ?>"<?php selected($type, $opt); ?>><?php echo esc_html($opt); ?></option>
                <?php endforeach; ?>
            </select></label></p>
        <p><label><strong>Date Engagement Started</strong><br>
            <input type="date" name="cc_cs_date_started" value="<?php echo esc_attr($started); ?>"></label></p>
        <p><label><strong>Date Case Study Published</strong><br>
            <input type="date" name="cc_cs_date_published" value="<?php echo esc_attr($published); ?>"></label></p>
        <p><label><strong>Last Verified Date</strong> (internal — keeps case study fresh)<br>
            <input type="date" name="cc_cs_last_verified" value="<?php echo esc_attr($verified); ?>"></label></p>
        <?php
    }

    public function render_story_box($post) {
        $challenge = get_post_meta($post->ID, '_cc_cs_challenge', true);
        $approach  = get_post_meta($post->ID, '_cc_cs_approach', true);
        $results   = get_post_meta($post->ID, '_cc_cs_results', true);
        ?>
        <p><label><strong>Challenge</strong> (the problem)<br>
            <textarea name="cc_cs_challenge" rows="4" style="width:100%"><?php echo esc_textarea($challenge); ?></textarea></label></p>
        <p><label><strong>Approach</strong> (what we did)<br>
            <textarea name="cc_cs_approach" rows="6" style="width:100%"><?php echo esc_textarea($approach); ?></textarea></label></p>
        <p><label><strong>Results</strong> (narrative — the headline numbers go in the Stats Grid below)<br>
            <textarea name="cc_cs_results" rows="4" style="width:100%"><?php echo esc_textarea($results); ?></textarea></label></p>
        <?php
    }

    public function render_stats_box($post) {
        $stats = get_post_meta($post->ID, '_cc_cs_stats', true);
        if (!is_array($stats)) $stats = array();
        // Always render at least 4 stat rows (extras can be left empty)
        while (count($stats) < 4) $stats[] = array('value' => '', 'label' => '');
        ?>
        <p class="description">Each row becomes a metric tile on the case study page. Examples: "+220%" / "Organic traffic" — "12 hrs/wk" / "Time saved".</p>
        <table style="width:100%">
            <thead><tr><th style="text-align:left;width:40%">Value</th><th style="text-align:left">Label</th></tr></thead>
            <tbody>
            <?php foreach ($stats as $i => $row) : ?>
                <tr>
                    <td><input type="text" name="cc_cs_stats[<?php echo $i; ?>][value]" value="<?php echo esc_attr($row['value']); ?>" style="width:100%" placeholder="+220%"></td>
                    <td><input type="text" name="cc_cs_stats[<?php echo $i; ?>][label]" value="<?php echo esc_attr($row['label']); ?>" style="width:100%" placeholder="Organic traffic"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function render_quote_box($post) {
        $quote   = get_post_meta($post->ID, '_cc_cs_quote', true);
        $name    = get_post_meta($post->ID, '_cc_cs_quote_name', true);
        $title   = get_post_meta($post->ID, '_cc_cs_quote_title', true);
        $company = get_post_meta($post->ID, '_cc_cs_quote_company', true);
        $photo   = get_post_meta($post->ID, '_cc_cs_quote_photo', true);
        $photo_url = '';
        if (is_numeric($photo) && intval($photo) > 0) {
            $photo_url = wp_get_attachment_url(intval($photo));
        } elseif (!empty($photo)) {
            $photo_url = $photo;
        }
        ?>
        <p><label><strong>Quote</strong><br>
            <textarea name="cc_cs_quote" rows="3" style="width:100%"><?php echo esc_textarea($quote); ?></textarea></label></p>
        <table style="width:100%"><tbody>
            <tr><td><label><strong>Attributed Name</strong><br>
                <input type="text" name="cc_cs_quote_name" value="<?php echo esc_attr($name); ?>" style="width:100%"></label></td>
                <td><label><strong>Title</strong><br>
                <input type="text" name="cc_cs_quote_title" value="<?php echo esc_attr($title); ?>" style="width:100%" placeholder="Owner, CMO, etc."></label></td></tr>
            <tr><td colspan="2"><label><strong>Company</strong><br>
                <input type="text" name="cc_cs_quote_company" value="<?php echo esc_attr($company); ?>" style="width:100%"></label></td></tr>
        </tbody></table>
        <p style="margin-top:14px"><strong>Headshot</strong></p>
        <input type="hidden" name="cc_cs_quote_photo" id="cc_cs_quote_photo" value="<?php echo esc_attr($photo); ?>">
        <div id="cc_cs_quote_photo_preview" style="margin-bottom:8px;">
            <?php if ($photo_url) : ?>
                <img src="<?php echo esc_url($photo_url); ?>" style="max-width:120px;height:auto;border-radius:50%;border:1px solid #e0e0e0;">
            <?php endif; ?>
        </div>
        <button type="button" class="button" id="cc_cs_quote_photo_btn">Select Headshot</button>
        <button type="button" class="button" id="cc_cs_quote_photo_remove"<?php echo !$photo_url ? ' style="display:none;"' : ''; ?>>Remove</button>
        <script>
        jQuery(function($){
            var f;
            $('#cc_cs_quote_photo_btn').on('click', function(e){
                e.preventDefault();
                f = wp.media({ title: 'Select Headshot', multiple: false });
                f.on('select', function(){
                    var att = f.state().get('selection').first().toJSON();
                    $('#cc_cs_quote_photo').val(att.id);
                    $('#cc_cs_quote_photo_preview').html('<img src="' + att.url + '" style="max-width:120px;height:auto;border-radius:50%;border:1px solid #e0e0e0;">');
                    $('#cc_cs_quote_photo_remove').show();
                });
                f.open();
            });
            $('#cc_cs_quote_photo_remove').on('click', function(e){
                e.preventDefault();
                $('#cc_cs_quote_photo').val('');
                $('#cc_cs_quote_photo_preview').html('');
                $(this).hide();
            });
        });
        </script>
        <?php
    }

    public function render_aeo_box($post) {
        $aeo = get_post_meta($post->ID, '_cc_cs_aeo_summary', true);
        ?>
        <p class="description">A 3-sentence summary that AI crawlers (ChatGPT, Perplexity, Google AI Overviews) can lift verbatim. Format: 1) Who they are. 2) What we did. 3) The headline result.</p>
        <textarea name="cc_cs_aeo_summary" rows="5" style="width:100%" placeholder="Chalet Market is a Montana gourmet food retailer on Shopify. We built a weekly content engine of blog posts and product descriptions, paired with on-page SEO. Organic traffic grew 220% in 12 months and revenue tracked alongside it."><?php echo esc_textarea($aeo); ?></textarea>
        <?php
    }

    public function render_team_box($post) {
        $writer = get_post_meta($post->ID, '_cc_cs_team_writer', true);
        $editor = get_post_meta($post->ID, '_cc_cs_team_editor', true);
        ?>
        <p><label><strong>Lead Writer</strong><br>
            <input type="text" name="cc_cs_team_writer" value="<?php echo esc_attr($writer); ?>" style="width:100%"></label></p>
        <p><label><strong>Editor</strong><br>
            <input type="text" name="cc_cs_team_editor" value="<?php echo esc_attr($editor); ?>" style="width:100%"></label></p>
        <p class="description">Adds E-E-A-T signals. Shown on the case study page as "Written by X, edited by Y".</p>
        <?php
    }

    public function render_display_box($post) {
        $pinned   = get_post_meta($post->ID, '_cc_cs_pinned', true);
        $priority = get_post_meta($post->ID, '_cc_cs_display_priority', true);
        ?>
        <p><label><input type="checkbox" name="cc_cs_pinned" value="1"<?php checked($pinned, '1'); ?>>
            <strong>Featured / Pinned</strong></label><br>
            <span class="description">Pinned case studies surface first on the index and homepage rotation.</span></p>
        <p><label><strong>Display Priority</strong> (0 = default, lower = sooner)<br>
            <input type="number" name="cc_cs_display_priority" value="<?php echo esc_attr($priority); ?>" style="width:100%" placeholder="0"></label></p>
        <?php
    }

    public function render_seo_box($post) {
        $title = get_post_meta($post->ID, '_cc_cs_seo_title', true);
        $desc  = get_post_meta($post->ID, '_cc_cs_seo_description', true);
        ?>
        <p><label><strong>Custom Meta Title</strong> (overrides default)<br>
            <input type="text" name="cc_cs_seo_title" value="<?php echo esc_attr($title); ?>" style="width:100%"></label></p>
        <p><label><strong>Custom Meta Description</strong><br>
            <textarea name="cc_cs_seo_description" rows="2" style="width:100%"><?php echo esc_textarea($desc); ?></textarea></label></p>
        <?php
    }

    // =========================================================================
    // SAVE HANDLER
    // =========================================================================
    public function save_meta($post_id, $post) {
        if (!isset($_POST['cc_case_study_nonce']) || !wp_verify_nonce($_POST['cc_case_study_nonce'], 'cc_case_study_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $text_fields = array(
            '_cc_cs_client_name'      => 'cc_cs_client_name',
            '_cc_cs_client_url'       => 'cc_cs_client_url',
            '_cc_cs_client_logo'      => 'cc_cs_client_logo',
            '_cc_cs_length'           => 'cc_cs_length',
            '_cc_cs_type'             => 'cc_cs_type',
            '_cc_cs_date_started'     => 'cc_cs_date_started',
            '_cc_cs_date_published'   => 'cc_cs_date_published',
            '_cc_cs_last_verified'    => 'cc_cs_last_verified',
            '_cc_cs_quote_name'       => 'cc_cs_quote_name',
            '_cc_cs_quote_title'      => 'cc_cs_quote_title',
            '_cc_cs_quote_company'    => 'cc_cs_quote_company',
            '_cc_cs_quote_photo'      => 'cc_cs_quote_photo',
            '_cc_cs_team_writer'      => 'cc_cs_team_writer',
            '_cc_cs_team_editor'      => 'cc_cs_team_editor',
            '_cc_cs_display_priority' => 'cc_cs_display_priority',
            '_cc_cs_seo_title'        => 'cc_cs_seo_title',
        );
        foreach ($text_fields as $meta_key => $post_key) {
            if (isset($_POST[$post_key])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field(wp_unslash($_POST[$post_key])));
            }
        }

        $textarea_fields = array(
            '_cc_cs_challenge'        => 'cc_cs_challenge',
            '_cc_cs_approach'         => 'cc_cs_approach',
            '_cc_cs_results'          => 'cc_cs_results',
            '_cc_cs_quote'            => 'cc_cs_quote',
            '_cc_cs_aeo_summary'      => 'cc_cs_aeo_summary',
            '_cc_cs_seo_description'  => 'cc_cs_seo_description',
        );
        foreach ($textarea_fields as $meta_key => $post_key) {
            if (isset($_POST[$post_key])) {
                update_post_meta($post_id, $meta_key, sanitize_textarea_field(wp_unslash($_POST[$post_key])));
            }
        }

        // Pinned checkbox
        update_post_meta($post_id, '_cc_cs_pinned', isset($_POST['cc_cs_pinned']) ? '1' : '0');

        // Stats grid (array of {value, label})
        if (isset($_POST['cc_cs_stats']) && is_array($_POST['cc_cs_stats'])) {
            $clean = array();
            foreach ($_POST['cc_cs_stats'] as $row) {
                $value = sanitize_text_field($row['value'] ?? '');
                $label = sanitize_text_field($row['label'] ?? '');
                if ($value !== '' || $label !== '') {
                    $clean[] = array('value' => $value, 'label' => $label);
                }
            }
            update_post_meta($post_id, '_cc_cs_stats', $clean);
        }
    }

    // =========================================================================
    // SCHEMA.ORG JSON-LD OUTPUT (single case study pages only)
    // =========================================================================
    public function output_schema() {
        if (!is_singular('cc_case_study')) return;
        $post_id = get_queried_object_id();

        $client_name  = get_post_meta($post_id, '_cc_cs_client_name', true);
        $client_url   = get_post_meta($post_id, '_cc_cs_client_url', true);
        $aeo          = get_post_meta($post_id, '_cc_cs_aeo_summary', true);
        $quote        = get_post_meta($post_id, '_cc_cs_quote', true);
        $quote_name   = get_post_meta($post_id, '_cc_cs_quote_name', true);
        $quote_title  = get_post_meta($post_id, '_cc_cs_quote_title', true);
        $writer       = get_post_meta($post_id, '_cc_cs_team_writer', true);
        $published    = get_post_meta($post_id, '_cc_cs_date_published', true);
        $thumb        = get_the_post_thumbnail_url($post_id, 'large');

        $schema = array(
            '@context'      => 'https://schema.org',
            '@type'         => 'Article',
            'headline'      => get_the_title($post_id),
            'description'   => $aeo ?: get_the_excerpt($post_id),
            'datePublished' => $published ?: get_the_date('Y-m-d', $post_id),
            'author'        => array(
                '@type' => 'Organization',
                'name'  => get_bloginfo('name'),
                'url'   => home_url('/'),
            ),
            'publisher'     => array(
                '@type' => 'Organization',
                'name'  => get_bloginfo('name'),
            ),
            'mainEntityOfPage' => get_permalink($post_id),
        );
        if ($writer) {
            $schema['author'] = array(
                array('@type' => 'Person', 'name' => $writer),
                array('@type' => 'Organization', 'name' => get_bloginfo('name')),
            );
        }
        if ($thumb) $schema['image'] = $thumb;
        if ($client_name) {
            $schema['about'] = array(
                '@type' => 'Organization',
                'name'  => $client_name,
                'url'   => $client_url ?: null,
            );
            $schema['about'] = array_filter($schema['about']);
        }
        if ($quote && $quote_name) {
            $schema['review'] = array(
                '@type'        => 'Review',
                'reviewBody'   => $quote,
                'author'       => array(
                    '@type' => 'Person',
                    'name'  => $quote_name,
                    'jobTitle' => $quote_title ?: null,
                ),
                'itemReviewed' => array(
                    '@type' => 'Organization',
                    'name'  => get_bloginfo('name'),
                ),
            );
            $schema['review']['author'] = array_filter($schema['review']['author']);
        }

        echo "\n<script type=\"application/ld+json\">" . wp_json_encode($schema, JSON_UNESCAPED_SLASHES) . "</script>\n";
    }

    // =========================================================================
    // ADMIN COLUMNS
    // =========================================================================
    public function admin_columns($cols) {
        $new = array();
        foreach ($cols as $key => $val) {
            $new[$key] = $val;
            if ($key === 'title') {
                $new['cc_cs_client']     = 'Client';
                $new['cc_cs_completion'] = 'Completion';
                $new['cc_cs_pinned']     = 'Pinned';
            }
        }
        return $new;
    }

    public function admin_column_content($col, $post_id) {
        if ($col === 'cc_cs_client') {
            echo esc_html(get_post_meta($post_id, '_cc_cs_client_name', true));
        }
        if ($col === 'cc_cs_completion') {
            $status = $this->get_completion($post_id);
            if ($status['complete']) {
                echo '<span style="display:inline-block;background:#d4edda;color:#155724;padding:2px 10px;border-radius:3px;font-size:11px;font-weight:600;">complete</span>';
            } else {
                foreach ($status['missing'] as $field) {
                    echo '<span style="display:inline-block;background:#fff3cd;color:#856404;padding:2px 8px;border-radius:3px;font-size:11px;margin-right:4px;margin-bottom:2px;">' . esc_html($field) . '</span>';
                }
            }
        }
        if ($col === 'cc_cs_pinned') {
            $pinned = get_post_meta($post_id, '_cc_cs_pinned', true);
            echo $pinned === '1' ? '&#9733;' : '';
        }
    }

    // Per-row completion checklist — surfaces what each case study is still missing
    // so the WP admin list view shows gaps at a glance. Drives the Completion column.
    public function get_completion($post_id) {
        $missing = array();

        if (!has_post_thumbnail($post_id)) {
            $missing[] = 'photo';
        }

        $tax_labels = array(
            'case_study_industry' => 'industry',
            'case_study_platform' => 'platform',
            'case_study_service'  => 'service',
            'case_study_outcome'  => 'outcome',
        );
        foreach ($tax_labels as $tax => $label) {
            $terms = wp_get_object_terms($post_id, $tax, array('fields' => 'ids'));
            if (is_wp_error($terms) || empty($terms)) {
                $missing[] = $label;
            }
        }

        $stats = get_post_meta($post_id, '_cc_cs_stats', true);
        if (!is_array($stats) || empty($stats[0]['value'])) {
            $missing[] = 'stat';
        }

        if (trim((string) get_post_field('post_excerpt', $post_id)) === '') {
            $missing[] = 'excerpt';
        }

        $body      = trim(strip_tags((string) get_post_field('post_content', $post_id)));
        $challenge = trim((string) get_post_meta($post_id, '_cc_cs_challenge', true));
        if ($body === '' && $challenge === '') {
            $missing[] = 'body';
        }

        if (trim((string) get_post_meta($post_id, '_cc_cs_aeo_summary', true)) === '') {
            $missing[] = 'AEO';
        }

        return array('complete' => empty($missing), 'missing' => $missing);
    }

    // =========================================================================
    // ARCHIVE: pinned items first, then by display priority, then by date
    // =========================================================================
    public function archive_sort_pinned($query) {
        if (is_admin() || !$query->is_main_query()) return;
        if (!is_post_type_archive('cc_case_study') && !is_tax(array('case_study_industry', 'case_study_platform', 'case_study_service', 'case_study_outcome', 'case_study_size', 'case_study_year'))) return;

        $query->set('meta_query', array(
            'relation' => 'OR',
            'pinned' => array('key' => '_cc_cs_pinned', 'compare' => 'EXISTS'),
            'no_pinned' => array('key' => '_cc_cs_pinned', 'compare' => 'NOT EXISTS'),
        ));
        $query->set('orderby', array(
            'pinned'   => 'DESC',
            'priority' => 'ASC',
            'date'     => 'DESC',
        ));
    }

    // =========================================================================
    // JSON IMPORT
    // =========================================================================
    public function add_import_page() {
        add_submenu_page(
            'edit.php?post_type=cc_case_study',
            'Import Case Studies',
            'Import Case Studies',
            'manage_options',
            'requestdesk-case-study-import',
            array($this, 'render_import_page')
        );
    }

    private function cs_import_dir() { return plugin_dir_path(__FILE__) . 'data/import/case-studies/'; }
    private function cs_legacy_path() { return plugin_dir_path(__FILE__) . 'data/case-studies-import.json'; }

    private function cs_ensure_dirs() {
        $b = $this->cs_import_dir();
        foreach (array($b, $b . 'imported/', $b . 'failed/') as $d) {
            if (!is_dir($d)) { wp_mkdir_p($d); }
        }
    }

    /**
     * Inbox model: ONE JSON object per file in data/import/case-studies/.
     * Backward compatible: a legacy data/case-studies-import.json ARRAY is
     * also surfaced (each element a pending row) until fully imported, then
     * the whole legacy file is archived. Returns a list of
     * ['key','entry','src'=>'file|legacy','path'].
     */
    private function cs_collect_pending() {
        $this->cs_ensure_dirs();
        $items = array();
        $files = glob($this->cs_import_dir() . '*.json');
        if (is_array($files)) {
            sort($files);
            foreach ($files as $f) {
                $data = json_decode(file_get_contents($f), true);
                if (!is_array($data)) { continue; }
                if (isset($data[0]) && is_array($data[0])) { $data = $data[0]; } // tolerate [ {…} ]
                $items[] = array('key' => 'file:' . basename($f), 'entry' => $data, 'src' => 'file', 'path' => $f);
            }
        }
        $legacy = $this->cs_legacy_path();
        if (file_exists($legacy)) {
            $arr = json_decode(file_get_contents($legacy), true);
            if (is_array($arr)) {
                foreach ($arr as $idx => $e) {
                    if (is_array($e)) {
                        $items[] = array('key' => 'legacy:' . $idx, 'entry' => $e, 'src' => 'legacy', 'path' => $legacy);
                    }
                }
            }
        }
        return $items;
    }

    private function cs_archive_file($path, $ok) {
        $b = $this->cs_import_dir();
        $sub = $ok ? 'imported/' : 'failed/';
        @rename($path, $b . $sub . date('Ymd-His') . '-' . basename($path));
    }

    public function render_import_page() {
        $pending = $this->cs_collect_pending();
        $dir_disp = 'includes/data/import/case-studies/';
        ?>
        <div class="wrap">
            <h1>Import Our Work (Case Studies)</h1>
            <?php
            if (isset($_GET['imported'])) {
                echo '<div class="notice notice-success"><p><strong>' . intval($_GET['imported']) . '</strong> created.</p></div>';
            }
            if (isset($_GET['updated'])) {
                echo '<div class="notice notice-info"><p><strong>' . intval($_GET['updated']) . '</strong> updated.</p></div>';
            }
            if (isset($_GET['skipped'])) {
                echo '<div class="notice notice-warning"><p><strong>' . intval($_GET['skipped']) . '</strong> skipped (errors).</p></div>';
            }

            if (empty($pending)) {
                echo '<div class="notice notice-info"><p><strong>Inbox empty.</strong> Drop one JSON file per entry into <code>' . esc_html($dir_disp) . '</code> (name it after the slug, e.g. <code>chalet-market.json</code>). Only pending files appear here; each moves to <code>imported/</code> after a successful import, so this list never grows past your new work.</p></div>';
            } else {
                echo '<p>Found <strong>' . count($pending) . '</strong> pending entr' . (count($pending) === 1 ? 'y' : 'ies') . ' in <code>' . esc_html($dir_disp) . '</code>. Existing slugs are <strong>updated</strong>; new slugs are created as drafts. Imported files move to <code>imported/</code> and leave this list. Failed files move to <code>failed/</code>.</p>';
                ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="requestdesk_import_case_studies">
                    <?php wp_nonce_field('requestdesk_import_case_studies', 'requestdesk_cs_import_nonce'); ?>
                    <table class="widefat striped">
                        <thead><tr><th style="width:30px;"><input type="checkbox" id="cs-check-all" checked></th><th>File</th><th>Slug</th><th>Title</th><th>Client</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($pending as $it) :
                            $c = $it['entry'];
                            $slug = $c['slug'] ?? '';
                            $existing = $slug ? get_page_by_path($slug, OBJECT, 'cc_case_study') : null;
                            $status = $existing ? '<span style="color:#0073aa">Will update</span>' : '<span style="color:#46b450">Will create</span>';
                            $fname = ($it['src'] === 'file') ? basename($it['path']) : 'legacy array #' . substr($it['key'], 7);
                        ?>
                            <tr>
                                <td><input type="checkbox" name="import_items[]" value="<?php echo esc_attr($it['key']); ?>" checked></td>
                                <td><code><?php echo esc_html($fname); ?></code></td>
                                <td><code><?php echo esc_html($slug); ?></code></td>
                                <td><?php echo esc_html($c['title'] ?? ''); ?></td>
                                <td><?php echo esc_html($c['client_name'] ?? ''); ?></td>
                                <td><?php echo $status; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary">Run Import (selected)</button>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=cc_case_study')); ?>" class="button">Cancel</a>
                    </p>
                </form>
                <script>jQuery(function($){$('#cs-check-all').on('change',function(){$('input[name="import_items[]"]').prop('checked',this.checked);});});</script>
                <?php
            }
            ?>

            <h2 style="margin-top:40px">JSON Schema (one object per file)</h2>
            <p>Each file in the import directory holds <strong>one</strong> case study object (a single-element array is also accepted). Top-level fields:</p>
            <pre style="background:#f6f7f7;padding:16px;border-radius:4px;overflow:auto;font-size:12px">{
  "slug":              "chalet-market",
  "title":             "How Chalet Market Grew Organic Traffic 105%",
  "status":            "draft" | "publish",
  "excerpt":           "...",
  "content":           "Optional body content (Markdown / HTML)",
  "client_name":       "Chalet Market",
  "client_url":        "https://chaletmarket.com",
  "client_logo":       "/wp-content/uploads/.../logo.png",
  "featured_image":    "/wp-content/uploads/.../hero.jpg",
  "length":            "Ongoing",
  "type":              "Retainer",
  "date_started":      "2024-03-01",
  "date_published":    "2026-04-28",
  "last_verified":     "2026-04-28",
  "challenge":         "...",
  "approach":          "...",
  "results":           "...",
  "stats": [
    { "value": "+105%", "label": "Organic Traffic" },
    { "value": "6 months", "label": "Time to Results" }
  ],
  "quote":             "...",
  "quote_name":        "...",
  "quote_title":       "...",
  "quote_company":     "...",
  "quote_photo":       "/wp-content/uploads/.../headshot.jpg",
  "aeo_summary":       "3-sentence summary for AI crawlers",
  "team_writer":       "...",
  "team_editor":       "...",
  "pinned":            true,
  "display_priority":  0,
  "seo_title":         "...",
  "seo_description":   "...",
  "industries":        ["Gourmet Food", "Specialty Food"],
  "platforms":         ["Shopify"],
  "services":          ["Blog Writing", "SEO Content"],
  "outcomes":          ["Traffic Growth"],
  "sizes":             ["SMB"],
  "years":             ["2024"]
}</pre>
        </div>
        <?php
    }

    public function handle_import() {
        if (!current_user_can('manage_options')) wp_die('Insufficient permissions');
        if (!isset($_POST['requestdesk_cs_import_nonce']) || !wp_verify_nonce($_POST['requestdesk_cs_import_nonce'], 'requestdesk_import_case_studies')) {
            wp_die('Invalid nonce');
        }

        $selected = isset($_POST['import_items']) ? (array) $_POST['import_items'] : array();
        $selected = array_map('sanitize_text_field', $selected);
        $pending  = $this->cs_collect_pending();

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $legacy_total = 0;
        $legacy_done  = 0;
        $legacy_path  = null;

        foreach ($pending as $it) {
            if ($it['src'] === 'legacy') { $legacy_total++; $legacy_path = $it['path']; }
            if (!in_array($it['key'], $selected, true)) { continue; }

            $c = $it['entry'];
            $ok = false;
            if (!empty($c['slug']) && !empty($c['title'])) {
                $res = $this->cs_import_entry($c);
                if ($res === 'created') { $created++; $ok = true; }
                elseif ($res === 'updated') { $updated++; $ok = true; }
                else { $skipped++; }
            } else {
                $skipped++;
            }

            if ($it['src'] === 'file') {
                $this->cs_archive_file($it['path'], $ok);
            } elseif ($it['src'] === 'legacy' && $ok) {
                $legacy_done++;
            }
        }

        // Archive the legacy array file only once every one of its entries
        // has been imported this run (partial selection leaves it in place).
        if ($legacy_path && $legacy_total > 0 && $legacy_done >= $legacy_total && file_exists($legacy_path)) {
            $b = $this->cs_import_dir();
            if (!is_dir($b . 'imported/')) { wp_mkdir_p($b . 'imported/'); }
            @rename($legacy_path, $b . 'imported/' . date('Ymd-His') . '-legacy-' . basename($legacy_path));
        }

        wp_redirect(admin_url('edit.php?post_type=cc_case_study&page=requestdesk-case-study-import&imported=' . $created . '&updated=' . $updated . '&skipped=' . $skipped));
        exit;
    }

    /**
     * Import (create or update) ONE case study entry. Returns 'created',
     * 'updated', or false on error. All meta/taxonomy/featured-image logic
     * is preserved verbatim from the original array importer.
     */
    private function cs_import_entry($c) {
            $existing = get_page_by_path($c['slug'], OBJECT, 'cc_case_study');
            $post_data = array(
                'post_type'    => 'cc_case_study',
                'post_status'  => $c['status'] ?? 'draft',
                'post_title'   => $c['title'],
                'post_name'    => $c['slug'],
                'post_excerpt' => $c['excerpt'] ?? '',
                'post_content' => $c['content'] ?? '',
            );

            if ($existing) {
                $post_data['ID'] = $existing->ID;
                $post_id = wp_update_post($post_data, true);
                $result = 'updated';
            } else {
                $post_id = wp_insert_post($post_data, true);
                $result = 'created';
            }
            if (is_wp_error($post_id) || !$post_id) { return false; }

            // Meta fields
            $meta_map = array(
                '_cc_cs_client_name'      => $c['client_name'] ?? '',
                '_cc_cs_client_url'       => $c['client_url'] ?? '',
                '_cc_cs_client_logo'      => $c['client_logo'] ?? '',
                '_cc_cs_length'           => $c['length'] ?? '',
                '_cc_cs_type'             => $c['type'] ?? '',
                '_cc_cs_date_started'     => $c['date_started'] ?? '',
                '_cc_cs_date_published'   => $c['date_published'] ?? '',
                '_cc_cs_last_verified'    => $c['last_verified'] ?? '',
                '_cc_cs_challenge'        => $c['challenge'] ?? '',
                '_cc_cs_approach'         => $c['approach'] ?? '',
                '_cc_cs_results'          => $c['results'] ?? '',
                '_cc_cs_quote'            => $c['quote'] ?? '',
                '_cc_cs_quote_name'       => $c['quote_name'] ?? '',
                '_cc_cs_quote_title'      => $c['quote_title'] ?? '',
                '_cc_cs_quote_company'    => $c['quote_company'] ?? '',
                '_cc_cs_quote_photo'      => $c['quote_photo'] ?? '',
                '_cc_cs_aeo_summary'      => $c['aeo_summary'] ?? '',
                '_cc_cs_team_writer'      => $c['team_writer'] ?? '',
                '_cc_cs_team_editor'      => $c['team_editor'] ?? '',
                '_cc_cs_pinned'           => !empty($c['pinned']) ? '1' : '0',
                '_cc_cs_display_priority' => isset($c['display_priority']) ? intval($c['display_priority']) : 0,
                '_cc_cs_seo_title'        => $c['seo_title'] ?? '',
                '_cc_cs_seo_description'  => $c['seo_description'] ?? '',
            );
            foreach ($meta_map as $k => $v) update_post_meta($post_id, $k, $v);

            // Stats array
            if (!empty($c['stats']) && is_array($c['stats'])) {
                update_post_meta($post_id, '_cc_cs_stats', $c['stats']);
            }

            // Taxonomies (auto-create terms if missing)
            $tax_map = array(
                'case_study_industry' => $c['industries'] ?? array(),
                'case_study_platform' => $c['platforms']  ?? array(),
                'case_study_service'  => $c['services']   ?? array(),
                'case_study_outcome'  => $c['outcomes']   ?? array(),
                'case_study_size'     => $c['sizes']      ?? array(),
                'case_study_year'     => $c['years']      ?? array(),
            );
            foreach ($tax_map as $tax => $terms) {
                if (!empty($terms) && is_array($terms)) {
                    wp_set_object_terms($post_id, $terms, $tax, false);
                }
            }

            // Featured image — if a /wp-content URL is given, find the matching attachment
            if (!empty($c['featured_image'])) {
                $url = $c['featured_image'];
                $attachment_id = attachment_url_to_postid($url);
                if (!$attachment_id) {
                    // Try with full URL
                    $full = home_url($url);
                    $attachment_id = attachment_url_to_postid($full);
                }
                if ($attachment_id) set_post_thumbnail($post_id, $attachment_id);
            }

        return $result;
    }
}

new RequestDesk_Case_Study();
