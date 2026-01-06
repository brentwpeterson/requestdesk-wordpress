<?php
/**
 * RequestDesk AEO Settings Page
 */

/**
 * Add AEO admin menu
 */
function requestdesk_aeo_add_admin_menu() {
    // Bulk AEO Tools submenu
    add_submenu_page(
        'requestdesk-aeo-analytics',
        'Bulk AEO Tools',
        'Bulk Tools',
        'manage_options',
        'requestdesk-aeo-bulk',
        'requestdesk_aeo_bulk_page'
    );
}

/**
 * AEO Settings Page
 */
function requestdesk_aeo_settings_page() {
    // Save settings if form submitted
    if (isset($_POST['requestdesk_aeo_save_settings']) && wp_verify_nonce($_POST['requestdesk_aeo_nonce'], 'requestdesk_aeo_settings')) {
        $settings = array(
            'enabled' => isset($_POST['enabled']),
            'auto_optimize_on_publish' => isset($_POST['auto_optimize_on_publish']),
            'auto_optimize_on_update' => isset($_POST['auto_optimize_on_update']),
            'generate_faq_schema' => isset($_POST['generate_faq_schema']),
            'extract_qa_pairs' => isset($_POST['extract_qa_pairs']),
            'track_citations' => isset($_POST['track_citations']),
            'monitor_freshness' => isset($_POST['monitor_freshness']),
            'min_content_length' => intval($_POST['min_content_length']),
            'qa_extraction_confidence' => floatval($_POST['qa_extraction_confidence']),
            'freshness_alert_days' => intval($_POST['freshness_alert_days']),
            'auto_display_qa_frontend' => isset($_POST['auto_display_qa_frontend']),
            'qa_frontend_title' => sanitize_text_field($_POST['qa_frontend_title']),
            'qa_frontend_max_pairs' => intval($_POST['qa_frontend_max_pairs']),
            'qa_frontend_min_confidence' => floatval($_POST['qa_frontend_min_confidence'])
        );

        update_option('requestdesk_aeo_settings', $settings);
        echo '<div class="notice notice-success"><p>AEO settings saved!</p></div>';
    }

    $settings = get_option('requestdesk_aeo_settings', array(
        'enabled' => true,
        'auto_optimize_on_publish' => true,
        'auto_optimize_on_update' => false,
        'generate_faq_schema' => true,
        'extract_qa_pairs' => true,
        'track_citations' => true,
        'monitor_freshness' => true,
        'min_content_length' => 300,
        'qa_extraction_confidence' => 0.7,
        'freshness_alert_days' => 90,
        'auto_display_qa_frontend' => false,
        'qa_frontend_title' => 'Frequently Asked Questions',
        'qa_frontend_max_pairs' => 5,
        'qa_frontend_min_confidence' => 0.7
    ));

    // Get system status
    $aeo_core = new RequestDesk_AEO_Core();
    $citation_tracker = new RequestDesk_Citation_Tracker();
    $freshness_tracker = new RequestDesk_Freshness_Tracker();

    $citation_analytics = $citation_tracker->get_citation_analytics();
    $freshness_analytics = $freshness_tracker->get_freshness_analytics();
    ?>

    <div class="wrap">
        <h1>RequestDesk AEO Settings</h1>

        <div class="card">
            <h2>📊 AEO System Overview</h2>
            <div class="aeo-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                <div class="stat-box" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa;">
                    <h3 style="margin: 0; color: #0073aa;">Content Statistics</h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold;"><?php echo $citation_analytics['total_statistics']; ?></p>
                    <small>Total citation-ready stats</small>
                </div>
                <div class="stat-box" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #46b450;">
                    <h3 style="margin: 0; color: #46b450;">Content Freshness</h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold;"><?php echo $freshness_analytics['avg_freshness_score']; ?>%</p>
                    <small>Average freshness score</small>
                </div>
                <div class="stat-box" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #ffb900;">
                    <h3 style="margin: 0; color: #ffb900;">Needs Attention</h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold;"><?php echo $freshness_analytics['needs_attention']; ?></p>
                    <small>Posts needing freshness updates</small>
                </div>
                <div class="stat-box" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #d63638;">
                    <h3 style="margin: 0; color: #d63638;">High Quality Stats</h3>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold;"><?php echo $citation_analytics['high_quality_stats']; ?></p>
                    <small>Premium citation stats</small>
                </div>
            </div>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field('requestdesk_aeo_settings', 'requestdesk_aeo_nonce'); ?>

            <div class="card">
                <h2>🚀 Core AEO Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable AEO System</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled'], true); ?>>
                                Enable Answer Engine Optimization features
                            </label>
                            <p class="description">
                                Master switch for all AEO functionality. When disabled, no AEO processing will occur.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Auto-Optimization</th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_optimize_on_publish" value="1" <?php checked($settings['auto_optimize_on_publish'], true); ?>>
                                Automatically optimize content when published
                            </label><br>
                            <label>
                                <input type="checkbox" name="auto_optimize_on_update" value="1" <?php checked($settings['auto_optimize_on_update'], true); ?>>
                                Automatically optimize content when updated
                            </label>
                            <p class="description">
                                Choose when AEO optimization should run automatically. Manual optimization is always available.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Minimum Content Length</th>
                        <td>
                            <input type="number" name="min_content_length" value="<?php echo esc_attr($settings['min_content_length']); ?>" min="100" max="2000" class="small-text">
                            <p class="description">
                                Minimum word count required for AEO optimization. Shorter content will be skipped.
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <h2>❓ Question & Answer Extraction</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Extract Q&A Pairs</th>
                        <td>
                            <label>
                                <input type="checkbox" name="extract_qa_pairs" value="1" <?php checked($settings['extract_qa_pairs'], true); ?>>
                                Automatically extract question-answer pairs from content
                            </label>
                            <p class="description">
                                AI engines love Q&A format. This extracts natural questions and answers from your content.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Q&A Confidence Threshold</th>
                        <td>
                            <select name="qa_extraction_confidence">
                                <option value="0.5" <?php selected($settings['qa_extraction_confidence'], 0.5); ?>>50% - More Q&A pairs, lower quality</option>
                                <option value="0.6" <?php selected($settings['qa_extraction_confidence'], 0.6); ?>>60% - Balanced</option>
                                <option value="0.7" <?php selected($settings['qa_extraction_confidence'], 0.7); ?>>70% - Recommended</option>
                                <option value="0.8" <?php selected($settings['qa_extraction_confidence'], 0.8); ?>>80% - High quality only</option>
                                <option value="0.9" <?php selected($settings['qa_extraction_confidence'], 0.9); ?>>90% - Excellent quality only</option>
                            </select>
                            <p class="description">
                                Higher thresholds extract fewer but higher-quality Q&A pairs.
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <h2>🌐 Frontend Q&A Display</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Auto-Display Q&A on Frontend</th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_display_qa_frontend" value="1" <?php checked($settings['auto_display_qa_frontend'] ?? false, true); ?>>
                                Automatically display Q&A pairs at the end of posts/pages
                            </label>
                            <p class="description">
                                When enabled, Q&A pairs will automatically appear at the end of post content on the frontend.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Q&A Section Title</th>
                        <td>
                            <input type="text" name="qa_frontend_title" value="<?php echo esc_attr($settings['qa_frontend_title'] ?? 'Frequently Asked Questions'); ?>" class="regular-text">
                            <p class="description">
                                The title displayed above Q&A pairs on the frontend.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Maximum Q&A Pairs to Display</th>
                        <td>
                            <select name="qa_frontend_max_pairs">
                                <option value="0" <?php selected($settings['qa_frontend_max_pairs'] ?? 0, 0); ?>>Show all available pairs</option>
                                <option value="3" <?php selected($settings['qa_frontend_max_pairs'] ?? 0, 3); ?>>3 pairs</option>
                                <option value="5" <?php selected($settings['qa_frontend_max_pairs'] ?? 0, 5); ?>>5 pairs</option>
                                <option value="8" <?php selected($settings['qa_frontend_max_pairs'] ?? 0, 8); ?>>8 pairs</option>
                                <option value="10" <?php selected($settings['qa_frontend_max_pairs'] ?? 0, 10); ?>>10 pairs</option>
                            </select>
                            <p class="description">
                                Limit the number of Q&A pairs displayed to avoid overwhelming readers.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Minimum Confidence for Frontend Display</th>
                        <td>
                            <select name="qa_frontend_min_confidence">
                                <option value="0.5" <?php selected($settings['qa_frontend_min_confidence'] ?? 0.5, 0.5); ?>>50% - Show more Q&A pairs</option>
                                <option value="0.6" <?php selected($settings['qa_frontend_min_confidence'] ?? 0.5, 0.6); ?>>60% - Balanced</option>
                                <option value="0.7" <?php selected($settings['qa_frontend_min_confidence'] ?? 0.5, 0.7); ?>>70% - Recommended</option>
                                <option value="0.8" <?php selected($settings['qa_frontend_min_confidence'] ?? 0.5, 0.8); ?>>80% - High quality only</option>
                                <option value="0.9" <?php selected($settings['qa_frontend_min_confidence'] ?? 0.5, 0.9); ?>>90% - Excellent quality only</option>
                            </select>
                            <p class="description">
                                Only display Q&A pairs above this confidence level on the frontend.
                            </p>
                        </td>
                    </tr>
                </table>
                <div class="postbox" style="margin: 15px 0;">
                    <h3 class="hndle" style="padding: 10px 15px; margin: 0; background: #f9f9f9;">💡 Manual Q&A Display Options</h3>
                    <div class="inside" style="padding: 15px;">
                        <p><strong>Shortcode:</strong> Use <code>[requestdesk_qa]</code> to display Q&A pairs anywhere in your content.</p>
                        <p><strong>Template Function:</strong> Use <code>&lt;?php requestdesk_display_qa_pairs(); ?&gt;</code> in your theme files.</p>
                        <p><strong>Conditional Check:</strong> Use <code>&lt;?php if (requestdesk_has_qa_pairs()) { ... } ?&gt;</code> to check if Q&A pairs exist.</p>

                        <h4 style="margin-top: 20px;">Shortcode Examples:</h4>
                        <ul>
                            <li><code>[requestdesk_qa]</code> - Display all Q&A pairs with default settings</li>
                            <li><code>[requestdesk_qa title="Common Questions" max_pairs="3"]</code> - Custom title, limit to 3 pairs</li>
                            <li><code>[requestdesk_qa show_confidence="true" min_confidence="0.8"]</code> - Show confidence scores, high quality only</li>
                            <li><code>[requestdesk_qa show_title="false"]</code> - Display without section title</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>🏷️ Schema Markup Generation</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Generate FAQ Schema</th>
                        <td>
                            <label>
                                <input type="checkbox" name="generate_faq_schema" value="1" <?php checked($settings['generate_faq_schema'], true); ?>>
                                Automatically generate FAQ structured data
                            </label>
                            <p class="description">
                                Creates schema.org FAQ markup for extracted Q&A pairs. Helps AI engines understand your content structure.
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <h2>📈 Citation Tracking</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Track Citation Statistics</th>
                        <td>
                            <label>
                                <input type="checkbox" name="track_citations" value="1" <?php checked($settings['track_citations'], true); ?>>
                                Extract and track citation-ready statistics
                            </label>
                            <p class="description">
                                Identifies statistics, percentages, and data points that AI engines can cite from your content.
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <h2>🕒 Content Freshness Monitoring</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Monitor Content Freshness</th>
                        <td>
                            <label>
                                <input type="checkbox" name="monitor_freshness" value="1" <?php checked($settings['monitor_freshness'], true); ?>>
                                Track and monitor content freshness
                            </label>
                            <p class="description">
                                AI engines prefer fresh, up-to-date content. This monitors and scores content freshness.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Freshness Alert Threshold</th>
                        <td>
                            <input type="number" name="freshness_alert_days" value="<?php echo esc_attr($settings['freshness_alert_days']); ?>" min="30" max="365" class="small-text"> days
                            <p class="description">
                                Get alerts when content hasn't been updated for this many days.
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit">
                <input type="submit" name="requestdesk_aeo_save_settings" class="button-primary" value="Save AEO Settings">
            </p>
        </form>

        <div class="card">
            <h2>🔧 System Information</h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Component</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>AEO Core</td>
                        <td><span style="color: #46b450;">✓ Active</span></td>
                        <td>Main AEO engine running</td>
                    </tr>
                    <tr>
                        <td>Content Analyzer</td>
                        <td><span style="color: #46b450;">✓ Active</span></td>
                        <td>Analyzing content for optimization opportunities</td>
                    </tr>
                    <tr>
                        <td>Schema Generator</td>
                        <td><span style="color: #46b450;">✓ Active</span></td>
                        <td>Generating structured data markup</td>
                    </tr>
                    <tr>
                        <td>Citation Tracker</td>
                        <td><span style="color: #46b450;">✓ Active</span></td>
                        <td><?php echo $citation_analytics['posts_with_stats']; ?> posts with statistics tracked</td>
                    </tr>
                    <tr>
                        <td>Freshness Monitor</td>
                        <td><span style="color: #46b450;">✓ Active</span></td>
                        <td><?php echo $freshness_analytics['total_content']; ?> pieces of content monitored</td>
                    </tr>
                    <tr>
                        <td>Database Tables</td>
                        <td>
                            <?php
                            global $wpdb;
                            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}requestdesk_aeo_data'");
                            if ($table_exists) {
                                echo '<span style="color: #46b450;">✓ Created</span>';
                            } else {
                                echo '<span style="color: #d63638;">✗ Missing</span>';
                            }
                            ?>
                        </td>
                        <td>AEO data storage tables</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>📚 Documentation & Help</h2>
            <p>Learn more about AEO optimization:</p>
            <ul>
                <li><strong>Answer Engine Optimization (AEO):</strong> Optimizing content for AI-powered search engines like ChatGPT, Claude, and Perplexity</li>
                <li><strong>AI Optimization (AIO):</strong> Using AI tools to enhance content creation and optimization</li>
                <li><strong>Generative Engine Optimization (GEO):</strong> Optimizing for AI engines that generate responses</li>
            </ul>
            <p>
                <a href="https://requestdesk.ai/docs/aeo-guide" target="_blank" class="button">📖 Read AEO Guide</a>
                <a href="https://requestdesk.ai/support" target="_blank" class="button">🆘 Get Support</a>
            </p>
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
        .aeo-stats-grid .stat-box h3 {
            font-size: 14px;
            margin: 0 0 10px 0;
        }
        .aeo-stats-grid .stat-box p {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            line-height: 1.2;
        }
        .aeo-stats-grid .stat-box small {
            color: #666;
            font-size: 12px;
        }
    </style>
    <?php
}

/**
 * AEO Analytics Page
 */
function requestdesk_aeo_analytics_page() {
    $citation_tracker = new RequestDesk_Citation_Tracker();
    $freshness_tracker = new RequestDesk_Freshness_Tracker();

    $citation_analytics = $citation_tracker->get_citation_analytics();
    $freshness_analytics = $freshness_tracker->get_freshness_analytics();
    $posts_needing_attention = $freshness_tracker->get_posts_needing_attention(20);
    ?>
    <style>
    /* Full-width layout for Dashboard */
    .wrap.requestdesk-dashboard {
        margin: 20px 20px 0 2px !important;
        max-width: none !important;
        width: calc(100vw - 180px) !important;
        box-sizing: border-box !important;
    }
    .wrap.requestdesk-dashboard > * {
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    .requestdesk-dashboard .dashboard-card {
        background: #fff;
        border: 1px solid #c3c4c7;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 20px;
        margin-bottom: 20px;
    }
    .requestdesk-dashboard .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 20px;
    }
    .requestdesk-dashboard .stat-card {
        background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
        border: 1px solid #e2e4e7;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
    }
    .requestdesk-dashboard .stat-card .stat-value {
        font-size: 36px;
        font-weight: 700;
        line-height: 1.2;
    }
    .requestdesk-dashboard .stat-card .stat-label {
        font-size: 13px;
        color: #646970;
        margin-top: 5px;
    }
    .requestdesk-dashboard .stat-card .stat-details {
        font-size: 12px;
        color: #8c8f94;
        margin-top: 10px;
    }
    .requestdesk-dashboard .freshness-bar {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 10px;
        margin: 20px 0;
    }
    .requestdesk-dashboard .freshness-item {
        text-align: center;
        padding: 15px 10px;
        border-radius: 8px;
    }
    .requestdesk-dashboard .freshness-item .count {
        font-size: 28px;
        font-weight: 700;
    }
    .requestdesk-dashboard .freshness-item .label {
        font-size: 13px;
        font-weight: 600;
        margin-top: 5px;
    }
    .requestdesk-dashboard .freshness-item .range {
        font-size: 11px;
        color: #666;
    }
    @media (max-width: 1200px) {
        .requestdesk-dashboard .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .requestdesk-dashboard .freshness-bar {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    @media (max-width: 782px) {
        .wrap.requestdesk-dashboard {
            width: calc(100vw - 50px) !important;
        }
        .requestdesk-dashboard .stats-grid {
            grid-template-columns: 1fr;
        }
        .requestdesk-dashboard .freshness-bar {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    </style>

    <div class="wrap requestdesk-dashboard">
        <h1>📊 AEO Analytics Dashboard</h1>
        <p style="color: #646970; margin-bottom: 20px;">Monitor your content's Answer Engine Optimization performance across all posts and pages.</p>

        <!-- Stats Overview Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" style="color: #0073aa;"><?php echo $citation_analytics['total_statistics']; ?></div>
                <div class="stat-label">Total Citations</div>
                <div class="stat-details">Citation-ready statistics</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #46b450;"><?php echo $citation_analytics['high_quality_stats']; ?></div>
                <div class="stat-label">High Quality</div>
                <div class="stat-details">Premium citations</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #9b59b6;"><?php echo $citation_analytics['posts_with_stats']; ?></div>
                <div class="stat-label">Posts with Stats</div>
                <div class="stat-details">Avg <?php echo $citation_analytics['avg_stats_per_post']; ?> per post</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: <?php echo $freshness_analytics['avg_freshness_score'] >= 60 ? '#46b450' : ($freshness_analytics['avg_freshness_score'] >= 40 ? '#f0ad4e' : '#d63638'); ?>;"><?php echo $freshness_analytics['avg_freshness_score']; ?>%</div>
                <div class="stat-label">Avg Freshness</div>
                <div class="stat-details"><?php echo $freshness_analytics['needs_attention']; ?> need attention</div>
            </div>
        </div>

        <!-- Content Freshness Breakdown -->
        <div class="dashboard-card">
            <h2 style="margin-top: 0;">🕒 Content Freshness Breakdown</h2>
            <div class="freshness-bar">
                <div class="freshness-item" style="background: #e8f5e9;">
                    <div class="count" style="color: #46b450;"><?php echo $freshness_analytics['excellent_count']; ?></div>
                    <div class="label" style="color: #46b450;">Excellent</div>
                    <div class="range">80-100%</div>
                </div>
                <div class="freshness-item" style="background: #e3f2fd;">
                    <div class="count" style="color: #0073aa;"><?php echo $freshness_analytics['good_count']; ?></div>
                    <div class="label" style="color: #0073aa;">Good</div>
                    <div class="range">60-79%</div>
                </div>
                <div class="freshness-item" style="background: #fff8e1;">
                    <div class="count" style="color: #f0ad4e;"><?php echo $freshness_analytics['fair_count']; ?></div>
                    <div class="label" style="color: #f0ad4e;">Fair</div>
                    <div class="range">40-59%</div>
                </div>
                <div class="freshness-item" style="background: #fff3e0;">
                    <div class="count" style="color: #f56e28;"><?php echo $freshness_analytics['poor_count']; ?></div>
                    <div class="label" style="color: #f56e28;">Poor</div>
                    <div class="range">20-39%</div>
                </div>
                <div class="freshness-item" style="background: #ffebee;">
                    <div class="count" style="color: #d63638;"><?php echo $freshness_analytics['critical_count']; ?></div>
                    <div class="label" style="color: #d63638;">Critical</div>
                    <div class="range">0-19%</div>
                </div>
            </div>
        </div>

        <!-- Statistic Types Distribution -->
        <div class="dashboard-card">
            <h2 style="margin-top: 0;">🎯 Statistic Types Distribution</h2>
            <?php if (!empty($citation_analytics['top_stat_types'])): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Statistic Type</th>
                        <th>Count</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($citation_analytics['top_stat_types'] as $type => $count): ?>
                    <tr>
                        <td><?php echo ucfirst($type); ?></td>
                        <td><?php echo $count; ?></td>
                        <td><?php echo round(($count / $citation_analytics['total_statistics']) * 100, 1); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>No statistics data available yet. <a href="<?php echo admin_url('admin.php?page=requestdesk-aeo-bulk-optimizer'); ?>">Run bulk analysis</a> to generate data.</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($posts_needing_attention)): ?>
        <!-- Content Needing Attention -->
        <div class="dashboard-card">
            <h2 style="margin-top: 0;">⚠️ Content Needing Attention</h2>
            <p style="color: #646970; margin-bottom: 15px;">These posts have low freshness scores and may need updating to maintain AEO performance.</p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Post Title</th>
                        <th>Freshness Score</th>
                        <th>Last Modified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts_needing_attention as $post): ?>
                    <?php
                    $freshness_score = get_post_meta($post->ID, '_requestdesk_freshness_score', true);
                    $freshness_status = get_post_meta($post->ID, '_requestdesk_freshness_status', true);
                    ?>
                    <tr>
                        <td>
                            <strong><a href="<?php echo get_edit_post_link($post->ID); ?>"><?php echo esc_html($post->post_title); ?></a></strong>
                            <div class="row-actions">
                                <span class="edit"><a href="<?php echo get_edit_post_link($post->ID); ?>">Edit</a> | </span>
                                <span class="view"><a href="<?php echo get_permalink($post->ID); ?>" target="_blank">View</a></span>
                            </div>
                        </td>
                        <td>
                            <span style="color: <?php echo $freshness_score < 20 ? '#d63638' : '#f56e28'; ?>; font-weight: bold;">
                                <?php echo $freshness_score ?: 'N/A'; ?>%
                            </span>
                            <br><small><?php echo ucfirst($freshness_status ?: 'unknown'); ?></small>
                        </td>
                        <td><?php echo get_the_modified_date('M j, Y', $post->ID); ?></td>
                        <td>
                            <a href="<?php echo get_edit_post_link($post->ID); ?>" class="button button-small">Update</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="dashboard-card">
            <h2 style="margin-top: 0;">⚡ Quick Actions</h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=requestdesk-aeo-bulk-optimizer'); ?>" class="button button-primary">Open Bulk Optimizer</a>
                <a href="<?php echo admin_url('admin.php?page=requestdesk-aeo-bulk'); ?>" class="button">Bulk Tools</a>
                <a href="<?php echo admin_url('admin.php?page=requestdesk-settings'); ?>" class="button">Settings</a>
            </p>
        </div>
    </div>
    <?php
}

/**
 * Bulk AEO Tools Page
 */
function requestdesk_aeo_bulk_page() {
    // Handle bulk operations
    if (isset($_POST['bulk_action']) && wp_verify_nonce($_POST['requestdesk_aeo_bulk_nonce'], 'requestdesk_aeo_bulk')) {
        $action = sanitize_text_field($_POST['bulk_action']);
        $post_ids = array_map('intval', $_POST['post_ids'] ?? array());

        if (!empty($post_ids)) {
            $aeo_core = new RequestDesk_AEO_Core();
            $results = array('success' => 0, 'failed' => 0, 'skipped' => 0);

            foreach ($post_ids as $post_id) {
                $result = $aeo_core->optimize_post($post_id, true);
                if (is_wp_error($result)) {
                    $results['failed']++;
                } else {
                    $results['success']++;
                }
            }

            echo '<div class="notice notice-success"><p>';
            echo sprintf('Bulk operation completed: %d successful, %d failed', $results['success'], $results['failed']);
            echo '</p></div>';
        }
    }

    // Handle rescan operations
    if (isset($_POST['action'])) {
        $action = sanitize_text_field($_POST['action']);

        // Full Site Rescan
        if ($action === 'full_rescan' && wp_verify_nonce($_POST['requestdesk_full_rescan_nonce'], 'requestdesk_full_rescan')) {
            $results = requestdesk_perform_full_rescan();
            echo '<div class="notice notice-success"><p>';
            echo sprintf('🌟 Full site rescan completed: %d posts processed, %d successful, %d failed',
                $results['total'], $results['success'], $results['failed']);
            echo '</p></div>';
        }

        // Citations Rescan
        elseif ($action === 'citations_rescan' && wp_verify_nonce($_POST['requestdesk_citations_rescan_nonce'], 'requestdesk_citations_rescan')) {
            $results = requestdesk_perform_citations_rescan();
            echo '<div class="notice notice-success"><p>';
            echo sprintf('📊 Citations rescan completed: %d posts processed', $results['processed']);
            echo '</p></div>';
        }

        // Freshness Rescan
        elseif ($action === 'freshness_rescan' && wp_verify_nonce($_POST['requestdesk_freshness_rescan_nonce'], 'requestdesk_freshness_rescan')) {
            $results = requestdesk_perform_freshness_rescan();
            echo '<div class="notice notice-success"><p>';
            echo sprintf('🕒 Freshness rescan completed: %d posts processed', $results['processed']);
            echo '</p></div>';
        }

        // Claude AI Reanalysis
        elseif ($action === 'claude_rescan' && wp_verify_nonce($_POST['requestdesk_claude_rescan_nonce'], 'requestdesk_claude_rescan')) {
            $results = requestdesk_perform_claude_rescan();
            echo '<div class="notice notice-success"><p>';
            echo sprintf('🤖 Claude AI reanalysis completed: %d posts processed, %d analyzed',
                $results['processed'], $results['analyzed']);
            echo '</p></div>';
        }

        // Clear Cache
        elseif ($action === 'clear_cache' && wp_verify_nonce($_POST['requestdesk_clear_cache_nonce'], 'requestdesk_clear_cache')) {
            $results = requestdesk_clear_aeo_cache();
            echo '<div class="notice notice-success"><p>';
            echo sprintf('🗑️ AEO cache cleared: %d entries removed', $results['cleared']);
            echo '</p></div>';
        }

        // Reset All Data
        elseif ($action === 'reset_all' && wp_verify_nonce($_POST['requestdesk_reset_all_nonce'], 'requestdesk_reset_all')) {
            $results = requestdesk_reset_all_aeo_data();
            echo '<div class="notice notice-success"><p>';
            echo sprintf('⚠️ All AEO data reset: %d records deleted', $results['deleted']);
            echo '</p></div>';
        }
    }

    // Get posts for bulk operations (increased limit for better visibility)
    $posts = get_posts(array(
        'post_type' => array('post', 'page'),
        'post_status' => 'publish',
        'posts_per_page' => 200, // Show more posts in admin interface
        'orderby' => 'modified',
        'order' => 'DESC'
    ));
    ?>

    <style>
    /* Full-width layout for Bulk Tools */
    .wrap.requestdesk-bulk-tools {
        margin: 20px 20px 0 2px !important;
        max-width: none !important;
        width: calc(100vw - 180px) !important;
        box-sizing: border-box !important;
    }
    .wrap.requestdesk-bulk-tools > * {
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    @media (max-width: 782px) {
        .wrap.requestdesk-bulk-tools {
            width: calc(100vw - 50px) !important;
        }
    }
    </style>

    <div class="wrap requestdesk-bulk-tools">
        <h1>Bulk AEO Tools</h1>

        <!-- Site Rescan Tools -->
        <div class="card">
            <h2>🔄 Site Rescan & Refresh</h2>
            <p>Use these tools after plugin updates, configuration changes, or to refresh all analysis data.</p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
                <!-- Full Site Rescan -->
                <div class="rescan-option" style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                    <h3>🌟 Complete Site Rescan</h3>
                    <p><strong>Reprocesses everything:</strong> Citations, freshness, AEO scores, Q&A pairs, schema markup</p>
                    <form method="post" action="" style="margin-top: 10px;">
                        <?php wp_nonce_field('requestdesk_full_rescan', 'requestdesk_full_rescan_nonce'); ?>
                        <input type="hidden" name="action" value="full_rescan">
                        <input type="submit" class="button button-primary" value="🔄 Full Site Rescan"
                               onclick="return confirm('This will reprocess ALL content on your site. This may take several minutes. Continue?');">
                    </form>
                    <small style="color: #666;">Recommended after major plugin updates or configuration changes.</small>
                </div>

                <!-- Citations Only -->
                <div class="rescan-option" style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                    <h3>📊 Refresh Citations</h3>
                    <p><strong>Re-extracts:</strong> Statistics and citation data from all content</p>
                    <form method="post" action="" style="margin-top: 10px;">
                        <?php wp_nonce_field('requestdesk_citations_rescan', 'requestdesk_citations_rescan_nonce'); ?>
                        <input type="hidden" name="action" value="citations_rescan">
                        <input type="submit" class="button" value="📊 Refresh Citations"
                               onclick="return confirm('This will re-extract citations from all posts. Continue?');">
                    </form>
                    <small style="color: #666;">Updates citation statistics and quality scores.</small>
                </div>

                <!-- Freshness Only -->
                <div class="rescan-option" style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                    <h3>🕒 Recalculate Freshness</h3>
                    <p><strong>Recalculates:</strong> Content freshness scores and recommendations</p>
                    <form method="post" action="" style="margin-top: 10px;">
                        <?php wp_nonce_field('requestdesk_freshness_rescan', 'requestdesk_freshness_rescan_nonce'); ?>
                        <input type="hidden" name="action" value="freshness_rescan">
                        <input type="submit" class="button" value="🕒 Recalculate Freshness"
                               onclick="return confirm('This will recalculate freshness scores for all content. Continue?');">
                    </form>
                    <small style="color: #666;">Updates content age and freshness metrics.</small>
                </div>

                <!-- Claude AI Reanalysis -->
                <div class="rescan-option" style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                    <h3>🤖 Claude AI Reanalysis</h3>
                    <p><strong>Re-runs:</strong> Claude AI analysis for enhanced AEO insights</p>
                    <form method="post" action="" style="margin-top: 10px;">
                        <?php wp_nonce_field('requestdesk_claude_rescan', 'requestdesk_claude_rescan_nonce'); ?>
                        <input type="hidden" name="action" value="claude_rescan">
                        <input type="submit" class="button" value="🤖 Claude AI Reanalysis"
                               onclick="return confirm('This will re-run Claude AI analysis on all content. This may consume API credits. Continue?');"
                               <?php echo empty(get_option('requestdesk_settings')['claude_api_key']) ? 'disabled title="Claude API key required"' : ''; ?>>
                    </form>
                    <small style="color: #666;">Requires Claude API key. May consume API credits.</small>
                </div>

                <!-- Clear Cache -->
                <div class="rescan-option" style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                    <h3>🗑️ Clear AEO Cache</h3>
                    <p><strong>Clears:</strong> All cached AEO data to force fresh analysis</p>
                    <form method="post" action="" style="margin-top: 10px;">
                        <?php wp_nonce_field('requestdesk_clear_cache', 'requestdesk_clear_cache_nonce'); ?>
                        <input type="hidden" name="action" value="clear_cache">
                        <input type="submit" class="button" value="🗑️ Clear AEO Cache"
                               onclick="return confirm('This will clear all AEO cache data. Next analysis will be slower but completely fresh. Continue?');">
                    </form>
                    <small style="color: #666;">Forces fresh analysis on next content access.</small>
                </div>

                <!-- Reset All Data -->
                <div class="rescan-option" style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; border-color: #dc3232;">
                    <h3 style="color: #dc3232;">⚠️ Reset All AEO Data</h3>
                    <p><strong>DANGER:</strong> Deletes all AEO data and starts fresh</p>
                    <form method="post" action="" style="margin-top: 10px;">
                        <?php wp_nonce_field('requestdesk_reset_all', 'requestdesk_reset_all_nonce'); ?>
                        <input type="hidden" name="action" value="reset_all">
                        <input type="submit" class="button button-delete" value="⚠️ Reset All Data" style="background: #dc3232; color: white;"
                               onclick="return confirm('DANGER: This will permanently delete ALL AEO data! This cannot be undone. Are you absolutely sure?');">
                    </form>
                    <small style="color: #dc3232;">⚠️ PERMANENT: Cannot be undone!</small>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>🚀 Bulk AEO Optimization</h2>
            <p>Select posts and pages to optimize for AEO. This will analyze content, extract Q&A pairs, generate schema markup, and update freshness scores.</p>

            <form method="post" action="">
                <?php wp_nonce_field('requestdesk_aeo_bulk', 'requestdesk_aeo_bulk_nonce'); ?>

                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select name="bulk_action">
                            <option value="optimize">Optimize for AEO</option>
                            <option value="analyze_only">Analyze Only</option>
                            <option value="regenerate_schema">Regenerate Schema</option>
                        </select>
                        <input type="submit" class="button action" value="Apply to Selected">
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="check-column"><input type="checkbox" id="select-all"></td>
                            <th>Title</th>
                            <th>Type</th>
                            <th>AEO Score</th>
                            <th>Freshness</th>
                            <th>Last Modified</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                        <?php
                        $aeo_score = get_post_meta($post->ID, '_requestdesk_aeo_score', true);
                        $freshness_score = get_post_meta($post->ID, '_requestdesk_freshness_score', true);
                        $freshness_status = get_post_meta($post->ID, '_requestdesk_freshness_status', true);
                        ?>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" name="post_ids[]" value="<?php echo $post->ID; ?>" class="post-checkbox">
                            </th>
                            <td>
                                <strong><a href="<?php echo get_edit_post_link($post->ID); ?>"><?php echo esc_html($post->post_title); ?></a></strong>
                                <div class="row-actions">
                                    <span class="edit"><a href="<?php echo get_edit_post_link($post->ID); ?>">Edit</a> | </span>
                                    <span class="view"><a href="<?php echo get_permalink($post->ID); ?>">View</a></span>
                                </div>
                            </td>
                            <td><?php echo ucfirst($post->post_type); ?></td>
                            <td>
                                <?php if ($aeo_score): ?>
                                    <span style="color: <?php echo $aeo_score >= 70 ? '#46b450' : ($aeo_score >= 40 ? '#ffb900' : '#d63638'); ?>; font-weight: bold;">
                                        <?php echo $aeo_score; ?>%
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;">Not analyzed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($freshness_score): ?>
                                    <span style="color: <?php echo $freshness_score >= 60 ? '#46b450' : ($freshness_score >= 40 ? '#ffb900' : '#d63638'); ?>; font-weight: bold;">
                                        <?php echo $freshness_score; ?>%
                                    </span>
                                    <br><small><?php echo ucfirst($freshness_status ?: 'unknown'); ?></small>
                                <?php else: ?>
                                    <span style="color: #999;">Not analyzed</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo get_the_modified_date('M j, Y g:i A', $post->ID); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="tablenav bottom">
                    <div class="alignleft actions">
                        <select name="bulk_action">
                            <option value="optimize">Optimize for AEO</option>
                            <option value="analyze_only">Analyze Only</option>
                            <option value="regenerate_schema">Regenerate Schema</option>
                        </select>
                        <input type="submit" class="button action" value="Apply to Selected">
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>⚡ Quick Actions</h2>
            <p>
                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=requestdesk_optimize_all'), 'requestdesk_optimize_all'); ?>" class="button button-primary">
                    Optimize All Published Content
                </a>
                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=requestdesk_analyze_all'), 'requestdesk_analyze_all'); ?>" class="button">
                    Analyze All Content
                </a>
                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=requestdesk_refresh_freshness'), 'requestdesk_refresh_freshness'); ?>" class="button">
                    Refresh Freshness Scores
                </a>
            </p>
            <p class="description">
                <strong>Note:</strong> Bulk operations on large sites may take several minutes to complete.
                Operations run in the background and you'll be notified when complete.
            </p>
        </div>
    </div>

    <script>
    document.getElementById('select-all').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.post-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
    </script>
    <?php
}

/**
 * Rescan Functions
 */

/**
 * Perform full site rescan
 */
function requestdesk_perform_full_rescan() {
    $results = array('total' => 0, 'success' => 0, 'failed' => 0);

    // Get all published posts and pages
    $posts = get_posts(array(
        'post_type' => array('post', 'page'),
        'post_status' => 'publish',
        'posts_per_page' => -1
    ));

    $aeo_core = new RequestDesk_AEO_Core();
    $citation_tracker = new RequestDesk_Citation_Tracker();
    $freshness_tracker = new RequestDesk_Freshness_Tracker();

    foreach ($posts as $post) {
        $results['total']++;

        try {
            // Full AEO optimization
            $aeo_result = $aeo_core->optimize_post($post->ID, true);

            // Citation extraction
            $citation_tracker->extract_citations($post);

            // Freshness calculation
            $freshness_tracker->update_freshness_data($post->ID, $post);

            if (!is_wp_error($aeo_result)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        } catch (Exception $e) {
            $results['failed']++;
        }
    }

    return $results;
}

/**
 * Perform citations rescan
 */
function requestdesk_perform_citations_rescan() {
    $results = array('processed' => 0);

    $posts = get_posts(array(
        'post_type' => array('post', 'page'),
        'post_status' => 'publish',
        'posts_per_page' => -1
    ));

    $citation_tracker = new RequestDesk_Citation_Tracker();

    foreach ($posts as $post) {
        $citation_tracker->extract_citations($post);
        $results['processed']++;
    }

    return $results;
}

/**
 * Perform freshness rescan
 */
function requestdesk_perform_freshness_rescan() {
    $results = array('processed' => 0);

    $posts = get_posts(array(
        'post_type' => array('post', 'page'),
        'post_status' => 'publish',
        'posts_per_page' => -1
    ));

    $freshness_tracker = new RequestDesk_Freshness_Tracker();

    foreach ($posts as $post) {
        $freshness_tracker->update_freshness_data($post->ID, $post);
        $results['processed']++;
    }

    return $results;
}

/**
 * Perform Claude AI reanalysis
 */
function requestdesk_perform_claude_rescan() {
    $results = array('processed' => 0, 'analyzed' => 0);

    $posts = get_posts(array(
        'post_type' => array('post', 'page'),
        'post_status' => 'publish',
        'posts_per_page' => -1
    ));

    $content_analyzer = new RequestDesk_Content_Analyzer();
    $claude_integration = new RequestDesk_Claude_Integration();

    if (!$claude_integration->is_available()) {
        return array('processed' => 0, 'analyzed' => 0, 'error' => 'Claude API key not configured');
    }

    foreach ($posts as $post) {
        $results['processed']++;

        try {
            // Force re-analysis with Claude
            $analysis = $content_analyzer->analyze_content($post);
            if (!empty($analysis['claude_aeo_score'])) {
                $results['analyzed']++;
            }
        } catch (Exception $e) {
            // Continue with other posts if one fails
        }
    }

    return $results;
}

/**
 * Clear AEO cache
 */
function requestdesk_clear_aeo_cache() {
    global $wpdb;

    $results = array('cleared' => 0);

    // Clear all AEO-related post meta
    $meta_keys = array(
        '_requestdesk_aeo_score',
        '_requestdesk_freshness_score',
        '_requestdesk_freshness_status',
        '_requestdesk_citation_stats',
        '_requestdesk_citation_updated',
        '_requestdesk_freshness_updated',
        '_requestdesk_aeo_analyzed'
    );

    foreach ($meta_keys as $meta_key) {
        $deleted = $wpdb->delete($wpdb->postmeta, array('meta_key' => $meta_key));
        $results['cleared'] += $deleted;
    }

    // Clear AEO database table
    $aeo_table = $wpdb->prefix . 'requestdesk_aeo_data';
    $wpdb->query("UPDATE $aeo_table SET ai_questions = '', faq_data = '', citation_stats = ''");

    return $results;
}

/**
 * Reset all AEO data
 */
function requestdesk_reset_all_aeo_data() {
    global $wpdb;

    $results = array('deleted' => 0);

    // Delete all AEO-related post meta
    $meta_keys = array(
        '_requestdesk_aeo_score',
        '_requestdesk_freshness_score',
        '_requestdesk_freshness_status',
        '_requestdesk_citation_stats',
        '_requestdesk_citation_updated',
        '_requestdesk_freshness_updated',
        '_requestdesk_aeo_analyzed'
    );

    foreach ($meta_keys as $meta_key) {
        $deleted = $wpdb->delete($wpdb->postmeta, array('meta_key' => $meta_key));
        $results['deleted'] += $deleted;
    }

    // Clear AEO database table
    $aeo_table = $wpdb->prefix . 'requestdesk_aeo_data';
    $deleted_rows = $wpdb->query("DELETE FROM $aeo_table");
    $results['deleted'] += $deleted_rows;

    return $results;
}