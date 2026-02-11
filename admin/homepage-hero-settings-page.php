<?php
/**
 * Homepage Hero Settings Page
 *
 * Admin tab for configuring the homepage hero section and rotating terminal text.
 * Renders inside the RequestDesk Settings tabbed page.
 *
 * @package RequestDesk
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function requestdesk_homepage_hero_settings_page() {
    // Save settings if form submitted
    if (isset($_POST['requestdesk_save_hero_settings']) && wp_verify_nonce($_POST['requestdesk_hero_nonce'], 'requestdesk_hero_settings')) {

        // Build terminal sequences from repeatable blocks
        $terminal_sequences = array();
        if (!empty($_POST['terminal_sequences']) && is_array($_POST['terminal_sequences'])) {
            foreach ($_POST['terminal_sequences'] as $seq) {
                if (empty($seq['lines'])) {
                    continue;
                }
                $lines = array();
                $raw_lines = explode("\n", str_replace("\r\n", "\n", $seq['lines']));
                $delay = isset($seq['delay']) ? intval($seq['delay']) : 500;
                foreach ($raw_lines as $i => $line) {
                    $line = sanitize_text_field($line);
                    if ($line === '') {
                        continue;
                    }
                    $lines[] = array(
                        'text'  => ($i === 0 ? '' : "\n") . $line,
                        'delay' => $delay,
                    );
                }
                if (!empty($lines)) {
                    $terminal_sequences[] = $lines;
                }
            }
        }

        $settings = array(
            'headline'              => wp_kses_post($_POST['hero_headline'] ?? ''),
            'form_heading'          => sanitize_text_field($_POST['hero_form_heading'] ?? ''),
            'seo_text'              => sanitize_textarea_field($_POST['hero_seo_text'] ?? ''),
            'hubspot_portal_id'     => sanitize_text_field($_POST['hubspot_portal_id'] ?? ''),
            'hubspot_form_id'       => sanitize_text_field($_POST['hubspot_form_id'] ?? ''),
            'hubspot_region'        => sanitize_text_field($_POST['hubspot_region'] ?? 'na1'),
            'terminal_enabled'      => isset($_POST['terminal_enabled']),
            'terminal_sequences'    => $terminal_sequences,
            'terminal_type_speed_min' => intval($_POST['terminal_type_speed_min'] ?? 45),
            'terminal_type_speed_max' => intval($_POST['terminal_type_speed_max'] ?? 95),
            'hero_bg_color'         => sanitize_hex_color($_POST['hero_bg_color'] ?? '#000000'),
            'headline_color'        => sanitize_hex_color($_POST['headline_color'] ?? '#58c558'),
            'max_width'             => intval($_POST['hero_max_width'] ?? 1200),
        );

        update_option('requestdesk_homepage_hero_settings', $settings);
        echo '<div class="notice notice-success"><p>Homepage Hero settings saved.</p></div>';
    }

    // Load current settings with defaults
    $settings = get_option('requestdesk_homepage_hero_settings', array());
    $defaults = array(
        'headline'              => 'Humans<br>Writing<br>Content',
        'form_heading'          => "Let's write your success story!",
        'seo_text'              => 'Humans in the loop. We believe AI should enhance human creativity, not replace it. Our approach: AI-powered content creation with human editors reviewing every piece. Executing with precision. Complete brand consistency across all platforms.',
        'hubspot_portal_id'     => '39487190',
        'hubspot_form_id'       => '3c945309-67c6-4812-ab65-c7280682e005',
        'hubspot_region'        => 'na1',
        'terminal_enabled'      => true,
        'terminal_sequences'    => array(
            array(
                array('text' => 'Write more, prompt less.', 'delay' => 500),
                array('text' => "\nNo AI slop.", 'delay' => 500),
                array('text' => "\nHumans in the loop. Always.", 'delay' => 1200),
            ),
            array(
                array('text' => '> content_engine.start()', 'delay' => 500),
                array('text' => "\nLoading brand voice...", 'delay' => 700),
                array('text' => "\nHuman writers standing by.", 'delay' => 900),
                array('text' => "\nReady.", 'delay' => 1200),
            ),
            array(
                array('text' => 'Blog posts. Landing pages.', 'delay' => 500),
                array('text' => "\nSocial. Email. SEO.", 'delay' => 500),
                array('text' => "\nAll written by real humans.", 'delay' => 1200),
            ),
        ),
        'terminal_type_speed_min' => 45,
        'terminal_type_speed_max' => 95,
        'hero_bg_color'         => '#000000',
        'headline_color'        => '#58c558',
        'max_width'             => 1200,
    );
    $settings = wp_parse_args($settings, $defaults);
    ?>

    <form method="post" action="">
        <?php wp_nonce_field('requestdesk_hero_settings', 'requestdesk_hero_nonce'); ?>

        <div class="card">
            <h2>Hero Content</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Headline</th>
                    <td>
                        <input type="text" name="hero_headline" value="<?php echo esc_attr($settings['headline']); ?>" class="large-text" placeholder="Humans&lt;br&gt;Writing&lt;br&gt;Content">
                        <p class="description">Use <code>&lt;br&gt;</code> for line breaks. HTML tags like <code>&lt;span&gt;</code> are allowed.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Form Heading</th>
                    <td>
                        <input type="text" name="hero_form_heading" value="<?php echo esc_attr($settings['form_heading']); ?>" class="large-text" placeholder="Let's write your success story!">
                    </td>
                </tr>
                <tr>
                    <th scope="row">SEO Text</th>
                    <td>
                        <textarea name="hero_seo_text" rows="3" class="large-text" placeholder="Hidden text for search engine crawlers"><?php echo esc_textarea($settings['seo_text']); ?></textarea>
                        <p class="description">Visually hidden but accessible to search engine crawlers. Describes the terminal animation content.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="card">
            <h2>HubSpot Form</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Portal ID</th>
                    <td>
                        <input type="text" name="hubspot_portal_id" value="<?php echo esc_attr($settings['hubspot_portal_id']); ?>" class="regular-text" placeholder="39487190">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Form ID</th>
                    <td>
                        <input type="text" name="hubspot_form_id" value="<?php echo esc_attr($settings['hubspot_form_id']); ?>" class="regular-text" placeholder="3c945309-67c6-4812-ab65-c7280682e005">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Region</th>
                    <td>
                        <select name="hubspot_region">
                            <option value="na1" <?php selected($settings['hubspot_region'], 'na1'); ?>>North America (na1)</option>
                            <option value="eu1" <?php selected($settings['hubspot_region'], 'eu1'); ?>>Europe (eu1)</option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <div class="card" id="rd-hero-sequences-card">
            <h2>Rotating Text Sequences</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Terminal Animation</th>
                    <td>
                        <label>
                            <input type="checkbox" name="terminal_enabled" value="1" <?php checked($settings['terminal_enabled'], true); ?>>
                            Enable terminal typing animation
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Type Speed (ms)</th>
                    <td>
                        <label>Min: <input type="number" name="terminal_type_speed_min" value="<?php echo esc_attr($settings['terminal_type_speed_min']); ?>" class="small-text" min="10" max="200"></label>
                        <label style="margin-left: 15px;">Max: <input type="number" name="terminal_type_speed_max" value="<?php echo esc_attr($settings['terminal_type_speed_max']); ?>" class="small-text" min="20" max="300"></label>
                        <p class="description">Random delay between each character typed. Lower = faster.</p>
                    </td>
                </tr>
            </table>

            <h3>Sequences</h3>
            <p class="description" style="margin-bottom: 15px;">Each sequence is a block of lines typed one after another. Multiple sequences rotate on each cycle. Enter one line per row in the textarea.</p>

            <div id="rd-hero-sequences-container">
                <?php
                $sequences = $settings['terminal_sequences'];
                if (empty($sequences)) {
                    $sequences = array(array()); // Start with one empty block
                }
                foreach ($sequences as $idx => $seq) :
                    // Convert sequence array back to plain text lines
                    $lines_text = '';
                    if (!empty($seq)) {
                        $text_lines = array();
                        foreach ($seq as $line) {
                            $text = isset($line['text']) ? $line['text'] : '';
                            // Strip leading \n for display
                            $text = ltrim($text, "\n");
                            $text_lines[] = $text;
                        }
                        $lines_text = implode("\n", $text_lines);
                    }
                    $delay = (!empty($seq) && isset($seq[0]['delay'])) ? $seq[0]['delay'] : 500;
                ?>
                <div class="rd-hero-sequence-block" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <strong>Sequence <?php echo $idx + 1; ?></strong>
                        <button type="button" class="button rd-hero-remove-sequence" style="color: #a00;">Remove</button>
                    </div>
                    <label style="display: block; margin-bottom: 5px;">Lines (one per row):</label>
                    <textarea name="terminal_sequences[<?php echo $idx; ?>][lines]" rows="4" class="large-text" placeholder="Write more, prompt less.&#10;No AI slop.&#10;Humans in the loop. Always."><?php echo esc_textarea($lines_text); ?></textarea>
                    <label style="display: block; margin-top: 10px;">
                        Delay after each line (ms):
                        <input type="number" name="terminal_sequences[<?php echo $idx; ?>][delay]" value="<?php echo esc_attr($delay); ?>" class="small-text" min="100" max="5000">
                    </label>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="button" id="rd-hero-add-sequence">+ Add Sequence</button>
        </div>

        <div class="card">
            <h2>Style</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Background Color</th>
                    <td>
                        <input type="color" name="hero_bg_color" value="<?php echo esc_attr($settings['hero_bg_color']); ?>">
                        <code><?php echo esc_html($settings['hero_bg_color']); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Headline Color</th>
                    <td>
                        <input type="color" name="headline_color" value="<?php echo esc_attr($settings['headline_color']); ?>">
                        <code><?php echo esc_html($settings['headline_color']); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Max Width (px)</th>
                    <td>
                        <input type="number" name="hero_max_width" value="<?php echo esc_attr($settings['max_width']); ?>" class="small-text" min="800" max="1600">
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <input type="submit" name="requestdesk_save_hero_settings" class="button-primary" value="Save Hero Settings">
        </p>
    </form>

    <div class="card">
        <h2>Shortcodes</h2>
        <table class="form-table">
            <tr>
                <th>Full Hero Section</th>
                <td><code>[requestdesk_homepage_hero]</code>
                    <p class="description">Renders the complete two-column hero with headline, terminal animation, and HubSpot form.</p>
                </td>
            </tr>
            <tr>
                <th>Terminal Animation Only</th>
                <td><code>[requestdesk_rotating_text]</code>
                    <p class="description">Renders just the terminal typing animation. Can be embedded anywhere.</p>
                </td>
            </tr>
        </table>
    </div>

    <script>
    (function() {
        'use strict';

        var container = document.getElementById('rd-hero-sequences-container');
        var addBtn = document.getElementById('rd-hero-add-sequence');

        function getSequenceCount() {
            return container.querySelectorAll('.rd-hero-sequence-block').length;
        }

        function renumberSequences() {
            var blocks = container.querySelectorAll('.rd-hero-sequence-block');
            blocks.forEach(function(block, i) {
                block.querySelector('strong').textContent = 'Sequence ' + (i + 1);
                var textarea = block.querySelector('textarea');
                textarea.name = 'terminal_sequences[' + i + '][lines]';
                var delayInput = block.querySelector('input[type="number"]');
                delayInput.name = 'terminal_sequences[' + i + '][delay]';
            });
        }

        addBtn.addEventListener('click', function() {
            var idx = getSequenceCount();
            var block = document.createElement('div');
            block.className = 'rd-hero-sequence-block';
            block.style.cssText = 'background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;';
            block.innerHTML =
                '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">' +
                    '<strong>Sequence ' + (idx + 1) + '</strong>' +
                    '<button type="button" class="button rd-hero-remove-sequence" style="color: #a00;">Remove</button>' +
                '</div>' +
                '<label style="display: block; margin-bottom: 5px;">Lines (one per row):</label>' +
                '<textarea name="terminal_sequences[' + idx + '][lines]" rows="4" class="large-text" placeholder="Write more, prompt less.&#10;No AI slop.&#10;Humans in the loop. Always."></textarea>' +
                '<label style="display: block; margin-top: 10px;">' +
                    'Delay after each line (ms): ' +
                    '<input type="number" name="terminal_sequences[' + idx + '][delay]" value="500" class="small-text" min="100" max="5000">' +
                '</label>';
            container.appendChild(block);
        });

        container.addEventListener('click', function(e) {
            if (e.target.classList.contains('rd-hero-remove-sequence')) {
                if (getSequenceCount() <= 1) {
                    alert('You need at least one sequence.');
                    return;
                }
                e.target.closest('.rd-hero-sequence-block').remove();
                renumberSequences();
            }
        });
    })();
    </script>
    <?php
}
