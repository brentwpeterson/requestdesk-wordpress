<?php
/**
 * RequestDesk AEO Meta Boxes
 */

/**
 * Add AEO meta boxes to post/page editors
 */
function requestdesk_aeo_add_meta_boxes() {
    $settings = get_option('requestdesk_aeo_settings', array());

    // Only add meta boxes if AEO is enabled
    if (!($settings['enabled'] ?? true)) {
        return;
    }

    add_meta_box(
        'requestdesk-aeo-optimization',
        'AEO Optimization',
        'requestdesk_aeo_optimization_meta_box',
        array('post', 'page'),
        'side',
        'default'
    );

    add_meta_box(
        'requestdesk-aeo-qa-pairs',
        'Q&A Pairs',
        'requestdesk_aeo_qa_pairs_meta_box',
        array('post', 'page'),
        'normal',
        'default'
    );

    add_meta_box(
        'requestdesk-aeo-citations',
        'Citation Statistics',
        'requestdesk_aeo_citations_meta_box',
        array('post', 'page'),
        'normal',
        'default'
    );

    add_meta_box(
        'requestdesk-aeo-schema-control',
        'Schema Control',
        'requestdesk_aeo_schema_control_meta_box',
        array('post', 'page'),
        'side',
        'default'
    );
}

/**
 * Main AEO optimization meta box
 */
function requestdesk_aeo_optimization_meta_box($post) {
    $aeo_core = new RequestDesk_AEO_Core();
    $aeo_data = $aeo_core->get_aeo_data($post->ID);

    $aeo_score = get_post_meta($post->ID, '_requestdesk_aeo_score', true);
    $freshness_score = get_post_meta($post->ID, '_requestdesk_freshness_score', true);
    $freshness_status = get_post_meta($post->ID, '_requestdesk_freshness_status', true);
    $last_update = get_post_meta($post->ID, '_requestdesk_aeo_last_update', true);

    wp_nonce_field('requestdesk_aeo_meta_box', 'requestdesk_aeo_meta_box_nonce');
    ?>

    <div class="requestdesk-aeo-overview">
        <!-- AEO Score Display -->
        <div class="aeo-score-section" style="margin-bottom: 20px;">
            <h4 style="margin: 0 0 10px 0;">AEO Optimization Score</h4>
            <?php if ($aeo_score): ?>
                <div class="aeo-score-display" style="text-align: center; padding: 15px; background: #f9f9f9; border-radius: 8px;">
                    <div class="score-circle" style="display: inline-block; width: 60px; height: 60px; border-radius: 50%; background: <?php echo requestdesk_get_score_color($aeo_score); ?>; color: white; line-height: 60px; font-size: 18px; font-weight: bold;">
                        <?php echo $aeo_score; ?>%
                    </div>
                    <div style="margin-top: 10px;">
                        <strong><?php echo requestdesk_get_score_label($aeo_score); ?></strong>
                    </div>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 15px; background: #f9f9f9; border-radius: 8px; color: #666;">
                    Not yet analyzed
                </div>
            <?php endif; ?>
        </div>

        <!-- Freshness Score -->
        <div class="freshness-section" style="margin-bottom: 20px;">
            <h4 style="margin: 0 0 10px 0;">Content Freshness</h4>
            <?php if ($freshness_score): ?>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="flex: 1; background: #f0f0f0; border-radius: 10px; height: 8px;">
                        <div style="width: <?php echo $freshness_score; ?>%; height: 100%; background: <?php echo requestdesk_get_score_color($freshness_score); ?>; border-radius: 10px;"></div>
                    </div>
                    <div style="font-weight: bold; color: <?php echo requestdesk_get_score_color($freshness_score); ?>;">
                        <?php echo $freshness_score; ?>%
                    </div>
                </div>
                <small style="color: #666;"><?php echo ucfirst($freshness_status ?: 'unknown'); ?> freshness</small>
            <?php else: ?>
                <div style="color: #666;">Not yet analyzed</div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="aeo-actions" style="margin-bottom: 20px;">
            <button type="button" class="button button-primary aeo-optimize-btn" data-post-id="<?php echo $post->ID; ?>">
                <?php echo $aeo_score ? 'Re-optimize Content' : 'Optimize Content'; ?>
            </button>
            <button type="button" class="button aeo-analyze-btn" data-post-id="<?php echo $post->ID; ?>">
                Analyze Only
            </button>
        </div>

        <!-- Status and Last Update -->
        <?php if ($last_update): ?>
        <div class="aeo-status" style="font-size: 12px; color: #666; border-top: 1px solid #eee; padding-top: 15px;">
            <strong>Last updated:</strong> <?php echo human_time_diff($last_update, current_time('timestamp')); ?> ago<br>
            <strong>Status:</strong> <?php echo ucfirst($aeo_data['optimization_status'] ?? 'pending'); ?>
        </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <?php if (!empty($aeo_data)): ?>
        <div class="aeo-quick-stats" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12px;">
                <div>
                    <strong>Q&A Pairs:</strong><br>
                    <?php echo count($aeo_data['ai_questions'] ?? array()); ?>
                </div>
                <div>
                    <strong>Statistics:</strong><br>
                    <?php echo count($aeo_data['citation_stats'] ?? array()); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="aeo-loading" style="display: none; text-align: center; padding: 20px;">
        <div class="spinner is-active"></div>
        <p>Processing...</p>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('.aeo-optimize-btn').on('click', function() {
            const postId = $(this).data('post-id');
            const $container = $('.requestdesk-aeo-overview');
            const $loading = $('.aeo-loading');

            $container.hide();
            $loading.show();

            $.post(ajaxurl, {
                action: 'requestdesk_optimize_content',
                post_id: postId,
                force: true,
                nonce: requestdesk_aeo.nonce
            }, function(response) {
                if (response.success) {
                    location.reload(); // Refresh to show updated data
                } else {
                    alert('Optimization failed: ' + response.data);
                    $loading.hide();
                    $container.show();
                }
            });
        });

        $('.aeo-analyze-btn').on('click', function() {
            const postId = $(this).data('post-id');

            $.post(ajaxurl, {
                action: 'requestdesk_analyze_content',
                post_id: postId,
                nonce: requestdesk_aeo.nonce
            }, function(response) {
                if (response.success) {
                    console.log('Analysis results:', response.data);
                    // You could show analysis results in a modal or update UI
                    alert('Analysis complete. Check browser console for details.');
                } else {
                    alert('Analysis failed: ' + response.data);
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Q&A Pairs meta box
 */
function requestdesk_aeo_qa_pairs_meta_box($post) {
    $aeo_core = new RequestDesk_AEO_Core();
    $aeo_data = $aeo_core->get_aeo_data($post->ID);
    $qa_pairs = $aeo_data['ai_questions'] ?? array();
    ?>

    <div class="requestdesk-qa-pairs">
        <?php if (!empty($qa_pairs)): ?>
            <div class="qa-pairs-list">
                <?php foreach ($qa_pairs as $index => $qa): ?>
                <div class="qa-pair" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 8px;">
                    <div class="qa-question" style="margin-bottom: 10px;">
                        <strong style="color: #0073aa;">Q: <?php echo esc_html($qa['question']); ?></strong>
                        <span class="qa-confidence" style="float: right; font-size: 11px; background: <?php echo requestdesk_get_confidence_color($qa['confidence']); ?>; color: white; padding: 2px 6px; border-radius: 3px;">
                            <?php echo round($qa['confidence'] * 100); ?>% confidence
                        </span>
                    </div>
                    <div class="qa-answer" style="color: #333; line-height: 1.4;">
                        <strong>A:</strong> <?php echo esc_html(wp_trim_words($qa['answer'], 25)); ?>
                    </div>
                    <div class="qa-meta" style="margin-top: 8px; font-size: 11px; color: #666;">
                        Type: <?php echo ucfirst($qa['type'] ?? 'unknown'); ?>
                        <button type="button" class="button-link qa-edit-btn" data-index="<?php echo $index; ?>" style="margin-left: 10px;">Edit</button>
                        <button type="button" class="button-link qa-remove-btn" data-index="<?php echo $index; ?>" style="margin-left: 5px; color: #d63638;">Remove</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="qa-actions" style="margin-top: 20px;">
                <button type="button" class="button qa-add-btn">Add Manual Q&A Pair</button>
                <button type="button" class="button qa-regenerate-btn" data-post-id="<?php echo $post->ID; ?>">Regenerate Q&A Pairs</button>
            </div>
        <?php else: ?>
            <div class="no-qa-pairs" style="text-align: center; padding: 40px 20px; color: #666;">
                <p>No Q&A pairs found.</p>
                <button type="button" class="button button-primary qa-generate-btn" data-post-id="<?php echo $post->ID; ?>">
                    Generate Q&A Pairs
                </button>
            </div>
        <?php endif; ?>

        <!-- Add/Edit Q&A Modal -->
        <div class="qa-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000;">
            <div class="qa-modal-content" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 600px;">
                <h3>Add/Edit Q&A Pair</h3>
                <div style="margin-bottom: 15px;">
                    <label><strong>Question:</strong></label>
                    <input type="text" class="qa-question-input" style="width: 100%; margin-top: 5px;" placeholder="Enter your question...">
                </div>
                <div style="margin-bottom: 20px;">
                    <label><strong>Answer:</strong></label>
                    <textarea class="qa-answer-input" rows="4" style="width: 100%; margin-top: 5px;" placeholder="Enter the answer..."></textarea>
                </div>
                <div style="text-align: right;">
                    <button type="button" class="button qa-modal-cancel">Cancel</button>
                    <button type="button" class="button button-primary qa-modal-save" style="margin-left: 10px;">Save</button>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="requestdesk_qa_pairs" value="<?php echo esc_attr(json_encode($qa_pairs)); ?>">

    <script>
    jQuery(document).ready(function($) {
        let currentQAIndex = -1;

        // Add new Q&A pair
        $('.qa-add-btn').on('click', function() {
            currentQAIndex = -1;
            $('.qa-question-input').val('');
            $('.qa-answer-input').val('');
            $('.qa-modal').show();
        });

        // Edit Q&A pair
        $('.qa-edit-btn').on('click', function() {
            currentQAIndex = $(this).data('index');
            const qaPairs = JSON.parse($('input[name="requestdesk_qa_pairs"]').val());
            const qa = qaPairs[currentQAIndex];

            $('.qa-question-input').val(qa.question);
            $('.qa-answer-input').val(qa.answer);
            $('.qa-modal').show();
        });

        // Remove Q&A pair
        $('.qa-remove-btn').on('click', function() {
            if (confirm('Remove this Q&A pair?')) {
                const index = $(this).data('index');
                let qaPairs = JSON.parse($('input[name="requestdesk_qa_pairs"]').val());
                qaPairs.splice(index, 1);
                $('input[name="requestdesk_qa_pairs"]').val(JSON.stringify(qaPairs));
                location.reload();
            }
        });

        // Modal actions
        $('.qa-modal-cancel').on('click', function() {
            $('.qa-modal').hide();
        });

        $('.qa-modal-save').on('click', function() {
            const question = $('.qa-question-input').val().trim();
            const answer = $('.qa-answer-input').val().trim();

            if (!question || !answer) {
                alert('Please enter both question and answer.');
                return;
            }

            let qaPairs = JSON.parse($('input[name="requestdesk_qa_pairs"]').val() || '[]');

            if (currentQAIndex >= 0) {
                // Edit existing
                qaPairs[currentQAIndex].question = question;
                qaPairs[currentQAIndex].answer = answer;
            } else {
                // Add new
                qaPairs.push({
                    question: question,
                    answer: answer,
                    type: 'manual',
                    confidence: 1.0
                });
            }

            $('input[name="requestdesk_qa_pairs"]').val(JSON.stringify(qaPairs));
            $('.qa-modal').hide();
            location.reload();
        });

        // Regenerate Q&A pairs
        $('.qa-regenerate-btn, .qa-generate-btn').on('click', function() {
            const postId = $(this).data('post-id');

            if (confirm('This will replace existing Q&A pairs. Continue?')) {
                $.post(ajaxurl, {
                    action: 'requestdesk_optimize_content',
                    post_id: postId,
                    force: true,
                    nonce: requestdesk_aeo.nonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to regenerate Q&A pairs: ' + response.data);
                    }
                });
            }
        });
    });
    </script>
    <?php
}

/**
 * Citation Statistics meta box
 */
function requestdesk_aeo_citations_meta_box($post) {
    $citation_tracker = new RequestDesk_Citation_Tracker();
    $citation_stats = $citation_tracker->get_citation_stats($post->ID);
    ?>

    <div class="requestdesk-citations">
        <?php if (!empty($citation_stats)): ?>
            <div class="citations-list">
                <?php foreach (array_slice($citation_stats, 0, 10) as $stat): ?>
                <?php $formatted = $citation_tracker->format_statistic_for_display($stat); ?>
                <div class="citation-item" style="margin-bottom: 15px; padding: 12px; background: #f9f9f9; border-radius: 6px; border-left: 4px solid <?php echo requestdesk_get_citation_color($stat['citation_quality']); ?>;">
                    <div class="citation-value" style="font-weight: bold; color: #0073aa; margin-bottom: 5px;">
                        <?php echo esc_html($formatted['display_value']); ?>
                        <span class="citation-badge" style="float: right; font-size: 10px; background: <?php echo requestdesk_get_badge_color($formatted['quality_badge']['class']); ?>; color: white; padding: 2px 6px; border-radius: 3px;">
                            <?php echo $formatted['quality_badge']['label']; ?>
                        </span>
                    </div>
                    <div class="citation-context" style="font-size: 13px; color: #666; line-height: 1.3;">
                        <?php echo esc_html($formatted['context']); ?>
                    </div>
                    <div class="citation-meta" style="margin-top: 8px; font-size: 11px; color: #999;">
                        <?php echo $formatted['type_label']; ?>
                        <?php if (!empty($formatted['freshness'])): ?>
                        | <span style="color: <?php echo requestdesk_get_badge_color($formatted['freshness']['class']); ?>;">
                            <?php echo $formatted['freshness']['label']; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($citation_stats) > 10): ?>
            <div class="citations-summary" style="margin-top: 15px; padding: 10px; background: #e8f4fd; border-radius: 6px; text-align: center;">
                <small>Showing top 10 of <?php echo count($citation_stats); ?> citation statistics found.</small>
            </div>
            <?php endif; ?>

            <div class="citations-actions" style="margin-top: 20px;">
                <button type="button" class="button citations-refresh-btn" data-post-id="<?php echo $post->ID; ?>">
                    Refresh Citations
                </button>
                <button type="button" class="button citations-export-btn" data-post-id="<?php echo $post->ID; ?>" disabled style="position: relative;">
                    Export Statistics
                    <span style="background: #e74c3c; color: white; font-size: 10px; padding: 2px 6px; border-radius: 3px; margin-left: 5px; font-weight: bold;">
                        COMING SOON
                    </span>
                </button>
            </div>
        <?php else: ?>
            <div class="no-citations" style="text-align: center; padding: 40px 20px; color: #666;">
                <p>No citation statistics found.</p>
                <button type="button" class="button button-primary citations-scan-btn" data-post-id="<?php echo $post->ID; ?>">
                    Scan for Statistics
                </button>
            </div>
        <?php endif; ?>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('.citations-refresh-btn, .citations-scan-btn').on('click', function() {
            const postId = $(this).data('post-id');

            $.post(ajaxurl, {
                action: 'requestdesk_update_citation_stats',
                post_id: postId,
                nonce: requestdesk_aeo.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed to update citation statistics: ' + response.data);
                }
            });
        });

        // Export button is disabled with "Coming Soon" badge
        // $('.citations-export-btn').on('click', function() {
        //     const postId = $(this).data('post-id');
        //     // This could open a modal or trigger a download
        //     alert('Export functionality coming soon!');
        // });
    });
    </script>
    <?php
}

/**
 * Save AEO meta box data
 */
add_action('save_post', 'requestdesk_save_aeo_meta_box_data');
function requestdesk_save_aeo_meta_box_data($post_id) {
    // Check if nonce is set
    if (!isset($_POST['requestdesk_aeo_meta_box_nonce'])) {
        return;
    }

    // Verify nonce
    if (!wp_verify_nonce($_POST['requestdesk_aeo_meta_box_nonce'], 'requestdesk_aeo_meta_box')) {
        return;
    }

    // Check if user has permission to edit post
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save manual Q&A pairs if provided.
    //
    // This path is kept deliberately in step with RequestDesk_AEO_Core::rest_set_qa_pairs().
    // Two write paths feed the same table, and where they disagreed it was always
    // the meta box that was wrong:
    //
    //   1. It wrote ai_questions but never regenerated faq_data. wp_head emits
    //      faq_data, so Q&A typed into this box rendered visually and emitted NO
    //      FAQPage schema. Fixed in the REST path only; this is the other half.
    //   2. It omitted source => 'manual', which is the flag auto-optimize checks
    //      before overwriting pairs (see v2.32.1). Pairs entered here were not
    //      protected and could be wiped -- the exact bug that release fixed.
    //   3. It stored the decoded POST payload unsanitized.
    if (isset($_POST['requestdesk_qa_pairs']) && !empty($_POST['requestdesk_qa_pairs'])) {
        $qa_pairs = json_decode(stripslashes($_POST['requestdesk_qa_pairs']), true);
        $post = get_post($post_id);

        if (is_array($qa_pairs) && $post) {
            $clean = array();
            foreach ($qa_pairs as $pair) {
                if (!is_array($pair)) {
                    continue;
                }
                $question = trim(wp_strip_all_tags($pair['question'] ?? ''));
                $answer = trim(wp_kses_post($pair['answer'] ?? ''));
                if ($question === '' || $answer === '') {
                    continue;
                }
                $confidence = isset($pair['confidence']) ? (float) $pair['confidence'] : 1.0;
                $clean[] = array(
                    'question' => $question,
                    'answer' => $answer,
                    'confidence' => max(0.0, min(1.0, $confidence)),
                    'source' => 'manual',
                );
            }

            $aeo_core = new RequestDesk_AEO_Core();

            // Ensure the row exists before update_aeo_data(), which is UPDATE-only.
            $aeo_core->get_aeo_data($post_id);

            // Regenerate FAQPage schema so <head> stays in sync with the visual block.
            $schema_generator = new RequestDesk_Schema_Generator();
            $faq_schema = $schema_generator->generate_faq_schema($post, $clean);

            $aeo_core->update_aeo_data($post_id, array(
                'ai_questions' => wp_json_encode($clean),
                'faq_data' => wp_json_encode($faq_schema),
                'updated_at' => current_time('mysql'),
            ));

            // Also update post meta for quick access
            update_post_meta($post_id, '_requestdesk_manual_qa_pairs', $clean);
        }
    }
}

/**
 * Helper functions for styling
 */
function requestdesk_get_score_color($score) {
    if ($score >= 80) return '#46b450';
    if ($score >= 60) return '#00a32a';
    if ($score >= 40) return '#ffb900';
    if ($score >= 20) return '#f56e28';
    return '#d63638';
}

function requestdesk_get_score_label($score) {
    if ($score >= 80) return 'Excellent';
    if ($score >= 60) return 'Good';
    if ($score >= 40) return 'Fair';
    if ($score >= 20) return 'Poor';
    return 'Needs Work';
}

function requestdesk_get_confidence_color($confidence) {
    if ($confidence >= 0.8) return '#46b450';
    if ($confidence >= 0.6) return '#ffb900';
    return '#f56e28';
}

function requestdesk_get_citation_color($quality) {
    if ($quality >= 80) return '#46b450';
    if ($quality >= 60) return '#00a32a';
    if ($quality >= 40) return '#ffb900';
    return '#f56e28';
}

function requestdesk_get_badge_color($class) {
    $colors = array(
        'success' => '#46b450',
        'warning' => '#ffb900',
        'info' => '#0073aa',
        'danger' => '#d63638',
        'secondary' => '#666'
    );
    return $colors[$class] ?? '#666';
}

/**
 * Schema Control meta box
 */
function requestdesk_aeo_schema_control_meta_box($post) {
    // Get detected schemas using content detector
    $detected_schemas = array();
    if (class_exists('RequestDesk_Content_Detector')) {
        $detector = new RequestDesk_Content_Detector();
        $detected_schemas = $detector->detect_schema_types($post);
    }

    // Get manual overrides from post meta
    $schema_overrides = get_post_meta($post->ID, '_requestdesk_schema_overrides', true);
    if (!is_array($schema_overrides)) {
        $schema_overrides = array();
    }

    // Get global settings
    $settings = get_option('requestdesk_aeo_settings', array());
    $confidence_threshold = floatval($settings['schema_detection_confidence'] ?? 0.6);

    // Define all schema types with their labels
    $schema_types = array(
        'article' => array('label' => 'Article', 'icon' => '📄', 'always' => true),
        'breadcrumb' => array('label' => 'Breadcrumb', 'icon' => '🔗', 'always' => true),
        'faq' => array('label' => 'FAQ', 'icon' => '❓', 'always' => false),
        'howto' => array('label' => 'HowTo', 'icon' => '📋', 'always' => false),
        'product' => array('label' => 'Product', 'icon' => '🛒', 'always' => false),
        'local_business' => array('label' => 'LocalBusiness', 'icon' => '🏢', 'always' => false),
        'video' => array('label' => 'Video', 'icon' => '🎬', 'always' => false),
        'course' => array('label' => 'Course', 'icon' => '🎓', 'always' => false),
    );

    wp_nonce_field('requestdesk_schema_control', 'requestdesk_schema_control_nonce');
    ?>

    <div class="requestdesk-schema-control">
        <p style="margin: 0 0 15px 0; font-size: 12px; color: #666;">
            Auto-detected schema types based on content analysis. Override to force enable/disable.
        </p>

        <div class="schema-types-list">
            <?php foreach ($schema_types as $type => $info): ?>
                <?php
                // Determine detection status
                $is_detected = false;
                $confidence = 0;
                $detection_info = '';

                if ($info['always']) {
                    $is_detected = true;
                    $confidence = 1.0;
                    $detection_info = 'Always enabled';
                } elseif (isset($detected_schemas[$type])) {
                    $detection = $detected_schemas[$type];
                    if (is_array($detection)) {
                        $is_detected = ($detection['confidence'] ?? 0) >= $confidence_threshold;
                        $confidence = $detection['confidence'] ?? 0;
                        $detection_info = implode(', ', array_slice($detection['signals'] ?? array(), 0, 2));
                    } else {
                        $is_detected = (bool)$detection;
                        $confidence = $detection ? 1.0 : 0;
                    }
                }

                // Check for manual override
                $override = $schema_overrides[$type] ?? 'auto';
                $final_enabled = ($override === 'force_on') || ($override === 'auto' && $is_detected);

                // Check if globally enabled
                $global_enabled = $settings['schema_' . $type] ?? true;
                ?>

                <div class="schema-type-row" style="display: flex; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee;">
                    <div class="schema-status" style="width: 24px; text-align: center;">
                        <?php if (!$global_enabled): ?>
                            <span title="Disabled in global settings" style="color: #999;">⊘</span>
                        <?php elseif ($final_enabled): ?>
                            <span title="Schema will be generated" style="color: #46b450;">✓</span>
                        <?php else: ?>
                            <span title="Schema will not be generated" style="color: #ccc;">○</span>
                        <?php endif; ?>
                    </div>

                    <div class="schema-info" style="flex: 1; min-width: 0;">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-size: 14px;"><?php echo $info['icon']; ?></span>
                            <strong style="font-size: 13px;"><?php echo esc_html($info['label']); ?></strong>
                        </div>
                        <?php if ($confidence > 0 && !$info['always']): ?>
                            <div style="font-size: 10px; color: #666; margin-top: 2px;">
                                <?php echo round($confidence * 100); ?>% confidence
                                <?php if ($detection_info): ?>
                                    · <?php echo esc_html($detection_info); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="schema-override" style="width: 90px;">
                        <select name="requestdesk_schema_override[<?php echo esc_attr($type); ?>]"
                                style="width: 100%; font-size: 11px; padding: 2px;"
                                <?php echo !$global_enabled ? 'disabled' : ''; ?>>
                            <option value="auto" <?php selected($override, 'auto'); ?>>Auto</option>
                            <option value="force_on" <?php selected($override, 'force_on'); ?>>Force On</option>
                            <option value="force_off" <?php selected($override, 'force_off'); ?>>Force Off</option>
                        </select>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Detection Summary -->
        <div class="schema-detection-summary" style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-radius: 6px;">
            <h4 style="margin: 0 0 8px 0; font-size: 12px; color: #333;">Detection Settings</h4>
            <div style="font-size: 11px; color: #666;">
                <strong>Confidence threshold:</strong> <?php echo round($confidence_threshold * 100); ?>%<br>
                <strong>Mode:</strong> <?php echo ucfirst($settings['schema_detection_mode'] ?? 'auto'); ?>
            </div>
        </div>

        <!-- Regenerate Button -->
        <div class="schema-actions" style="margin-top: 15px;">
            <button type="button" class="button schema-regenerate-btn" data-post-id="<?php echo $post->ID; ?>" style="width: 100%;">
                🔄 Regenerate Schema
            </button>
        </div>

        <!-- Schema Preview Toggle -->
        <div class="schema-preview-toggle" style="margin-top: 10px;">
            <button type="button" class="button-link schema-preview-btn" style="font-size: 11px; width: 100%; text-align: center;">
                Show Schema Preview ▼
            </button>
        </div>

        <div class="schema-preview-content" style="display: none; margin-top: 10px; padding: 10px; background: #1e1e1e; border-radius: 4px; max-height: 200px; overflow: auto;">
            <pre style="margin: 0; font-size: 10px; color: #d4d4d4; white-space: pre-wrap; word-break: break-all;" id="schema-preview-json">Loading...</pre>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Regenerate schema
        $('.schema-regenerate-btn').on('click', function() {
            const postId = $(this).data('post-id');
            const $btn = $(this);

            $btn.prop('disabled', true).text('Regenerating...');

            $.post(ajaxurl, {
                action: 'requestdesk_regenerate_schema',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('requestdesk_regenerate_schema'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed to regenerate schema: ' + (response.data || 'Unknown error'));
                    $btn.prop('disabled', false).text('🔄 Regenerate Schema');
                }
            }).fail(function() {
                alert('Request failed. Please try again.');
                $btn.prop('disabled', false).text('🔄 Regenerate Schema');
            });
        });

        // Toggle schema preview
        $('.schema-preview-btn').on('click', function() {
            const $content = $('.schema-preview-content');
            const $btn = $(this);

            if ($content.is(':visible')) {
                $content.slideUp(200);
                $btn.text('Show Schema Preview ▼');
            } else {
                $content.slideDown(200);
                $btn.text('Hide Schema Preview ▲');

                // Load schema preview if not already loaded
                if ($('#schema-preview-json').text() === 'Loading...') {
                    $.get(ajaxurl, {
                        action: 'requestdesk_get_schema_preview',
                        post_id: <?php echo $post->ID; ?>,
                        nonce: '<?php echo wp_create_nonce('requestdesk_schema_preview'); ?>'
                    }, function(response) {
                        if (response.success && response.data) {
                            $('#schema-preview-json').text(JSON.stringify(response.data, null, 2));
                        } else {
                            $('#schema-preview-json').text('No schema generated yet.');
                        }
                    }).fail(function() {
                        $('#schema-preview-json').text('Failed to load schema preview.');
                    });
                }
            }
        });
    });
    </script>
    <?php
}

/**
 * Save schema control overrides
 */
add_action('save_post', 'requestdesk_save_schema_overrides');
function requestdesk_save_schema_overrides($post_id) {
    // Check nonce
    if (!isset($_POST['requestdesk_schema_control_nonce'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['requestdesk_schema_control_nonce'], 'requestdesk_schema_control')) {
        return;
    }

    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save overrides
    if (isset($_POST['requestdesk_schema_override']) && is_array($_POST['requestdesk_schema_override'])) {
        $overrides = array();
        $valid_values = array('auto', 'force_on', 'force_off');

        foreach ($_POST['requestdesk_schema_override'] as $type => $value) {
            $type = sanitize_key($type);
            $value = sanitize_key($value);

            if (in_array($value, $valid_values)) {
                $overrides[$type] = $value;
            }
        }

        update_post_meta($post_id, '_requestdesk_schema_overrides', $overrides);
    }
}

/**
 * AJAX handler for regenerating schema
 */
add_action('wp_ajax_requestdesk_regenerate_schema', 'requestdesk_ajax_regenerate_schema');
function requestdesk_ajax_regenerate_schema() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'requestdesk_regenerate_schema')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    $post_id = intval($_POST['post_id']);

    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error('Permission denied');
        return;
    }

    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error('Post not found');
        return;
    }

    // Generate fresh schema
    if (class_exists('RequestDesk_Schema_Generator')) {
        $generator = new RequestDesk_Schema_Generator();
        $schema = $generator->generate_comprehensive_schema($post);

        // Store the generated schema
        update_post_meta($post_id, '_requestdesk_schema_data', $schema);
        update_post_meta($post_id, '_requestdesk_schema_generated', current_time('timestamp'));

        wp_send_json_success(array(
            'message' => 'Schema regenerated successfully',
            'schema_count' => count($schema)
        ));
    } else {
        wp_send_json_error('Schema generator not available');
    }
}

/**
 * AJAX handler for getting schema preview
 */
add_action('wp_ajax_requestdesk_get_schema_preview', 'requestdesk_ajax_get_schema_preview');
function requestdesk_ajax_get_schema_preview() {
    // Verify nonce
    if (!wp_verify_nonce($_GET['nonce'], 'requestdesk_schema_preview')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    $post_id = intval($_GET['post_id']);

    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error('Post not found');
        return;
    }

    // Get or generate schema
    $schema = get_post_meta($post_id, '_requestdesk_schema_data', true);

    if (empty($schema) && class_exists('RequestDesk_Schema_Generator')) {
        $generator = new RequestDesk_Schema_Generator();
        $schema = $generator->generate_comprehensive_schema($post);
    }

    if (!empty($schema)) {
        wp_send_json_success($schema);
    } else {
        wp_send_json_error('No schema available');
    }
}