<?php
/**
 * Runs when the RequestDesk Connector is deleted from wp-admin.
 *
 * Deleting the plugin used to leave three custom tables, nineteen options
 * (four of them API keys) and four scheduled events behind on the site. A
 * client has to be able to remove this plugin and have it gone.
 *
 * What this does NOT delete: the content. Posts, pages, cc_audit_request /
 * rd_video / rd_event entries and their post meta stay, because they are the
 * site's own content and a delete-then-reinstall (the usual way to repair a
 * plugin) must not empty them. Removing the settings is enough for the plugin
 * to be gone; removing a site's posts would be a different, unasked-for act.
 *
 * @package RequestDesk
 * @since 2.48.0
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// 1. Scheduled events. Left running, these fire daily against nothing.
foreach (array(
    'requestdesk_sync_headless_counts',
    'requestdesk_freshness_monitor',
    'requestdesk_citation_monitor',
    'requestdesk_process_aeo_optimization',
) as $requestdesk_hook) {
    wp_clear_scheduled_hook($requestdesk_hook);
}

// 2. Options. The first four hold API keys, so they are the ones that matter
//    most: a deleted plugin must not leave credentials in the database.
foreach (array(
    'requestdesk_settings',
    'requestdesk_headless_settings',
    'requestdesk_seo_settings',
    'requestdesk_aeo_settings',
    'requestdesk_headless_api_count',
    'requestdesk_homepage_hero_settings',
    'requestdesk_stats_bar_settings',
    'requestdesk_comparison_table_settings',
    'requestdesk_local_business_settings',
    'requestdesk_activation_complete',
    'requestdesk_event_rewrite_version',
    'requestdesk_freshness_alerts',
    'requestdesk_indexnow_key',
    'requestdesk_indexnow_enabled',
    'requestdesk_indexnow_post_types',
    'requestdesk_indexnow_log',
    'requestdesk_audit_capture_settings',
    'requestdesk_qr_redirect_map',
    'cc_case_study_data_version',
) as $requestdesk_option) {
    delete_option($requestdesk_option);
}

// 3. The plugin's own tables. Nothing else reads them.
foreach (array('requestdesk_sync_log', 'requestdesk_aeo_data', 'requestdesk_push_log') as $requestdesk_table) {
    $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . $requestdesk_table . '`');
}

// 4. Bookkeeping meta the plugin wrote about posts. The SEO and schema values
//    an editor typed are left alone, since those are the site's own content.
foreach (array(
    '_requestdesk_last_push',
    '_requestdesk_push_status',
    '_requestdesk_aeo_score',
    '_requestdesk_aeo_analyzed',
    '_requestdesk_freshness_score',
    '_requestdesk_freshness_status',
    '_requestdesk_freshness_updated',
    '_requestdesk_citation_stats',
    '_requestdesk_citation_updated',
    '_requestdesk_schema_generated',
) as $requestdesk_meta_key) {
    delete_post_meta_by_key($requestdesk_meta_key);
}
