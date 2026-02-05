<?php
/**
 * RequestDesk Headless API Settings Page
 *
 * Standalone settings for using WordPress as a headless CMS
 * Does NOT require RequestDesk integration
 */

function requestdesk_headless_settings_page() {
    // Save settings if form submitted
    if (isset($_POST['requestdesk_headless_save']) && wp_verify_nonce($_POST['requestdesk_headless_nonce'], 'requestdesk_headless_settings')) {
        $settings = array(
            'api_key' => sanitize_text_field($_POST['headless_api_key']),
            'enabled' => isset($_POST['headless_enabled'])
        );

        update_option('requestdesk_headless_settings', $settings);
        echo '<div class="notice notice-success"><p>Headless API settings saved!</p></div>';
    }

    // Generate new API key if requested
    if (isset($_POST['requestdesk_headless_generate']) && wp_verify_nonce($_POST['requestdesk_headless_nonce'], 'requestdesk_headless_settings')) {
        $new_key = wp_generate_password(32, false);
        $settings = get_option('requestdesk_headless_settings', array());
        $settings['api_key'] = $new_key;
        update_option('requestdesk_headless_settings', $settings);
        echo '<div class="notice notice-success"><p>New API key generated!</p></div>';
    }

    $settings = get_option('requestdesk_headless_settings', array(
        'api_key' => '',
        'enabled' => true
    ));

    $site_url = home_url();
    ?>

    <div class="wrap">
        <h1>Headless API Settings</h1>
        <p>Use WordPress as a headless CMS for Astro, Next.js, or any frontend framework.</p>
        <p><strong>No RequestDesk account required.</strong> This works standalone.</p>

        <form method="post">
            <?php wp_nonce_field('requestdesk_headless_settings', 'requestdesk_headless_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">Enable Headless API</th>
                    <td>
                        <label>
                            <input type="checkbox" name="headless_enabled" value="1" <?php checked($settings['enabled'] ?? true); ?> />
                            Allow external frontends to fetch content via API
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">API Key</th>
                    <td>
                        <input type="text" name="headless_api_key" id="headless_api_key" value="<?php echo esc_attr($settings['api_key']); ?>" class="regular-text" style="font-family: monospace;" readonly />
                        <button type="button" class="button" onclick="copyApiKey()" id="copy-btn">Copy</button>
                        <p class="description">This key authenticates requests to the headless API.</p>
                        <p>
                            <button type="submit" name="requestdesk_headless_generate" class="button">Generate New Key</button>
                        </p>
                        <script>
                        function copyApiKey() {
                            var input = document.getElementById('headless_api_key');
                            navigator.clipboard.writeText(input.value).then(function() {
                                var btn = document.getElementById('copy-btn');
                                btn.textContent = 'Copied!';
                                setTimeout(function() { btn.textContent = 'Copy'; }, 2000);
                            });
                        }
                        </script>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="requestdesk_headless_save" class="button button-primary">Save Settings</button>
            </p>
        </form>

        <hr />

        <h2>API Endpoints</h2>
        <p>Your frontend can fetch content from these endpoints:</p>

        <table class="widefat" style="max-width: 800px;">
            <thead>
                <tr>
                    <th>Endpoint</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>GET /wp-json/requestdesk/v1/headless/posts</code></td>
                    <td>List posts (supports ?page, ?per_page, ?category, ?tag, ?search)</td>
                </tr>
                <tr>
                    <td><code>GET /wp-json/requestdesk/v1/headless/posts/{slug}</code></td>
                    <td>Get single post by slug</td>
                </tr>
                <tr>
                    <td><code>GET /wp-json/requestdesk/v1/headless/pages</code></td>
                    <td>List pages (supports ?page, ?per_page, ?parent)</td>
                </tr>
                <tr>
                    <td><code>GET /wp-json/requestdesk/v1/headless/pages/{slug}</code></td>
                    <td>Get single page by slug</td>
                </tr>
                <tr>
                    <td><code>GET /wp-json/requestdesk/v1/headless/site</code></td>
                    <td>Get site metadata, categories, and menus</td>
                </tr>
            </tbody>
        </table>

        <h3 style="margin-top: 2em;">Authentication</h3>
        <p>Include your API key in requests using one of these methods:</p>
        <ul style="list-style: disc; margin-left: 2em;">
            <li>Header: <code>X-WP-Headless-Key: your-api-key</code></li>
            <li>Header: <code>Authorization: Bearer your-api-key</code></li>
            <li>Query param: <code>?api_key=your-api-key</code></li>
        </ul>

        <h3 style="margin-top: 2em;">Test Your API</h3>
        <?php if (!empty($settings['api_key'])): ?>
        <p>Try this command to test:</p>
        <pre style="background: #f1f1f1; padding: 1em; overflow-x: auto;">curl -H "X-WP-Headless-Key: <?php echo esc_html($settings['api_key']); ?>" \
  "<?php echo esc_url($site_url); ?>/wp-json/requestdesk/v1/headless/posts"</pre>
        <?php else: ?>
        <p><em>Generate an API key above to see test commands.</em></p>
        <?php endif; ?>

        <h3 style="margin-top: 2em;">Astro Example</h3>
        <pre style="background: #f1f1f1; padding: 1em; overflow-x: auto;">// .env
WORDPRESS_URL=<?php echo esc_html($site_url); ?>

WORDPRESS_API_KEY=<?php echo esc_html($settings['api_key'] ?: 'your-api-key'); ?>


// src/pages/blog/[slug].astro
const response = await fetch(
  `${import.meta.env.WORDPRESS_URL}/wp-json/requestdesk/v1/headless/posts/${slug}`,
  { headers: { 'X-WP-Headless-Key': import.meta.env.WORDPRESS_API_KEY } }
);
const { post } = await response.json();</pre>
    </div>
    <?php
}

/**
 * Add Headless API submenu under RequestDesk
 */
function requestdesk_headless_add_admin_menu() {
    add_submenu_page(
        'requestdesk-aeo-analytics',
        'Headless API',
        'Headless API',
        'manage_options',
        'requestdesk-headless',
        'requestdesk_headless_settings_page'
    );
}

// Only add action if WordPress is loaded
if (function_exists('add_action')) {
    add_action('admin_menu', 'requestdesk_headless_add_admin_menu', 20);
}
