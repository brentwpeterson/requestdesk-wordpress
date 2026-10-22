<?php
/**
 * RequestDesk Citation Tracker
 *
 * Tracks and manages citation-ready statistics and data points for AI engines
 */

class RequestDesk_Citation_Tracker {

    public function __construct() {
        // Hook into post save to track citations
        add_action('save_post', array($this, 'update_citation_stats'), 20, 2);

        // Add admin hooks for citation management
        add_action('wp_ajax_requestdesk_get_citation_stats', array($this, 'ajax_get_citation_stats'));
        add_action('wp_ajax_requestdesk_update_citation_stats', array($this, 'ajax_update_citation_stats'));

        // Schedule citation monitoring
        add_action('requestdesk_citation_monitor', array($this, 'monitor_citations'));

        if (!wp_next_scheduled('requestdesk_citation_monitor')) {
            wp_schedule_event(time(), 'daily', 'requestdesk_citation_monitor');
        }
    }

    /**
     * Extract statistics from content
     */
    public function extract_statistics($content) {
        $stats = array();
        $content = strip_tags($content);

        // Pattern 1: Percentages
        preg_match_all('/(\d+(?:\.\d+)?)\s*%(?:\s+(?:of|increase|decrease|growth|decline|more|less|higher|lower))?([^.]*)/i', $content, $percentage_matches, PREG_SET_ORDER);

        foreach ($percentage_matches as $match) {
            $context = $this->extract_context($content, $match[0]);
            $stats[] = array(
                'type' => 'percentage',
                'value' => $match[1] . '%',
                'raw_value' => floatval($match[1]),
                'context' => trim($context),
                'full_text' => trim($match[0] . ' ' . ($match[2] ?? '')),
                'citation_quality' => $this->assess_citation_quality($match[0], $context),
                'source_reliability' => 'internal'
            );
        }

        // Pattern 2: Large numbers with units
        preg_match_all('/(\d{1,3}(?:,\d{3})*(?:\.\d+)?)\s*(million|billion|trillion|thousand|k|m|b|users|customers|people|companies|businesses|websites|dollars?|euros?|pounds?|years?|months?|days?|hours?|minutes?|seconds?)/i', $content, $number_matches, PREG_SET_ORDER);

        foreach ($number_matches as $match) {
            $context = $this->extract_context($content, $match[0]);
            $normalized_value = $this->normalize_number($match[1], $match[2]);

            $stats[] = array(
                'type' => 'numeric',
                'value' => $match[1] . ' ' . $match[2],
                'raw_value' => $normalized_value,
                'unit' => strtolower($match[2]),
                'context' => trim($context),
                'full_text' => trim($match[0]),
                'citation_quality' => $this->assess_citation_quality($match[0], $context),
                'source_reliability' => 'internal'
            );
        }

        // Pattern 3: Ratios and fractions
        preg_match_all('/(\d+)\s+(?:out of|in|of every)\s+(\d+)([^.]*)/i', $content, $ratio_matches, PREG_SET_ORDER);

        foreach ($ratio_matches as $match) {
            $context = $this->extract_context($content, $match[0]);
            $percentage = round(($match[1] / $match[2]) * 100, 1);

            $stats[] = array(
                'type' => 'ratio',
                'value' => $match[1] . ' out of ' . $match[2],
                'percentage_equivalent' => $percentage . '%',
                'raw_value' => $percentage,
                'context' => trim($context),
                'full_text' => trim($match[0] . ' ' . ($match[3] ?? '')),
                'citation_quality' => $this->assess_citation_quality($match[0], $context),
                'source_reliability' => 'internal'
            );
        }

        // Pattern 4: Growth/change indicators
        preg_match_all('/(increased?|decreased?|grew|declined|rose|fell|jumped|dropped)\s+(?:by\s+)?(\d+(?:\.\d+)?)\s*%?([^.]*)/i', $content, $change_matches, PREG_SET_ORDER);

        foreach ($change_matches as $match) {
            $context = $this->extract_context($content, $match[0]);
            $direction = $this->categorize_change_direction($match[1]);

            $stats[] = array(
                'type' => 'change',
                'value' => $match[1] . ' ' . $match[2] . ($match[3] ?? ''),
                'change_direction' => $direction,
                'change_amount' => floatval($match[2]),
                'context' => trim($context),
                'full_text' => trim($match[0]),
                'citation_quality' => $this->assess_citation_quality($match[0], $context),
                'source_reliability' => 'internal'
            );
        }

        // Pattern 5: Date-based statistics
        preg_match_all('/(?:in|by|since|during)\s+(20\d{2}|19\d{2})([^.]*?)(\d+(?:\.\d+)?(?:%|\s+(?:million|billion|thousand))?)([^.]*)/i', $content, $date_stats, PREG_SET_ORDER);

        foreach ($date_stats as $match) {
            $context = $this->extract_context($content, $match[0]);
            $year = intval($match[1]);
            $current_year = date('Y');
            $age = $current_year - $year;

            $stats[] = array(
                'type' => 'temporal',
                'value' => $match[3],
                'year' => $year,
                'data_age' => $age,
                'context' => trim($context),
                'full_text' => trim($match[0]),
                'citation_quality' => $this->assess_temporal_citation_quality($age, $match[0]),
                'source_reliability' => 'internal'
            );
        }

        // Pattern 6: Ranking and positioning
        preg_match_all('/(?:#|number|ranked?|position)\s*(\d+)(?:\s+(?:in|among|out of|of))?\s*([^.]*)/i', $content, $ranking_matches, PREG_SET_ORDER);

        foreach ($ranking_matches as $match) {
            $context = $this->extract_context($content, $match[0]);

            $stats[] = array(
                'type' => 'ranking',
                'value' => '#' . $match[1],
                'rank' => intval($match[1]),
                'context' => trim($context . ' ' . ($match[2] ?? '')),
                'full_text' => trim($match[0]),
                'citation_quality' => $this->assess_citation_quality($match[0], $context),
                'source_reliability' => 'internal'
            );
        }

        // Remove duplicates and sort by citation quality
        $stats = $this->deduplicate_stats($stats);
        usort($stats, function($a, $b) {
            return $b['citation_quality'] <=> $a['citation_quality'];
        });

        // Add unique IDs and timestamps
        foreach ($stats as &$stat) {
            $stat['id'] = md5($stat['full_text']);
            $stat['extracted_at'] = current_time('mysql');
            $stat['last_verified'] = null;
            $stat['citation_count'] = 0;
        }

        return array_slice($stats, 0, 20); // Limit to top 20 statistics
    }

    /**
     * Extract context around a statistic
     */
    private function extract_context($content, $statistic) {
        $position = strpos($content, $statistic);
        if ($position === false) {
            return '';
        }

        // Extract 100 characters before and after
        $start = max(0, $position - 100);
        $length = 200 + strlen($statistic);
        $context = substr($content, $start, $length);

        // Clean up the context
        $context = preg_replace('/^\S*\s+/', '', $context); // Remove partial word at start
        $context = preg_replace('/\s+\S*$/', '', $context); // Remove partial word at end

        return $context;
    }

    /**
     * Normalize numbers to standard format
     */
    private function normalize_number($number, $unit) {
        $value = floatval(str_replace(',', '', $number));
        $unit = strtolower($unit);

        $multipliers = array(
            'k' => 1000,
            'thousand' => 1000,
            'm' => 1000000,
            'million' => 1000000,
            'b' => 1000000000,
            'billion' => 1000000000,
            'trillion' => 1000000000000
        );

        if (isset($multipliers[$unit])) {
            $value *= $multipliers[$unit];
        }

        return $value;
    }

    /**
     * Assess citation quality score (0-100)
     */
    private function assess_citation_quality($statistic, $context) {
        $score = 50; // Base score

        // Length and specificity
        if (strlen($context) > 50) $score += 10;
        if (strlen($context) > 100) $score += 10;

        // Presence of source indicators
        $source_indicators = array('according to', 'study', 'research', 'survey', 'report', 'data', 'statistics');
        foreach ($source_indicators as $indicator) {
            if (stripos($context, $indicator) !== false) {
                $score += 15;
                break;
            }
        }

        // Presence of time indicators
        $time_indicators = array('2024', '2023', '2022', 'recent', 'latest', 'current');
        foreach ($time_indicators as $indicator) {
            if (stripos($context, $indicator) !== false) {
                $score += 10;
                break;
            }
        }

        // Precision indicators
        if (preg_match('/\d+\.\d+/', $statistic)) $score += 5; // Decimal precision
        if (preg_match('/\d{4,}/', $statistic)) $score += 5; // Large specific numbers

        // Authority indicators
        $authority_indicators = array('expert', 'industry', 'leading', 'official', 'verified');
        foreach ($authority_indicators as $indicator) {
            if (stripos($context, $indicator) !== false) {
                $score += 10;
                break;
            }
        }

        return min(100, max(0, $score));
    }

    /**
     * Assess temporal citation quality based on data age
     */
    private function assess_temporal_citation_quality($age, $statistic) {
        $base_score = $this->assess_citation_quality($statistic, '');

        // Deduct points for old data
        if ($age <= 1) $base_score += 20; // Very recent
        elseif ($age <= 2) $base_score += 10; // Recent
        elseif ($age <= 3) $base_score += 0; // Acceptable
        elseif ($age <= 5) $base_score -= 10; // Getting old
        else $base_score -= 20; // Old data

        return min(100, max(0, $base_score));
    }

    /**
     * Categorize change direction
     */
    private function categorize_change_direction($verb) {
        $positive = array('increased', 'grew', 'rose', 'jumped');
        $negative = array('decreased', 'declined', 'fell', 'dropped');

        $verb = strtolower($verb);

        foreach ($positive as $pos) {
            if (strpos($verb, $pos) !== false) {
                return 'positive';
            }
        }

        foreach ($negative as $neg) {
            if (strpos($verb, $neg) !== false) {
                return 'negative';
            }
        }

        return 'neutral';
    }

    /**
     * Remove duplicate statistics
     */
    private function deduplicate_stats($stats) {
        $seen = array();
        $unique = array();

        foreach ($stats as $stat) {
            $key = $stat['type'] . '|' . $stat['value'];
            if (!in_array($key, $seen)) {
                $seen[] = $key;
                $unique[] = $stat;
            }
        }

        return $unique;
    }

    /**
     * Update citation statistics for a post
     */
    public function update_citation_stats($post_id, $post = null) {
        if (!$post) {
            $post = get_post($post_id);
        }

        if (!$post || !in_array($post->post_type, array('post', 'page'))) {
            return;
        }

        $stats = $this->extract_statistics($post->post_content);

        // Save to post meta for quick access
        update_post_meta($post_id, '_requestdesk_citation_stats', $stats);
        update_post_meta($post_id, '_requestdesk_citation_count', count($stats));
        update_post_meta($post_id, '_requestdesk_citation_updated', current_time('timestamp'));

        return $stats;
    }

    /**
     * Get citation statistics for a post
     */
    public function get_citation_stats($post_id) {
        $stats = get_post_meta($post_id, '_requestdesk_citation_stats', true);

        if (!$stats) {
            $post = get_post($post_id);
            if ($post) {
                $stats = $this->update_citation_stats($post_id, $post);
            }
        }

        return $stats ?: array();
    }

    /**
     * Monitor citations across the site
     */
    public function monitor_citations() {
        // Get all posts that need citation monitoring
        $posts = get_posts(array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'posts_per_page' => -1, // Process all posts for complete analytics
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_requestdesk_citation_updated',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_requestdesk_citation_updated',
                    'value' => strtotime('-7 days'),
                    'compare' => '<',
                    'type' => 'NUMERIC'
                )
            )
        ));

        foreach ($posts as $post) {
            $this->update_citation_stats($post->ID, $post);
        }

        // Log monitoring activity
        error_log('RequestDesk Citation Monitor: Updated ' . count($posts) . ' posts');
    }

    /**
     * Get citation analytics for dashboard
     */
    public function get_citation_analytics() {
        global $wpdb;

        $analytics = array(
            'total_statistics' => 0,
            'high_quality_stats' => 0,
            'posts_with_stats' => 0,
            'avg_stats_per_post' => 0,
            'top_stat_types' => array(),
            'citation_trends' => array()
        );

        // Get total statistics across all posts
        $results = $wpdb->get_results("
            SELECT meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_requestdesk_citation_stats'
            AND meta_value != ''
        ");

        $all_stats = array();
        $type_counts = array();

        foreach ($results as $result) {
            $stats = maybe_unserialize($result->meta_value);
            if (is_array($stats)) {
                $all_stats = array_merge($all_stats, $stats);

                foreach ($stats as $stat) {
                    $type = $stat['type'] ?? 'unknown';
                    $type_counts[$type] = ($type_counts[$type] ?? 0) + 1;
                }
            }
        }

        $analytics['total_statistics'] = count($all_stats);
        $analytics['posts_with_stats'] = count($results);
        $analytics['avg_stats_per_post'] = $analytics['posts_with_stats'] > 0
            ? round($analytics['total_statistics'] / $analytics['posts_with_stats'], 1)
            : 0;

        // Count high-quality statistics (score >= 70)
        $analytics['high_quality_stats'] = count(array_filter($all_stats, function($stat) {
            return ($stat['citation_quality'] ?? 0) >= 70;
        }));

        // Top statistic types
        arsort($type_counts);
        $analytics['top_stat_types'] = array_slice($type_counts, 0, 5, true);

        return $analytics;
    }

    /**
     * AJAX handler for getting citation stats
     */
    public function ajax_get_citation_stats() {
        check_ajax_referer('requestdesk_aeo_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_die('Unauthorized');
        }

        $stats = $this->get_citation_stats($post_id);

        wp_send_json_success($stats);
    }

    /**
     * AJAX handler for updating citation stats
     */
    public function ajax_update_citation_stats() {
        check_ajax_referer('requestdesk_aeo_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_die('Unauthorized');
        }

        $stats = $this->update_citation_stats($post_id);

        wp_send_json_success(array(
            'stats' => $stats,
            'count' => count($stats),
            'updated' => current_time('mysql')
        ));
    }

    /**
     * Format statistic for display
     */
    public function format_statistic_for_display($stat) {
        $formatted = array(
            'display_value' => $stat['value'],
            'context' => wp_trim_words($stat['context'], 15),
            'quality_badge' => $this->get_quality_badge($stat['citation_quality']),
            'type_label' => $this->get_type_label($stat['type']),
            'freshness' => $this->get_freshness_indicator($stat)
        );

        return $formatted;
    }

    /**
     * Get quality badge for a statistic
     */
    private function get_quality_badge($score) {
        if ($score >= 80) return array('label' => 'Excellent', 'class' => 'success');
        if ($score >= 60) return array('label' => 'Good', 'class' => 'warning');
        if ($score >= 40) return array('label' => 'Fair', 'class' => 'info');
        return array('label' => 'Poor', 'class' => 'danger');
    }

    /**
     * Get human-readable type label
     */
    private function get_type_label($type) {
        $labels = array(
            'percentage' => 'Percentage',
            'numeric' => 'Number',
            'ratio' => 'Ratio',
            'change' => 'Change',
            'temporal' => 'Time-based',
            'ranking' => 'Ranking'
        );

        return $labels[$type] ?? ucfirst($type);
    }

    /**
     * Get freshness indicator
     */
    private function get_freshness_indicator($stat) {
        if (isset($stat['data_age'])) {
            $age = $stat['data_age'];
            if ($age <= 1) return array('label' => 'Very Fresh', 'class' => 'success');
            if ($age <= 2) return array('label' => 'Fresh', 'class' => 'success');
            if ($age <= 3) return array('label' => 'Recent', 'class' => 'warning');
            if ($age <= 5) return array('label' => 'Aging', 'class' => 'warning');
            return array('label' => 'Stale', 'class' => 'danger');
        }

        return array('label' => 'Unknown', 'class' => 'secondary');
    }
}