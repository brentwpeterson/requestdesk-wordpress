<?php
/**
 * RequestDesk SEO Meta Boxes
 *
 * Provides the post editor interface for SEO settings including
 * SEO title, meta description, focus keyphrase, canonical URL,
 * and robots meta settings.
 *
 * @package RequestDesk
 * @since 2.5.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add SEO meta boxes to post editor
 */
function requestdesk_seo_add_meta_boxes() {
    $seo_core = new RequestDesk_SEO_Core();

    // Don't add meta box if Yoast is active and we're set to defer
    if ($seo_core->is_yoast_active()) {
        $settings = $seo_core->get_settings();
        if ($settings['disable_if_yoast_active'] ?? true) {
            return;
        }
    }

    $post_types = array('post', 'page');

    foreach ($post_types as $post_type) {
        add_meta_box(
            'requestdesk-seo-settings',
            'SEO Settings - RequestDesk',
            'requestdesk_seo_meta_box_callback',
            $post_type,
            'normal',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'requestdesk_seo_add_meta_boxes');

/**
 * SEO meta box callback
 */
function requestdesk_seo_meta_box_callback($post) {
    // Security nonce
    wp_nonce_field('requestdesk_seo_meta_box', 'requestdesk_seo_meta_box_nonce');

    // Get existing values
    $seo_title = get_post_meta($post->ID, '_requestdesk_seo_title', true);
    $seo_description = get_post_meta($post->ID, '_requestdesk_seo_description', true);
    $focus_keyphrase = get_post_meta($post->ID, '_requestdesk_focus_keyphrase', true);
    $canonical_url = get_post_meta($post->ID, '_requestdesk_canonical_url', true);
    $noindex = get_post_meta($post->ID, '_requestdesk_noindex', true);
    $nofollow = get_post_meta($post->ID, '_requestdesk_nofollow', true);

    // Get keyphrase analysis
    $seo_core = new RequestDesk_SEO_Core();
    $keyphrase_analysis = $seo_core->analyze_keyphrase($post->ID, $focus_keyphrase);

    ?>
    <style>
        .requestdesk-seo-box {
            padding: 15px 0;
        }
        .requestdesk-seo-field {
            margin-bottom: 20px;
        }
        .requestdesk-seo-field label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #1d2327;
        }
        .requestdesk-seo-field input[type="text"],
        .requestdesk-seo-field textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            font-size: 14px;
        }
        .requestdesk-seo-field textarea {
            resize: vertical;
            min-height: 80px;
        }
        .requestdesk-seo-field .description {
            color: #646970;
            font-size: 12px;
            margin-top: 5px;
        }
        .requestdesk-seo-counter {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 10px;
        }
        .counter-ok { background: #d4edda; color: #155724; }
        .counter-warning { background: #fff3cd; color: #856404; }
        .counter-danger { background: #f8d7da; color: #721c24; }

        /* Google Preview */
        .requestdesk-serp-preview {
            background: #fff;
            border: 1px solid #dfe1e5;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            font-family: Arial, sans-serif;
        }
        .requestdesk-serp-preview h4 {
            margin: 0 0 10px 0;
            font-size: 12px;
            color: #70757a;
            text-transform: uppercase;
        }
        .serp-title {
            color: #1a0dab;
            font-size: 20px;
            line-height: 1.3;
            margin-bottom: 3px;
            word-wrap: break-word;
        }
        .serp-url {
            color: #006621;
            font-size: 14px;
            margin-bottom: 3px;
        }
        .serp-description {
            color: #545454;
            font-size: 14px;
            line-height: 1.57;
            word-wrap: break-word;
        }

        /* Focus Keyphrase Analysis */
        .requestdesk-keyphrase-analysis {
            background: #f8f9fa;
            border: 1px solid #e2e4e7;
            border-radius: 4px;
            padding: 15px;
            margin-top: 15px;
        }
        .keyphrase-score {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .keyphrase-score-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: bold;
            color: white;
            margin-right: 15px;
        }
        .score-excellent { background: #46b450; }
        .score-good { background: #00a32a; }
        .score-fair { background: #ffb900; }
        .score-poor { background: #f56e28; }
        .score-bad { background: #d63638; }

        .keyphrase-checks {
            margin-top: 10px;
        }
        .keyphrase-check {
            display: flex;
            align-items: center;
            padding: 5px 0;
            font-size: 13px;
        }
        .keyphrase-check .dashicons {
            margin-right: 8px;
        }
        .check-passed .dashicons { color: #46b450; }
        .check-failed .dashicons { color: #d63638; }

        /* Tabs */
        .requestdesk-seo-tabs {
            display: flex;
            border-bottom: 1px solid #c3c4c7;
            margin-bottom: 15px;
        }
        .requestdesk-seo-tab {
            padding: 10px 15px;
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
            margin-bottom: -1px;
            background: #f0f0f1;
            color: #50575e;
        }
        .requestdesk-seo-tab.active {
            background: #fff;
            border-color: #c3c4c7;
            color: #1d2327;
        }
        .requestdesk-seo-tab-content {
            display: none;
        }
        .requestdesk-seo-tab-content.active {
            display: block;
        }

        /* Robots checkboxes */
        .requestdesk-robots-options {
            display: flex;
            gap: 20px;
        }
        .requestdesk-robots-options label {
            display: flex;
            align-items: center;
            font-weight: normal;
        }
        .requestdesk-robots-options input[type="checkbox"] {
            margin-right: 5px;
        }
    </style>

    <div class="requestdesk-seo-box">
        <!-- Tabs -->
        <div class="requestdesk-seo-tabs">
            <div class="requestdesk-seo-tab active" data-tab="general">General</div>
            <div class="requestdesk-seo-tab" data-tab="keyphrase">Focus Keyphrase</div>
            <div class="requestdesk-seo-tab" data-tab="advanced">Advanced</div>
        </div>

        <!-- General Tab -->
        <div class="requestdesk-seo-tab-content active" id="tab-general">
            <!-- SEO Title -->
            <div class="requestdesk-seo-field">
                <label for="requestdesk_seo_title">
                    SEO Title
                    <span class="requestdesk-seo-counter" id="title-counter">0 / 60</span>
                </label>
                <input type="text"
                       id="requestdesk_seo_title"
                       name="requestdesk_seo_title"
                       value="<?php echo esc_attr($seo_title); ?>"
                       placeholder="<?php echo esc_attr($post->post_title); ?>">
                <p class="description">Leave blank to use post title. Recommended length: 50-60 characters.</p>
            </div>

            <!-- Meta Description -->
            <div class="requestdesk-seo-field">
                <label for="requestdesk_seo_description">
                    Meta Description
                    <span class="requestdesk-seo-counter" id="description-counter">0 / 160</span>
                </label>
                <textarea id="requestdesk_seo_description"
                          name="requestdesk_seo_description"
                          rows="3"
                          placeholder="Enter a description for search engines..."><?php echo esc_textarea($seo_description); ?></textarea>
                <p class="description">A brief summary of the page content. Recommended length: 120-160 characters.</p>
            </div>

            <!-- Google Preview -->
            <div class="requestdesk-serp-preview">
                <h4>Google Search Preview</h4>
                <div class="serp-title" id="serp-preview-title"><?php echo esc_html($seo_title ?: $post->post_title); ?></div>
                <div class="serp-url"><?php echo esc_url(get_permalink($post->ID)); ?></div>
                <div class="serp-description" id="serp-preview-description"><?php echo esc_html($seo_description ?: wp_trim_words(strip_tags($post->post_content), 25)); ?></div>
            </div>
        </div>

        <!-- Focus Keyphrase Tab -->
        <div class="requestdesk-seo-tab-content" id="tab-keyphrase">
            <div class="requestdesk-seo-field">
                <label for="requestdesk_focus_keyphrase">Focus Keyphrase</label>
                <input type="text"
                       id="requestdesk_focus_keyphrase"
                       name="requestdesk_focus_keyphrase"
                       value="<?php echo esc_attr($focus_keyphrase); ?>"
                       placeholder="Enter your main keyword or phrase">
                <p class="description">The primary keyword or phrase you want this page to rank for.</p>
            </div>

            <?php if (!empty($focus_keyphrase)): ?>
            <div class="requestdesk-keyphrase-analysis">
                <div class="keyphrase-score">
                    <?php
                    $score = $keyphrase_analysis['score'];
                    $score_class = 'score-bad';
                    if ($score >= 80) $score_class = 'score-excellent';
                    elseif ($score >= 60) $score_class = 'score-good';
                    elseif ($score >= 40) $score_class = 'score-fair';
                    elseif ($score >= 20) $score_class = 'score-poor';
                    ?>
                    <div class="keyphrase-score-circle <?php echo $score_class; ?>">
                        <?php echo $score; ?>
                    </div>
                    <div>
                        <strong>Keyphrase Score</strong><br>
                        <small>Based on <?php echo count($keyphrase_analysis['checks']); ?> checks</small>
                    </div>
                </div>

                <div class="keyphrase-checks">
                    <?php foreach ($keyphrase_analysis['checks'] as $check_id => $check): ?>
                    <div class="keyphrase-check <?php echo $check['passed'] ? 'check-passed' : 'check-failed'; ?>">
                        <span class="dashicons <?php echo $check['passed'] ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                        <?php echo esc_html($check['message']); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="requestdesk-keyphrase-analysis">
                <p><em>Enter a focus keyphrase above to see analysis.</em></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Advanced Tab -->
        <div class="requestdesk-seo-tab-content" id="tab-advanced">
            <!-- Canonical URL -->
            <div class="requestdesk-seo-field">
                <label for="requestdesk_canonical_url">Canonical URL</label>
                <input type="url"
                       id="requestdesk_canonical_url"
                       name="requestdesk_canonical_url"
                       value="<?php echo esc_url($canonical_url); ?>"
                       placeholder="<?php echo esc_url(get_permalink($post->ID)); ?>">
                <p class="description">Leave blank to use the default permalink. Use this to point to the original source if this content exists elsewhere.</p>
            </div>

            <!-- Robots Meta -->
            <div class="requestdesk-seo-field">
                <label>Robots Meta</label>
                <div class="requestdesk-robots-options">
                    <label>
                        <input type="checkbox"
                               name="requestdesk_noindex"
                               value="1"
                               <?php checked($noindex, '1'); ?>>
                        No Index (prevent search engines from indexing)
                    </label>
                    <label>
                        <input type="checkbox"
                               name="requestdesk_nofollow"
                               value="1"
                               <?php checked($nofollow, '1'); ?>>
                        No Follow (prevent search engines from following links)
                    </label>
                </div>
                <p class="description">Use these options to control how search engines handle this page.</p>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Tab switching
        $('.requestdesk-seo-tab').on('click', function() {
            var tabId = $(this).data('tab');

            $('.requestdesk-seo-tab').removeClass('active');
            $(this).addClass('active');

            $('.requestdesk-seo-tab-content').removeClass('active');
            $('#tab-' + tabId).addClass('active');
        });

        // Character counters
        function updateCounter(input, counterId, maxLength, warnLength) {
            var length = $(input).val().length;
            var $counter = $('#' + counterId);

            $counter.text(length + ' / ' + maxLength);

            $counter.removeClass('counter-ok counter-warning counter-danger');
            if (length === 0) {
                $counter.addClass('counter-ok');
            } else if (length <= warnLength) {
                $counter.addClass('counter-ok');
            } else if (length <= maxLength) {
                $counter.addClass('counter-warning');
            } else {
                $counter.addClass('counter-danger');
            }
        }

        // Title counter
        $('#requestdesk_seo_title').on('input', function() {
            updateCounter(this, 'title-counter', 60, 50);
            updateSerpPreview();
        }).trigger('input');

        // Description counter
        $('#requestdesk_seo_description').on('input', function() {
            updateCounter(this, 'description-counter', 160, 120);
            updateSerpPreview();
        }).trigger('input');

        // SERP Preview update
        function updateSerpPreview() {
            var title = $('#requestdesk_seo_title').val() || '<?php echo esc_js($post->post_title); ?>';
            var description = $('#requestdesk_seo_description').val() || '<?php echo esc_js(wp_trim_words(strip_tags($post->post_content), 25)); ?>';

            $('#serp-preview-title').text(title.substring(0, 60));
            $('#serp-preview-description').text(description.substring(0, 160));
        }
    });
    </script>
    <?php
}

/**
 * Save SEO meta box data
 */
function requestdesk_seo_save_meta_box_data($post_id) {
    // Check nonce
    if (!isset($_POST['requestdesk_seo_meta_box_nonce']) ||
        !wp_verify_nonce($_POST['requestdesk_seo_meta_box_nonce'], 'requestdesk_seo_meta_box')) {
        return;
    }

    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save SEO title
    if (isset($_POST['requestdesk_seo_title'])) {
        update_post_meta($post_id, '_requestdesk_seo_title', sanitize_text_field($_POST['requestdesk_seo_title']));
    }

    // Save SEO description
    if (isset($_POST['requestdesk_seo_description'])) {
        update_post_meta($post_id, '_requestdesk_seo_description', sanitize_textarea_field($_POST['requestdesk_seo_description']));
    }

    // Save focus keyphrase
    if (isset($_POST['requestdesk_focus_keyphrase'])) {
        update_post_meta($post_id, '_requestdesk_focus_keyphrase', sanitize_text_field($_POST['requestdesk_focus_keyphrase']));
    }

    // Save canonical URL
    if (isset($_POST['requestdesk_canonical_url'])) {
        $canonical = esc_url_raw($_POST['requestdesk_canonical_url']);
        if (!empty($canonical)) {
            update_post_meta($post_id, '_requestdesk_canonical_url', $canonical);
        } else {
            delete_post_meta($post_id, '_requestdesk_canonical_url');
        }
    }

    // Save noindex
    if (isset($_POST['requestdesk_noindex'])) {
        update_post_meta($post_id, '_requestdesk_noindex', '1');
    } else {
        delete_post_meta($post_id, '_requestdesk_noindex');
    }

    // Save nofollow
    if (isset($_POST['requestdesk_nofollow'])) {
        update_post_meta($post_id, '_requestdesk_nofollow', '1');
    } else {
        delete_post_meta($post_id, '_requestdesk_nofollow');
    }
}
add_action('save_post', 'requestdesk_seo_save_meta_box_data', 10, 1);

/**
 * Enqueue admin scripts for SEO meta box
 */
function requestdesk_seo_enqueue_admin_scripts($hook) {
    if (!in_array($hook, array('post.php', 'post-new.php'))) {
        return;
    }

    // jQuery is already loaded by WordPress
    wp_enqueue_script('jquery');
}
add_action('admin_enqueue_scripts', 'requestdesk_seo_enqueue_admin_scripts');
