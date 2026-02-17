<?php
/**
 * RequestDesk Partner Directory
 *
 * Registers the cc_partner custom post type, cc_partner_category taxonomy,
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
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_cc_partner', array($this, 'save_meta'), 10, 2);
    }

    /**
     * Register cc_partner post type and cc_partner_category taxonomy
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
            'menu_icon'    => 'dashicons-groups',
            'menu_position' => 25,
            'show_in_rest' => true,
        ));

        $cat_labels = array(
            'name'              => 'Partner Categories',
            'singular_name'     => 'Partner Category',
            'search_items'      => 'Search Categories',
            'all_items'         => 'All Categories',
            'parent_item'       => 'Parent Category',
            'parent_item_colon' => 'Parent Category:',
            'edit_item'         => 'Edit Category',
            'update_item'       => 'Update Category',
            'add_new_item'      => 'Add New Category',
            'new_item_name'     => 'New Category Name',
            'menu_name'         => 'Categories',
        );

        register_taxonomy('cc_partner_category', 'cc_partner', array(
            'labels'       => $cat_labels,
            'hierarchical' => true,
            'public'       => true,
            'rewrite'      => array('slug' => 'partner-category'),
            'show_in_rest' => true,
        ));
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
                        <option value="gold" <?php selected($tier, 'gold'); ?>>Gold</option>
                        <option value="silver" <?php selected($tier, 'silver'); ?>>Silver</option>
                        <option value="bronze" <?php selected($tier, 'bronze'); ?>>Bronze</option>
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

        $categories = get_the_terms($post->ID, 'cc_partner_category');
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
