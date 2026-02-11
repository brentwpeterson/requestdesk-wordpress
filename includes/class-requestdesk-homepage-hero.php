<?php
/**
 * RequestDesk Homepage Hero Shortcodes
 *
 * [requestdesk_homepage_hero]  - Full two-column hero (headline + terminal + HubSpot form)
 * [requestdesk_rotating_text]  - Standalone terminal typing animation
 *
 * Settings are managed via RequestDesk > Settings > Homepage Hero tab.
 * Shortcode attributes override admin settings where specified.
 *
 * @package RequestDesk
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Homepage_Hero {

    /** @var bool Track whether assets have been enqueued for this request */
    private $assets_enqueued = false;

    /** @var int Auto-increment ID for multiple terminal instances on one page */
    private static $instance_count = 0;

    public function __construct() {
        add_shortcode('requestdesk_homepage_hero', array($this, 'hero_shortcode'));
        add_shortcode('requestdesk_rotating_text', array($this, 'rotating_text_shortcode'));
    }

    /**
     * Get merged settings (admin defaults + shortcode attribute overrides)
     */
    private function get_settings($atts = array()) {
        $defaults = array(
            'headline'              => 'Humans<br>Writing<br>Content',
            'form_heading'          => "Let's write your success story!",
            'seo_text'              => 'Humans in the loop. We believe AI should enhance human creativity, not replace it. Our approach: AI-powered content creation with human editors reviewing every piece. Executing with precision. Complete brand consistency across all platforms.',
            'hubspot_portal_id'     => '39487190',
            'hubspot_form_id'       => '3c945309-67c6-4812-ab65-c7280682e005',
            'hubspot_region'        => 'na1',
            'terminal_enabled'      => true,
            'terminal_sequences'    => array(
                array(
                    array('text' => 'Write more, prompt less.', 'delay' => 500),
                    array('text' => "\nNo AI slop.", 'delay' => 500),
                    array('text' => "\nHumans in the loop. Always.", 'delay' => 1200),
                ),
                array(
                    array('text' => '> content_engine.start()', 'delay' => 500),
                    array('text' => "\nLoading brand voice...", 'delay' => 700),
                    array('text' => "\nHuman writers standing by.", 'delay' => 900),
                    array('text' => "\nReady.", 'delay' => 1200),
                ),
                array(
                    array('text' => 'Blog posts. Landing pages.', 'delay' => 500),
                    array('text' => "\nSocial. Email. SEO.", 'delay' => 500),
                    array('text' => "\nAll written by real humans.", 'delay' => 1200),
                ),
            ),
            'terminal_type_speed_min' => 45,
            'terminal_type_speed_max' => 95,
            'hero_bg_color'         => '#000000',
            'headline_color'        => '#58c558',
            'max_width'             => 1200,
        );

        $saved = get_option('requestdesk_homepage_hero_settings', array());
        $settings = wp_parse_args($saved, $defaults);

        // wp_parse_args is shallow - if saved has an empty terminal_sequences key,
        // it won't be replaced by the default. Explicitly fall back.
        if (empty($settings['terminal_sequences'])) {
            $settings['terminal_sequences'] = $defaults['terminal_sequences'];
        }

        // Shortcode attributes override saved settings
        if (!empty($atts['headline'])) {
            $settings['headline'] = $atts['headline'];
        }
        if (!empty($atts['form_heading'])) {
            $settings['form_heading'] = $atts['form_heading'];
        }
        if (!empty($atts['portal_id'])) {
            $settings['hubspot_portal_id'] = $atts['portal_id'];
        }
        if (!empty($atts['form_id'])) {
            $settings['hubspot_form_id'] = $atts['form_id'];
        }
        if (!empty($atts['region'])) {
            $settings['hubspot_region'] = $atts['region'];
        }
        if (!empty($atts['bg_color'])) {
            $settings['hero_bg_color'] = $atts['bg_color'];
        }
        if (!empty($atts['headline_color'])) {
            $settings['headline_color'] = $atts['headline_color'];
        }
        if (!empty($atts['max_width'])) {
            $settings['max_width'] = intval($atts['max_width']);
        }
        if ($atts['terminal'] !== '') {
            $settings['terminal_enabled'] = filter_var($atts['terminal'], FILTER_VALIDATE_BOOLEAN);
        }

        return $settings;
    }

    /**
     * Enqueue CSS and localize terminal JS (once per request)
     */
    private function enqueue_assets($settings) {
        // CSS always needed
        wp_enqueue_style(
            'requestdesk-homepage-hero',
            REQUESTDESK_PLUGIN_URL . 'assets/css/homepage-hero.css',
            array(),
            REQUESTDESK_VERSION
        );

        // Terminal JS only if enabled and sequences exist
        if ($settings['terminal_enabled'] && !empty($settings['terminal_sequences'])) {
            wp_enqueue_script(
                'requestdesk-homepage-hero-terminal',
                REQUESTDESK_PLUGIN_URL . 'assets/js/homepage-hero-terminal.js',
                array(),
                REQUESTDESK_VERSION,
                true // load in footer
            );
        }

        $this->assets_enqueued = true;
    }

    /**
     * Localize terminal script with config for a specific instance
     */
    private function localize_terminal($settings, $instance_id) {
        if (!$settings['terminal_enabled'] || empty($settings['terminal_sequences'])) {
            return;
        }

        $config = array(
            'sequences'    => $settings['terminal_sequences'],
            'typeSpeedMin' => $settings['terminal_type_speed_min'],
            'typeSpeedMax' => $settings['terminal_type_speed_max'],
            'containerId'  => 'rd-hero-terminal-' . $instance_id,
        );

        // wp_localize_script only works once per handle, so use wp_add_inline_script
        wp_add_inline_script(
            'requestdesk-homepage-hero-terminal',
            'window.requestdeskHeroTerminal = ' . wp_json_encode($config) . ';',
            'before'
        );
    }

    /**
     * Render inline CSS custom properties
     */
    private function get_inline_styles($settings) {
        $styles = array();
        if (!empty($settings['hero_bg_color'])) {
            $styles[] = '--rd-hero-bg: ' . esc_attr($settings['hero_bg_color']);
        }
        if (!empty($settings['headline_color'])) {
            $styles[] = '--rd-hero-headline-color: ' . esc_attr($settings['headline_color']);
        }
        if (!empty($settings['max_width'])) {
            $styles[] = '--rd-hero-max-width: ' . intval($settings['max_width']) . 'px';
        }
        return implode('; ', $styles);
    }

    /**
     * [requestdesk_homepage_hero] - Full hero section
     */
    public function hero_shortcode($atts) {
        $atts = shortcode_atts(array(
            'headline'       => '',
            'form_heading'   => '',
            'portal_id'      => '',
            'form_id'        => '',
            'region'         => '',
            'bg_color'       => '',
            'headline_color' => '',
            'max_width'      => '',
            'terminal'       => '',
        ), $atts, 'requestdesk_homepage_hero');

        $settings = $this->get_settings($atts);
        $this->enqueue_assets($settings);

        self::$instance_count++;
        $instance_id = self::$instance_count;
        $this->localize_terminal($settings, $instance_id);

        $inline_styles = $this->get_inline_styles($settings);

        ob_start();
        ?>
        <section class="rd-home-hero" style="<?php echo esc_attr($inline_styles); ?>">
            <div class="rd-home-hero__inner">

                <!-- Left Column: Messaging + Terminal -->
                <div class="rd-home-hero__left">
                    <h1 class="rd-home-hero__headline"><?php echo wp_kses_post($settings['headline']); ?></h1>

                    <?php if ($settings['terminal_enabled'] && !empty($settings['terminal_sequences'])) : ?>
                    <div class="rd-hero-terminal-wrapper" id="rd-hero-terminal-<?php echo $instance_id; ?>" role="img" aria-label="Terminal animation showing rotating text sequences">
                        <div class="rd-hero-terminal">
                            <div class="rd-hero-terminal-titlebar">
                                <span class="rd-hero-terminal-btn rd-hero-terminal-btn--close"></span>
                                <span class="rd-hero-terminal-btn rd-hero-terminal-btn--minimize"></span>
                                <span class="rd-hero-terminal-btn rd-hero-terminal-btn--maximize"></span>
                            </div>
                            <div class="rd-hero-terminal-content">
                                <div class="rd-hero-terminal-output"></div>
                                <span class="rd-hero-terminal-cursor"></span>
                            </div>
                        </div>
                        <?php if (!empty($settings['seo_text'])) : ?>
                        <p class="rd-hero-terminal-seo"><?php echo esc_html($settings['seo_text']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column: HubSpot Form -->
                <?php if (!empty($settings['hubspot_portal_id']) && !empty($settings['hubspot_form_id'])) : ?>
                <div class="rd-home-hero__right">
                    <div class="rd-home-hero__form-wrap">
                        <?php if (!empty($settings['form_heading'])) : ?>
                        <h2 class="rd-home-hero__form-heading"><?php echo esc_html($settings['form_heading']); ?></h2>
                        <?php endif; ?>
                        <script src="https://js.hsforms.net/forms/embed/developer/<?php echo esc_attr($settings['hubspot_portal_id']); ?>.js" defer></script>
                        <div class="hs-form-html"
                             data-region="<?php echo esc_attr($settings['hubspot_region']); ?>"
                             data-form-id="<?php echo esc_attr($settings['hubspot_form_id']); ?>"
                             data-portal-id="<?php echo esc_attr($settings['hubspot_portal_id']); ?>"
                             style="--hsf-background__background-color: <?php echo esc_attr($settings['hero_bg_color']); ?>; --hsf-button__background-color: #116530;">
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * [requestdesk_rotating_text] - Standalone terminal animation
     */
    public function rotating_text_shortcode($atts) {
        $atts = shortcode_atts(array(
            'bg_color'       => '',
            'headline_color' => '',
            'terminal'       => '',
        ), $atts, 'requestdesk_rotating_text');

        $settings = $this->get_settings($atts);

        // Force terminal enabled for this shortcode (it's the whole point)
        $settings['terminal_enabled'] = true;

        if (empty($settings['terminal_sequences'])) {
            return '<!-- requestdesk_rotating_text: no sequences configured -->';
        }

        $this->enqueue_assets($settings);

        self::$instance_count++;
        $instance_id = self::$instance_count;
        $this->localize_terminal($settings, $instance_id);

        ob_start();
        ?>
        <div class="rd-hero-terminal-wrapper" id="rd-hero-terminal-<?php echo $instance_id; ?>" role="img" aria-label="Terminal animation showing rotating text sequences">
            <div class="rd-hero-terminal">
                <div class="rd-hero-terminal-titlebar">
                    <span class="rd-hero-terminal-btn rd-hero-terminal-btn--close"></span>
                    <span class="rd-hero-terminal-btn rd-hero-terminal-btn--minimize"></span>
                    <span class="rd-hero-terminal-btn rd-hero-terminal-btn--maximize"></span>
                </div>
                <div class="rd-hero-terminal-content">
                    <div class="rd-hero-terminal-output"></div>
                    <span class="rd-hero-terminal-cursor"></span>
                </div>
            </div>
            <?php if (!empty($settings['seo_text'])) : ?>
            <p class="rd-hero-terminal-seo"><?php echo esc_html($settings['seo_text']); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize
new RequestDesk_Homepage_Hero();
