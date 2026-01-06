<?php
/**
 * RequestDesk Settings Page
 */

function requestdesk_settings_page() {
    // Check and create missing AEO tables if needed
    global $wpdb;
    $aeo_table_name = $wpdb->prefix . 'requestdesk_aeo_data';

    if ($wpdb->get_var("SHOW TABLES LIKE '$aeo_table_name'") != $aeo_table_name) {
        // Create the missing AEO table
        $charset_collate = $wpdb->get_charset_collate();

        $aeo_sql = "CREATE TABLE IF NOT EXISTS $aeo_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            content_type varchar(20) DEFAULT 'post',
            aeo_score tinyint(3) DEFAULT 0,
            last_analyzed datetime DEFAULT CURRENT_TIMESTAMP,
            ai_questions longtext,
            faq_data longtext,
            citation_stats longtext,
            optimization_status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            KEY content_type (content_type),
            KEY optimization_status (optimization_status),
            KEY aeo_score (aeo_score)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($aeo_sql);

        echo '<div class="notice notice-success"><p>✅ AEO database tables created successfully!</p></div>';
    }

    // Save settings if form submitted
    if (isset($_POST['requestdesk_save_settings']) && wp_verify_nonce($_POST['requestdesk_nonce'], 'requestdesk_settings')) {
        $settings = array(
            'debug_mode' => isset($_POST['debug_mode']),
            'default_post_status' => sanitize_text_field($_POST['default_post_status']),
            'allowed_post_types' => array('post'), // For MVP, only posts
            'api_key' => sanitize_text_field($_POST['api_key']),
            'claude_api_key' => sanitize_text_field($_POST['claude_api_key']),
            'claude_model' => sanitize_text_field($_POST['claude_model']),
            'requestdesk_endpoint' => sanitize_text_field($_POST['requestdesk_endpoint']),
            'auto_sync_on_publish' => isset($_POST['auto_sync_on_publish']),
            'auto_sync_on_update' => isset($_POST['auto_sync_on_update'])
        );

        update_option('requestdesk_settings', $settings);
        echo '<div class="notice notice-success"><p>Settings saved! Claude API key: ' . (empty($_POST['claude_api_key']) ? 'EMPTY' : 'PROVIDED') . '</p></div>';
    }
    
    // Check for bulk sync results
    if (isset($_GET['sync']) && $_GET['sync'] === 'complete') {
        $results = get_transient('requestdesk_bulk_sync_results');
        if ($results) {
            echo '<div class="notice notice-success"><p>';
            echo sprintf('Bulk sync completed: %d posts processed, %d successful, %d failed, %d skipped', 
                $results['total'], $results['success'], $results['failed'], $results['skipped']);
            echo '</p></div>';
            delete_transient('requestdesk_bulk_sync_results');
        }
    }
    
    $settings = get_option('requestdesk_settings', array(
        'debug_mode' => false,
        'default_post_status' => 'draft',
        'allowed_post_types' => array('post'),
        'api_key' => '',
        'claude_api_key' => '',
        'claude_model' => 'claude-sonnet-4-5-20250929'
    ));
    
    // Get sync logs
    global $wpdb;
    $table_name = $wpdb->prefix . 'requestdesk_sync_log';
    $recent_syncs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY sync_date DESC LIMIT 10");
    ?>
    
    <style>
    /* Full-width layout for Settings */
    .wrap.requestdesk-settings {
        margin: 20px 20px 0 2px !important;
        max-width: none !important;
        width: calc(100vw - 180px) !important;
        box-sizing: border-box !important;
    }
    .wrap.requestdesk-settings > * {
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    @media (max-width: 782px) {
        .wrap.requestdesk-settings {
            width: calc(100vw - 50px) !important;
        }
    }
    </style>

    <div class="wrap requestdesk-settings">
        <h1>RequestDesk Connector Settings <span style="color: #666; font-size: 0.7em; font-weight: normal;">v<?php echo REQUESTDESK_VERSION; ?></span></h1>

        <div class="notice notice-info" style="margin-bottom: 20px;">
            <p><strong>Plugin Information:</strong> RequestDesk Connector v<?php echo REQUESTDESK_VERSION; ?> with Universal CSV AEO Template System and Enhanced Action Instructions</p>
        </div>

        <div class="card">
            <h2>Connection Information</h2>
            <table class="form-table">
                <tr>
                    <th>Posts Endpoint</th>
                    <td>
                        <code><?php echo get_rest_url(null, 'requestdesk/v1/pull-posts'); ?></code>
                        <p class="description">RequestDesk uses this endpoint to pull posts from WordPress</p>
                    </td>
                </tr>
                <tr>
                    <th>Pages Endpoint <span style="color: green;">(NEW v1.3.0)</span></th>
                    <td>
                        <code><?php echo get_rest_url(null, 'requestdesk/v1/pull-pages'); ?></code>
                        <p class="description">RequestDesk uses this endpoint to pull pages from WordPress</p>
                    </td>
                </tr>
                <tr>
                    <th>Test Endpoint</th>
                    <td>
                        <code><?php echo get_rest_url(null, 'requestdesk/v1/test-connection'); ?></code>
                        <p class="description">Use this to test your connection</p>
                    </td>
                </tr>
                <tr>
                    <th>Authentication</th>
                    <td>
                        <p class="description">
                            Add header: <code>X-RequestDesk-API-Key: [Your Agent API Key]</code><br>
                            <strong>Security:</strong> Only requests with the exact API key configured above will be accepted.
                        </p>
                        <?php if (!empty($settings['api_key'])): ?>
                        <p class="description" style="color: green;">
                            ✅ <strong>Secure:</strong> API key is configured and connections are protected.
                        </p>
                        <?php else: ?>
                        <p class="description" style="color: red;">
                            ❌ <strong>Insecure:</strong> No API key configured! Configure one above to enable connections.
                        </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('requestdesk_settings', 'requestdesk_nonce'); ?>
            
            <div class="card">
                <h2>📤 Push to RequestDesk RAG Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">RequestDesk Endpoint</th>
                        <td>
                            <input type="text" name="requestdesk_endpoint" value="<?php echo esc_attr($settings['requestdesk_endpoint'] ?? ''); ?>" class="regular-text" placeholder="https://api.requestdesk.ai">
                            <p class="description">
                                Your RequestDesk API endpoint for pushing content to the RAG system
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Auto Sync Options</th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_sync_on_publish" value="1" <?php checked($settings['auto_sync_on_publish'] ?? false, true); ?>>
                                Automatically sync new posts when published
                            </label><br>
                            <label>
                                <input type="checkbox" name="auto_sync_on_update" value="1" <?php checked($settings['auto_sync_on_update'] ?? false, true); ?>>
                                Automatically sync posts when updated
                            </label>
                            <p class="description">
                                Choose when posts should be automatically pushed to RequestDesk's RAG system
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h3>Bulk Sync All Posts</h3>
                <p>Push all published posts to RequestDesk's RAG system at once.</p>
                <?php
                $post_count = wp_count_posts('post');
                $published_count = $post_count->publish;
                ?>
                <p>You have <strong><?php echo $published_count; ?></strong> published posts ready to sync.</p>
                <p class="description">
                    This will push all published posts to RequestDesk's RAG system. Posts that haven't changed since the last sync will be skipped.
                </p>
            </div>
            
            <div class="card">
                <h2>🔐 Security Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">RequestDesk API Key</th>
                        <td>
                            <input type="password" name="api_key" value="<?php echo esc_attr($settings['api_key']); ?>" class="regular-text" placeholder="Enter your RequestDesk Agent API Key">
                            <p class="description">
                                <strong>Required:</strong> Enter your RequestDesk agent's API key to secure this connection.<br>
                                Only requests with this exact API key will be accepted.<br>
                                You can find your agent's API key in the RequestDesk dashboard under Agent Settings.
                            </p>
                            <?php if (empty($settings['api_key']) && !$settings['debug_mode']): ?>
                            <div class="notice notice-warning inline">
                                <p><strong>⚠️ Warning:</strong> No API key configured! Your WordPress site will reject all RequestDesk connections until you set an API key.</p>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Claude AI API Key</th>
                        <td>
                            <input type="password" id="claude_api_key" name="claude_api_key" value="<?php echo esc_attr($settings['claude_api_key'] ?? ''); ?>" class="regular-text" placeholder="sk-ant-api03-...">
                            <button type="button" id="test_claude_connection" class="button" style="margin-left: 10px;">Test Connection</button>
                            <div id="claude_test_result" style="margin-top: 10px;"></div>
                            <p class="description">
                                <strong>Required for AEO Features:</strong> Enter your Claude AI API key to enable Answer Engine Optimization.<br>
                                This powers content analysis, Q&A generation, optimization scoring, and schema markup.<br>
                                Get your API key from <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>.
                            </p>
                            <?php if (empty($settings['claude_api_key'])): ?>
                            <div class="notice notice-info inline">
                                <p><strong>ℹ️ Info:</strong> Claude API key required for AEO features. Content analysis and optimization will be disabled until configured.</p>
                            </div>
                            <?php else: ?>
                            <div class="notice notice-success inline">
                                <p><strong>✅ Ready:</strong> Claude AI integration enabled for AEO features.</p>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Claude AI Model</th>
                        <td>
                            <select id="claude_model" name="claude_model" class="regular-text">
                                <option value="claude-sonnet-4-5-20250929" <?php selected($settings['claude_model'] ?? 'claude-sonnet-4-5-20250929', 'claude-sonnet-4-5-20250929'); ?>>
                                    Claude Sonnet 4.5 (Latest) - Most Capable, Highest Cost
                                </option>
                                <option value="claude-opus-4-1-20250805" <?php selected($settings['claude_model'] ?? '', 'claude-opus-4-1-20250805'); ?>>
                                    Claude Opus 4.1 - Very Capable, High Cost
                                </option>
                                <option value="claude-opus-4-20250514" <?php selected($settings['claude_model'] ?? '', 'claude-opus-4-20250514'); ?>>
                                    Claude Opus 4 - Capable, Moderate Cost
                                </option>
                                <option value="claude-sonnet-4-20250514" <?php selected($settings['claude_model'] ?? '', 'claude-sonnet-4-20250514'); ?>>
                                    Claude Sonnet 4 - Balanced Performance, Moderate Cost
                                </option>
                                <option value="claude-haiku-4-5-20251001" <?php selected($settings['claude_model'] ?? '', 'claude-haiku-4-5-20251001'); ?>>
                                    Claude Haiku 4.5 - Fastest, Lowest Cost
                                </option>
                            </select>
                            <p class="description">
                                <strong>Choose your Claude model:</strong> Ordered by cost (highest to lowest).<br>
                                Higher cost models offer better performance but consume more API credits.<br>
                                <strong>Recommended:</strong> Claude 3.5 Sonnet for best results, Claude 3.5 Haiku for cost savings.
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h2>Plugin Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Debug Mode</th>
                        <td>
                            <label>
                                <input type="checkbox" name="debug_mode" value="1" <?php checked($settings['debug_mode'], true); ?>>
                                Enable debug mode (bypasses API key validation)
                            </label>
                            <p class="description">
                                <strong>⚠️ Security Risk:</strong> Debug mode disables API key validation and accepts ANY API key.<br>
                                Only enable this for testing. <strong>NEVER enable in production!</strong>
                            </p>
                            <?php if ($settings['debug_mode']): ?>
                            <div class="notice notice-error inline">
                                <p><strong>🚨 Security Warning:</strong> Debug mode is currently ENABLED! Your site accepts any API key. Disable this immediately for production use.</p>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Post Status</th>
                        <td>
                            <select name="default_post_status">
                                <option value="draft" <?php selected($settings['default_post_status'], 'draft'); ?>>Draft</option>
                                <option value="pending" <?php selected($settings['default_post_status'], 'pending'); ?>>Pending Review</option>
                                <option value="private" <?php selected($settings['default_post_status'], 'private'); ?>>Private</option>
                                <option value="publish" <?php selected($settings['default_post_status'], 'publish'); ?>>Published</option>
                            </select>
                            <p class="description">Default status for posts created from RequestDesk</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p class="submit">
                <input type="submit" name="requestdesk_save_settings" class="button-primary" value="Save Settings">
            </p>
        </form>

        <!-- Bulk Sync Form (separate from main settings form) -->
        <div class="card">
            <h2>Bulk Operations</h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="requestdesk_bulk_sync">
                <?php wp_nonce_field('requestdesk_bulk_sync'); ?>
                <p>
                    <input type="submit" class="button button-primary" value="Sync All Published Posts (<?php echo $published_count; ?>)"
                           onclick="return confirm('This will sync all <?php echo $published_count; ?> published posts to RequestDesk. Continue?');">
                </p>
            </form>
        </div>
        
        <div class="card">
            <h2>Test Connection</h2>
            <p>Use this curl command to test your connection:</p>
            <pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;">
curl -X GET \
  <?php echo get_rest_url(null, 'requestdesk/v1/test'); ?> \
  -H "X-RequestDesk-API-Key: YOUR_AGENT_API_KEY"</pre>
            
            <p>To send a test post:</p>
            <pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;">
curl -X POST \
  <?php echo get_rest_url(null, 'requestdesk/v1/posts'); ?> \
  -H "Content-Type: application/json" \
  -H "X-RequestDesk-API-Key: YOUR_AGENT_API_KEY" \
  -d '{
    "title": "Test Post from RequestDesk",
    "content": "This is test content from RequestDesk integration.",
    "excerpt": "Test excerpt",
    "ticket_id": "test_ticket_123",
    "agent_id": "test_agent_456",
    "post_status": "draft",
    "categories": ["Test Category"],
    "tags": ["test", "requestdesk"]
  }'</pre>
        </div>
        
        <?php if (!empty($recent_syncs)): ?>
        <div class="card">
            <h2>Recent Sync Activity</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Post ID</th>
                        <th>Agent ID</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_syncs as $sync): ?>
                    <tr>
                        <td><?php echo esc_html($sync->ticket_id); ?></td>
                        <td>
                            <?php if ($sync->post_id): ?>
                                <a href="<?php echo get_edit_post_link($sync->post_id); ?>">
                                    #<?php echo $sync->post_id; ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($sync->agent_id); ?></td>
                        <td>
                            <span class="<?php echo $sync->sync_status === 'success' ? 'text-success' : 'text-error'; ?>">
                                <?php echo esc_html($sync->sync_status); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($sync->sync_date); ?></td>
                        <td><?php echo esc_html($sync->error_message ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Documentation</h2>
            <ul>
                <li><a href="https://requestdesk.ai/docs/wordpress-integration" target="_blank">Integration Guide</a></li>
                <li><a href="https://requestdesk.ai/support" target="_blank">Get Support</a></li>
            </ul>
        </div>
    </div>

    <style>
        .card {
            background: white;
            border: 1px solid #ccd0d4;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .text-success { color: #46b450; }
        .text-error { color: #dc3232; }
    </style>

    <script>
    jQuery(document).ready(function($) {
        $('#test_claude_connection').click(function() {
            var button = $(this);
            var apiKey = $('#claude_api_key').val();
            var resultDiv = $('#claude_test_result');

            if (!apiKey) {
                resultDiv.html('<div class="notice notice-error inline"><p><strong>Error:</strong> Please enter a Claude API key first.</p></div>');
                return;
            }

            button.prop('disabled', true).text('Testing...');
            resultDiv.html('<div class="notice notice-info inline"><p>Testing Claude API connection...</p></div>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'test_claude_connection',
                    api_key: apiKey,
                    nonce: '<?php echo wp_create_nonce('test_claude_connection'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        resultDiv.html('<div class="notice notice-success inline"><p><strong>✅ Success:</strong> ' + response.data.message + '</p></div>');
                    } else {
                        resultDiv.html('<div class="notice notice-error inline"><p><strong>❌ Error:</strong> ' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    resultDiv.html('<div class="notice notice-error inline"><p><strong>❌ Error:</strong> Failed to test connection. Please try again.</p></div>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Test Connection');
                }
            });
        });
    });
    </script>
    <?php
}