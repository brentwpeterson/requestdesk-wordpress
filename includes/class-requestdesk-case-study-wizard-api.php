<?php
/**
 * RequestDesk Case Study Wizard — server-side proxy + publish handler.
 *
 * Exposes a small WordPress REST namespace `requestdesk/v1/wizard/*` that the
 * wizard JS calls. Each route is admin-only + nonce-protected. The proxy
 * routes forward to the RequestDesk backend (`/api/case-study-wizard/*`)
 * using the connector's configured agent api_key (Bearer). The publish route
 * is local-only and creates a `cc_case_study` draft from the wizard payload
 * + generated content.
 *
 * Why proxy instead of calling RD directly from the browser:
 *   - The agent api_key must NEVER reach the browser.
 *   - WP-side nonce + capability check keeps random visitors out.
 *   - One layer to swap if RD's base URL changes.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Case_Study_Wizard_Api {

    const NS = 'requestdesk/v1';

    public function __construct() {
        if (!RequestDesk_Case_Study::wizard_enabled()) {
            return;
        }
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        $perm = array($this, 'permission_check');

        register_rest_route(self::NS, '/wizard/sessions', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'create_session'),
            'permission_callback' => $perm,
        ));

        register_rest_route(self::NS, '/wizard/sessions/(?P<id>[a-zA-Z0-9_-]+)', array(
            'methods'             => 'PUT',
            'callback'            => array($this, 'update_session'),
            'permission_callback' => $perm,
        ));

        register_rest_route(self::NS, '/wizard/sessions/(?P<id>[a-zA-Z0-9_-]+)/generate', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'generate_content'),
            'permission_callback' => $perm,
        ));

        register_rest_route(self::NS, '/wizard/sessions/(?P<id>[a-zA-Z0-9_-]+)/publish', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'publish_to_cpt'),
            'permission_callback' => $perm,
        ));
    }

    public function permission_check(\WP_REST_Request $request) {
        if (!current_user_can('edit_posts')) {
            return new WP_Error('rest_forbidden', 'Insufficient permissions', array('status' => 403));
        }
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('rest_invalid_nonce', 'Invalid nonce', array('status' => 403));
        }
        return true;
    }

    // ------------------------------------------------------------------
    // Proxy: create session
    // ------------------------------------------------------------------
    public function create_session(\WP_REST_Request $request) {
        $payload = array(
            'client_name' => $this->str($request->get_param('client_name')),
        );

        $partner_id = (int) $request->get_param('partner_id');
        $additional = $this->build_additional_instructions(
            $request->get_param('input_mode'),
            $request->get_param('raw_input'),
            $partner_id
        );
        if ($additional !== '') {
            $payload['additional_instructions'] = $additional;
        }

        $resp = $this->rd_request('POST', '/case-study-wizard', $payload);
        if (is_wp_error($resp)) {
            return $resp;
        }
        $resp['wp'] = array('partner_id' => $partner_id);
        return rest_ensure_response($resp);
    }

    // ------------------------------------------------------------------
    // Proxy: update session (auto-save)
    // ------------------------------------------------------------------
    public function update_session(\WP_REST_Request $request) {
        $id = sanitize_text_field($request->get_param('id'));
        if (empty($id)) {
            return new WP_Error('missing_id', 'Missing wizard session id', array('status' => 400));
        }

        $payload = array_filter(array(
            'client_name'        => $this->str($request->get_param('client_name')),
            'challenge_summary'  => $this->str($request->get_param('challenge')),
            'solution_summary'   => $this->str($request->get_param('solution')),
            'results_summary'    => $this->str($request->get_param('results')),
        ), function ($v) { return $v !== '' && $v !== null; });

        // Metrics text → store as a single qualitative result. The wizard
        // collects free-form text; RD's model expects structured arrays, so
        // we wrap it as a single entry to keep the data alive without
        // pretending to parse numbers we don't have.
        $metrics = $this->str($request->get_param('metrics'));
        if ($metrics !== '') {
            $payload['qualitative_results'] = array(array(
                'category'    => 'metrics',
                'description' => $metrics,
            ));
        }

        $quote = $this->str($request->get_param('quote'));
        if ($quote !== '') {
            $payload['client_testimonial'] = array(
                'quote' => $quote,
            );
        }

        $partner_id = (int) $request->get_param('partner_id');
        $additional = $this->build_additional_instructions(
            $request->get_param('input_mode'),
            $request->get_param('raw_input'),
            $partner_id
        );
        if ($additional !== '') {
            $payload['additional_instructions'] = $additional;
        }

        $resp = $this->rd_request('PUT', '/case-study-wizard/' . rawurlencode($id), $payload);
        if (is_wp_error($resp)) {
            return $resp;
        }
        return rest_ensure_response($resp);
    }

    // ------------------------------------------------------------------
    // Proxy: trigger AI generation
    // ------------------------------------------------------------------
    public function generate_content(\WP_REST_Request $request) {
        $id = sanitize_text_field($request->get_param('id'));
        if (empty($id)) {
            return new WP_Error('missing_id', 'Missing wizard session id', array('status' => 400));
        }
        $regenerate = (bool) $request->get_param('regenerate');
        $resp = $this->rd_request('POST', '/case-study-wizard/' . rawurlencode($id) . '/generate', array(
            'regenerate' => $regenerate,
        ));
        if (is_wp_error($resp)) {
            return $resp;
        }
        return rest_ensure_response($resp);
    }

    // ------------------------------------------------------------------
    // Local publish: create cc_case_study draft from wizard payload
    // ------------------------------------------------------------------
    public function publish_to_cpt(\WP_REST_Request $request) {
        $id = sanitize_text_field($request->get_param('id'));
        if (empty($id)) {
            return new WP_Error('missing_id', 'Missing wizard session id', array('status' => 400));
        }

        // Fetch the full session from RD (includes generated content if any)
        $session = $this->rd_request('GET', '/case-study-wizard/' . rawurlencode($id), null);
        if (is_wp_error($session)) {
            return $session;
        }
        $cs = isset($session['case_study']) ? $session['case_study'] : array();

        // The browser-edited fields override what RD has stored. Allows the
        // user to keep edits made in Step 4 before publishing.
        $title = $this->str($request->get_param('post_title'));
        if ($title === '') {
            $title = $this->str(isset($cs['client_name']) ? $cs['client_name'] : '') ?: 'Untitled Case Study';
        }

        $content = $this->str($request->get_param('content'));
        if ($content === '') {
            // Try RD's last generated content if present in session
            $content = $this->str(isset($cs['generated_content']) ? $cs['generated_content'] : '');
        }

        $post_id = wp_insert_post(array(
            'post_type'    => 'cc_case_study',
            'post_status'  => 'draft',
            'post_title'   => $title,
            'post_content' => $content,
        ), true);

        if (is_wp_error($post_id)) {
            return new WP_Error('wp_insert_failed', $post_id->get_error_message(), array('status' => 500));
        }

        // Map fields → meta. Wizard payload (which may have been edited in
        // Step 4) takes precedence over the RD-stored copy.
        $challenge  = $this->str($request->get_param('challenge'))  ?: $this->str(isset($cs['challenge_summary']) ? $cs['challenge_summary'] : '');
        $solution   = $this->str($request->get_param('solution'))   ?: $this->str(isset($cs['solution_summary']) ? $cs['solution_summary'] : '');
        $results    = $this->str($request->get_param('results'))    ?: $this->str(isset($cs['results_summary']) ? $cs['results_summary'] : '');
        $quote_text = $this->str($request->get_param('quote'))      ?: $this->str(isset($cs['client_testimonial']['quote']) ? $cs['client_testimonial']['quote'] : '');
        $client_name = $title;

        if ($client_name !== '') update_post_meta($post_id, '_cc_cs_client_name', $client_name);
        if ($challenge !== '')   update_post_meta($post_id, '_cc_cs_challenge', $challenge);
        if ($solution !== '')    update_post_meta($post_id, '_cc_cs_approach', $solution);
        if ($results !== '')     update_post_meta($post_id, '_cc_cs_results', $results);
        if ($quote_text !== '')  update_post_meta($post_id, '_cc_cs_quote', $quote_text);

        // Partner connect → post meta so the case-study template can render
        // partner attribution if desired. Phase B+1 may upgrade to a real
        // taxonomy.
        $partner_id = (int) $request->get_param('partner_id');
        if ($partner_id > 0) {
            update_post_meta($post_id, '_cc_cs_partner_id', $partner_id);
        }

        // Stamp source so future maintenance knows this came from the wizard.
        update_post_meta($post_id, '_cc_cs_wizard_session_id', $id);
        update_post_meta($post_id, '_cc_cs_source', 'wizard');

        return rest_ensure_response(array(
            'success'      => true,
            'post_id'      => (int) $post_id,
            'edit_url'     => get_edit_post_link($post_id, ''),
            'preview_url'  => get_preview_post_link($post_id),
            'wizard_id'    => $id,
        ));
    }

    // ------------------------------------------------------------------
    // Internal: HTTP call to RequestDesk
    // ------------------------------------------------------------------
    private function rd_request($method, $path, $body) {
        $settings = get_option('requestdesk_settings', array());
        $api_key  = $settings['api_key']             ?? '';
        $endpoint = $settings['requestdesk_endpoint'] ?? '';

        if (empty($api_key) || empty($endpoint)) {
            return new WP_Error(
                'rd_not_configured',
                'RequestDesk connector is not configured (missing api_key or endpoint).',
                array('status' => 503)
            );
        }

        $url = rtrim($endpoint, '/') . '/api' . $path;

        $args = array(
            'method'  => $method,
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
        );

        if ($body !== null && $method !== 'GET') {
            $args['body'] = wp_json_encode($body);
        }

        $resp = wp_remote_request($url, $args);
        if (is_wp_error($resp)) {
            return new WP_Error('rd_http_error', $resp->get_error_message(), array('status' => 502));
        }

        $code = wp_remote_retrieve_response_code($resp);
        $raw  = wp_remote_retrieve_body($resp);
        $json = json_decode($raw, true);

        if ($code >= 400) {
            $detail = is_array($json) && isset($json['detail']) ? $json['detail'] : $raw;
            return new WP_Error('rd_api_error', 'RequestDesk responded ' . $code . ': ' . $detail, array('status' => $code));
        }

        if (!is_array($json)) {
            return new WP_Error('rd_bad_json', 'RequestDesk returned non-JSON', array('status' => 502));
        }
        return $json;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    private function str($v) {
        if ($v === null || $v === false) return '';
        return is_string($v) ? trim($v) : '';
    }

    private function build_additional_instructions($input_mode, $raw_input, $partner_id) {
        $input_mode = $this->str($input_mode);
        $raw_input  = $this->str($raw_input);
        $parts = array();

        if ($input_mode !== '') {
            $parts[] = '[INPUT_MODE] ' . $input_mode;
        }
        if ($partner_id > 0) {
            $partner = get_post((int) $partner_id);
            if ($partner instanceof WP_Post) {
                $parts[] = '[PARTNER] ' . sanitize_text_field($partner->post_title) . ' (id=' . (int) $partner_id . ')';
            }
        }
        if ($raw_input !== '') {
            $parts[] = "[RAW INPUT]\n" . $raw_input;
        }
        return implode("\n\n", $parts);
    }
}

new RequestDesk_Case_Study_Wizard_Api();
