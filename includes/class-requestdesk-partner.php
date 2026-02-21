<?php
/**
 * RequestDesk Partner Directory
 *
 * Registers the cc_partner custom post type with built-in category taxonomy,
 * meta boxes for partner details, and a reusable card render method.
 *
 * @package RequestDesk
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Partner {

    public function __construct() {
        add_action('init', array($this, 'register_cpt'));
        add_action('init', array($this, 'add_partner_rewrite_rules'));
        add_filter('query_vars', array($this, 'register_partner_query_vars'));
        add_filter('request', array($this, 'resolve_partner_request'));
        add_filter('template_include', array($this, 'partner_category_template'), 99);
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_cc_partner', array($this, 'save_meta'), 10, 2);
        add_action('admin_menu', array($this, 'add_import_page'));
        add_action('admin_post_requestdesk_import_partners', array($this, 'handle_import'));
    }

    /**
     * Register cc_partner post type with built-in category taxonomy
     */
    public function register_cpt() {
        $labels = array(
            'name'               => 'Partners',
            'singular_name'      => 'Partner',
            'menu_name'          => 'Partners',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Partner',
            'edit_item'          => 'Edit Partner',
            'new_item'           => 'New Partner',
            'view_item'          => 'View Partner',
            'search_items'       => 'Search Partners',
            'not_found'          => 'No partners found',
            'not_found_in_trash' => 'No partners found in Trash',
            'all_items'          => 'All Partners',
        );

        register_post_type('cc_partner', array(
            'labels'       => $labels,
            'public'       => true,
            'has_archive'  => true,
            'rewrite'      => array('slug' => 'partners', 'with_front' => false),
            'supports'     => array('title', 'editor', 'thumbnail', 'excerpt'),
            'taxonomies'   => array('category'),
            'menu_icon'    => 'dashicons-groups',
            'menu_position' => 25,
            'show_in_rest' => true,
        ));
    }

    /**
     * Add rewrite rules for /partners/{category-slug}/ URLs
     */
    public function add_partner_rewrite_rules() {
        add_rewrite_rule(
            'partners/([^/]+)/page/([0-9]+)/?$',
            'index.php?partner_cat_filter=$matches[1]&paged=$matches[2]',
            'top'
        );
        add_rewrite_rule(
            'partners/([^/]+)/?$',
            'index.php?partner_cat_filter=$matches[1]',
            'top'
        );
    }

    /**
     * Register custom query var for partner category filtering
     */
    public function register_partner_query_vars($vars) {
        $vars[] = 'partner_cat_filter';
        $vars[] = 'partner_category_id';
        return $vars;
    }

    /**
     * Resolve /partners/{slug}/ at the request level before WP_Query runs.
     * If slug matches a partner category, set up a category archive query.
     * Otherwise, hand it back to WordPress as a normal single partner post.
     */
    public function resolve_partner_request($query_vars) {
        if (empty($query_vars['partner_cat_filter'])) {
            return $query_vars;
        }

        $slug = $query_vars['partner_cat_filter'];
        unset($query_vars['partner_cat_filter']);

        // Get the "Partners" parent category
        $partners_parent = get_term_by('slug', 'partners', 'category');
        $parent_id = $partners_parent ? $partners_parent->term_id : 0;

        // Check if slug matches a child category under "Partners"
        $term = get_term_by('slug', $slug, 'category');
        if ($term && (int) $term->parent === (int) $parent_id) {
            // It's a partner category - set up as a post type archive with a custom var.
            // Do NOT set 'cat' here - that triggers WP's category template hierarchy
            // and GP Elements for category archives, causing duplicate content.
            $query_vars['post_type'] = 'cc_partner';
            $query_vars['partner_category_id'] = $term->term_id;
            return $query_vars;
        }

        // Not a category - treat as single partner post
        $query_vars['cc_partner'] = $slug;
        $query_vars['post_type'] = 'cc_partner';
        $query_vars['name'] = $slug;
        return $query_vars;
    }

    /**
     * Use the archive template for partner category pages
     */
    public function partner_category_template($template) {
        global $wp_query;

        // Check for partner category pages using our custom query var.
        // We avoid using WP's 'cat' var to prevent the category template hierarchy
        // and GP Elements from injecting default category content.
        $partner_cat = $wp_query->get('partner_category_id');

        if (!empty($partner_cat)) {
            $cat_template = locate_template('archive-cc_partner-category.php');
            if ($cat_template) {
                return $cat_template;
            }
            $archive_template = locate_template('archive-cc_partner.php');
            if ($archive_template) {
                return $archive_template;
            }
        }

        return $template;
    }

    /**
     * Register partner detail meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'requestdesk_partner_details',
            'Partner Details',
            array($this, 'render_meta_box'),
            'cc_partner',
            'normal',
            'high'
        );
    }

    /**
     * Render partner detail fields in the editor
     */
    public function render_meta_box($post) {
        wp_nonce_field('requestdesk_partner_save', 'requestdesk_partner_nonce');

        $logo       = get_post_meta($post->ID, '_requestdesk_partner_logo', true);
        $website    = get_post_meta($post->ID, '_requestdesk_partner_website', true);
        $tier       = get_post_meta($post->ID, '_requestdesk_partner_tier', true);
        $featured   = get_post_meta($post->ID, '_requestdesk_partner_featured', true);
        $cta_text   = get_post_meta($post->ID, '_requestdesk_partner_cta_text', true);
        $cta_url    = get_post_meta($post->ID, '_requestdesk_partner_cta_url', true);
        $hs_company = get_post_meta($post->ID, '_requestdesk_partner_hubspot_company_id', true);
        $hs_form    = get_post_meta($post->ID, '_requestdesk_partner_hubspot_form_id', true);
        $tagline    = get_post_meta($post->ID, '_requestdesk_partner_tagline', true);
        $overlay    = get_post_meta($post->ID, '_requestdesk_partner_hero_overlay', true);
        $hero_img   = get_post_meta($post->ID, '_requestdesk_partner_hero_image', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="requestdesk_partner_logo">Partner Logo</label></th>
                <td>
                    <input type="hidden" name="_requestdesk_partner_logo" id="requestdesk_partner_logo" value="<?php echo esc_attr($logo); ?>" />
                    <div id="requestdesk_partner_logo_preview" style="margin-bottom: 8px;">
                        <?php if ($logo) : ?>
                            <img src="<?php echo esc_url(wp_get_attachment_url($logo)); ?>" style="max-width: 200px; height: auto;" />
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button" id="requestdesk_partner_logo_btn">Select Logo</button>
                    <?php if ($logo) : ?>
                        <button type="button" class="button" id="requestdesk_partner_logo_remove">Remove</button>
                    <?php endif; ?>
                    <script>
                    jQuery(document).ready(function($) {
                        var frame;
                        $('#requestdesk_partner_logo_btn').on('click', function(e) {
                            e.preventDefault();
                            if (frame) { frame.open(); return; }
                            frame = wp.media({ title: 'Select Partner Logo', multiple: false });
                            frame.on('select', function() {
                                var attachment = frame.state().get('selection').first().toJSON();
                                $('#requestdesk_partner_logo').val(attachment.id);
                                var imgUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                                $('#requestdesk_partner_logo_preview').html('<img src="' + imgUrl + '" style="max-width: 200px; height: auto;" />');
                            });
                            frame.open();
                        });
                        $('#requestdesk_partner_logo_remove').on('click', function(e) {
                            e.preventDefault();
                            $('#requestdesk_partner_logo').val('');
                            $('#requestdesk_partner_logo_preview').html('');
                        });
                    });
                    </script>
                </td>
            </tr>
            <tr>
                <th><label for="requestdesk_partner_website">Website URL</label></th>
                <td>
                    <input type="url" name="_requestdesk_partner_website" id="requestdesk_partner_website"
                           value="<?php echo esc_attr($website); ?>" class="regular-text" placeholder="https://example.com" />
                </td>
            </tr>
            <tr>
                <th><label for="requestdesk_partner_tier">Tier Level</label></th>
                <td>
                    <select name="_requestdesk_partner_tier" id="requestdesk_partner_tier">
                        <option value="">-- Select Tier --</option>
                        <option value="strategic" <?php selected($tier, 'strategic'); ?>>Strategic</option>
                        <option value="growth" <?php selected($tier, 'growth'); ?>>Growth</option>
                        <option value="emerging" <?php selected($tier, 'emerging'); ?>>Emerging</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="requestdesk_partner_featured">Featured Partner</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="_requestdesk_partner_featured" id="requestdesk_partner_featured"
                               value="1" <?php checked($featured, '1'); ?> />
                        Highlight this partner in the directory
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="requestdesk_partner_cta_text">CTA Button Text</label></th>
                <td>
                    <input type="text" name="_requestdesk_partner_cta_text" id="requestdesk_partner_cta_text"
                           value="<?php echo esc_attr($cta_text); ?>" class="regular-text" placeholder="Visit Partner" />
                </td>
            </tr>
            <tr>
                <th><label for="requestdesk_partner_cta_url">CTA Button URL</label></th>
                <td>
                    <input type="url" name="_requestdesk_partner_cta_url" id="requestdesk_partner_cta_url"
                           value="<?php echo esc_attr($cta_url); ?>" class="regular-text" placeholder="Leave blank to use website URL" />
                </td>
            </tr>
            <tr>
                <td colspan="2"><hr /><strong>HubSpot Integration</strong></td>
            </tr>
            <tr>
                <th><label for="requestdesk_partner_hubspot_company_id">HubSpot Company ID</label></th>
                <td>
                    <input type="text" name="_requestdesk_partner_hubspot_company_id" id="requestdesk_partner_hubspot_company_id"
                           value="<?php echo esc_attr($hs_company); ?>" class="regular-text" placeholder="e.g. 12345678" />
                    <p class="description">The HubSpot Company record ID. Auto-passed as a hidden field when the contact form is submitted.</p>
                </td>
            </tr>
            <tr>
                <th><label for="requestdesk_partner_hubspot_form_id">HubSpot Form ID</label></th>
                <td>
                    <input type="text" name="_requestdesk_partner_hubspot_form_id" id="requestdesk_partner_hubspot_form_id"
                           value="<?php echo esc_attr($hs_form); ?>" class="regular-text" placeholder="e.g. 3c945309-67c6-4812-ab65-c7280682e005" />
                    <p class="description">Optional. Leave blank to use the site default partner form.</p>
                </td>
            </tr>
            <tr>
                <td colspan="2"><hr /><strong>Hero &amp; Display</strong></td>
            </tr>
            <tr>
                <th><label for="requestdesk_partner_hero_image">Hero Image</label></th>
                <td>
                    <input type="hidden" name="_requestdesk_partner_hero_image" id="requestdesk_partner_hero_image" value="<?php echo esc_attr($hero_img); ?>" />
                    <div id="requestdesk_partner_hero_preview" style="margin-bottom: 8px;">
                        <?php if ($hero_img) : ?>
                            <img src="<?php echo esc_url(wp_get_attachment_url($hero_img)); ?>" style="max-width: 400px; height: auto; border-radius: 4px;" />
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button" id="requestdesk_partner_hero_btn">Select Hero Image</button>
                    <?php if ($hero_img) : ?>
                        <button type="button" class="button" id="requestdesk_partner_hero_remove">Remove</button>
                    <?php endif; ?>
                    <p class="description">Wide landscape image for the full-bleed hero banner. Falls back to grey if not set.</p>
                    <script>
                    jQuery(document).ready(function($) {
                        var heroFrame;
                        $('#requestdesk_partner_hero_btn').on('click', function(e) {
                            e.preventDefault();
                            if (heroFrame) { heroFrame.open(); return; }
                            heroFrame = wp.media({ title: 'Select Hero Image', multiple: false });
                            heroFrame.on('select', function() {
                                var attachment = heroFrame.state().get('selection').first().toJSON();
                                $('#requestdesk_partner_hero_image').val(attachment.id);
                                var imgUrl = attachment.sizes && attachment.sizes.large ? attachment.sizes.large.url : attachment.url;
                                $('#requestdesk_partner_hero_preview').html('<img src="' + imgUrl + '" style="max-width: 400px; height: auto; border-radius: 4px;" />');
                            });
                            heroFrame.open();
                        });
                        $('#requestdesk_partner_hero_remove').on('click', function(e) {
                            e.preventDefault();
                            $('#requestdesk_partner_hero_image').val('');
                            $('#requestdesk_partner_hero_preview').html('');
                        });
                    });
                    </script>
                </td>
            </tr>
            <tr>
                <th><label for="requestdesk_partner_tagline">Hero Tagline</label></th>
                <td>
                    <input type="text" name="_requestdesk_partner_tagline" id="requestdesk_partner_tagline"
                           value="<?php echo esc_attr($tagline); ?>" class="large-text" placeholder="e.g. Powering enterprise ecommerce together" />
                    <p class="description">Displayed below the partner name in the hero banner.</p>
                </td>
            </tr>
            <tr>
                <th><label for="requestdesk_partner_hero_overlay">Hero Overlay Color</label></th>
                <td>
                    <input type="text" name="_requestdesk_partner_hero_overlay" id="requestdesk_partner_hero_overlay"
                           value="<?php echo esc_attr($overlay); ?>" class="regular-text" placeholder="#1e3a5f" />
                    <p class="description">Hex color for the hero image tint. Leave blank for default dark grey.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save partner meta on post save
     */
    public function save_meta($post_id, $post) {
        if (!isset($_POST['requestdesk_partner_nonce']) ||
            !wp_verify_nonce($_POST['requestdesk_partner_nonce'], 'requestdesk_partner_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = array(
            '_requestdesk_partner_logo'     => 'intval',
            '_requestdesk_partner_website'  => 'esc_url_raw',
            '_requestdesk_partner_tier'     => 'sanitize_text_field',
            '_requestdesk_partner_cta_text'             => 'sanitize_text_field',
            '_requestdesk_partner_cta_url'              => 'esc_url_raw',
            '_requestdesk_partner_hubspot_company_id'   => 'sanitize_text_field',
            '_requestdesk_partner_hubspot_form_id'      => 'sanitize_text_field',
            '_requestdesk_partner_tagline'              => 'sanitize_text_field',
            '_requestdesk_partner_hero_overlay'         => 'sanitize_hex_color',
            '_requestdesk_partner_hero_image'           => 'intval',
        );

        foreach ($fields as $key => $sanitize) {
            if (isset($_POST[$key])) {
                $value = call_user_func($sanitize, $_POST[$key]);
                update_post_meta($post_id, $key, $value);
            }
        }

        // Checkbox: save 1 or delete
        if (!empty($_POST['_requestdesk_partner_featured'])) {
            update_post_meta($post_id, '_requestdesk_partner_featured', '1');
        } else {
            delete_post_meta($post_id, '_requestdesk_partner_featured');
        }
    }

    /**
     * Add Import Partners submenu page
     */
    public function add_import_page() {
        add_submenu_page(
            'edit.php?post_type=cc_partner',
            'Import Partners',
            'Import Partners',
            'manage_options',
            'requestdesk-partner-import',
            array($this, 'render_import_page')
        );
    }

    /**
     * Render the Import Partners admin page
     */
    public function render_import_page() {
        $json_path = plugin_dir_path(__FILE__) . 'data/partners-import.json';

        if (!file_exists($json_path)) {
            echo '<div class="wrap"><h1>Import Partners</h1>';
            echo '<div class="notice notice-error"><p>Import file not found: ' . esc_html($json_path) . '</p></div></div>';
            return;
        }

        $partners = json_decode(file_get_contents($json_path), true);
        if (!$partners) {
            echo '<div class="wrap"><h1>Import Partners</h1>';
            echo '<div class="notice notice-error"><p>Could not parse import JSON.</p></div></div>';
            return;
        }

        // Check which partners already exist
        $existing = get_posts(array(
            'post_type'      => 'cc_partner',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ));
        $existing_titles = array();
        foreach ($existing as $pid) {
            $existing_titles[] = strtolower(get_the_title($pid));
        }

        // Show results message if redirected after import
        if (isset($_GET['imported'])) {
            $count = intval($_GET['imported']);
            echo '<div class="notice notice-success"><p>' . $count . ' partner(s) imported as drafts.</p></div>';
        }
        if (isset($_GET['skipped'])) {
            $count = intval($_GET['skipped']);
            echo '<div class="notice notice-warning"><p>' . $count . ' partner(s) skipped (already exist).</p></div>';
        }

        ?>
        <div class="wrap">
            <h1>Import Partners</h1>
            <p>Preview partners from <code>includes/data/partners-import.json</code>. Partners already in the system will be skipped.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="requestdesk_import_partners" />
                <?php wp_nonce_field('requestdesk_import_partners', 'requestdesk_import_nonce'); ?>

                <table class="widefat striped" style="max-width: 900px;">
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" id="check-all" checked /></th>
                            <th>Name</th>
                            <th>Website</th>
                            <th>Logo</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partners as $i => $partner) :
                            $already_exists = in_array(strtolower($partner['name']), $existing_titles);
                        ?>
                        <tr<?php echo $already_exists ? ' style="opacity:0.5;"' : ''; ?>>
                            <td>
                                <?php if (!$already_exists) : ?>
                                    <input type="checkbox" name="import_partners[]" value="<?php echo $i; ?>" checked />
                                <?php else : ?>
                                    <span title="Already exists">&#10003;</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($partner['name']); ?></strong></td>
                            <td><a href="<?php echo esc_url($partner['website']); ?>" target="_blank"><?php echo esc_html(parse_url($partner['website'], PHP_URL_HOST)); ?></a></td>
                            <td><?php echo $partner['logo_file'] ? esc_html($partner['logo_file']) : '<em>none</em>'; ?></td>
                            <td><?php echo $already_exists ? '<span style="color:green;">Exists</span>' : '<span style="color:orange;">New</span>'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top: 16px;">
                    <button type="submit" class="button button-primary">Import Selected as Drafts</button>
                </p>
            </form>

            <script>
            jQuery(document).ready(function($) {
                $('#check-all').on('change', function() {
                    $('input[name="import_partners[]"]').prop('checked', this.checked);
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Handle partner import form submission
     */
    public function handle_import() {
        if (!current_user_can('manage_options') ||
            !isset($_POST['requestdesk_import_nonce']) ||
            !wp_verify_nonce($_POST['requestdesk_import_nonce'], 'requestdesk_import_partners')) {
            wp_die('Unauthorized');
        }

        $selected = isset($_POST['import_partners']) ? array_map('intval', $_POST['import_partners']) : array();

        if (empty($selected)) {
            wp_redirect(admin_url('edit.php?post_type=cc_partner&page=requestdesk-partner-import&imported=0'));
            exit;
        }

        $json_path = plugin_dir_path(__FILE__) . 'data/partners-import.json';
        $partners = json_decode(file_get_contents($json_path), true);

        $imported = 0;
        $skipped = 0;
        $logo_dir = plugin_dir_path(__FILE__) . 'data/logos/';

        foreach ($selected as $index) {
            if (!isset($partners[$index])) {
                continue;
            }

            $partner = $partners[$index];

            // Check if already exists
            $existing = get_posts(array(
                'post_type'      => 'cc_partner',
                'title'          => $partner['name'],
                'posts_per_page' => 1,
                'post_status'    => 'any',
            ));

            if (!empty($existing)) {
                $skipped++;
                continue;
            }

            // Create the partner post
            $post_content = '';
            if (!empty($partner['content'])) {
                $post_content = wp_kses_post($partner['content']);
            }

            $post_id = wp_insert_post(array(
                'post_title'   => sanitize_text_field($partner['name']),
                'post_content' => $post_content,
                'post_excerpt' => sanitize_textarea_field($partner['excerpt']),
                'post_status'  => 'draft',
                'post_type'    => 'cc_partner',
            ));

            if (is_wp_error($post_id)) {
                continue;
            }

            // Set website meta
            if (!empty($partner['website'])) {
                update_post_meta($post_id, '_requestdesk_partner_website', esc_url_raw($partner['website']));
            }

            // Set tagline if provided
            if (!empty($partner['tagline'])) {
                update_post_meta($post_id, '_requestdesk_partner_tagline', sanitize_text_field($partner['tagline']));
            }

            // Set hero overlay color if provided
            if (!empty($partner['hero_overlay'])) {
                update_post_meta($post_id, '_requestdesk_partner_hero_overlay', sanitize_hex_color($partner['hero_overlay']));
            }

            // Upload and attach logo if file exists
            if (!empty($partner['logo_file'])) {
                $logo_path = $logo_dir . $partner['logo_file'];
                if (file_exists($logo_path)) {
                    $attachment_id = $this->upload_logo($logo_path, $post_id, $partner['name']);
                    if ($attachment_id) {
                        update_post_meta($post_id, '_requestdesk_partner_logo', $attachment_id);
                    }
                }
            }

            $imported++;
        }

        wp_redirect(admin_url('edit.php?post_type=cc_partner&page=requestdesk-partner-import&imported=' . $imported . '&skipped=' . $skipped));
        exit;
    }

    /**
     * Upload a logo file to the media library and attach to a partner post
     */
    private function upload_logo($file_path, $post_id, $partner_name) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $file_name = basename($file_path);
        $file_type = wp_check_filetype($file_name);

        // Copy file to uploads directory
        $upload_dir = wp_upload_dir();
        $dest_path = $upload_dir['path'] . '/' . $file_name;

        if (!copy($file_path, $dest_path)) {
            return false;
        }

        $attachment = array(
            'post_mime_type' => $file_type['type'],
            'post_title'     => sanitize_text_field($partner_name . ' Logo'),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attachment_id = wp_insert_attachment($attachment, $dest_path, $post_id);

        if (is_wp_error($attachment_id)) {
            return false;
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $dest_path);
        wp_update_attachment_metadata($attachment_id, $metadata);

        return $attachment_id;
    }

    /**
     * Render a partner card (reusable across templates)
     *
     * @param WP_Post $post The partner post object
     */
    public static function render_card($post) {
        $logo_id  = get_post_meta($post->ID, '_requestdesk_partner_logo', true);
        $website  = get_post_meta($post->ID, '_requestdesk_partner_website', true);
        $tier     = get_post_meta($post->ID, '_requestdesk_partner_tier', true);
        $featured = get_post_meta($post->ID, '_requestdesk_partner_featured', true);
        $cta_text = get_post_meta($post->ID, '_requestdesk_partner_cta_text', true);
        $cta_url  = get_post_meta($post->ID, '_requestdesk_partner_cta_url', true);

        if (empty($cta_text)) {
            $cta_text = 'Visit Partner';
        }
        if (empty($cta_url)) {
            $cta_url = $website;
        }

        $categories = get_the_terms($post->ID, 'category');
        $card_class = 'cc-partner-card';
        if ($featured) {
            $card_class .= ' cc-partner-card--featured';
        }
        if ($tier) {
            $card_class .= ' cc-partner-card--' . esc_attr($tier);
        }
        ?>
        <div class="<?php echo esc_attr($card_class); ?>">
            <?php if ($logo_id) : ?>
                <div class="cc-partner-card__logo">
                    <?php echo wp_get_attachment_image($logo_id, 'medium', false, array('class' => 'cc-partner-card__logo-img')); ?>
                </div>
            <?php endif; ?>

            <h3 class="cc-partner-card__name">
                <a href="<?php echo esc_url(get_permalink($post->ID)); ?>"><?php echo esc_html($post->post_title); ?></a>
            </h3>

            <?php if ($categories && !is_wp_error($categories)) : ?>
                <span class="cc-partner-card__category"><?php echo esc_html($categories[0]->name); ?></span>
            <?php endif; ?>

            <?php if ($tier) : ?>
                <span class="cc-partner-tier-badge cc-partner-tier-badge--<?php echo esc_attr($tier); ?>">
                    <?php echo esc_html(ucfirst($tier)); ?>
                </span>
            <?php endif; ?>

            <?php
            $excerpt = get_the_excerpt($post);
            if (!empty($excerpt)) : ?>
                <p class="cc-partner-card__excerpt"><?php echo esc_html(wp_trim_words($excerpt, 20, '...')); ?></p>
            <?php endif; ?>

            <?php if (!empty($cta_url)) : ?>
                <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" class="cc-partner-card__cta">
                    Learn More
                </a>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Initialize
new RequestDesk_Partner();
