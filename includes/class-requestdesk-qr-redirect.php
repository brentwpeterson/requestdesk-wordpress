<?php
/**
 * RequestDesk QR Redirect
 *
 * One permanent short URL - /go - behind every printed QR code.
 *
 * WHY THIS EXISTS: a QR code on a printed sticker, business card or booth
 * banner can never be changed once it leaves the building. Encoding a real
 * destination directly into the code means the printed asset dies the moment
 * that campaign ends. Encoding /go instead means the code is permanent and the
 * destination behind it is repointed per conference - eTail Boston, then
 * ShopTalk, then whatever is next - from wp-admin, with no reprint and no
 * deploy.
 *
 * THE 302 IS LOAD-BEARING. This MUST NOT be a 301. A 301 is cached by the
 * browser permanently, so anyone who scanned the code at eTail would keep
 * landing on the eTail page forever, even after the destination is repointed,
 * and the stale mapping lives on their device where it cannot be corrected.
 * A 302 plus no-store revalidates on every scan. Do not "optimize" this to a
 * 301.
 *
 * @package RequestDesk_Connector
 * @since 2.34.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_QR_Redirect {

    /** Option holding the destination map: key => array(url, campaign). */
    const OPTION_MAP = 'requestdesk_qr_redirect_map';

    /**
     * Fallback used when no destination is configured yet.
     *
     * Deliberately NOT a real URL. This plugin installs on sites other than the
     * one it was written for, so a vendor address baked in here would point
     * someone else's visitors at us the moment their map was empty. An empty
     * string means "resolve to this site's own home_url()" -- see default_url().
     */
    const DEFAULT_URL = '';

    /**
     * Where /go points when nothing is configured.
     *
     * Filterable so a site can set a real landing page without touching code:
     *     add_filter('requestdesk_qr_default_url', fn() => home_url('/events/'));
     */
    public static function default_url() {
        $url = self::DEFAULT_URL !== '' ? self::DEFAULT_URL : home_url('/');
        return apply_filters('requestdesk_qr_default_url', $url);
    }

    /** The path prefix the QR codes encode. */
    const PREFIX = 'go';

    public function __construct() {
        // Priority 0 on init: resolve before WP routes the request, so /go
        // never has to exist as a page and no rewrite-rule flush is required.
        // A flush-on-activation approach breaks quietly whenever permalinks
        // are re-saved or the site is migrated; this does not.
        add_action('init', array($this, 'maybe_redirect'), 0);
        add_action('admin_menu', array($this, 'register_settings_page'), 99);
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Resolve /go (and /go/<key>) and send the redirect.
     */
    public function maybe_redirect() {
        // Never interfere with admin, cron, REST, AJAX or CLI.
        if (is_admin() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) {
            return;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return;
        }

        $key = $this->request_key();
        if ($key === null) {
            return;
        }

        $entry  = $this->resolve($key);
        $target = $this->add_utm($entry['url'], $entry['campaign'], $key);

        // A cached redirect defeats the whole design. Force revalidation at the
        // browser and at any intermediary (Flywheel edge, CDN, corporate proxy).
        nocache_headers();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow', true);

        // 302 - deliberate. See the class docblock before changing this.
        wp_redirect($target, 302);
        exit;
    }

    /**
     * Return the redirect key for this request, or null when the request is
     * not a /go request at all.
     *
     * /go        -> 'default'
     * /go/       -> 'default'
     * /go/etail  -> 'etail'
     */
    private function request_key() {
        if (empty($_SERVER['REQUEST_URI'])) {
            return null;
        }

        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        // Strip any subdirectory install prefix so this works off the site root.
        $home_path = parse_url(home_url('/'), PHP_URL_PATH);
        if (is_string($home_path) && $home_path !== '' && $home_path !== '/'
            && strpos($path, $home_path) === 0) {
            $path = substr($path, strlen($home_path) - 1);
        }

        $path = trim($path, '/');
        if ($path === '') {
            return null;
        }

        $parts = explode('/', $path);
        if (strtolower($parts[0]) !== self::PREFIX) {
            return null;
        }

        if (count($parts) === 1 || $parts[1] === '') {
            return 'default';
        }

        $key = sanitize_key($parts[1]);

        return $key === '' ? 'default' : $key;
    }

    /**
     * Look up a key in the configured map, falling back to the default entry
     * and then to the hardcoded URL. A /go scan must NEVER 404 - a dead QR on
     * a sticker someone kept is worse than no QR at all.
     */
    private function resolve($key) {
        $map = get_option(self::OPTION_MAP, array());
        if (!is_array($map)) {
            $map = array();
        }

        foreach (array($key, 'default') as $candidate) {
            if (!empty($map[$candidate]['url'])) {
                return array(
                    'url'      => $map[$candidate]['url'],
                    'campaign' => isset($map[$candidate]['campaign'])
                        ? $map[$candidate]['campaign'] : '',
                );
            }
        }

        return array('url' => self::default_url(), 'campaign' => '');
    }

    /**
     * Append QR attribution without clobbering params the destination already
     * carries. This is what makes ONE static printed code still produce
     * per-event numbers in GA4: the campaign value changes when the
     * destination is repointed, so eTail scans and ShopTalk scans stay
     * separated even though the code never changed.
     */
    private function add_utm($url, $campaign, $key) {
        $args = array(
            'utm_source' => 'qr',
            'utm_medium' => 'print',
        );

        $campaign = trim((string) $campaign);
        if ($campaign !== '') {
            $args['utm_campaign'] = $campaign;
        }
        if ($key !== 'default') {
            $args['utm_content'] = $key;
        }

        // Anything already on the destination URL wins - a hand-set param on
        // the destination is a deliberate choice and must not be overwritten.
        $existing = array();
        $query    = parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            parse_str($query, $existing);
        }
        foreach (array_keys($existing) as $have) {
            unset($args[$have]);
        }

        return empty($args) ? $url : add_query_arg($args, $url);
    }

    /* ------------------------------------------------------------------ */
    /* Admin                                                              */
    /* ------------------------------------------------------------------ */

    public function register_settings_page() {
        add_submenu_page(
            'options-general.php',
            'QR Redirect',
            'QR Redirect',
            'manage_options',
            'requestdesk-qr-redirect',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting(
            'requestdesk_qr_redirect',
            self::OPTION_MAP,
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize_map'),
                'default'           => array(),
            )
        );
    }

    /**
     * Accepts the repeatable rows from the settings screen. A row with an empty
     * URL is dropped. An unparseable URL is rejected rather than saved, because
     * saving a broken destination silently kills every printed code.
     */
    public function sanitize_map($input) {
        $out = array();

        if (!is_array($input)) {
            return $out;
        }

        foreach ($input as $row) {
            if (!is_array($row) || empty($row['url'])) {
                continue;
            }

            $key = isset($row['key']) ? sanitize_key($row['key']) : '';
            if ($key === '') {
                $key = 'default';
            }

            $url = esc_url_raw(trim($row['url']));
            if ($url === '' || !wp_http_validate_url($url)) {
                add_settings_error(
                    self::OPTION_MAP,
                    'requestdesk_qr_bad_url',
                    sprintf('Destination for "%s" is not a valid URL and was not saved.', $key)
                );
                continue;
            }

            $out[$key] = array(
                'url'      => $url,
                'campaign' => isset($row['campaign'])
                    ? sanitize_text_field(trim($row['campaign'])) : '',
            );
        }

        return $out;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $map = get_option(self::OPTION_MAP, array());
        if (!is_array($map) || empty($map)) {
            $map = array('default' => array('url' => self::default_url(), 'campaign' => ''));
        }

        // Always render one spare row so a new event can be added without
        // any JavaScript.
        $rows = $map;
        $rows['__new__'] = array('url' => '', 'campaign' => '');
        ?>
        <div class="wrap">
            <h1>QR Redirect</h1>

            <p>
                Printed QR codes encode <code><?php echo esc_html(home_url('/go')); ?></code>
                and never change. Repoint the destination here for each conference.
                The redirect is a <strong>302</strong> so a repoint takes effect immediately,
                even for people who scanned the same code at a previous event.
            </p>
            <p>
                <strong>Campaign</strong> becomes <code>utm_campaign</code> on the way through
                (alongside <code>utm_source=qr</code> and <code>utm_medium=print</code>), so each
                event's scans stay separated in GA4 without reprinting anything.
                Use a distinct value per event, for example <code>etail-boston-2026</code>.
            </p>
            <p>
                The row keyed <code>default</code> answers <code>/go</code> and is the fallback
                for any key with no destination set. Keep it pointed at something real
                between events. Extra keys answer <code>/go/&lt;key&gt;</code>, which is how a
                second printed asset gets its own tracking without a second redirect.
            </p>

            <?php settings_errors(self::OPTION_MAP); ?>

            <form method="post" action="options.php">
                <?php settings_fields('requestdesk_qr_redirect'); ?>
                <table class="widefat striped" style="max-width:60rem;">
                    <thead>
                        <tr>
                            <th style="width:12rem;">Key</th>
                            <th>Destination URL</th>
                            <th style="width:18rem;">Campaign (utm_campaign)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i = 0; foreach ($rows as $key => $row) : $i++; ?>
                        <tr>
                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr(self::OPTION_MAP); ?>[<?php echo (int) $i; ?>][key]"
                                       value="<?php echo esc_attr($key === '__new__' ? '' : $key); ?>"
                                       placeholder="default"
                                       class="regular-text" style="width:100%;">
                            </td>
                            <td>
                                <input type="url"
                                       name="<?php echo esc_attr(self::OPTION_MAP); ?>[<?php echo (int) $i; ?>][url]"
                                       value="<?php echo esc_attr($row['url']); ?>"
                                       placeholder="https://example.com/..."
                                       class="regular-text" style="width:100%;">
                            </td>
                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr(self::OPTION_MAP); ?>[<?php echo (int) $i; ?>][campaign]"
                                       value="<?php echo esc_attr($row['campaign']); ?>"
                                       placeholder="etail-boston-2026"
                                       class="regular-text" style="width:100%;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button('Save QR destinations'); ?>
            </form>
        </div>
        <?php
    }
}

// Self-instantiated at require time, unlike the classes constructed inside
// requestdesk_init(). That function is itself hooked to `init` at the default
// priority 10, so a class built there could never register an `init` hook at
// priority 0 - the redirect would resolve too late. Do not move this into the
// $aeo_classes list.
new RequestDesk_QR_Redirect();
