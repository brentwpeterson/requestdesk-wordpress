<?php
/**
 * RequestDesk Child Grid Shortcode
 *
 * Displays child pages of the current page in a responsive grid layout.
 * Uses CSS classes from the cucumber-gp-child theme for styling.
 *
 * Usage: [requestdesk_child_grid columns="3"]
 *
 * @package RequestDesk
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Child_Grid {

    public function __construct() {
        add_shortcode('requestdesk_child_grid', array($this, 'child_grid_shortcode'));
    }

    /**
     * Render child pages grid
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function child_grid_shortcode($atts) {
        $atts = shortcode_atts(array(
            'columns'   => 3,
            'parent_id' => 0,
            'orderby'   => 'menu_order',
            'order'     => 'ASC',
            'card_type' => '', // 'category' or 'service' - auto-detected if empty
        ), $atts, 'requestdesk_child_grid');

        $columns = intval($atts['columns']);
        if ($columns < 1 || $columns > 4) {
            $columns = 3;
        }

        $parent_id = intval($atts['parent_id']);
        if ($parent_id === 0) {
            $parent_id = get_the_ID();
        }

        if (!$parent_id) {
            return '';
        }

        // Get child pages
        $children = get_pages(array(
            'parent'      => $parent_id,
            'sort_column' => $atts['orderby'],
            'sort_order'  => $atts['order'],
            'post_status' => 'publish',
        ));

        if (empty($children)) {
            return $this->render_empty_state();
        }

        // Auto-detect card type based on page depth
        $card_type = $atts['card_type'];
        if (empty($card_type)) {
            $parent_depth = count(get_post_ancestors($parent_id));
            $card_type = ($parent_depth === 0) ? 'category' : 'service';
        }

        // Build grid HTML
        $grid_classes = 'requestdesk-child-grid requestdesk-grid--cols-' . $columns;

        $output = '<div class="' . esc_attr($grid_classes) . '">';

        foreach ($children as $child) {
            $output .= $this->render_card($child, $card_type);
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render a single card
     *
     * @param WP_Post $page The child page
     * @param string $card_type 'category' or 'service'
     * @return string HTML for the card
     */
    private function render_card($page, $card_type) {
        // Use redirect URL if set, otherwise use the child page permalink
        $redirect_url = get_post_meta($page->ID, '_requestdesk_redirect_url', true);
        $permalink = !empty($redirect_url) ? $redirect_url : get_permalink($page->ID);
        $title = get_the_title($page->ID);
        $excerpt = $page->post_excerpt;

        // Fall back to trimmed content if no excerpt
        if (empty($excerpt)) {
            $excerpt = wp_trim_words(strip_shortcodes($page->post_content), 25, '...');
        }

        // Check for featured card meta
        $is_featured = get_post_meta($page->ID, '_requestdesk_featured', true);

        // Check for icon meta
        $icon = get_post_meta($page->ID, '_requestdesk_icon', true);

        $card_classes = 'requestdesk-card requestdesk-card--' . esc_attr($card_type);
        if ($is_featured) {
            $card_classes .= ' requestdesk-card--featured';
        }

        $cta_text = ($card_type === 'category') ? 'Explore Services' : 'Learn More';
        $cta_class = ($card_type === 'category') ? 'requestdesk-card__cta--primary' : 'requestdesk-card__cta--outline';
        $icon_class = ($card_type === 'category') ? 'requestdesk-card__icon--large' : 'requestdesk-card__icon';
        $title_class = ($card_type === 'category') ? 'requestdesk-card__title requestdesk-card__title--large' : 'requestdesk-card__title';

        $output = '<div class="' . esc_attr($card_classes) . '">';

        if (!empty($icon)) {
            $output .= '<div class="' . esc_attr($icon_class) . '">' . esc_html($icon) . '</div>';
        }

        $output .= '<h3 class="' . esc_attr($title_class) . '">' . esc_html($title) . '</h3>';

        if (!empty($excerpt)) {
            $output .= '<p class="requestdesk-card__description">' . esc_html($excerpt) . '</p>';
        }

        $output .= '<a href="' . esc_url($permalink) . '" class="requestdesk-card__cta ' . esc_attr($cta_class) . '">' . $cta_text . '</a>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render empty state when no child pages found
     *
     * @return string HTML for empty state
     */
    private function render_empty_state() {
        $output = '<div class="requestdesk-grid-empty">';
        $output .= '<p class="requestdesk-grid-empty__title">No child pages found.</p>';
        $output .= '<p class="requestdesk-grid-empty__text">Add child pages to this page to see them displayed here.</p>';
        $output .= '</div>';
        return $output;
    }
}

// Initialize
new RequestDesk_Child_Grid();
