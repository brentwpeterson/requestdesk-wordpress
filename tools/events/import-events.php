<?php
/**
 * Seed the rd_event library from a JSON file. CLI only.
 *
 * Idempotent: an event whose slug already exists is updated in place, never
 * duplicated, so re-running after an edit to the seed is safe.
 *
 * Writes meta through RequestDesk_Event::save_values(), so seeded events obey
 * the same sanitizing rules as the wp-admin editor. Needs RequestDesk Connector
 * 2.45.0+ active on the target site; it stops with an error if not.
 *
 * Usage:
 *   php import-events.php <wordpress-root> <seed.json> [--dry-run]
 *
 * Events live in Content Cucumber's WordPress only. Talk Commerce reads them
 * from there and must never be seeded. Run this against Content Cucumber:
 *   php import-events.php <wordpress-root> events-seed.json [--dry-run]
 * Day-to-day changes go through the RequestDesk MCP, not this script.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$args    = array_values(array_filter(array_slice($argv, 1), function ($a) { return $a !== '--dry-run'; }));
$dry_run = in_array('--dry-run', $argv, true);

if (count($args) < 2) {
    fwrite(STDERR, "Usage: php import-events.php <wordpress-root> <seed.json> [--dry-run]\n");
    exit(2);
}

list($wp_root, $seed_path) = $args;

$seed = json_decode((string) @file_get_contents($seed_path), true);
if (!is_array($seed) || !isset($seed['events']) || !is_array($seed['events'])) {
    fwrite(STDERR, "Could not read an events array from $seed_path\n");
    exit(1);
}

if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}
define('WP_USE_THEMES', false);
require rtrim($wp_root, '/') . '/wp-load.php';

if (!class_exists('RequestDesk_Event') || !post_type_exists(RequestDesk_Event::POST_TYPE)) {
    fwrite(STDERR, "rd_event is not registered on " . get_bloginfo('name') . ". Activate RequestDesk Connector 2.45.0+ first.\n");
    exit(1);
}

// A CLI run has no logged-in user, so kses would strip the iframes in event
// bodies (the LinkedIn and YouTube embeds). The seed is our own reviewed copy.
kses_remove_filters();

echo ($dry_run ? '[dry run] ' : '') . 'Target: ' . get_bloginfo('name') . ' (' . home_url() . ")\n";

$failures = 0;
foreach ($seed['events'] as $event) {
    $slug = isset($event['slug']) ? sanitize_title($event['slug']) : '';
    if ($slug === '' || empty($event['title']) || empty($event['meta']['start_date']) || empty($event['meta']['city'])) {
        fwrite(STDERR, "SKIP  an entry is missing slug, title, start_date or city\n");
        $failures++;
        continue;
    }

    // Statuses listed explicitly, not 'any': with no logged-in user, WP_Query
    // drops a draft from a by-name lookup unless 'draft' was asked for by name,
    // and a re-run would then create a duplicate instead of updating.
    $existing = get_posts(array(
        'post_type'   => RequestDesk_Event::POST_TYPE,
        'name'        => $slug,
        'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
        'numberposts' => 1,
    ));

    $postarr = array(
        'post_type'    => RequestDesk_Event::POST_TYPE,
        'post_title'   => $event['title'],
        'post_name'    => $slug,
        'post_content' => isset($event['content']) ? $event['content'] : '',
        'post_status'  => 'publish',
    );
    $action = $existing ? 'update' : 'create';

    if ($dry_run) {
        echo "$action  $slug\n";
        continue;
    }

    if ($existing) {
        $postarr['ID'] = $existing[0]->ID;
        $post_id = wp_update_post(wp_slash($postarr), true);
    } else {
        $post_id = wp_insert_post(wp_slash($postarr), true);
    }

    if (is_wp_error($post_id)) {
        fwrite(STDERR, "FAIL  $slug: " . $post_id->get_error_message() . "\n");
        $failures++;
        continue;
    }

    RequestDesk_Event::save_values($post_id, isset($event['meta']) ? $event['meta'] : array());

    // Read back through the API formatter, so "done" means the event the site
    // will see, not just a row that was written.
    $check = RequestDesk_Event::format_for_api(get_post($post_id), false);
    if ($check === null) {
        fwrite(STDERR, "FAIL  $slug (#$post_id) saved but the API formatter rejects it\n");
        $failures++;
        continue;
    }
    echo "{$action}d  $slug  #$post_id  {$check['startDate']} to {$check['endDate']}  " . ($check['upcoming'] ? 'upcoming' : 'past') . ($check['hero'] ? '  homepage' : '') . "\n";
}

exit($failures > 0 ? 1 : 0);
