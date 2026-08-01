<?php
/**
 * RequestDesk Promote — one-click "promote a Local post to Live".
 *
 * This is the granular opposite of a full Magic Sync: instead of overwriting the
 * entire live database to ship one blog change, it updates exactly one post on
 * the live site and touches nothing else.
 *
 * v1 scope — UPDATE AN EXISTING LIVE POST ONLY.
 *   - Pushes title + content (with .local -> .com URL rewrite) + excerpt + the
 *     AEO Q&A pairs to the matching live post, by the SAME post ID.
 *   - PRESERVES the live post's status, URL/slug, publish date, author,
 *     categories, tags, and featured image (does not send them, so live keeps
 *     its own). The blast radius is deliberately the post body + Q&A.
 *   - Refuses to run unless it can confirm, via the live site's public REST API,
 *     that a post exists at that ID with a MATCHING slug. This makes it
 *     impossible to overwrite the wrong post or silently create a duplicate.
 *
 * Deferred (explicitly not handled yet): creating brand-new posts on live,
 * sideloading images that were newly uploaded on Local, and re-linking Polylang
 * (Spanish) translations.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Promote {

    public function __construct() {
        add_filter('post_row_actions', array($this, 'add_row_action'), 10, 2);
        add_action('post_submitbox_misc_actions', array($this, 'render_editor_button'));
        add_action('admin_post_requestdesk_promote', array($this, 'handle_promote_request'));
        add_action('admin_notices', array($this, 'maybe_render_notice'));
    }

    /**
     * Live target (URL + API key) from connector settings.
     */
    private function get_target() {
        $settings = get_option('requestdesk_settings', array());
        return array(
            'url' => untrailingslashit($settings['promote_target_url'] ?? ''),
            'key' => $settings['promote_api_key'] ?? '',
        );
    }

    private function is_configured() {
        $t = $this->get_target();
        return !empty($t['url']) && !empty($t['key']);
    }

    /* ---------------------------------------------------------------------
     * UI
     * ------------------------------------------------------------------- */

    public function add_row_action($actions, $post) {
        if ($post->post_type !== 'post' || $post->post_status !== 'publish') {
            return $actions;
        }
        if (!current_user_can('edit_post', $post->ID)) {
            return $actions;
        }
        $actions['requestdesk_promote'] = sprintf(
            '<a href="%s" onclick="return confirm(\'Promote this post to LIVE? This updates the live post\\\'s content and Q&A in place.\');">%s</a>',
            esc_url($this->promote_url($post->ID)),
            esc_html__('Promote to Live', 'requestdesk')
        );
        return $actions;
    }

    public function render_editor_button($post) {
        if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish') {
            return;
        }
        if (!current_user_can('edit_post', $post->ID)) {
            return;
        }
        ?>
        <div class="misc-pub-section" style="border-top:1px solid #eee;">
            <a href="<?php echo esc_url($this->promote_url($post->ID)); ?>"
               class="button button-secondary"
               onclick="return confirm('Promote this post to LIVE? This updates the live post\'s content and Q&A in place.');">
                ⬆ <?php esc_html_e('Promote to Live', 'requestdesk'); ?>
            </a>
            <?php if (!$this->is_configured()): ?>
                <p style="color:#b32d2e;margin:6px 0 0;font-size:11px;">
                    <?php esc_html_e('Set the live URL + API key under RequestDesk settings first.', 'requestdesk'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function promote_url($post_id) {
        return wp_nonce_url(
            admin_url('admin-post.php?action=requestdesk_promote&post=' . (int) $post_id),
            'requestdesk_promote_' . (int) $post_id
        );
    }

    /* ---------------------------------------------------------------------
     * Request handler
     * ------------------------------------------------------------------- */

    public function handle_promote_request() {
        $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;

        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_die('Not allowed.');
        }
        check_admin_referer('requestdesk_promote_' . $post_id);

        $result = $this->promote_post($post_id);

        $redirect = wp_get_referer() ?: admin_url('edit.php');
        if (is_wp_error($result)) {
            $redirect = add_query_arg(array(
                'rd_promote' => 'error',
                'rd_promote_msg' => rawurlencode($result->get_error_message()),
            ), $redirect);
        } else {
            $redirect = add_query_arg(array(
                'rd_promote' => 'ok',
                'rd_promote_url' => rawurlencode($result['live_url']),
                'rd_promote_qa' => (int) $result['qa_pushed'],
                'rd_promote_warn' => rawurlencode($result['warning'] ?? ''),
            ), $redirect);
        }
        wp_safe_redirect($redirect);
        exit;
    }

    /* ---------------------------------------------------------------------
     * Core: promote (update) one post to live
     * ------------------------------------------------------------------- */

    /**
     * @return array|WP_Error  array{live_url,qa_pushed,warning} on success
     */
    public function promote_post($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post') {
            return new WP_Error('rd_promote', 'Local post not found.');
        }
        if ($post->post_status !== 'publish') {
            return new WP_Error('rd_promote', 'Only published posts can be promoted (v1 updates existing live posts).');
        }

        $target = $this->get_target();
        if (empty($target['url']) || empty($target['key'])) {
            return new WP_Error('rd_promote', 'Live target not configured. Set the promote URL + API key in RequestDesk settings.');
        }

        // --- Identity guard: the live post must exist at this ID with the same slug.
        $guard = $this->verify_live_target($target, $post_id, $post->post_name);
        if (is_wp_error($guard)) {
            return $guard;
        }

        // --- Body: rewrite local domain -> live domain so inline media/links resolve.
        $content = $this->rewrite_urls($post->post_content, $target['url']);
        $warning = $this->image_warning($post->post_content);

        // --- Push the post body (UPDATE by same ID; preserve live's own status).
        $publish = $this->remote_post($target, '/wp-json/requestdesk/v1/publish', array(
            'post_id' => $post_id,               // same-ID update (guarded above)
            'title'   => $post->post_title,
            'content' => $content,
            'excerpt' => $post->post_excerpt,
            'slug'    => $post->post_name,
            'status'  => $guard['status'],       // preserve live's current status
        ));
        if (is_wp_error($publish)) {
            return $publish;
        }

        // --- Push the AEO Q&A pairs (if any) to the same post.
        $qa_pushed = 0;
        $qa_pairs = $this->get_local_qa_pairs($post_id);
        if (!empty($qa_pairs)) {
            $qa = $this->remote_post($target, '/wp-json/requestdesk/v1/aeo-qa/' . $post_id, array(
                'qa_pairs' => $qa_pairs,
                'mode'     => 'replace',
            ));
            if (is_wp_error($qa)) {
                // Body already promoted; report Q&A failure as a soft warning.
                $warning = trim($warning . ' Q&A push failed: ' . $qa->get_error_message());
            } else {
                $qa_pushed = isset($qa['qa_pairs_saved']) ? (int) $qa['qa_pairs_saved'] : count($qa_pairs);
            }
        }

        return array(
            'live_url'  => $guard['link'],
            'qa_pushed' => $qa_pushed,
            'warning'   => $warning,
        );
    }

    /**
     * Confirm the live post exists at this ID and its slug matches, via the
     * connector's API-key'd /post-identity endpoint. We use the requestdesk/v1
     * namespace (not public wp/v2) because production locks down wp/v2 — this
     * namespace is what actually works headlessly against live.
     *
     * @return array|WP_Error  array{status,link} on success
     */
    private function verify_live_target($target, $post_id, $local_slug) {
        $url = $target['url'] . '/wp-json/requestdesk/v1/post-identity/' . (int) $post_id;
        $resp = wp_remote_get($url, array(
            'timeout' => 20,
            'headers' => array('X-RequestDesk-API-Key' => $target['key']),
        ));

        if (is_wp_error($resp)) {
            return new WP_Error('rd_promote', 'Could not reach live site: ' . $resp->get_error_message());
        }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code === 401) {
            return new WP_Error('rd_promote', 'Live site rejected the API key (401). Check the Live API Key in settings.');
        }
        if ($code !== 200) {
            return new WP_Error('rd_promote', "Live site returned HTTP {$code} checking post {$post_id}.");
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($body['exists'])) {
            return new WP_Error('rd_promote', "No live post at ID {$post_id}. v1 only updates existing live posts — this one would need to be created (deferred).");
        }
        if (empty($body['slug']) || $body['slug'] !== $local_slug) {
            return new WP_Error('rd_promote', sprintf(
                'Slug mismatch: live post %d is "%s" but local is "%s". Refusing to overwrite a different post.',
                $post_id, $body['slug'] ?? '(none)', $local_slug
            ));
        }

        return array(
            'status' => !empty($body['status']) ? $body['status'] : 'publish',
            'link'   => !empty($body['link']) ? $body['link'] : ($target['url'] . '/?p=' . $post_id),
        );
    }

    /**
     * Read local AEO Q&A pairs for a post, shaped for the /aeo-qa endpoint.
     */
    private function get_local_qa_pairs($post_id) {
        if (!class_exists('RequestDesk_AEO_Core')) {
            return array();
        }
        $core = new RequestDesk_AEO_Core();
        $data = $core->get_aeo_data($post_id);
        $pairs = is_array($data['ai_questions'] ?? null) ? $data['ai_questions'] : array();

        $out = array();
        foreach ($pairs as $p) {
            if (empty($p['question']) || empty($p['answer'])) {
                continue;
            }
            $out[] = array(
                'question'   => $p['question'],
                'answer'     => $p['answer'],
                'confidence' => isset($p['confidence']) ? (float) $p['confidence'] : 1.0,
            );
        }
        return $out;
    }

    /**
     * Rewrite this (Local) site's home URL to the live target URL in content, so
     * inline images and links point at the live copies (which already exist for
     * cloned posts).
     */
    private function rewrite_urls($content, $live_url) {
        $local = untrailingslashit(home_url());
        $live  = untrailingslashit($live_url);

        // Cover both http/https of the local host.
        $local_host = wp_parse_url($local, PHP_URL_HOST);
        $replacements = array(
            'https://' . $local_host => $live,
            'http://' . $local_host  => $live,
        );
        // Normalise: strip scheme from $live once, re-add per replacement.
        $live_host = wp_parse_url($live, PHP_URL_HOST);
        $live_scheme = wp_parse_url($live, PHP_URL_SCHEME) ?: 'https';
        foreach ($replacements as $from => $_) {
            $content = str_replace($from, $live_scheme . '://' . $live_host, $content);
        }
        return $content;
    }

    /**
     * Warn if the body references upload URLs on the local host — a newly
     * uploaded image won't exist on live until a media sync.
     */
    private function image_warning($content) {
        $local_host = wp_parse_url(home_url(), PHP_URL_HOST);
        if ($local_host && strpos($content, $local_host . '/wp-content/uploads') !== false) {
            return 'Note: this post references locally-hosted images. Existing images resolve on live after URL rewrite; any newly-uploaded image will 404 on live until media is synced.';
        }
        return '';
    }

    /**
     * POST JSON to a live connector endpoint with the API key. Returns the
     * decoded body on 2xx, WP_Error otherwise.
     */
    private function remote_post($target, $path, $payload) {
        $resp = wp_remote_post($target['url'] . $path, array(
            'timeout' => 45,
            'headers' => array(
                'Content-Type'            => 'application/json',
                'X-RequestDesk-API-Key'   => $target['key'],
            ),
            'body' => wp_json_encode($payload),
        ));

        if (is_wp_error($resp)) {
            return new WP_Error('rd_promote', 'Live request failed: ' . $resp->get_error_message());
        }
        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code < 200 || $code >= 300) {
            $msg = is_array($body) && !empty($body['message']) ? $body['message'] : "HTTP {$code}";
            return new WP_Error('rd_promote', "Live rejected {$path}: {$msg}");
        }
        return is_array($body) ? $body : array();
    }

    /* ---------------------------------------------------------------------
     * Admin notice after a promote
     * ------------------------------------------------------------------- */

    public function maybe_render_notice() {
        if (empty($_GET['rd_promote'])) {
            return;
        }
        if ($_GET['rd_promote'] === 'ok') {
            $url = isset($_GET['rd_promote_url']) ? esc_url_raw(rawurldecode($_GET['rd_promote_url'])) : '';
            $qa  = isset($_GET['rd_promote_qa']) ? (int) $_GET['rd_promote_qa'] : 0;
            $warn = isset($_GET['rd_promote_warn']) ? sanitize_text_field(rawurldecode($_GET['rd_promote_warn'])) : '';
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo '✅ <strong>Promoted to live.</strong> ';
            echo 'Updated ' . ($qa > 0 ? esc_html($qa) . ' Q&A + content' : 'content') . '. ';
            if ($url) {
                echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">View live post ↗</a>';
            }
            echo '</p>';
            if ($warn) {
                echo '<p style="color:#8a6d3b;">⚠ ' . esc_html($warn) . '</p>';
            }
            echo '</div>';
        } elseif ($_GET['rd_promote'] === 'error') {
            $msg = isset($_GET['rd_promote_msg']) ? sanitize_text_field(rawurldecode($_GET['rd_promote_msg'])) : 'Unknown error';
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo '❌ <strong>Promote failed:</strong> ' . esc_html($msg);
            echo '</p></div>';
        }
    }
}
