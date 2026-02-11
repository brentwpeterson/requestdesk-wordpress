<?php
/**
 * Stats Bar Settings Page
 *
 * Admin tab for configuring the stats bar section.
 * Renders inside the RequestDesk Settings tabbed page.
 *
 * @package RequestDesk
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function requestdesk_stats_bar_settings_page() {
    // Save settings if form submitted
    if (isset($_POST['requestdesk_save_stats_bar_settings']) && wp_verify_nonce($_POST['requestdesk_stats_bar_nonce'], 'requestdesk_stats_bar_settings')) {

        // Build stats array from repeatable blocks
        $stats = array();
        if (!empty($_POST['stats_items']) && is_array($_POST['stats_items'])) {
            foreach ($_POST['stats_items'] as $item) {
                $value = sanitize_text_field($item['value'] ?? '');
                $label = sanitize_text_field($item['label'] ?? '');
                if ($value === '' && $label === '') {
                    continue;
                }
                $stats[] = array(
                    'value' => $value,
                    'label' => $label,
                    'icon'  => sanitize_text_field($item['icon'] ?? ''),
                );
            }
        }

        $settings = array(
            'stats'       => $stats,
            'bg_color'    => sanitize_hex_color($_POST['stats_bg_color'] ?? '#000000'),
            'value_color' => sanitize_hex_color($_POST['stats_value_color'] ?? '#2B4C8C'),
            'label_color' => sanitize_hex_color($_POST['stats_label_color'] ?? '#ffffff'),
            'max_width'   => intval($_POST['stats_max_width'] ?? 1200),
            'columns'     => intval($_POST['stats_columns'] ?? 3),
        );

        update_option('requestdesk_stats_bar_settings', $settings);
        echo '<div class="notice notice-success"><p>Stats Bar settings saved.</p></div>';
    }

    // Load current settings with defaults
    $settings = get_option('requestdesk_stats_bar_settings', array());
    $defaults = array(
        'stats' => array(
            array('value' => '60,000 +', 'label' => 'Projects Delivered', 'icon' => ''),
            array('value' => '55 Million +', 'label' => 'Words Written', 'icon' => ''),
            array('value' => '4.9/5', 'label' => 'Average Project Rating', 'icon' => ''),
        ),
        'bg_color'    => '#000000',
        'value_color' => '#FF8C00',
        'label_color' => '#ffffff',
        'max_width'   => 1200,
        'columns'     => 3,
    );
    $settings = wp_parse_args($settings, $defaults);
    ?>

    <form method="post" action="">
        <?php wp_nonce_field('requestdesk_stats_bar_settings', 'requestdesk_stats_bar_nonce'); ?>

        <div class="card" id="rd-stats-items-card">
            <h2>Stats Items</h2>
            <p class="description" style="margin-bottom: 15px;">Each item shows a value and label. Add an optional icon (emoji or symbol) to display above the value.</p>

            <div id="rd-stats-items-container">
                <?php
                $stats = $settings['stats'];
                if (empty($stats)) {
                    $stats = array(array('value' => '', 'label' => '', 'icon' => ''));
                }
                foreach ($stats as $idx => $stat) :
                ?>
                <div class="rd-stats-item-block" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <strong>Stat <?php echo $idx + 1; ?></strong>
                        <button type="button" class="button rd-stats-remove-item" style="color: #a00;">Remove</button>
                    </div>
                    <table class="form-table" style="margin: 0;">
                        <tr>
                            <th scope="row" style="width: 80px; padding: 8px 10px 8px 0;">Value</th>
                            <td style="padding: 8px 0;">
                                <input type="text" name="stats_items[<?php echo $idx; ?>][value]" value="<?php echo esc_attr($stat['value']); ?>" class="regular-text" placeholder="60,000 +">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" style="width: 80px; padding: 8px 10px 8px 0;">Label</th>
                            <td style="padding: 8px 0;">
                                <input type="text" name="stats_items[<?php echo $idx; ?>][label]" value="<?php echo esc_attr($stat['label']); ?>" class="regular-text" placeholder="Projects Delivered">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" style="width: 80px; padding: 8px 10px 8px 0;">Icon</th>
                            <td style="padding: 8px 0;">
                                <input type="text" name="stats_items[<?php echo $idx; ?>][icon]" value="<?php echo esc_attr($stat['icon']); ?>" class="small-text" placeholder="&#9733;">
                                <p class="description">Optional. Emoji or symbol displayed above the value.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="button" id="rd-stats-add-item">+ Add Stat</button>
        </div>

        <div class="card">
            <h2>Style</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Background Color</th>
                    <td>
                        <input type="color" name="stats_bg_color" value="<?php echo esc_attr($settings['bg_color']); ?>">
                        <code><?php echo esc_html($settings['bg_color']); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Value Color</th>
                    <td>
                        <input type="color" name="stats_value_color" value="<?php echo esc_attr($settings['value_color']); ?>">
                        <code><?php echo esc_html($settings['value_color']); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Label Color</th>
                    <td>
                        <input type="color" name="stats_label_color" value="<?php echo esc_attr($settings['label_color']); ?>">
                        <code><?php echo esc_html($settings['label_color']); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Max Width (px)</th>
                    <td>
                        <input type="number" name="stats_max_width" value="<?php echo esc_attr($settings['max_width']); ?>" class="small-text" min="800" max="1600">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Columns (Desktop)</th>
                    <td>
                        <input type="number" name="stats_columns" value="<?php echo esc_attr($settings['columns']); ?>" class="small-text" min="1" max="6">
                        <p class="description">Number of columns on desktop. Cards stack to 1 column on mobile.</p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <input type="submit" name="requestdesk_save_stats_bar_settings" class="button-primary" value="Save Stats Bar Settings">
        </p>
    </form>

    <div class="card">
        <h2>Shortcode</h2>
        <table class="form-table">
            <tr>
                <th>Stats Bar</th>
                <td><code>[requestdesk_stats_bar]</code>
                    <p class="description">Renders the full-width stats section with all configured stat cards.</p>
                </td>
            </tr>
            <tr>
                <th>With Overrides</th>
                <td><code>[requestdesk_stats_bar bg_color="#111111" columns="2" max_width="1000"]</code>
                    <p class="description">Shortcode attributes override admin settings. Available: <code>bg_color</code>, <code>text_color</code>, <code>columns</code>, <code>max_width</code>.</p>
                </td>
            </tr>
        </table>
    </div>

    <script>
    (function() {
        'use strict';

        var container = document.getElementById('rd-stats-items-container');
        var addBtn = document.getElementById('rd-stats-add-item');

        function getItemCount() {
            return container.querySelectorAll('.rd-stats-item-block').length;
        }

        function renumberItems() {
            var blocks = container.querySelectorAll('.rd-stats-item-block');
            blocks.forEach(function(block, i) {
                block.querySelector('strong').textContent = 'Stat ' + (i + 1);
                var inputs = block.querySelectorAll('input[type="text"], input.small-text');
                inputs.forEach(function(input) {
                    input.name = input.name.replace(/stats_items\[\d+\]/, 'stats_items[' + i + ']');
                });
            });
        }

        addBtn.addEventListener('click', function() {
            var idx = getItemCount();
            var block = document.createElement('div');
            block.className = 'rd-stats-item-block';
            block.style.cssText = 'background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;';
            block.innerHTML =
                '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">' +
                    '<strong>Stat ' + (idx + 1) + '</strong>' +
                    '<button type="button" class="button rd-stats-remove-item" style="color: #a00;">Remove</button>' +
                '</div>' +
                '<table class="form-table" style="margin: 0;">' +
                    '<tr>' +
                        '<th scope="row" style="width: 80px; padding: 8px 10px 8px 0;">Value</th>' +
                        '<td style="padding: 8px 0;"><input type="text" name="stats_items[' + idx + '][value]" value="" class="regular-text" placeholder="60,000 +"></td>' +
                    '</tr>' +
                    '<tr>' +
                        '<th scope="row" style="width: 80px; padding: 8px 10px 8px 0;">Label</th>' +
                        '<td style="padding: 8px 0;"><input type="text" name="stats_items[' + idx + '][label]" value="" class="regular-text" placeholder="Projects Delivered"></td>' +
                    '</tr>' +
                    '<tr>' +
                        '<th scope="row" style="width: 80px; padding: 8px 10px 8px 0;">Icon</th>' +
                        '<td style="padding: 8px 0;"><input type="text" name="stats_items[' + idx + '][icon]" value="" class="small-text" placeholder="&#9733;"><p class="description">Optional. Emoji or symbol displayed above the value.</p></td>' +
                    '</tr>' +
                '</table>';
            container.appendChild(block);
        });

        container.addEventListener('click', function(e) {
            if (e.target.classList.contains('rd-stats-remove-item')) {
                if (getItemCount() <= 1) {
                    alert('You need at least one stat.');
                    return;
                }
                e.target.closest('.rd-stats-item-block').remove();
                renumberItems();
            }
        });
    })();
    </script>
    <?php
}
