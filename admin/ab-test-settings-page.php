<?php
/**
 * A/B Test Settings Tab
 *
 * Renders inside the RequestDesk Settings tabbed page. Saves the one running
 * test and shows its numbers. See includes/class-requestdesk-ab-test.php.
 *
 * @package RequestDesk
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A selector goes into an inline <style> and into querySelectorAll(), so it
 * must not be able to close the rule or the tag. '' means it is unusable.
 */
function requestdesk_ab_test_clean_selector($selector) {
    $selector = trim(wp_unslash($selector));
    if ($selector === '' || preg_match('/[{}<;]/', $selector) || strlen($selector) > 200) {
        return '';
    }
    return $selector;
}

function requestdesk_ab_test_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $errors = array();

    if (isset($_POST['requestdesk_save_ab_test']) && wp_verify_nonce($_POST['requestdesk_ab_test_nonce'] ?? '', 'requestdesk_ab_test_settings')) {
        $changes = array();
        if (!empty($_POST['ab_changes']) && is_array($_POST['ab_changes'])) {
            foreach (array_slice($_POST['ab_changes'], 0, RequestDesk_AB_Test::MAX_CHANGES) as $i => $row) {
                $raw_selector = isset($row['selector']) ? $row['selector'] : '';
                $html         = isset($row['html']) ? wp_kses_post(wp_unslash($row['html'])) : '';
                if (trim($raw_selector) === '' && trim($html) === '') {
                    continue;
                }
                $selector = requestdesk_ab_test_clean_selector($raw_selector);
                if ($selector === '') {
                    $errors[] = 'Change ' . ($i + 1) . ': the selector is empty, too long, or contains { } < or ; and was not saved.';
                    continue;
                }
                $changes[] = array('selector' => $selector, 'html' => $html);
            }
        }

        $goal_raw      = isset($_POST['ab_goal_selector']) ? $_POST['ab_goal_selector'] : '';
        $goal_selector = requestdesk_ab_test_clean_selector($goal_raw);
        if (trim(wp_unslash($goal_raw)) !== '' && $goal_selector === '') {
            $errors[] = 'The goal selector contains { } < or ; and was not saved.';
        }

        $split = isset($_POST['ab_split_b']) ? intval($_POST['ab_split_b']) : 50;

        update_option(RequestDesk_AB_Test::OPTION_SETTINGS, array(
            'enabled'       => isset($_POST['ab_enabled']),
            'test_id'       => sanitize_key($_POST['ab_test_id'] ?? ''),
            'split_b'       => max(1, min(99, $split)),
            'changes'       => $changes,
            'goal_selector' => $goal_selector,
            'goal_hubspot'  => isset($_POST['ab_goal_hubspot']),
        ));

        echo '<div class="notice notice-success"><p>A/B test settings saved. If the site sits behind a page cache, purge the front page so visitors get the new settings.</p></div>';
    }

    if (isset($_POST['requestdesk_reset_ab_counts']) && wp_verify_nonce($_POST['requestdesk_ab_reset_nonce'] ?? '', 'requestdesk_ab_test_reset')) {
        RequestDesk_AB_Test::reset_counts(sanitize_key($_POST['ab_reset_test_id'] ?? ''));
        echo '<div class="notice notice-success"><p>Counts reset.</p></div>';
    }

    foreach ($errors as $error) {
        echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
    }

    $settings = RequestDesk_AB_Test::get_settings();
    $problem  = RequestDesk_AB_Test::config_error($settings);
    if ($problem !== '') {
        echo '<div class="notice notice-error"><p><strong>The A/B test is switched on but not running.</strong> ' . esc_html($problem) . ' Every visitor sees the page as it is.</p></div>';
    }

    $rows = $settings['changes'];
    while (count($rows) < RequestDesk_AB_Test::MAX_CHANGES) {
        $rows[] = array('selector' => '', 'html' => '');
    }
    $home = home_url('/');
    ?>

    <form method="post" action="">
        <?php wp_nonce_field('requestdesk_ab_test_settings', 'requestdesk_ab_test_nonce'); ?>

        <div class="card" style="max-width: 900px;">
            <h2>Front Page A/B Test</h2>
            <p>Variant A is the front page as it is now. Variant B replaces the content of up to three elements, each found by a CSS selector. Visitors are split in the browser and keep their variant for 90 days.</p>
            <table class="form-table">
                <tr>
                    <th scope="row">Running</th>
                    <td>
                        <label>
                            <input type="checkbox" name="ab_enabled" value="1" <?php checked(!empty($settings['enabled'])); ?>>
                            Run this test on the front page
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Test name</th>
                    <td>
                        <input type="text" name="ab_test_id" value="<?php echo esc_attr($settings['test_id']); ?>" class="regular-text" placeholder="hero-headline-oct">
                        <p class="description">Lowercase letters, numbers, dashes. A new name starts a new test: visitors are split again and counting starts from zero.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Share sent to B (%)</th>
                    <td>
                        <input type="number" name="ab_split_b" value="<?php echo esc_attr($settings['split_b']); ?>" class="small-text" min="1" max="99">
                    </td>
                </tr>
            </table>

            <h3>Variant B changes</h3>
            <?php foreach ($rows as $i => $row) : ?>
            <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 12px 15px; margin-bottom: 12px; border-radius: 4px;">
                <strong>Change <?php echo $i + 1; ?></strong>
                <p>
                    <label>CSS selector<br>
                        <input type="text" name="ab_changes[<?php echo $i; ?>][selector]" value="<?php echo esc_attr($row['selector']); ?>" class="large-text" placeholder=".cc-hero-bold__headline">
                    </label>
                </p>
                <p>
                    <label>Variant B content (HTML allowed, e.g. <code>&lt;br&gt;</code>)<br>
                        <textarea name="ab_changes[<?php echo $i; ?>][html]" rows="2" class="large-text"><?php echo esc_textarea($row['html']); ?></textarea>
                    </label>
                </p>
            </div>
            <?php endforeach; ?>

            <h3>Goal</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Click on</th>
                    <td>
                        <input type="text" name="ab_goal_selector" value="<?php echo esc_attr($settings['goal_selector']); ?>" class="large-text" placeholder=".cc-hero-bold__cta--primary">
                        <p class="description">A click on anything matching this selector counts as a goal, once per visitor.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">HubSpot form</th>
                    <td>
                        <label>
                            <input type="checkbox" name="ab_goal_hubspot" value="1" <?php checked(!empty($settings['goal_hubspot'])); ?>>
                            Also count a HubSpot form submission on the front page as a goal
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="requestdesk_save_ab_test" class="button-primary" value="Save A/B Test">
            </p>
        </div>
    </form>

    <?php if ($settings['test_id'] !== '') :
        $counts = RequestDesk_AB_Test::get_counts($settings['test_id']);
        ?>
    <div class="card" style="max-width: 900px;">
        <h2>Results: <?php echo esc_html($settings['test_id']); ?></h2>
        <table class="widefat striped" style="max-width: 600px;">
            <thead>
                <tr><th>Variant</th><th>Views</th><th>Goals</th><th>Rate</th></tr>
            </thead>
            <tbody>
            <?php foreach (array('a' => 'A (current page)', 'b' => 'B') as $key => $label) :
                $views = intval($counts[$key]['views']);
                $goals = intval($counts[$key]['goals']);
                $rate  = $views > 0 ? number_format_i18n(100 * $goals / $views, 1) . '%' : '–';
                ?>
                <tr>
                    <td><?php echo esc_html($label); ?></td>
                    <td><?php echo esc_html(number_format_i18n($views)); ?></td>
                    <td><?php echo esc_html(number_format_i18n($goals)); ?></td>
                    <td><?php echo esc_html($rate); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">A view is counted once per browser session, a goal once per visitor. Anyone can post to the counter, so read these as a guide. Each event is also pushed to the GTM dataLayer as <code>rd_ab_view</code> / <code>rd_ab_goal</code> with <code>rd_ab_test</code> and <code>rd_ab_variant</code>.</p>
        <p>
            Preview without counting:
            <a href="<?php echo esc_url(add_query_arg('rd_ab', 'a', $home)); ?>" target="_blank">Variant A</a> ·
            <a href="<?php echo esc_url(add_query_arg('rd_ab', 'b', $home)); ?>" target="_blank">Variant B</a>
        </p>
        <form method="post" action="">
            <?php wp_nonce_field('requestdesk_ab_test_reset', 'requestdesk_ab_reset_nonce'); ?>
            <input type="hidden" name="ab_reset_test_id" value="<?php echo esc_attr($settings['test_id']); ?>">
            <input type="submit" name="requestdesk_reset_ab_counts" class="button" value="Reset counts for this test" onclick="return confirm('Reset the counts for <?php echo esc_js($settings['test_id']); ?>? This cannot be undone.');">
        </form>
    </div>
    <?php endif; ?>
    <?php
}
