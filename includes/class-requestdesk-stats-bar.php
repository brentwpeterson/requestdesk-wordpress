<?php
/**
 * RequestDesk Stats Bar Shortcode
 *
 * [requestdesk_stats_bar] - Full-width stats section with editable stat cards.
 *
 * Settings are managed via RequestDesk > Settings > Stats Bar tab.
 * Shortcode attributes override admin settings where specified.
 *
 * @package RequestDesk
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Stats_Bar {

    /** @var bool Track whether assets have been enqueued for this request */
    private $assets_enqueued = false;

    public function __construct() {
        add_shortcode('requestdesk_stats_bar', array($this, 'stats_bar_shortcode'));
    }

    /**
     * Get merged settings (admin defaults + shortcode attribute overrides)
     */
    private function get_settings($atts = array()) {
        $defaults = array(
            'stats' => array(
                array('value' => '60,000 +', 'label' => 'Projects Delivered', 'icon' => ''),
                array('value' => '55 Million +', 'label' => 'Words Written', 'icon' => ''),
                array('value' => '4.9/5', 'label' => 'Average Project Rating', 'icon' => ''),
            ),
            'bg_color'    => '#000000',
            'value_color' => '#FF8C00',
            'label_color' => '#ffffff',
            'max_width'   => 1200,
            'columns'     => 3,
        );

        $saved = get_option('requestdesk_stats_bar_settings', array());
        $settings = wp_parse_args($saved, $defaults);

        // wp_parse_args is shallow - if saved has an empty stats key,
        // it won't be replaced by the default. Explicitly fall back.
        if (empty($settings['stats'])) {
            $settings['stats'] = $defaults['stats'];
        }

        // Shortcode attributes override saved settings
        if (!empty($atts['bg_color'])) {
            $settings['bg_color'] = $atts['bg_color'];
        }
        if (!empty($atts['text_color'])) {
            $settings['label_color'] = $atts['text_color'];
        }
        if (!empty($atts['columns'])) {
            $settings['columns'] = intval($atts['columns']);
        }
        if (!empty($atts['max_width'])) {
            $settings['max_width'] = intval($atts['max_width']);
        }

        return $settings;
    }

    /**
     * Enqueue CSS (once per request)
     */
    private function enqueue_assets() {
        if ($this->assets_enqueued) {
            return;
        }

        wp_enqueue_style(
            'requestdesk-stats-bar',
            REQUESTDESK_PLUGIN_URL . 'assets/css/stats-bar.css',
            array(),
            REQUESTDESK_VERSION
        );

        $this->assets_enqueued = true;
    }

    /**
     * Render inline CSS custom properties
     */
    private function get_inline_styles($settings) {
        $styles = array();
        if (!empty($settings['bg_color'])) {
            $styles[] = '--rd-stats-bg: ' . esc_attr($settings['bg_color']);
        }
        if (!empty($settings['value_color'])) {
            $styles[] = '--rd-stats-value-color: ' . esc_attr($settings['value_color']);
        }
        if (!empty($settings['label_color'])) {
            $styles[] = '--rd-stats-label-color: ' . esc_attr($settings['label_color']);
        }
        if (!empty($settings['max_width'])) {
            $styles[] = '--rd-stats-max-width: ' . intval($settings['max_width']) . 'px';
        }
        if (!empty($settings['columns'])) {
            $styles[] = '--rd-stats-columns: ' . intval($settings['columns']);
        }
        return implode('; ', $styles);
    }

    /**
     * [requestdesk_stats_bar] - Full stats section
     */
    public function stats_bar_shortcode($atts) {
        $atts = shortcode_atts(array(
            'bg_color'   => '',
            'text_color' => '',
            'columns'    => '',
            'max_width'  => '',
        ), $atts, 'requestdesk_stats_bar');

        $settings = $this->get_settings($atts);
        $this->enqueue_assets();

        $inline_styles = $this->get_inline_styles($settings);
        $stats = $settings['stats'];

        if (empty($stats)) {
            return '<!-- requestdesk_stats_bar: no stats configured -->';
        }

        ob_start();
        ?>
        <section class="rd-stats-bar" style="<?php echo esc_attr($inline_styles); ?>">
            <div class="rd-stats-bar__inner">
                <?php foreach ($stats as $stat) : ?>
                <div class="rd-stats-bar__item">
                    <?php if (!empty($stat['icon'])) : ?>
                    <span class="rd-stats-bar__icon"><?php echo esc_html($stat['icon']); ?></span>
                    <?php endif; ?>
                    <span class="rd-stats-bar__value"><?php echo esc_html($stat['value']); ?></span>
                    <span class="rd-stats-bar__label"><?php echo esc_html($stat['label']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}

// Initialize
new RequestDesk_Stats_Bar();
