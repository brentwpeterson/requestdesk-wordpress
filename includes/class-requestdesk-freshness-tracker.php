<?php
/**
 * RequestDesk Freshness Tracker
 *
 * Monitors and tracks content freshness for AEO optimization
 */

class RequestDesk_Freshness_Tracker {

    private $claude_integration;

    public function __construct() {
        $this->claude_integration = new RequestDesk_Claude_Integration();
        // Hook into post updates
        add_action('save_post', array($this, 'update_freshness_data'), 10, 2);
        add_action('transition_post_status', array($this, 'handle_status_change'), 10, 3);

        // Admin hooks
        add_action('wp_ajax_requestdesk_check_freshness', array($this, 'ajax_check_freshness'));
        add_action('wp_ajax_requestdesk_mark_fresh', array($this, 'ajax_mark_fresh'));

        // Schedule freshness monitoring
        add_action('requestdesk_freshness_monitor', array($this, 'monitor_content_freshness'));

        if (!wp_next_scheduled('requestdesk_freshness_monitor')) {
            wp_schedule_event(time(), 'daily', 'requestdesk_freshness_monitor');
        }

        // Add freshness indicator to admin columns
        add_filter('manage_posts_columns', array($this, 'add_freshness_column'));
        add_filter('manage_pages_columns', array($this, 'add_freshness_column'));
        add_action('manage_posts_custom_column', array($this, 'show_freshness_column'), 10, 2);
        add_action('manage_pages_custom_column', array($this, 'show_freshness_column'), 10, 2);
    }

    /**
     * Calculate freshness score for a post
     */
    public function calculate_freshness_score($post) {
        $score_data = array(
            'overall_score' => 0,
            'age_score' => 0,
            'update_score' => 0,
            'content_score' => 0,
            'engagement_score' => 0,
            'factors' => array(),
            'recommendations' => array()
        );

        // Age-based scoring (40% weight)
        $age_score = $this->calculate_age_score($post);
        $score_data['age_score'] = $age_score;

        // Update frequency scoring (25% weight)
        $update_score = $this->calculate_update_score($post);
        $score_data['update_score'] = $update_score;

        // Content freshness indicators (25% weight)
        $content_score = $this->calculate_content_freshness_score($post);
        $score_data['content_score'] = $content_score;

        // Engagement freshness (10% weight)
        $engagement_score = $this->calculate_engagement_score($post);
        $score_data['engagement_score'] = $engagement_score;

        // Calculate weighted overall score
        $score_data['overall_score'] = round(
            ($age_score * 0.4) +
            ($update_score * 0.25) +
            ($content_score * 0.25) +
            ($engagement_score * 0.1)
        );

        // Generate recommendations
        $score_data['recommendations'] = $this->generate_freshness_recommendations($post, $score_data);

        return $score_data;
    }

    /**
     * Calculate age-based freshness score
     */
    private function calculate_age_score($post) {
        $published_date = strtotime($post->post_date);
        $modified_date = strtotime($post->post_modified);
        $current_time = current_time('timestamp');

        // Use the more recent of published or modified date
        $reference_date = max($published_date, $modified_date);
        $age_days = ($current_time - $reference_date) / DAY_IN_SECONDS;

        // Scoring based on age
        if ($age_days <= 30) return 100;        // Excellent: 0-30 days
        if ($age_days <= 90) return 85;         // Very good: 31-90 days
        if ($age_days <= 180) return 70;        // Good: 91-180 days
        if ($age_days <= 365) return 50;        // Fair: 181-365 days
        if ($age_days <= 730) return 30;        // Poor: 1-2 years
        return 10;                              // Very poor: 2+ years
    }

    /**
     * Calculate update frequency score
     */
    private function calculate_update_score($post) {
        $published_date = strtotime($post->post_date);
        $modified_date = strtotime($post->post_modified);
        $current_time = current_time('timestamp');

        // Check if content has been updated since publication
        $days_since_publish = ($current_time - $published_date) / DAY_IN_SECONDS;
        $days_since_update = ($current_time - $modified_date) / DAY_IN_SECONDS;

        $score = 50; // Base score

        // Bonus for recent updates
        if ($days_since_update <= 7) $score += 30;      // Updated this week
        elseif ($days_since_update <= 30) $score += 20; // Updated this month
        elseif ($days_since_update <= 90) $score += 10; // Updated this quarter

        // Bonus for multiple updates (check revisions)
        $revisions = wp_get_post_revisions($post->ID);
        $revision_count = count($revisions);

        if ($revision_count >= 10) $score += 20;
        elseif ($revision_count >= 5) $score += 15;
        elseif ($revision_count >= 2) $score += 10;

        // Penalty for old content that's never been updated
        if ($days_since_publish > 365 && $published_date === $modified_date) {
            $score -= 30;
        }

        return min(100, max(0, $score));
    }

    /**
     * Calculate content freshness score based on content analysis
     */
    private function calculate_content_freshness_score($post) {
        $content = $post->post_content;
        $title = $post->post_title;
        $score = 50; // Base score

        // Check for date references
        $current_year = date('Y');
        $last_year = $current_year - 1;

        // Bonus for current year mentions
        if (preg_match('/\b' . $current_year . '\b/', $content . ' ' . $title)) {
            $score += 25;
        } elseif (preg_match('/\b' . $last_year . '\b/', $content . ' ' . $title)) {
            $score += 15;
        }

        // Check for freshness indicators
        $fresh_phrases = array(
            'updated', 'latest', 'recent', 'current', 'new', 'now', 'today',
            'this year', 'this month', 'recently', 'currently', 'modern'
        );

        foreach ($fresh_phrases as $phrase) {
            if (stripos($content . ' ' . $title, $phrase) !== false) {
                $score += 5;
            }
        }

        // Check for stale indicators
        $stale_phrases = array(
            'last year', 'previous year', 'in the past', 'historically',
            'traditionally', 'used to', 'formerly', 'old'
        );

        foreach ($stale_phrases as $phrase) {
            if (stripos($content . ' ' . $title, $phrase) !== false) {
                $score -= 5;
            }
        }

        // Check for technology/trend references
        $tech_trends_2024_2025 = array(
            'AI', 'artificial intelligence', 'machine learning', 'ChatGPT',
            'cloud computing', 'remote work', 'sustainability', 'blockchain',
            'IoT', 'automation', 'digital transformation'
        );

        foreach ($tech_trends_2024_2025 as $trend) {
            if (stripos($content . ' ' . $title, $trend) !== false) {
                $score += 3;
            }
        }

        // Check for outdated references
        $outdated_refs = array(
            'Flash', 'Internet Explorer', 'Blackberry', 'Google+',
            'Windows XP', 'iPhone 6', 'Facebook Timeline'
        );

        foreach ($outdated_refs as $ref) {
            if (stripos($content . ' ' . $title, $ref) !== false) {
                $score -= 10;
            }
        }

        return min(100, max(0, $score));
    }

    /**
     * Calculate engagement-based freshness score
     */
    private function calculate_engagement_score($post) {
        $score = 50; // Base score

        // Recent comments indicate fresh, relevant content
        $recent_comments = get_comments(array(
            'post_id' => $post->ID,
            'date_query' => array(
                'after' => '30 days ago'
            ),
            'count' => true
        ));

        if ($recent_comments > 10) $score += 30;
        elseif ($recent_comments > 5) $score += 20;
        elseif ($recent_comments > 0) $score += 10;

        // Check for social shares (if available)
        $shares = get_post_meta($post->ID, '_social_shares', true);
        if ($shares) {
            if ($shares > 100) $score += 20;
            elseif ($shares > 50) $score += 15;
            elseif ($shares > 10) $score += 10;
        }

        return min(100, max(0, $score));
    }

    /**
     * Generate freshness recommendations
     */
    private function generate_freshness_recommendations($post, $score_data) {
        $recommendations = array();

        // Get Claude AI freshness assessment if available
        if ($this->claude_integration->is_available()) {
            $claude_freshness = $this->claude_integration->assess_content_freshness(
                $post->post_title,
                strip_tags($post->post_content),
                $post->post_date,
                $post->post_modified
            );

            if (!is_wp_error($claude_freshness)) {
                // Use Claude's detailed recommendations
                if (!empty($claude_freshness['update_suggestions'])) {
                    foreach ($claude_freshness['update_suggestions'] as $suggestion) {
                        $recommendations[] = array(
                            'type' => 'claude_ai',
                            'priority' => $claude_freshness['update_priority'] ?? 'medium',
                            'message' => $suggestion,
                            'action' => 'AI-powered content update recommendation',
                            'freshness_score' => $claude_freshness['freshness_score'] ?? 0
                        );
                    }
                }

                // Add Claude's specific outdated elements
                if (!empty($claude_freshness['outdated_elements'])) {
                    foreach ($claude_freshness['outdated_elements'] as $element) {
                        $recommendations[] = array(
                            'type' => 'outdated_content',
                            'priority' => 'high',
                            'message' => "Outdated content detected: " . $element,
                            'action' => 'Update or remove outdated information'
                        );
                    }
                }
            }
        }

        // Fallback to basic recommendations if Claude not available or as supplements
        // Age-based recommendations
        if ($score_data['age_score'] < 50) {
            $recommendations[] = array(
                'type' => 'age',
                'priority' => 'high',
                'message' => 'Content is getting old. Consider updating with fresh information.',
                'action' => 'Update content with current data and trends'
            );
        }

        // Update frequency recommendations
        if ($score_data['update_score'] < 40) {
            $recommendations[] = array(
                'type' => 'updates',
                'priority' => 'medium',
                'message' => 'Content hasn\'t been updated recently.',
                'action' => 'Review and refresh content with latest information'
            );
        }

        // Content freshness recommendations
        if ($score_data['content_score'] < 60) {
            $recommendations[] = array(
                'type' => 'content',
                'priority' => 'medium',
                'message' => 'Content contains outdated references.',
                'action' => 'Update dates, statistics, and technology references'
            );
        }

        // Engagement recommendations
        if ($score_data['engagement_score'] < 30) {
            $recommendations[] = array(
                'type' => 'engagement',
                'priority' => 'low',
                'message' => 'Low recent engagement on this content.',
                'action' => 'Consider promoting or improving content quality'
            );
        }

        // Specific actionable recommendations based on overall score
        if ($score_data['overall_score'] < 40) {
            $recommendations[] = array(
                'type' => 'urgent',
                'priority' => 'high',
                'message' => 'Content freshness is critically low.',
                'action' => 'Immediate update required - add current year data, recent examples, and updated statistics'
            );
        } elseif ($score_data['overall_score'] < 60) {
            $recommendations[] = array(
                'type' => 'maintenance',
                'priority' => 'medium',
                'message' => 'Content needs maintenance to stay fresh.',
                'action' => 'Schedule quarterly review and update cycle'
            );
        }

        return $recommendations;
    }

    /**
     * Update freshness data when post is saved
     */
    public function update_freshness_data($post_id, $post) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!in_array($post->post_type, requestdesk_content_post_types())) {
            return;
        }

        $freshness_data = $this->calculate_freshness_score($post);

        // Save freshness data
        update_post_meta($post_id, '_requestdesk_freshness_score', $freshness_data['overall_score']);
        update_post_meta($post_id, '_requestdesk_freshness_data', $freshness_data);
        update_post_meta($post_id, '_requestdesk_freshness_updated', current_time('timestamp'));

        // Set freshness status
        $status = $this->get_freshness_status($freshness_data['overall_score']);
        update_post_meta($post_id, '_requestdesk_freshness_status', $status);
    }

    /**
     * Handle post status changes
     */
    public function handle_status_change($new_status, $old_status, $post) {
        if ($new_status === 'publish' && $old_status !== 'publish') {
            // Content was just published, mark as fresh
            update_post_meta($post->ID, '_requestdesk_freshness_score', 100);
            update_post_meta($post->ID, '_requestdesk_freshness_status', 'excellent');
            update_post_meta($post->ID, '_requestdesk_freshness_updated', current_time('timestamp'));
        }
    }

    /**
     * Get freshness status label
     */
    private function get_freshness_status($score) {
        if ($score >= 80) return 'excellent';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'fair';
        if ($score >= 20) return 'poor';
        return 'critical';
    }

    /**
     * Monitor content freshness across the site
     */
    public function monitor_content_freshness() {
        $settings = get_option('requestdesk_aeo_settings', array());
        $alert_threshold = $settings['freshness_alert_days'] ?? 90;

        // Find content that needs freshness updates
        $stale_posts = get_posts(array(
            'post_type' => requestdesk_content_post_types(),
            'post_status' => 'publish',
            'posts_per_page' => -1, // Process all posts for complete analytics
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_requestdesk_freshness_updated',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_requestdesk_freshness_updated',
                    'value' => strtotime('-7 days'),
                    'compare' => '<',
                    'type' => 'NUMERIC'
                )
            )
        ));

        $alerts = array();

        foreach ($stale_posts as $post) {
            $freshness_data = $this->calculate_freshness_score($post);
            $this->update_freshness_data($post->ID, $post);

            // Generate alerts for critical content
            if ($freshness_data['overall_score'] < 40) {
                $alerts[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'score' => $freshness_data['overall_score'],
                    'url' => get_edit_post_link($post->ID),
                    'recommendations' => $freshness_data['recommendations']
                );
            }
        }

        // Store alerts for admin notification
        if (!empty($alerts)) {
            update_option('requestdesk_freshness_alerts', $alerts);
        }

        // Log monitoring activity
        error_log('RequestDesk Freshness Monitor: Checked ' . count($stale_posts) . ' posts, found ' . count($alerts) . ' alerts');
    }

    /**
     * Get freshness analytics for dashboard
     */
    public function get_freshness_analytics() {
        global $wpdb;

        $analytics = array(
            'total_content' => 0,
            'excellent_count' => 0,
            'good_count' => 0,
            'fair_count' => 0,
            'poor_count' => 0,
            'critical_count' => 0,
            'avg_freshness_score' => 0,
            'needs_attention' => 0
        );

        // Get freshness scores for all published content
        $results = $wpdb->get_results("
            SELECT pm.meta_value as score
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_requestdesk_freshness_score'
            AND p.post_status = 'publish'
            AND p.post_type IN ('post', 'page')
        ");

        $scores = array_map('intval', wp_list_pluck($results, 'score'));
        $analytics['total_content'] = count($scores);

        if (!empty($scores)) {
            $analytics['avg_freshness_score'] = round(array_sum($scores) / count($scores));

            foreach ($scores as $score) {
                if ($score >= 80) $analytics['excellent_count']++;
                elseif ($score >= 60) $analytics['good_count']++;
                elseif ($score >= 40) $analytics['fair_count']++;
                elseif ($score >= 20) $analytics['poor_count']++;
                else $analytics['critical_count']++;
            }

            $analytics['needs_attention'] = $analytics['poor_count'] + $analytics['critical_count'];
        }

        return $analytics;
    }

    /**
     * Add freshness column to post list
     */
    public function add_freshness_column($columns) {
        $columns['freshness'] = 'Freshness';
        return $columns;
    }

    /**
     * Show freshness in admin column
     */
    public function show_freshness_column($column, $post_id) {
        if ($column === 'freshness') {
            $score = get_post_meta($post_id, '_requestdesk_freshness_score', true);
            $status = get_post_meta($post_id, '_requestdesk_freshness_status', true);

            if ($score) {
                $color = $this->get_status_color($status);
                echo '<span style="color: ' . $color . '; font-weight: bold;">' . $score . '%</span>';
                echo '<br><small>' . ucfirst($status) . '</small>';
            } else {
                echo '<span style="color: #999;">Not analyzed</span>';
            }
        }
    }

    /**
     * Get color for freshness status
     */
    private function get_status_color($status) {
        $colors = array(
            'excellent' => '#46b450',
            'good' => '#00a32a',
            'fair' => '#ffb900',
            'poor' => '#f56e28',
            'critical' => '#d63638'
        );

        return $colors[$status] ?? '#999';
    }

    /**
     * AJAX handler for checking freshness
     */
    public function ajax_check_freshness() {
        check_ajax_referer('requestdesk_aeo_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_die('Unauthorized');
        }

        $post = get_post($post_id);

        if (!$post) {
            wp_send_json_error('Post not found');
        }

        $freshness_data = $this->calculate_freshness_score($post);
        $this->update_freshness_data($post_id, $post);

        wp_send_json_success($freshness_data);
    }

    /**
     * AJAX handler for marking content as fresh
     */
    public function ajax_mark_fresh() {
        check_ajax_referer('requestdesk_aeo_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_die('Unauthorized');
        }


        // Update the post modified date
        wp_update_post(array(
            'ID' => $post_id,
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1)
        ));

        // Recalculate freshness
        $post = get_post($post_id);
        $freshness_data = $this->calculate_freshness_score($post);
        $this->update_freshness_data($post_id, $post);

        wp_send_json_success(array(
            'message' => 'Content marked as fresh',
            'new_score' => $freshness_data['overall_score'],
            'status' => $this->get_freshness_status($freshness_data['overall_score'])
        ));
    }

    /**
     * Get posts that need freshness attention
     */
    public function get_posts_needing_attention($limit = 10) {
        return get_posts(array(
            'post_type' => requestdesk_content_post_types(),
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_key' => '_requestdesk_freshness_score',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_requestdesk_freshness_score',
                    'value' => 40,
                    'compare' => '<',
                    'type' => 'NUMERIC'
                )
            )
        ));
    }
}