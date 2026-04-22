<?php
/**
 * RequestDesk IndexNow
 *
 * Submits new and updated URLs to IndexNow (Bing, Yandex, Seznam, Naver) on publish.
 * Also supports bulk submission of all published URLs for migration-recovery scenarios.
 *
 * @since 2.14.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_IndexNow {

    const OPTION_KEY        = 'requestdesk_indexnow_key';
    const OPTION_ENABLED    = 'requestdesk_indexnow_enabled';
    const OPTION_POST_TYPES = 'requestdesk_indexnow_post_types';
    const OPTION_LOG        = 'requestdesk_indexnow_log';
    const ENDPOINT          = 'https://api.indexnow.org/indexnow';
    const BATCH_SIZE        = 1000;
    const MAX_LOG_ENTRIES   = 50;
    const REMOTE_TIMEOUT    = 15;
    const NONCE_ACTION      = 'requestdesk_indexnow_bulk';

    public function __construct() {
        // Ensure a key exists (runs lazily on first admin page load)
        add_action('admin_init', array($this, 'maybe_generate_key'));

        // Serve the key file
        add_action('template_redirect', array($this, 'serve_key_file'));

        // Auto-submit on publish or update
        add_action('transition_post_status', array($this, 'on_post_transition'), 10, 3);

        // Admin UI
        add_action('admin_menu', array($this, 'add_admin_menu'), 25);

        // AJAX handlers
        add_action('wp_ajax_requestdesk_indexnow_bulk', array($this, 'ajax_bulk_submit'));
        add_action('wp_ajax_requestdesk_indexnow_test_key', array($this, 'ajax_test_key'));
        add_action('wp_ajax_requestdesk_indexnow_regenerate', array($this, 'ajax_regenerate_key'));
    }

    /* ---------------- Key management ---------------- */

    public function maybe_generate_key() {
        if (!get_option(self::OPTION_KEY)) {
            update_option(self::OPTION_KEY, $this->generate_uuid_v4());
        }
    }

    private function generate_uuid_v4() {
        if (function_exists('wp_generate_uuid4')) {
            return str_replace('-', '', wp_generate_uuid4());
        }
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return bin2hex($data);
    }

    public function get_key() {
        $key = get_option(self::OPTION_KEY);
        if (!$key) {
            $key = $this->generate_uuid_v4();
            update_option(self::OPTION_KEY, $key);
        }
        return $key;
    }

    public function serve_key_file() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $path = parse_url($request_uri, PHP_URL_PATH);
        if (empty($path)) {
            return;
        }

        // Match /{key}.txt exactly
        $key = $this->get_key();
        if ($path === '/' . $key . '.txt') {
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
            echo esc_html($key);
            exit;
        }
    }

    public function get_key_file_url() {
        return home_url('/' . $this->get_key() . '.txt');
    }

    /* ---------------- Auto-submission ---------------- */

    public function on_post_transition($new_status, $old_status, $post) {
        if (!get_option(self::OPTION_ENABLED)) {
            return;
        }

        // Only submit when going to publish
        if ($new_status !== 'publish') {
            return;
        }

        // Skip revisions and auto-saves
        if (wp_is_post_revision($post) || wp_is_post_autosave($post)) {
            return;
        }

        $allowed_types = $this->get_enabled_post_types();
        if (!in_array($post->post_type, $allowed_types, true)) {
            return;
        }

        $url = get_permalink($post);
        if (!$url) {
            return;
        }

        $trigger = ($old_status === 'publish') ? 'update' : 'publish';
        $this->submit_urls(array($url), $trigger);
    }

    /* ---------------- Submission ---------------- */

    public function submit_urls(array $urls, $trigger = 'publish') {
        if (empty($urls)) {
            return array('success' => false, 'message' => 'No URLs supplied');
        }

        $host = parse_url(home_url(), PHP_URL_HOST);
        $key  = $this->get_key();

        $payload = array(
            'host'        => $host,
            'key'         => $key,
            'keyLocation' => $this->get_key_file_url(),
            'urlList'     => array_values($urls),
        );

        $response = wp_remote_post(self::ENDPOINT, array(
            'timeout' => self::REMOTE_TIMEOUT,
            'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
            'body'    => wp_json_encode($payload),
        ));

        if (is_wp_error($response)) {
            $this->log_entry(count($urls), 0, $response->get_error_message(), $trigger);
            error_log('[RequestDesk IndexNow] Submission failed: ' . $response->get_error_message());
            return array('success' => false, 'message' => $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $note = $this->summarize_response_code($code);

        $this->log_entry(count($urls), $code, $note, $trigger);

        return array(
            'success'       => ($code >= 200 && $code < 300),
            'response_code' => $code,
            'message'       => $note,
            'body'          => $body,
        );
    }

    private function summarize_response_code($code) {
        $map = array(
            200 => 'OK — accepted',
            202 => 'Accepted — queued for validation',
            400 => 'Bad Request — check payload',
            403 => 'Forbidden — key file not valid',
            422 => 'Unprocessable — URL host mismatch',
            429 => 'Too Many Requests — rate limited',
        );
        return isset($map[$code]) ? $map[$code] : 'HTTP ' . intval($code);
    }

    /* ---------------- Bulk submission ---------------- */

    public function ajax_bulk_submit() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'), 403);
        }
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $urls = $this->get_all_public_urls();

        if (empty($urls)) {
            wp_send_json_success(array(
                'batches'   => 0,
                'submitted' => 0,
                'message'   => 'No URLs found to submit.',
            ));
        }

        $batches = array_chunk($urls, self::BATCH_SIZE);
        $results = array();
        $ok_count = 0;
        foreach ($batches as $i => $batch) {
            $result = $this->submit_urls($batch, 'bulk');
            $results[] = array(
                'batch'  => $i + 1,
                'count'  => count($batch),
                'code'   => isset($result['response_code']) ? $result['response_code'] : null,
                'note'   => isset($result['message']) ? $result['message'] : '',
                'ok'     => !empty($result['success']),
            );
            if (!empty($result['success'])) {
                $ok_count += count($batch);
            } else {
                // Abort on first failure — usually a key/host problem
                break;
            }
            // Pace between batches to be polite
            if (count($batches) > 1 && $i < count($batches) - 1) {
                sleep(2);
            }
        }

        wp_send_json_success(array(
            'total_urls' => count($urls),
            'batches'    => count($batches),
            'submitted'  => $ok_count,
            'results'    => $results,
        ));
    }

    public function ajax_test_key() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'), 403);
        }
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $response = wp_remote_get($this->get_key_file_url(), array('timeout' => 10));
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = trim(wp_remote_retrieve_body($response));
        $key  = $this->get_key();

        if ($code === 200 && $body === $key) {
            wp_send_json_success(array('message' => 'Key file accessible and matches stored key.'));
        }

        wp_send_json_error(array(
            'message' => sprintf('Key file check failed. HTTP %d, body match: %s', $code, $body === $key ? 'yes' : 'no'),
        ));
    }

    public function ajax_regenerate_key() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'), 403);
        }
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $new_key = $this->generate_uuid_v4();
        update_option(self::OPTION_KEY, $new_key);
        wp_send_json_success(array('key' => $new_key));
    }

    public function get_all_public_urls() {
        $post_types = $this->get_enabled_post_types();
        $urls = array(home_url('/'));

        foreach ($post_types as $type) {
            $paged = 1;
            $per_page = 500;
            while (true) {
                $posts = get_posts(array(
                    'post_type'      => $type,
                    'post_status'    => 'publish',
                    'posts_per_page' => $per_page,
                    'paged'          => $paged,
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                ));
                if (empty($posts)) {
                    break;
                }
                foreach ($posts as $pid) {
                    $permalink = get_permalink($pid);
                    if ($permalink) {
                        $urls[] = $permalink;
                    }
                }
                if (count($posts) < $per_page) {
                    break;
                }
                $paged++;
            }
        }

        return array_values(array_unique($urls));
    }

    /* ---------------- Settings ---------------- */

    public function get_enabled_post_types() {
        $stored = get_option(self::OPTION_POST_TYPES);
        if (!is_array($stored) || empty($stored)) {
            return array('post', 'page');
        }
        return $stored;
    }

    public function add_admin_menu() {
        add_submenu_page(
            'requestdesk-aeo-analytics',
            'IndexNow',
            'IndexNow',
            'manage_options',
            'requestdesk-indexnow',
            array($this, 'render_settings_page')
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Handle settings POST
        if (isset($_POST['requestdesk_indexnow_save']) && check_admin_referer('requestdesk_indexnow_settings')) {
            update_option(self::OPTION_ENABLED, !empty($_POST['indexnow_enabled']));
            $types = isset($_POST['indexnow_post_types']) && is_array($_POST['indexnow_post_types'])
                ? array_map('sanitize_text_field', $_POST['indexnow_post_types'])
                : array();
            update_option(self::OPTION_POST_TYPES, $types);
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }

        $key            = $this->get_key();
        $enabled        = (bool) get_option(self::OPTION_ENABLED);
        $enabled_types  = $this->get_enabled_post_types();
        $all_types      = get_post_types(array('public' => true), 'objects');
        $key_file_url   = $this->get_key_file_url();
        $log            = get_option(self::OPTION_LOG, array());
        $url_count      = count($this->get_all_public_urls());
        $nonce          = wp_create_nonce(self::NONCE_ACTION);

        $masked_key = substr($key, 0, 8) . str_repeat('•', 16) . substr($key, -4);

        ?>
        <div class="wrap">
            <h1>IndexNow Submission</h1>
            <p>Signal search engines (Bing, Yandex, Seznam, Naver) to re-crawl your URLs on publish or in bulk.</p>

            <h2>Status</h2>
            <table class="form-table">
                <tr>
                    <th>Site key</th>
                    <td>
                        <code><?php echo esc_html($masked_key); ?></code>
                        <button type="button" class="button" id="rd-in-regen">Regenerate key</button>
                    </td>
                </tr>
                <tr>
                    <th>Key file</th>
                    <td>
                        <a href="<?php echo esc_url($key_file_url); ?>" target="_blank"><?php echo esc_html($key_file_url); ?></a>
                        <button type="button" class="button" id="rd-in-test">Test key file</button>
                        <span id="rd-in-test-result"></span>
                    </td>
                </tr>
            </table>

            <form method="post">
                <?php wp_nonce_field('requestdesk_indexnow_settings'); ?>
                <h2>Auto-submission</h2>
                <table class="form-table">
                    <tr>
                        <th>Enable</th>
                        <td>
                            <label>
                                <input type="checkbox" name="indexnow_enabled" value="1" <?php checked($enabled); ?> />
                                Submit URLs to IndexNow automatically when posts are published or updated
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Post types</th>
                        <td>
                            <?php foreach ($all_types as $pt_name => $pt_obj):
                                if (in_array($pt_name, array('attachment', 'revision'), true)) continue;
                                ?>
                                <label style="display:inline-block; margin-right:1em;">
                                    <input type="checkbox"
                                           name="indexnow_post_types[]"
                                           value="<?php echo esc_attr($pt_name); ?>"
                                           <?php checked(in_array($pt_name, $enabled_types, true)); ?> />
                                    <?php echo esc_html($pt_obj->labels->name); ?>
                                    <code><?php echo esc_html($pt_name); ?></code>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="submit" name="requestdesk_indexnow_save" class="button button-primary">Save settings</button>
                </p>
            </form>

            <h2>Bulk submit</h2>
            <p>Submit <strong><?php echo intval($url_count); ?></strong> published URLs (homepage + selected post types) to IndexNow.</p>
            <p>
                <button type="button" id="rd-in-bulk" class="button button-secondary">Submit all URLs now</button>
                <span id="rd-in-bulk-result" style="margin-left:1em;"></span>
            </p>

            <h2>Recent submissions</h2>
            <?php if (empty($log)): ?>
                <p><em>No submissions yet.</em></p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr><th>When</th><th>URLs</th><th>Trigger</th><th>Response</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_reverse($log) as $entry): ?>
                        <tr>
                            <td><?php echo esc_html(date('Y-m-d H:i:s', $entry['timestamp'])); ?></td>
                            <td><?php echo intval($entry['urls_count']); ?></td>
                            <td><?php echo esc_html($entry['trigger']); ?></td>
                            <td>
                                <?php echo intval($entry['response_code']); ?>
                                <?php if (!empty($entry['response_note'])): ?>
                                    — <?php echo esc_html($entry['response_note']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <script>
        (function(){
            var nonce = <?php echo wp_json_encode($nonce); ?>;
            var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

            function post(action, cb) {
                var body = new URLSearchParams();
                body.append('action', action);
                body.append('nonce', nonce);
                fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                    .then(function(r){ return r.json(); })
                    .then(cb)
                    .catch(function(e){ cb({success:false, data:{message:String(e)}}); });
            }

            var testBtn = document.getElementById('rd-in-test');
            if (testBtn) testBtn.addEventListener('click', function(){
                var out = document.getElementById('rd-in-test-result');
                out.textContent = ' Testing...';
                post('requestdesk_indexnow_test_key', function(res){
                    out.textContent = ' ' + (res.success ? '✓ ' + res.data.message : '✗ ' + (res.data && res.data.message || 'Failed'));
                });
            });

            var bulkBtn = document.getElementById('rd-in-bulk');
            if (bulkBtn) bulkBtn.addEventListener('click', function(){
                if (!confirm('Submit all URLs to IndexNow now?')) return;
                var out = document.getElementById('rd-in-bulk-result');
                bulkBtn.disabled = true;
                out.textContent = 'Submitting... this may take a few seconds.';
                post('requestdesk_indexnow_bulk', function(res){
                    bulkBtn.disabled = false;
                    if (res.success) {
                        out.textContent = 'Submitted ' + res.data.submitted + ' / ' + res.data.total_urls + ' URLs in ' + res.data.batches + ' batch(es).';
                        setTimeout(function(){ location.reload(); }, 1500);
                    } else {
                        out.textContent = 'Error: ' + (res.data && res.data.message || 'unknown');
                    }
                });
            });

            var regenBtn = document.getElementById('rd-in-regen');
            if (regenBtn) regenBtn.addEventListener('click', function(){
                if (!confirm('Generate a new IndexNow key? Existing submissions under the old key will stop working.')) return;
                post('requestdesk_indexnow_regenerate', function(res){
                    if (res.success) {
                        alert('New key: ' + res.data.key);
                        location.reload();
                    } else {
                        alert('Failed: ' + (res.data && res.data.message || 'unknown'));
                    }
                });
            });
        })();
        </script>
        <?php
    }

    /* ---------------- Logging ---------------- */

    private function log_entry($urls_count, $response_code, $response_note, $trigger) {
        $log = get_option(self::OPTION_LOG, array());
        if (!is_array($log)) {
            $log = array();
        }
        $log[] = array(
            'timestamp'     => time(),
            'urls_count'    => intval($urls_count),
            'response_code' => intval($response_code),
            'response_note' => substr((string) $response_note, 0, 200),
            'trigger'       => $trigger,
        );
        if (count($log) > self::MAX_LOG_ENTRIES) {
            $log = array_slice($log, -self::MAX_LOG_ENTRIES);
        }
        update_option(self::OPTION_LOG, $log, false);
    }
}
