<?php
/**
 * RequestDesk AEO Bulk Optimizer - Dedicated Page
 * Enhanced grid interface for bulk content optimization
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced Bulk AEO Optimizer Page
 */
function requestdesk_aeo_bulk_optimizer_page() {
    // Handle score breakdown analysis
    if (isset($_GET['breakdown_post_id']) && is_numeric($_GET['breakdown_post_id'])) {
        $breakdown_post_id = intval($_GET['breakdown_post_id']);
        ?>
        <style>
        /* Optimized full-width for debug page - no overflow */
        .wrap {
            margin: 20px 20px 0 2px !important;
            max-width: none !important;
            width: calc(100vw - 180px) !important; /* Account for admin sidebar */
            box-sizing: border-box !important;
        }

        #wpbody-content {
            padding: 0 10px !important;
        }

        /* Ensure all child elements fit within viewport */
        .wrap > * {
            max-width: 100% !important;
            box-sizing: border-box !important;
        }

        /* Grid containers should not overflow */
        div[style*="display: grid"] {
            width: 100% !important;
            box-sizing: border-box !important;
        }

        /* Prevent horizontal scroll */
        body.wp-admin {
            overflow-x: hidden !important;
        }
        </style>
        <div class="wrap"><h1>📊 AEO Score Breakdown</h1>
        <?php
        requestdesk_aeo_score_breakdown($breakdown_post_id);
        echo '<p><a href="?page=requestdesk-aeo-bulk-optimizer" class="button">← Back to Bulk Optimizer</a></p>';
        echo '</div>';
        return;
    }

    // Handle bulk operations
    if (isset($_POST['bulk_action']) && wp_verify_nonce($_POST['requestdesk_aeo_bulk_nonce'], 'requestdesk_aeo_bulk')) {
        $action = sanitize_text_field($_POST['bulk_action']);
        $post_ids = array_map('intval', $_POST['post_ids'] ?? array());

        if (!empty($post_ids)) {
            $aeo_core = new RequestDesk_AEO_Core();
            $results = array('success' => 0, 'failed' => 0, 'skipped' => 0);
            $detailed_results = array();

            foreach ($post_ids as $post_id) {
                $post_title = get_the_title($post_id);
                $result = $aeo_core->optimize_post($post_id, true);

                if (is_wp_error($result)) {
                    $results['failed']++;
                    $detailed_results[] = array(
                        'post_id' => $post_id,
                        'title' => $post_title,
                        'status' => 'failed',
                        'message' => $result->get_error_message()
                    );
                } else {
                    $results['success']++;
                    $detailed_results[] = array(
                        'post_id' => $post_id,
                        'title' => $post_title,
                        'status' => 'success',
                        'score' => $result['optimization_score'] ?? 'N/A'
                    );
                }
            }

            // Display enhanced results
            echo '<div class="notice notice-success">';
            echo '<h3>🚀 Bulk Operation Completed</h3>';
            echo '<p>';
            echo sprintf('<strong>%d successful</strong>, <strong>%d failed</strong> out of <strong>%d total</strong>',
                $results['success'], $results['failed'], count($post_ids));
            echo '</p>';

            // Detailed results table
            if (!empty($detailed_results)) {
                echo '<table class="widefat" style="margin-top: 15px;">';
                echo '<thead><tr><th>Post</th><th>Status</th><th>AEO Score</th><th>Details</th></tr></thead>';
                echo '<tbody>';
                foreach ($detailed_results as $detail) {
                    $status_color = $detail['status'] === 'success' ? '#46b450' : '#d63638';
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($detail['title']) . '</strong></td>';
                    echo '<td><span style="color: ' . $status_color . '; font-weight: bold;">' . ucfirst($detail['status']) . '</span></td>';
                    echo '<td>';
                    if ($detail['status'] === 'success' && isset($detail['score'])) {
                        echo '<span style="color: #46b450; font-weight: bold;">' . $detail['score'] . '%</span>';
                    } else {
                        echo 'N/A';
                    }
                    echo '</td>';
                    echo '<td>';
                    if ($detail['status'] === 'failed') {
                        echo '<small style="color: #d63638;">' . esc_html($detail['message']) . '</small>';
                    } else {
                        echo '<small style="color: #46b450;">Optimized successfully</small>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            echo '</div>';
        }
    }

    // Get posts for the grid
    $posts_per_page = 50; // Show more posts for bulk operations
    $args = array(
        'post_type' => requestdesk_content_post_types(),
        'post_status' => 'publish',
        'posts_per_page' => $posts_per_page,
        'orderby' => 'modified',
        'order' => 'DESC'
    );

    // Handle filtering
    $filter_type = $_GET['filter_type'] ?? 'all';
    $filter_score = $_GET['filter_score'] ?? 'all';

    if ($filter_type !== 'all') {
        $args['post_type'] = array($filter_type);
    }

    $posts = get_posts($args);

    // Filter by AEO score if needed
    if ($filter_score !== 'all') {
        $filtered_posts = array();
        foreach ($posts as $post) {
            $aeo_score = intval(get_post_meta($post->ID, '_requestdesk_aeo_score', true));

            switch ($filter_score) {
                case 'high':
                    if ($aeo_score >= 70) $filtered_posts[] = $post;
                    break;
                case 'medium':
                    if ($aeo_score >= 40 && $aeo_score < 70) $filtered_posts[] = $post;
                    break;
                case 'low':
                    if ($aeo_score > 0 && $aeo_score < 40) $filtered_posts[] = $post;
                    break;
                case 'unanalyzed':
                    if ($aeo_score == 0) $filtered_posts[] = $post;
                    break;
            }
        }
        $posts = $filtered_posts;
    }

    // Calculate statistics
    $stats = array(
        'total' => count($posts),
        'analyzed' => 0,
        'unanalyzed' => 0,
        'high_score' => 0,
        'medium_score' => 0,
        'low_score' => 0,
        'avg_score' => 0
    );

    $score_sum = 0;
    $scored_count = 0;

    foreach ($posts as $post) {
        $aeo_score = intval(get_post_meta($post->ID, '_requestdesk_aeo_score', true));

        if ($aeo_score > 0) {
            $stats['analyzed']++;
            $score_sum += $aeo_score;
            $scored_count++;

            if ($aeo_score >= 70) $stats['high_score']++;
            elseif ($aeo_score >= 40) $stats['medium_score']++;
            else $stats['low_score']++;
        } else {
            $stats['unanalyzed']++;
        }
    }

    if ($scored_count > 0) {
        $stats['avg_score'] = round($score_sum / $scored_count);
    }
    ?>

    <div class="wrap">
        <h1>🚀 Bulk AEO Optimizer</h1>
        <p class="description">Efficiently optimize multiple posts and pages for Answer Engine Optimization (AEO) with advanced filtering and batch processing.</p>

        <!-- Enhanced Statistics Dashboard -->
        <div class="card" style="margin-bottom: 20px;">
            <h2>📊 Content Overview</h2>
            <div class="stats-grid">
                <div style="text-align: center; padding: 15px; background: #f0f8ff; border-radius: 8px; border-left: 4px solid #0073aa;">
                    <div style="font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo $stats['total']; ?></div>
                    <div style="color: #666;">Total Posts</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f0fff4; border-radius: 8px; border-left: 4px solid #46b450;">
                    <div style="font-size: 24px; font-weight: bold; color: #46b450;"><?php echo $stats['analyzed']; ?></div>
                    <div style="color: #666;">Analyzed</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #fffbf0; border-radius: 8px; border-left: 4px solid #ffb900;">
                    <div style="font-size: 24px; font-weight: bold; color: #ffb900;"><?php echo $stats['unanalyzed']; ?></div>
                    <div style="color: #666;">Unanalyzed</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #8c8f94;">
                    <div style="font-size: 24px; font-weight: bold; color: #8c8f94;"><?php echo $stats['avg_score']; ?>%</div>
                    <div style="color: #666;">Avg Score</div>
                </div>
            </div>

            <!-- Score Distribution -->
            <div style="margin-top: 20px;">
                <h3>Score Distribution</h3>
                <div class="score-distribution-grid">
                    <div style="text-align: center; padding: 10px; background: #f0fff4; border-radius: 6px;">
                        <div style="font-weight: bold; color: #46b450;"><?php echo $stats['high_score']; ?></div>
                        <div style="font-size: 12px; color: #666;">High (70%+)</div>
                    </div>
                    <div style="text-align: center; padding: 10px; background: #fffbf0; border-radius: 6px;">
                        <div style="font-weight: bold; color: #ffb900;"><?php echo $stats['medium_score']; ?></div>
                        <div style="font-size: 12px; color: #666;">Medium (40-69%)</div>
                    </div>
                    <div style="text-align: center; padding: 10px; background: #fef7f0; border-radius: 6px;">
                        <div style="font-weight: bold; color: #d63638;"><?php echo $stats['low_score']; ?></div>
                        <div style="font-size: 12px; color: #666;">Low (1-39%)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Filters -->
        <div class="card" style="margin-bottom: 20px;">
            <h2>🔍 Content Filters</h2>
            <form method="get" action="">
                <input type="hidden" name="page" value="requestdesk-aeo-bulk-optimizer">
                <div class="filter-grid">
                    <div>
                        <label style="font-weight: bold; display: block; margin-bottom: 5px;">Content Type</label>
                        <select name="filter_type" style="width: 100%;">
                            <option value="all" <?php selected($filter_type, 'all'); ?>>All Content</option>
                            <option value="post" <?php selected($filter_type, 'post'); ?>>Posts Only</option>
                            <option value="page" <?php selected($filter_type, 'page'); ?>>Pages Only</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight: bold; display: block; margin-bottom: 5px;">AEO Score</label>
                        <select name="filter_score" style="width: 100%;">
                            <option value="all" <?php selected($filter_score, 'all'); ?>>All Scores</option>
                            <option value="high" <?php selected($filter_score, 'high'); ?>>High (70%+)</option>
                            <option value="medium" <?php selected($filter_score, 'medium'); ?>>Medium (40-69%)</option>
                            <option value="low" <?php selected($filter_score, 'low'); ?>>Low (1-39%)</option>
                            <option value="unanalyzed" <?php selected($filter_score, 'unanalyzed'); ?>>Unanalyzed</option>
                        </select>
                    </div>
                    <div>
                        <input type="submit" class="button" value="Apply Filters">
                        <a href="?page=requestdesk-aeo-bulk-optimizer" class="button" style="margin-left: 10px;">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Enhanced Bulk Operations Grid -->
        <div class="card">
            <h2>📋 Content Grid (<?php echo count($posts); ?> items)</h2>

            <form method="post" action="">
                <?php wp_nonce_field('requestdesk_aeo_bulk', 'requestdesk_aeo_bulk_nonce'); ?>

                <!-- Enhanced Bulk Actions -->
                <div class="tablenav top" style="padding: 10px 0; border-bottom: 1px solid #ddd; margin-bottom: 15px;">
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <div>
                            <label style="font-weight: bold; margin-right: 8px;">Bulk Action:</label>
                            <select name="bulk_action" style="min-width: 180px;">
                                <option value="optimize">🚀 Full Optimization</option>
                                <option value="analyze_only">🔍 Analyze Only</option>
                                <option value="regenerate_schema">🏷️ Regenerate Schema</option>
                                <option value="update_freshness">⏰ Update Freshness</option>
                            </select>
                        </div>
                        <div>
                            <input type="submit" class="button button-primary" value="Apply to Selected" style="font-weight: bold;">
                        </div>
                        <div style="margin-left: auto;">
                            <label>
                                <input type="checkbox" id="select-all" style="margin-right: 5px;">
                                <strong>Select All</strong>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Data Grid -->
                <div class="table-container">
                <table class="wp-list-table widefat fixed striped" style="border: 1px solid #ddd;">
                    <thead>
                        <tr>
                            <td class="check-column" style="background: #f9f9f9;"><input type="checkbox" id="select-all-header"></td>
                            <th style="background: #f9f9f9; font-weight: bold;">Content</th>
                            <th style="background: #f9f9f9; font-weight: bold;">Type</th>
                            <th style="background: #f9f9f9; font-weight: bold;">AEO Score</th>
                            <th style="background: #f9f9f9; font-weight: bold;">Freshness</th>
                            <th style="background: #f9f9f9; font-weight: bold;">Status</th>
                            <th style="background: #f9f9f9; font-weight: bold;">Last Modified</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($posts)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #666;">
                                <strong>No content found matching your filters.</strong><br>
                                <small>Try adjusting your filter criteria or create some content first.</small>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                        <?php
                        $aeo_score = intval(get_post_meta($post->ID, '_requestdesk_aeo_score', true));
                        $freshness_score = intval(get_post_meta($post->ID, '_requestdesk_freshness_score', true));
                        $freshness_status = get_post_meta($post->ID, '_requestdesk_freshness_status', true);

                        // Determine row styling based on scores
                        $row_style = '';
                        if ($aeo_score == 0) {
                            $row_style = 'background-color: #fff8e1;'; // Light yellow for unanalyzed
                        } elseif ($aeo_score >= 70) {
                            $row_style = 'background-color: #f1f8e9;'; // Light green for high scores
                        }
                        ?>
                        <tr style="<?php echo $row_style; ?>">
                            <th class="check-column">
                                <input type="checkbox" name="post_ids[]" value="<?php echo $post->ID; ?>" class="post-checkbox">
                            </th>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div>
                                        <strong><a href="<?php echo get_edit_post_link($post->ID); ?>" style="text-decoration: none;"><?php echo esc_html($post->post_title ?: '(No Title)'); ?></a></strong>
                                        <div class="row-actions" style="margin-top: 5px;">
                                            <span class="edit"><a href="<?php echo get_edit_post_link($post->ID); ?>">✏️ Edit</a> | </span>
                                            <span class="view"><a href="<?php echo get_permalink($post->ID); ?>" target="_blank">👁️ View</a></span>
                                            <?php if ($aeo_score > 0): ?>
                                            | <span class="breakdown"><a href="?page=requestdesk-aeo-bulk-optimizer&breakdown_post_id=<?php echo $post->ID; ?>" style="color: #0073aa;">📊 Score Breakdown</a></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="background: #e1f5fe; color: #01579b; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold;">
                                    <?php echo strtoupper($post->post_type); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($aeo_score > 0): ?>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="width: 40px; height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                                            <div style="width: <?php echo $aeo_score; ?>%; height: 100%; background: <?php echo $aeo_score >= 70 ? '#46b450' : ($aeo_score >= 40 ? '#ffb900' : '#d63638'); ?>;"></div>
                                        </div>
                                        <span style="color: <?php echo $aeo_score >= 70 ? '#46b450' : ($aeo_score >= 40 ? '#ffb900' : '#d63638'); ?>; font-weight: bold; font-size: 14px;">
                                            <?php echo $aeo_score; ?>%
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #999; font-style: italic;">Not analyzed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($freshness_score > 0): ?>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="width: 30px; height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                                            <div style="width: <?php echo $freshness_score; ?>%; height: 100%; background: <?php echo $freshness_score >= 60 ? '#46b450' : ($freshness_score >= 40 ? '#ffb900' : '#d63638'); ?>;"></div>
                                        </div>
                                        <span style="color: <?php echo $freshness_score >= 60 ? '#46b450' : ($freshness_score >= 40 ? '#ffb900' : '#d63638'); ?>; font-weight: bold; font-size: 12px;">
                                            <?php echo $freshness_score; ?>%
                                        </span>
                                    </div>
                                    <?php if ($freshness_status): ?>
                                    <small style="color: #666; text-transform: capitalize;"><?php echo esc_html($freshness_status); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #999; font-style: italic;">Not analyzed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status_text = 'Ready';
                                $status_color = '#0073aa';

                                if ($aeo_score > 0) {
                                    if ($aeo_score >= 70) {
                                        $status_text = 'Optimized';
                                        $status_color = '#46b450';
                                    } elseif ($aeo_score >= 40) {
                                        $status_text = 'Good';
                                        $status_color = '#ffb900';
                                    } else {
                                        $status_text = 'Needs Work';
                                        $status_color = '#d63638';
                                    }
                                }
                                ?>
                                <span style="color: <?php echo $status_color; ?>; font-weight: bold; font-size: 12px;">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: #666; font-size: 12px;">
                                    <?php echo get_the_modified_date('M j, Y', $post->ID); ?>
                                    <br>
                                    <small style="color: #999;"><?php echo get_the_modified_date('g:i A', $post->ID); ?></small>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>

                <!-- Bottom Actions -->
                <?php if (!empty($posts)): ?>
                <div class="tablenav bottom" style="padding: 15px 0; border-top: 1px solid #ddd; margin-top: 15px;">
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <div>
                            <select name="bulk_action" style="min-width: 180px;">
                                <option value="optimize">🚀 Full Optimization</option>
                                <option value="analyze_only">🔍 Analyze Only</option>
                                <option value="regenerate_schema">🏷️ Regenerate Schema</option>
                                <option value="update_freshness">⏰ Update Freshness</option>
                            </select>
                        </div>
                        <div>
                            <input type="submit" class="button button-primary" value="Apply to Selected" style="font-weight: bold;">
                        </div>
                        <div style="margin-left: auto; color: #666;">
                            <small>Select items above and choose an action to optimize multiple posts at once.</small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Enhanced JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Enhanced select all functionality
        const selectAllBoxes = document.querySelectorAll('#select-all, #select-all-header');
        const postCheckboxes = document.querySelectorAll('.post-checkbox');

        selectAllBoxes.forEach(selectAll => {
            selectAll.addEventListener('change', function() {
                postCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                // Sync all select-all checkboxes
                selectAllBoxes.forEach(box => {
                    box.checked = this.checked;
                });
                updateBulkActionButtons();
            });
        });

        // Update select-all when individual checkboxes change
        postCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allChecked = Array.from(postCheckboxes).every(cb => cb.checked);
                const anyChecked = Array.from(postCheckboxes).some(cb => cb.checked);

                selectAllBoxes.forEach(selectAll => {
                    selectAll.checked = allChecked;
                    selectAll.indeterminate = anyChecked && !allChecked;
                });
                updateBulkActionButtons();
            });
        });

        function updateBulkActionButtons() {
            const checkedCount = Array.from(postCheckboxes).filter(cb => cb.checked).length;
            const submitButtons = document.querySelectorAll('input[type="submit"][value*="Apply"]');

            submitButtons.forEach(button => {
                if (checkedCount > 0) {
                    button.value = `Apply to Selected (${checkedCount})`;
                    button.disabled = false;
                    button.style.opacity = '1';
                } else {
                    button.value = 'Apply to Selected';
                    button.disabled = true;
                    button.style.opacity = '0.5';
                }
            });
        }

        // Initial state
        updateBulkActionButtons();

        // Confirmation for bulk actions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const checkedCount = Array.from(postCheckboxes).filter(cb => cb.checked).length;
                const action = form.querySelector('select[name="bulk_action"]')?.value;

                if (checkedCount > 0 && action) {
                    const actionNames = {
                        'optimize': 'Full Optimization',
                        'analyze_only': 'Analysis',
                        'regenerate_schema': 'Schema Regeneration',
                        'update_freshness': 'Freshness Update'
                    };

                    const actionName = actionNames[action] || action;

                    if (!confirm(`Are you sure you want to perform "${actionName}" on ${checkedCount} selected items?\n\nThis action may take a few moments to complete.`)) {
                        e.preventDefault();
                    }
                }
            });
        });
    });
    </script>

    <style>
    /* Full-width layout */
    .wrap {
        margin: 20px 20px 0 2px !important;
        max-width: none !important;
        width: calc(100% - 22px) !important;
    }

    /* Ensure content uses full available width */
    .wrap > * {
        max-width: none !important;
    }

    .wrap h1 {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
    }

    /* Full-width cards */
    .card {
        background: white;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        width: 100%;
        box-sizing: border-box;
    }

    .card h2 {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }

    /* Statistics grid responsive full-width */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 15px;
        width: 100%;
    }

    .score-distribution-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 10px;
        width: 100%;
    }

    /* Filter grid full-width */
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        align-items: end;
        width: 100%;
    }

    /* Table full-width */
    .wp-list-table {
        width: 100% !important;
        max-width: none !important;
        border-collapse: collapse;
    }

    .wp-list-table th,
    .wp-list-table td {
        vertical-align: middle;
        padding: 12px 8px;
    }

    .wp-list-table .check-column {
        width: 2.2em;
        max-width: 2.2em;
    }

    /* Content column should take more space */
    .wp-list-table th:nth-child(2),
    .wp-list-table td:nth-child(2) {
        width: 30%;
        min-width: 200px;
    }

    /* Other columns auto-size */
    .wp-list-table th:nth-child(3),
    .wp-list-table td:nth-child(3) {
        width: 8%;
        min-width: 80px;
    }

    .wp-list-table th:nth-child(4),
    .wp-list-table td:nth-child(4) {
        width: 12%;
        min-width: 100px;
    }

    .wp-list-table th:nth-child(5),
    .wp-list-table td:nth-child(5) {
        width: 12%;
        min-width: 100px;
    }

    .wp-list-table th:nth-child(6),
    .wp-list-table td:nth-child(6) {
        width: 10%;
        min-width: 80px;
    }

    .wp-list-table th:nth-child(7),
    .wp-list-table td:nth-child(7) {
        width: 15%;
        min-width: 120px;
    }

    .row-actions {
        visibility: hidden;
    }

    .wp-list-table tr:hover .row-actions {
        visibility: visible;
    }

    /* Responsive adjustments */
    @media (max-width: 1200px) {
        .wrap {
            margin: 20px 10px 0 2px !important;
            width: calc(100% - 12px) !important;
        }

        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        }

        .filter-grid {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .wrap {
            margin: 10px 5px 0 2px !important;
            width: calc(100% - 7px) !important;
        }

        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 10px;
        }

        .filter-grid {
            grid-template-columns: 1fr;
        }

        /* Make table horizontally scrollable on mobile */
        .table-container {
            overflow-x: auto;
            width: 100%;
        }

        .wp-list-table {
            min-width: 800px;
        }
    }

    /* Bulk actions bar full-width */
    .tablenav {
        width: 100%;
        box-sizing: border-box;
    }

    .tablenav > div {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    </style>

    <?php
}

/**
 * Add the enhanced bulk optimizer to admin menu
 */
function requestdesk_aeo_add_bulk_optimizer_menu() {
    add_submenu_page(
        'requestdesk-aeo-analytics',
        'Bulk AEO Optimizer',
        'Bulk Optimizer',
        'manage_options',
        'requestdesk-aeo-bulk-optimizer',
        'requestdesk_aeo_bulk_optimizer_page'
    );
}
add_action('admin_menu', 'requestdesk_aeo_add_bulk_optimizer_menu', 12);

/**
 * AEO Score Breakdown Analysis
 */
function requestdesk_aeo_score_breakdown($post_id) {
    $post = get_post($post_id);
    if (!$post) {
        echo '<div class="notice notice-error"><p>Post not found.</p></div>';
        return;
    }

    echo '<div class="card" style="margin-bottom: 20px;">';
    echo '<h2>📊 ' . esc_html($post->post_title) . ' <small style="color: #666;">(ID: ' . $post->ID . ')</small></h2>';

    // Get current AEO score
    $aeo_score = get_post_meta($post->ID, '_requestdesk_aeo_score', true);
    echo '<p><strong>Current AEO Score:</strong> <span style="font-size: 24px; color: ' . ($aeo_score >= 70 ? '#46b450' : ($aeo_score >= 40 ? '#ffb900' : '#d63638')) . '; font-weight: bold;">' . ($aeo_score ?: 'Not set') . '%</span></p>';

    // Get AEO data from database
    global $wpdb;
    $table_name = $wpdb->prefix . 'requestdesk_aeo_data';
    $aeo_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE post_id = %d",
        $post->ID
    ), ARRAY_A);

    if (!$aeo_data) {
        echo '<div class="notice notice-warning"><p>❌ No AEO data found in database for this post.</p></div>';
        echo '</div>';
        return;
    }

    // Decode JSON data
    $ai_questions = json_decode($aeo_data['ai_questions'] ?: '[]', true);
    $faq_data = json_decode($aeo_data['faq_data'] ?: '[]', true);
    $citation_stats = json_decode($aeo_data['citation_stats'] ?: '[]', true);

    // Summary section first
    echo '<div style="background: #f0f8ff; border: 2px solid #0073aa; padding: 20px; border-radius: 8px; margin: 20px 0;">';
    echo '<h3>🎯 SCORE BREAKDOWN</h3>';
    echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">';

    // We'll calculate scores first and show summary
    $temp_total_score = 0;

    // Quick calculation for summary (we'll do detailed below)
    $temp_qa_score = !empty($ai_questions) ? min(25, count($ai_questions) * 5) : 0;
    $temp_faq_score = !empty($faq_data) ? 20 : 0;
    $temp_citation_score = !empty($citation_stats) ? min(20, count($citation_stats) * 4) : 0;
    $temp_freshness_points = get_post_meta($post->ID, '_requestdesk_freshness_score', true) ? (get_post_meta($post->ID, '_requestdesk_freshness_score', true) / 100) * 15 : 0;

    $post_content = $post->post_content;
    $word_count = str_word_count(strip_tags($post_content));
    $temp_content_score = 0;
    if (preg_match_all('/^#+\s*[^?\n]*\?/m', $post_content) > 0) $temp_content_score += 8;
    if (preg_match_all('/^#+\s+/m', $post_content) >= 3) $temp_content_score += 7;
    if ($word_count > 500) $temp_content_score += 5;

    $temp_total_score = $temp_qa_score + $temp_faq_score + $temp_citation_score + $temp_freshness_points + $temp_content_score;

    echo '<div style="text-align: center; padding: 15px; background: white; border-radius: 6px; border: 1px solid #ddd;">';
    echo '<div style="font-size: 18px; font-weight: bold; color: #0073aa;">Q&A Pairs</div>';
    echo '<div style="font-size: 18px; font-weight: bold; color: ' . ($temp_qa_score >= 20 ? '#d63638' : '#46b450') . ';">' . $temp_qa_score . '/25</div>';
    echo '</div>';

    echo '<div style="text-align: center; padding: 15px; background: white; border-radius: 6px; border: 1px solid #ddd;">';
    echo '<div style="font-size: 18px; font-weight: bold; color: #0073aa;">FAQ Schema</div>';
    echo '<div style="font-size: 18px; font-weight: bold; color: ' . ($temp_faq_score > 0 ? '#46b450' : '#999') . ';">' . $temp_faq_score . '/20</div>';
    echo '</div>';

    echo '<div style="text-align: center; padding: 15px; background: white; border-radius: 6px; border: 1px solid #ddd;">';
    echo '<div style="font-size: 18px; font-weight: bold; color: #0073aa;">Citations</div>';
    echo '<div style="font-size: 18px; font-weight: bold; color: ' . ($temp_citation_score >= 16 ? '#d63638' : '#46b450') . ';">' . $temp_citation_score . '/20</div>';
    echo '</div>';

    echo '<div style="text-align: center; padding: 15px; background: white; border-radius: 6px; border: 1px solid #ddd;">';
    echo '<div style="font-size: 18px; font-weight: bold; color: #0073aa;">Freshness</div>';
    echo '<div style="font-size: 18px; font-weight: bold; color: ' . ($temp_freshness_points >= 12 ? '#d63638' : '#46b450') . ';">' . round($temp_freshness_points, 1) . '/15</div>';
    echo '</div>';

    echo '<div style="text-align: center; padding: 15px; background: white; border-radius: 6px; border: 1px solid #ddd;">';
    echo '<div style="font-size: 18px; font-weight: bold; color: #0073aa;">Content</div>';
    echo '<div style="font-size: 18px; font-weight: bold; color: ' . ($temp_content_score >= 15 ? '#d63638' : '#46b450') . ';">' . $temp_content_score . '/20</div>';
    echo '</div>';

    echo '<div style="text-align: center; padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 6px; border: 2px solid #0073aa;">';
    echo '<div style="font-size: 18px; font-weight: bold;">TOTAL SCORE</div>';
    echo '<div style="font-size: 24px; font-weight: bold;">' . round($temp_total_score) . '%</div>';
    echo '<div style="font-size: 12px;">Calculated: ' . round($temp_total_score) . '% | Stored: ' . $aeo_score . '%</div>';
    echo '</div>';

    echo '</div>'; // End summary grid
    echo '</div>'; // End summary section

    // Now detailed breakdown in full width
    echo '<h3>🔍 DETAILED SCORING BREAKDOWN</h3>';
    echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-top: 20px;">';

    $total_score = 0;

    // 1. Q&A Pairs (25 points max)
    echo '<div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;">';
    echo '<h4>1️⃣ Q&A PAIRS (Max: 25 points)</h4>';
    $qa_score = 0;
    if (!empty($ai_questions)) {
        $qa_count = count($ai_questions);
        $qa_score = min(25, $qa_count * 5);
        echo '<p>✅ Found <strong>' . $qa_count . '</strong> Q&A pairs</p>';
        echo '<p>📊 Score: <strong>' . $qa_score . '</strong> points (' . $qa_count . ' × 5 points)</p>';

        if ($qa_count > 0) {
            echo '<details><summary>Show Q&A pairs</summary><ul>';
            foreach (array_slice($ai_questions, 0, 5) as $i => $qa) {
                echo '<li><strong>Q' . ($i+1) . ':</strong> ' . esc_html(substr($qa['question'] ?? 'No question', 0, 80)) . '...</li>';
            }
            if ($qa_count > 5) echo '<li><em>... and ' . ($qa_count - 5) . ' more</em></li>';
            echo '</ul></details>';
        }
    } else {
        echo '<p>❌ No Q&A pairs found</p>';
        echo '<p>📊 Score: <strong>0</strong> points</p>';
    }

    // Add educational accordion
    echo '<details style="margin-top: 15px;"><summary style="cursor: pointer; color: #0073aa; font-weight: bold;">💡 How to Improve This Score</summary>';
    echo '<div style="padding: 15px; background: #f8f9fa; border-radius: 5px; margin-top: 10px;">';
    echo '<h5 style="margin-top: 0; color: #0073aa;">What are Q&A Pairs?</h5>';
    echo '<p>Q&A pairs are question-answer combinations that make your content more AI-friendly and improve user experience.</p>';
    echo '<h5 style="color: #0073aa;">How Scoring Works:</h5>';
    echo '<ul>';
    echo '<li><strong>5 points per Q&A pair</strong> (maximum 25 points for 5+ pairs)</li>';
    echo '<li>Questions can be explicit ("How does this work?") or implicit ("Getting Started")</li>';
    echo '<li>Answers should be clear and concise (20-500 characters work best)</li>';
    echo '</ul>';
    echo '<h5 style="color: #0073aa;">Improvement Tips:</h5>';
    echo '<ul>';
    echo '<li>🔹 <strong>Add FAQ sections</strong> with common customer questions</li>';
    echo '<li>🔹 <strong>Use question headings</strong> like "What is..." or "How to..."</li>';
    echo '<li>🔹 <strong>Create "How to" guides</strong> with step-by-step instructions</li>';
    echo '<li>🔹 <strong>Answer customer pain points</strong> directly in your content</li>';
    echo '<li>🔹 <strong>Break complex topics</strong> into question-based sections</li>';
    echo '</ul>';
    if ($qa_score < 25) {
        $needed = ceil((25 - $qa_score) / 5);
        echo '<div style="background: #e8f4fd; padding: 10px; border-radius: 5px; border-left: 4px solid #0073aa;">';
        echo '<strong>Quick Win:</strong> Add ' . $needed . ' more question-answer pairs to gain ' . (($needed * 5)) . ' points and reach the maximum score!';
        echo '</div>';
    }
    echo '</div></details>';
    echo '</div>';
    $total_score += $qa_score;

    // 2. FAQ Schema (20 points)
    echo '<div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;">';
    echo '<h4>2️⃣ FAQ SCHEMA (Max: 20 points)</h4>';
    $faq_score = 0;
    if (!empty($faq_data)) {
        $faq_score = 20;
        echo '<p>✅ FAQ schema exists</p>';
        echo '<p>📊 Score: <strong>20</strong> points</p>';
        echo '<p>📝 FAQ items: ' . count($faq_data) . '</p>';
    } else {
        echo '<p>❌ No FAQ schema found</p>';
        echo '<p>📊 Score: <strong>0</strong> points</p>';
    }

    // Add educational accordion for FAQ Schema
    echo '<details style="margin-top: 15px;"><summary style="cursor: pointer; color: #0073aa; font-weight: bold;">💡 How to Improve This Score</summary>';
    echo '<div style="padding: 15px; background: #f8f9fa; border-radius: 5px; margin-top: 10px;">';
    echo '<h5 style="margin-top: 0; color: #0073aa;">What is FAQ Schema?</h5>';
    echo '<p>FAQ Schema is structured data markup that helps search engines understand your frequently asked questions, potentially showing them as rich snippets in search results.</p>';
    echo '<h5 style="color: #0073aa;">How Scoring Works:</h5>';
    echo '<ul>';
    echo '<li><strong>All or nothing:</strong> 20 points if FAQ schema is present, 0 if not</li>';
    echo '<li>Schema must be properly formatted using schema.org standards</li>';
    echo '<li>Questions and answers must be clearly defined</li>';
    echo '</ul>';
    echo '<h5 style="color: #0073aa;">Improvement Tips:</h5>';
    echo '<ul>';
    echo '<li>🔹 <strong>Create an FAQ section</strong> on your page with common questions</li>';
    echo '<li>🔹 <strong>Use proper schema markup</strong> (many SEO plugins can help with this)</li>';
    echo '<li>🔹 <strong>Include 3-10 relevant questions</strong> that customers actually ask</li>';
    echo '<li>🔹 <strong>Keep answers concise</strong> but informative (1-3 sentences)</li>';
    echo '<li>🔹 <strong>Update regularly</strong> based on customer inquiries</li>';
    echo '</ul>';
    if ($faq_score == 0) {
        echo '<div style="background: #fff3cd; padding: 10px; border-radius: 5px; border-left: 4px solid #ffc107;">';
        echo '<strong>Quick Win:</strong> Add an FAQ section with schema markup to instantly gain 20 points! This is one of the easiest ways to boost your AEO score.';
        echo '</div>';
    }
    echo '</div></details>';
    echo '</div>';
    $total_score += $faq_score;

    // 3. Citation Statistics (20 points max)
    echo '<div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;">';
    echo '<h4>3️⃣ CITATION STATISTICS (Max: 20 points)</h4>';
    $citation_score = 0;
    if (!empty($citation_stats)) {
        $stat_count = count($citation_stats);
        $citation_score = min(20, $stat_count * 4);
        echo '<p>✅ Found <strong>' . $stat_count . '</strong> citation statistics</p>';
        echo '<p>📊 Score: <strong>' . $citation_score . '</strong> points (' . $stat_count . ' × 4 points)</p>';

        if ($stat_count > 0) {
            echo '<details><summary>Show statistics</summary><ul>';
            foreach (array_slice($citation_stats, 0, 3) as $i => $stat) {
                echo '<li><strong>Stat' . ($i+1) . ':</strong> ' . esc_html(substr($stat['text'] ?? 'No text', 0, 60)) . '...</li>';
            }
            if ($stat_count > 3) echo '<li><em>... and ' . ($stat_count - 3) . ' more</em></li>';
            echo '</ul></details>';
        }
    } else {
        echo '<p>❌ No citation statistics found</p>';
        echo '<p>📊 Score: <strong>0</strong> points</p>';
    }

    // Add educational accordion for Citation Statistics
    echo '<details style="margin-top: 15px;"><summary style="cursor: pointer; color: #0073aa; font-weight: bold;">💡 How to Improve This Score</summary>';
    echo '<div style="padding: 15px; background: #f8f9fa; border-radius: 5px; margin-top: 10px;">';
    echo '<h5 style="margin-top: 0; color: #0073aa;">What are Citation Statistics?</h5>';
    echo '<p>Citation statistics are data points, numbers, and quantifiable facts that support your content with credible information and make it more authoritative.</p>';
    echo '<h5 style="color: #0073aa;">How Scoring Works:</h5>';
    echo '<ul>';
    echo '<li><strong>4 points per statistic</strong> (maximum 20 points for 5+ statistics)</li>';
    echo '<li>Includes percentages (85%), numbers with units (2.5 million users), and ratios (3 out of 4)</li>';
    echo '<li>Statistics should be relevant and meaningful to your content</li>';
    echo '</ul>';
    echo '<h5 style="color: #0073aa;">Examples of Good Statistics:</h5>';
    echo '<ul>';
    echo '<li>📊 <strong>Percentages:</strong> "85% of customers see results within 30 days"</li>';
    echo '<li>📊 <strong>Growth numbers:</strong> "2.5 million active users worldwide"</li>';
    echo '<li>📊 <strong>Time-based:</strong> "Projects completed in 5-7 business days"</li>';
    echo '<li>📊 <strong>Ratios:</strong> "9 out of 10 clients recommend our service"</li>';
    echo '<li>📊 <strong>Industry data:</strong> "Market grew 23% year-over-year"</li>';
    echo '</ul>';
    echo '<h5 style="color: #0073aa;">Improvement Tips:</h5>';
    echo '<ul>';
    echo '<li>🔹 <strong>Add customer success metrics</strong> and testimonial data</li>';
    echo '<li>🔹 <strong>Include industry benchmarks</strong> and market research</li>';
    echo '<li>🔹 <strong>Reference company achievements</strong> with specific numbers</li>';
    echo '<li>🔹 <strong>Use time-based statistics</strong> (delivery times, response rates)</li>';
    echo '<li>🔹 <strong>Cite credible sources</strong> for external statistics</li>';
    echo '</ul>';
    if ($citation_score < 20) {
        $needed = ceil((20 - $citation_score) / 4);
        echo '<div style="background: #e8f4fd; padding: 10px; border-radius: 5px; border-left: 4px solid #0073aa;">';
        echo '<strong>Quick Win:</strong> Add ' . $needed . ' more statistical data points to gain ' . (($needed * 4)) . ' points. Focus on customer success rates, timelines, or industry data.';
        echo '</div>';
    }
    echo '</div></details>';
    echo '</div>';
    $total_score += $citation_score;

    // 4. Content Freshness (15 points max)
    echo '<div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;">';
    echo '<h4>4️⃣ CONTENT FRESHNESS (Max: 15 points)</h4>';
    $freshness_score_meta = get_post_meta($post->ID, '_requestdesk_freshness_score', true);
    $freshness_points = 0;
    if ($freshness_score_meta) {
        $freshness_points = ($freshness_score_meta / 100) * 15;
        echo '<p>✅ Freshness score: <strong>' . $freshness_score_meta . '%</strong></p>';
        echo '<p>📊 Score: <strong>' . round($freshness_points, 2) . '</strong> points (' . $freshness_score_meta . '% × 0.15)</p>';
    } else {
        echo '<p>❌ No freshness score found</p>';
        echo '<p>📊 Score: <strong>0</strong> points</p>';
    }

    // Add educational accordion for Content Freshness
    echo '<details style="margin-top: 15px;"><summary style="cursor: pointer; color: #0073aa; font-weight: bold;">💡 How to Improve This Score</summary>';
    echo '<div style="padding: 15px; background: #f8f9fa; border-radius: 5px; margin-top: 10px;">';
    echo '<h5 style="margin-top: 0; color: #0073aa;">What is Content Freshness?</h5>';
    echo '<p>Content freshness measures how current and up-to-date your content appears, based on dates, recent events, and time-sensitive information.</p>';
    echo '<h5 style="color: #0073aa;">How Scoring Works:</h5>';
    echo '<ul>';
    echo '<li><strong>0-15 points</strong> based on freshness percentage (15 points = 100% fresh)</li>';
    echo '<li>Analyzes dates, recent events, and update indicators in your content</li>';
    echo '<li>Considers current year references and "last updated" mentions</li>';
    echo '</ul>';
    echo '<h5 style="color: #0073aa;">What Makes Content Fresh:</h5>';
    echo '<ul>';
    echo '<li>📅 <strong>Recent dates:</strong> 2025, Q1 2025, January 2025</li>';
    echo '<li>📅 <strong>Update indicators:</strong> "Updated January 2025", "Last revised"</li>';
    echo '<li>📅 <strong>Current events:</strong> Recent industry news or trends</li>';
    echo '<li>📅 <strong>Recent statistics:</strong> Latest market data or research</li>';
    echo '<li>📅 <strong>Timely references:</strong> "This year", "currently", "recent studies"</li>';
    echo '</ul>';
    echo '<h5 style="color: #0073aa;">Improvement Tips:</h5>';
    echo '<ul>';
    echo '<li>🔹 <strong>Add current year</strong> references throughout your content</li>';
    echo '<li>🔹 <strong>Include "last updated"</strong> timestamps</li>';
    echo '<li>🔹 <strong>Reference recent events</strong> or industry developments</li>';
    echo '<li>🔹 <strong>Update statistics</strong> with latest available data</li>';
    echo '<li>🔹 <strong>Mention current trends</strong> relevant to your topic</li>';
    echo '<li>🔹 <strong>Review quarterly</strong> and add fresh examples or case studies</li>';
    echo '</ul>';
    if ($freshness_points < 10) {
        echo '<div style="background: #fff3cd; padding: 10px; border-radius: 5px; border-left: 4px solid #ffc107;">';
        echo '<strong>Quick Win:</strong> Add current dates, "updated [month] 2025" text, or recent industry examples to boost freshness. Even small updates can significantly improve this score!';
        echo '</div>';
    }
    echo '</div></details>';
    echo '</div>';
    $total_score += $freshness_points;

    // 5. Content Analysis (20 points max)
    echo '<div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;">';
    echo '<h4>5️⃣ CONTENT ANALYSIS (Max: 20 points)</h4>';
    $content_analysis_score = 0;

    $post_content = $post->post_content;
    $word_count = str_word_count(strip_tags($post_content));

    echo '<p>📝 Word count: <strong>' . $word_count . '</strong></p>';

    // Check for question headings
    $question_headings = preg_match_all('/^#+\s*[^?\n]*\?/m', $post_content);
    if ($question_headings > 0) {
        $content_analysis_score += 8;
        echo '<p>✅ Question headings: <strong>' . $question_headings . '</strong> (+8 points)</p>';
    } else {
        echo '<p>❌ No question headings found</p>';
    }

    // Check for clear structure
    $headings = preg_match_all('/^#+\s+/m', $post_content);
    if ($headings >= 3) {
        $content_analysis_score += 7;
        echo '<p>✅ Clear structure (≥3 headings): <strong>' . $headings . '</strong> (+7 points)</p>';
    } else {
        echo '<p>❌ Unclear structure (<3 headings): <strong>' . $headings . '</strong></p>';
    }

    // Word count bonus
    if ($word_count > 500) {
        $content_analysis_score += 5;
        echo '<p>✅ Word count bonus (>500 words): <strong>' . $word_count . '</strong> (+5 points)</p>';
    } else {
        echo '<p>❌ No word count bonus (≤500 words): <strong>' . $word_count . '</strong></p>';
    }

    echo '<p>📊 Content Analysis Score: <strong>' . $content_analysis_score . '</strong> points</p>';

    // Add educational accordion for Content Analysis
    echo '<details style="margin-top: 15px;"><summary style="cursor: pointer; color: #0073aa; font-weight: bold;">💡 How to Improve This Score</summary>';
    echo '<div style="padding: 15px; background: #f8f9fa; border-radius: 5px; margin-top: 10px;">';
    echo '<h5 style="margin-top: 0; color: #0073aa;">What is Content Analysis?</h5>';
    echo '<p>Content analysis evaluates your content structure, readability, and organization to ensure it\'s well-formatted and user-friendly.</p>';
    echo '<h5 style="color: #0073aa;">How Scoring Works:</h5>';
    echo '<ul>';
    echo '<li><strong>Question headings:</strong> +8 points for having headings that end with "?"</li>';
    echo '<li><strong>Clear structure:</strong> +7 points for having 3+ headings</li>';
    echo '<li><strong>Word count bonus:</strong> +5 points for having 500+ words</li>';
    echo '<li><strong>Maximum score:</strong> 20 points total</li>';
    echo '</ul>';
    echo '<h5 style="color: #0073aa;">Structure Best Practices:</h5>';
    echo '<ul>';
    echo '<li>📝 <strong>Use descriptive headings</strong> that organize your content logically</li>';
    echo '<li>📝 <strong>Include question-style headings</strong> like "How does this work?" or "What are the benefits?"</li>';
    echo '<li>📝 <strong>Break up long paragraphs</strong> with subheadings every 200-300 words</li>';
    echo '<li>📝 <strong>Use bullet points and numbered lists</strong> to improve readability</li>';
    echo '<li>📝 <strong>Write comprehensive content</strong> (500+ words shows depth)</li>';
    echo '</ul>';
    echo '<h5 style="color: #0073aa;">Quick Improvement Tips:</h5>';
    echo '<ul>';
    if ($question_headings == 0) {
        echo '<li>🎯 <strong>Add question headings</strong> to gain 8 points instantly</li>';
    }
    if ($headings < 3) {
        echo '<li>🎯 <strong>Add more headings</strong> to reach 3+ and gain 7 points</li>';
    }
    if ($word_count <= 500) {
        echo '<li>🎯 <strong>Expand content</strong> by ' . (500 - $word_count) . ' words to get the 5-point bonus</li>';
    }
    echo '<li>🔹 <strong>Use H2, H3 tags</strong> to create a clear hierarchy</li>';
    echo '<li>🔹 <strong>Add FAQ sections</strong> with question-style headings</li>';
    echo '<li>🔹 <strong>Include "How to" sections</strong> with step-by-step instructions</li>';
    echo '<li>🔹 <strong>Break content into logical sections</strong> with descriptive headings</li>';
    echo '</ul>';
    if ($content_analysis_score < 15) {
        echo '<div style="background: #fff3cd; padding: 10px; border-radius: 5px; border-left: 4px solid #ffc107;">';
        echo '<strong>Quick Win:</strong> This is often the easiest category to improve! Add question headings and break your content into more sections with clear headings.';
        echo '</div>';
    }
    echo '</div></details>';
    echo '</div>';
    $total_score += $content_analysis_score;

    echo '</div>'; // End detailed breakdown grid

    // Full-width warnings section
    echo '<div style="margin-top: 30px;">';
    echo '<h3>🤔 POTENTIAL ISSUES & WARNINGS</h3>';
    echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">';

    $warnings = array();
    if ($qa_score >= 20) $warnings[] = array('title' => 'High Q&A Score', 'message' => 'Unusually high Q&A score - verify questions are legitimate', 'severity' => 'high');
    if ($faq_score == 20) $warnings[] = array('title' => 'FAQ Schema Present', 'message' => 'FAQ schema detected - check if it\'s meaningful', 'severity' => 'medium');
    if ($citation_score >= 16) $warnings[] = array('title' => 'High Citation Score', 'message' => 'High citation score - verify statistics are valuable', 'severity' => 'high');
    if ($freshness_points >= 12) $warnings[] = array('title' => 'High Freshness Score', 'message' => 'High freshness score - check calculation accuracy', 'severity' => 'medium');
    if ($content_analysis_score >= 15) $warnings[] = array('title' => 'High Content Score', 'message' => 'High content analysis score - verify structure detection', 'severity' => 'medium');

    if (abs($total_score - $aeo_score) > 1) {
        $warnings[] = array('title' => 'Score Mismatch', 'message' => 'Calculated score differs from stored score!', 'severity' => 'critical');
    }

    if (!empty($warnings)) {
        foreach ($warnings as $warning) {
            $border_color = '#d63638';
            $bg_color = '#fef7f0';
            $icon = '🚨';

            if ($warning['severity'] === 'critical') {
                $border_color = '#d63638';
                $bg_color = '#fef2f2';
                $icon = '🔥';
            } elseif ($warning['severity'] === 'medium') {
                $border_color = '#ffb900';
                $bg_color = '#fffbf0';
                $icon = '⚠️';
            }

            echo '<div style="border: 2px solid ' . $border_color . '; padding: 15px; border-radius: 8px; background: ' . $bg_color . ';">';
            echo '<h4 style="margin: 0 0 10px 0; color: ' . $border_color . ';">' . $icon . ' ' . $warning['title'] . '</h4>';
            echo '<p style="margin: 0; color: #333;">' . $warning['message'] . '</p>';
            echo '</div>';
        }
    } else {
        echo '<div style="border: 2px solid #46b450; padding: 20px; border-radius: 8px; background: #f0fff4; text-align: center;">';
        echo '<h4 style="margin: 0 0 10px 0; color: #46b450;">✅ No Issues Detected</h4>';
        echo '<p style="margin: 0; color: #333;">The scoring appears to be working correctly with no obvious red flags.</p>';
        echo '</div>';
    }

    echo '</div>'; // End warnings grid
    echo '</div>'; // End warnings section

    // Add improvement recommendations
    $total_calculated = $temp_qa_score + $temp_faq_score + $temp_citation_score + $temp_freshness_points + $temp_content_score;
    if ($total_calculated < 90) {
        echo '<div style="background: #e8f5e8; border: 2px solid #46b450; padding: 20px; border-radius: 8px; margin: 20px 0;">';
        echo '<h3 style="color: #46b450;">💡 Quick Improvement Tips</h3>';
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">';

        if ($temp_qa_score < 25) {
            $needed_qa = ceil((25 - $temp_qa_score) / 5);
            echo '<div style="background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #0073aa;">';
            echo '<h4 style="margin: 0 0 10px 0; color: #0073aa;">📝 Add More Q&A Content</h4>';
            echo '<p style="margin: 0;">Add ' . $needed_qa . ' more question-answer pairs to gain ' . ($needed_qa * 5) . ' points.</p>';
            echo '</div>';
        }

        if ($temp_faq_score == 0) {
            echo '<div style="background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #d63638;">';
            echo '<h4 style="margin: 0 0 10px 0; color: #d63638;">❓ Add FAQ Schema</h4>';
            echo '<p style="margin: 0;">Create an FAQ section with proper schema markup to gain 20 points.</p>';
            echo '</div>';
        }

        if ($temp_citation_score < 20) {
            $needed_stats = ceil((20 - $temp_citation_score) / 4);
            echo '<div style="background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #ffb900;">';
            echo '<h4 style="margin: 0 0 10px 0; color: #ffb900;">📊 Add Statistics</h4>';
            echo '<p style="margin: 0;">Include ' . $needed_stats . ' more statistics or data points to gain ' . ($needed_stats * 4) . ' points.</p>';
            echo '</div>';
        }

        if ($temp_freshness_points < 10) {
            echo '<div style="background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #e74c3c;">';
            echo '<h4 style="margin: 0 0 10px 0; color: #e74c3c;">🔄 Update Content</h4>';
            echo '<p style="margin: 0;">Add recent dates, current statistics, or "updated" information to improve freshness.</p>';
            echo '</div>';
        }

        echo '</div>';
        echo '<p style="text-align: center; margin: 15px 0 0 0;"><a href="' . get_edit_post_link($post->ID) . '" class="button button-primary" style="margin-right: 10px;">✏️ Edit This Post</a><a href="?page=requestdesk-aeo-bulk-optimizer" class="button">← Back to Optimizer</a></p>';
        echo '</div>';
    }

    echo '</div>'; // End card
}