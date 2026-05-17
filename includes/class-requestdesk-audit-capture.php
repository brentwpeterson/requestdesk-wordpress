<?php
/**
 * Content Cucumber audit-request capture.
 *
 * Captures audit requests from newsletter button clicks with minimal friction.
 * Subscriber is already known (email in URL), so no form refill is required
 * when their domain is also known. When domain is missing, a single-field form
 * collects the URL to audit. Every request is logged as a `cc_audit_request`
 * CPT post and a notification email is sent for manual fulfillment.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Audit_Capture {

    const CPT_SLUG       = 'cc_audit_request';
    const OPTION_KEY     = 'requestdesk_audit_capture_settings';
    const REST_NAMESPACE = 'cc-audit/v1';
    const SHORTCODE_TAG  = 'cc_audit_landing';

    public function __construct() {
        add_action('init',          array($this, 'register_cpt'));
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('admin_menu',    array($this, 'register_settings_page'), 25);
        add_action('admin_init',    array($this, 'register_settings'));
        add_shortcode(self::SHORTCODE_TAG, array($this, 'render_landing'));
    }

    public function register_cpt() {
        register_post_type(self::CPT_SLUG, array(
            'labels' => array(
                'name'          => 'Audit Requests',
                'singular_name' => 'Audit Request',
                'menu_name'     => 'Audit Requests',
            ),
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-search',
            'menu_position'       => 31,
            'supports'            => array('title', 'custom-fields'),
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'has_archive'         => false,
            'rewrite'             => false,
            'show_in_rest'        => false,
            'exclude_from_search' => true,
        ));
    }

    public function register_routes() {
        register_rest_route(self::REST_NAMESPACE, '/request', array(
            'methods'             => array('POST'),
            'callback'            => array($this, 'rest_submit'),
            'permission_callback' => '__return_true',
            'args' => array(
                'email'  => array('required' => true,  'type' => 'string'),
                'url'    => array('required' => true,  'type' => 'string'),
                'source' => array('required' => false, 'type' => 'string'),
            ),
        ));
    }

    public function rest_submit(WP_REST_Request $request) {
        $email  = sanitize_email((string) $request->get_param('email'));
        $url    = esc_url_raw((string) $request->get_param('url'));
        $source = sanitize_text_field((string) $request->get_param('source'));

        if (!is_email($email) || empty($url)) {
            return new WP_Error('cc_audit_invalid', 'Email and URL are required.', array('status' => 400));
        }

        $post_id = $this->create_request_post($email, $url, $source ?: 'rest');
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        return rest_ensure_response(array(
            'ok'      => true,
            'id'      => (int) $post_id,
            'message' => 'Audit request received. Report coming to your inbox in ~5 minutes.',
        ));
    }

    /**
     * Landing-page shortcode.
     *
     * Behavior:
     * - GET ?em=<email>&dom=<domain> -> register request, show confirmation.
     * - GET ?em=<email> only         -> show one-field URL form.
     * - POST cc_audit_url + cc_audit_em -> register request, show confirmation.
     * - No params                    -> show one-field form with email + URL.
     */
    public function render_landing($atts = array(), $content = '') {
        $atts = shortcode_atts(array(
            'confirm_message' => "We're auditing %s. Your report will hit your inbox in about 5 minutes.",
            'submit_label'    => 'Run my audit',
            'heading'         => 'Your Content Cucumber audit',
        ), $atts, self::SHORTCODE_TAG);

        // POST submission from the form below.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cc_audit_nonce'])) {
            return $this->handle_form_post($atts);
        }

        $email  = isset($_GET['em'])  ? sanitize_email(wp_unslash($_GET['em']))            : '';
        $domain = isset($_GET['dom']) ? sanitize_text_field(wp_unslash($_GET['dom']))      : '';

        // Direct click path: email + domain present -> one-click capture.
        if ($email && $domain && is_email($email)) {
            $url = $this->normalize_url($domain);
            $post_id = $this->create_request_post($email, $url, 'newsletter-click');
            if (is_wp_error($post_id)) {
                return $this->render_error($post_id->get_error_message());
            }
            return $this->render_confirmation($atts, $url);
        }

        // Fallback path: render the one-field form, prefill email if known.
        return $this->render_form($atts, $email);
    }

    private function handle_form_post($atts) {
        if (!wp_verify_nonce($_POST['cc_audit_nonce'], 'cc_audit_submit')) {
            return $this->render_error('Session expired. Refresh and try again.');
        }

        $email = isset($_POST['cc_audit_em'])  ? sanitize_email(wp_unslash($_POST['cc_audit_em']))   : '';
        $url   = isset($_POST['cc_audit_url']) ? esc_url_raw(wp_unslash($_POST['cc_audit_url']))     : '';

        if (!is_email($email)) {
            return $this->render_error('That email does not look right. Try again?');
        }
        if (empty($url)) {
            return $this->render_error('We need a URL to audit.');
        }

        $url = $this->normalize_url($url);
        $post_id = $this->create_request_post($email, $url, 'landing-form');
        if (is_wp_error($post_id)) {
            return $this->render_error($post_id->get_error_message());
        }
        return $this->render_confirmation($atts, $url);
    }

    private function render_confirmation($atts, $url) {
        $msg = sprintf($atts['confirm_message'], '<strong>' . esc_html($url) . '</strong>');
        return '<div class="cc-audit-card cc-audit-card--confirm">'
             . '<h2>' . esc_html($atts['heading']) . '</h2>'
             . '<p>' . wp_kses_post($msg) . '</p>'
             . '<p class="cc-audit-note">Watch for an email from <code>audit@contentcucumber.com</code>.</p>'
             . '</div>';
    }

    private function render_form($atts, $prefill_email = '') {
        ob_start();
        ?>
        <div class="cc-audit-card cc-audit-card--form">
            <h2><?php echo esc_html($atts['heading']); ?></h2>
            <p>Tell us where to look. We'll handle the rest.</p>
            <form method="post" class="cc-audit-form">
                <?php wp_nonce_field('cc_audit_submit', 'cc_audit_nonce'); ?>
                <?php if ($prefill_email): ?>
                    <input type="hidden" name="cc_audit_em" value="<?php echo esc_attr($prefill_email); ?>" />
                <?php else: ?>
                    <label>
                        Email
                        <input type="email" name="cc_audit_em" required />
                    </label>
                <?php endif; ?>
                <label>
                    URL to audit
                    <input type="url" name="cc_audit_url" placeholder="https://yoursite.com" required />
                </label>
                <button type="submit"><?php echo esc_html($atts['submit_label']); ?></button>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function render_error($msg) {
        return '<div class="cc-audit-card cc-audit-card--error">'
             . '<p><strong>Something went sideways.</strong> ' . esc_html($msg) . '</p>'
             . '</div>';
    }

    private function normalize_url($input) {
        $input = trim((string) $input);
        if ($input === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $input)) {
            $input = 'https://' . ltrim($input, '/');
        }
        return esc_url_raw($input);
    }

    private function create_request_post($email, $url, $source) {
        $title = sprintf('%s — %s', $email, parse_url($url, PHP_URL_HOST) ?: $url);

        $post_id = wp_insert_post(array(
            'post_type'   => self::CPT_SLUG,
            'post_status' => 'publish',
            'post_title'  => $title,
            'meta_input'  => array(
                '_cc_audit_email'    => $email,
                '_cc_audit_url'      => $url,
                '_cc_audit_source'   => $source,
                '_cc_audit_status'   => 'new',
                '_cc_audit_received' => current_time('mysql'),
                '_cc_audit_ip'       => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
                '_cc_audit_ua'       => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            ),
        ), true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $this->notify($post_id, $email, $url, $source);
        return $post_id;
    }

    private function notify($post_id, $email, $url, $source) {
        $settings = get_option(self::OPTION_KEY, array());
        $to       = !empty($settings['notify_email']) ? $settings['notify_email'] : get_option('admin_email');
        if (empty($to)) {
            return;
        }

        $admin_link = admin_url('post.php?post=' . (int) $post_id . '&action=edit');
        $subject    = 'New audit request: ' . parse_url($url, PHP_URL_HOST);
        $body  = "New audit request captured.\n\n";
        $body .= "Email:  $email\n";
        $body .= "URL:    $url\n";
        $body .= "Source: $source\n";
        $body .= "Time:   " . current_time('mysql') . "\n\n";
        $body .= "Review: $admin_link\n";

        wp_mail($to, $subject, $body);
    }

    public function register_settings() {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, array(
            'type'              => 'array',
            'sanitize_callback' => array($this, 'sanitize_settings'),
            'default'           => array('notify_email' => get_option('admin_email')),
        ));
    }

    public function sanitize_settings($input) {
        $out = array();
        $out['notify_email'] = isset($input['notify_email']) ? sanitize_email($input['notify_email']) : '';
        return $out;
    }

    public function register_settings_page() {
        add_submenu_page(
            'edit.php?post_type=' . self::CPT_SLUG,
            'Audit Capture Settings',
            'Settings',
            'manage_options',
            'cc-audit-capture-settings',
            array($this, 'render_settings_page')
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = get_option(self::OPTION_KEY, array());
        $notify   = isset($settings['notify_email']) ? $settings['notify_email'] : get_option('admin_email');
        ?>
        <div class="wrap">
            <h1>Audit Capture Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_KEY); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="cc_audit_notify_email">Notification email</label></th>
                        <td>
                            <input type="email" id="cc_audit_notify_email"
                                   name="<?php echo esc_attr(self::OPTION_KEY); ?>[notify_email]"
                                   value="<?php echo esc_attr($notify); ?>" class="regular-text" />
                            <p class="description">Where new audit-request notifications get sent.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2>How to use</h2>
            <p>
                Drop the <code>[cc_audit_landing]</code> shortcode on a page (e.g.
                <code>contentcucumber.com/audit/</code>). Newsletter buttons should
                link there with <code>?em={{contact.email}}&amp;dom={{contact.company}}</code>.
            </p>
            <p>
                REST endpoint for programmatic submission:
                <code>POST <?php echo esc_html(rest_url(self::REST_NAMESPACE . '/request')); ?></code>
                with JSON body <code>{ "email": "...", "url": "...", "source": "..." }</code>.
            </p>
        </div>
        <?php
    }
}
