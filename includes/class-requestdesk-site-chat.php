<?php
/**
 * RequestDesk Website Chat
 *
 * Puts an agent's RequestDesk website chat on this site, or takes it off. The
 * chat itself (what it answers from, model, limits, look, allowed websites)
 * lives on the agent in RequestDesk. WordPress only decides whether the page
 * loads it, with one script tag in the footer:
 *
 *   <script src="<RequestDesk URL>/api/public/site-chat/widget.js"
 *           data-chat="<slug>" async></script>
 *
 * Off by default. Switched on in Settings -> RequestDesk -> Website Chat.
 *
 * The RequestDesk URL has no default: this plugin ships to sites that are not
 * ours, so the admin enters the address of the RequestDesk app they use.
 *
 * A switch that is on but cannot work (no slug, no RequestDesk URL, or one
 * that is not https) prints nothing and says why on the settings tab, so the site never
 * loads a broken widget and the admin is not left guessing.
 *
 * The chat shows only on websites listed in the agent's allowed websites
 * (exact origin match). On a site not on that list the widget's first
 * request is refused with 403 and nothing is drawn, so the settings tab
 * makes that same request server-side and names the address to add. A local
 * or staging copy needs its own address on the list to be tested.
 *
 * @package RequestDesk
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Site_Chat {

    const OPTION_SETTINGS = 'requestdesk_site_chat_settings';
    const WIDGET_PATH     = '/api/public/site-chat/widget.js';
    const PREVIEW_PATH    = '/api/public/site-chat/%s/preview';

    public function __construct() {
        add_action('wp_footer', array($this, 'print_script_tag'), 20);
    }

    /**
     * Saved settings merged over defaults. Kept in its own option, because the
     * General tab's save rewrites requestdesk_settings and drops keys it does
     * not list.
     */
    public static function get_settings() {
        $defaults = array(
            'enabled'  => false,
            'slug'     => '',
            'base_url' => '',
        );
        return wp_parse_args(get_option(self::OPTION_SETTINGS, array()), $defaults);
    }

    /**
     * Why the switched-on chat cannot load, or '' when it can (or is off).
     */
    public static function config_error($settings = null) {
        if ($settings === null) {
            $settings = self::get_settings();
        }
        if (empty($settings['enabled'])) {
            return '';
        }
        if ($settings['slug'] === '') {
            return 'No chat slug is set.';
        }
        if ($settings['base_url'] === '') {
            return 'No RequestDesk URL is set.';
        }
        if (wp_parse_url($settings['base_url'], PHP_URL_SCHEME) !== 'https') {
            return 'The RequestDesk URL must use https.';
        }
        return '';
    }

    /**
     * This site's origin, as the browser sends it (scheme://host[:port]).
     */
    public static function site_origin() {
        $parts = wp_parse_url(home_url());
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        return $origin;
    }

    /**
     * Ask RequestDesk whether it serves this chat to this site, the same
     * request the widget makes in the browser. Returns '' when it does, or
     * the reason it does not. Without this check a site missing from the
     * agent's allowed websites shows no chat and says nothing.
     */
    public static function remote_check($settings) {
        $url = untrailingslashit($settings['base_url']) . '/api/public/site-chat/' . rawurlencode($settings['slug']) . '/config';
        $origin = self::site_origin();
        $response = wp_remote_get($url, array('timeout' => 8, 'headers' => array('Origin' => $origin)));
        if (is_wp_error($response)) {
            return 'RequestDesk could not be reached at ' . $settings['base_url'] . ': ' . $response->get_error_message();
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            return '';
        }
        if ($code === 403) {
            return 'RequestDesk refuses this site. Add ' . $origin . ' to the allowed websites on the agent\'s Website Chat settings in RequestDesk.';
        }
        if ($code === 404) {
            return 'RequestDesk has no switched-on chat with the slug "' . $settings['slug'] . '".';
        }
        return 'RequestDesk answered HTTP ' . $code . ' for this chat.';
    }

    /**
     * The widget URL for these settings.
     */
    public static function widget_url($settings) {
        return untrailingslashit($settings['base_url']) . self::WIDGET_PATH;
    }

    /**
     * The RequestDesk preview page for a slug.
     */
    public static function preview_url($settings) {
        return untrailingslashit($settings['base_url']) . sprintf(self::PREVIEW_PATH, rawurlencode($settings['slug']));
    }

    /**
     * Footer script tag on front-end pages, only when switched on and valid.
     */
    public function print_script_tag() {
        if (is_admin() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        $settings = self::get_settings();
        if (empty($settings['enabled']) || self::config_error($settings) !== '') {
            return;
        }
        printf(
            "<script src=\"%s\" data-chat=\"%s\" async></script>\n",
            esc_url(self::widget_url($settings)),
            esc_attr($settings['slug'])
        );
    }
}
