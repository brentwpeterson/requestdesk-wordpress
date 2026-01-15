<?php
/**
 * RequestDesk Yoast Import Admin Page
 *
 * Provides the admin interface for importing SEO data from Yoast SEO.
 *
 * @package RequestDesk
 * @since 2.5.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the Yoast Import page
 */
function requestdesk_yoast_import_page() {
    $importer = new RequestDesk_Yoast_Importer();
    $has_yoast_data = $importer->has_yoast_data();
    $total_posts = $importer->get_total_posts();
    $import_summary = $importer->get_import_summary();
    $yoast_active = defined('WPSEO_VERSION');
    ?>
    <style>
        .requestdesk-import-wrap {
            max-width: 1200px;
        }
        .requestdesk-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 20px;
            margin-bottom: 20px;
        }
        .requestdesk-card h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .import-status-box {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        .import-status-item {
            flex: 1;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 4px;
            text-align: center;
        }
        .import-status-item .count {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
        }
        .import-status-item .label {
            color: #666;
            margin-top: 5px;
        }
        .import-summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .import-summary-table th,
        .import-summary-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .import-summary-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .import-progress {
            display: none;
            margin: 20px 0;
        }
        .import-progress-bar {
            width: 100%;
            height: 30px;
            background: #e2e4e7;
            border-radius: 4px;
            overflow: hidden;
        }
        .import-progress-fill {
            height: 100%;
            background: #0073aa;
            width: 0%;
            transition: width 0.3s ease;
        }
        .import-progress-text {
            text-align: center;
            margin-top: 10px;
            color: #666;
        }
        .import-results {
            display: none;
            margin: 20px 0;
            padding: 15px;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
        }
        .import-results.error {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        .import-options {
            margin: 20px 0;
            padding: 15px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
        }
        .import-options label {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .yoast-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
        .no-data-notice {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .no-data-notice .dashicons {
            font-size: 48px;
            width: 48px;
            height: 48px;
            color: #ccc;
        }
    </style>

    <div class="wrap requestdesk-import-wrap">
        <h1>Import from Yoast SEO</h1>
        <p>Migrate your SEO settings from Yoast SEO to RequestDesk.</p>

        <?php if ($yoast_active): ?>
        <div class="yoast-warning">
            <strong>Yoast SEO is currently active.</strong>
            You can import the data now, but we recommend deactivating Yoast after the import to avoid conflicts.
            RequestDesk SEO features are currently disabled while Yoast is active.
        </div>
        <?php endif; ?>

        <?php if (!$has_yoast_data): ?>
        <div class="requestdesk-card">
            <div class="no-data-notice">
                <span class="dashicons dashicons-info-outline"></span>
                <h3>No Yoast SEO Data Found</h3>
                <p>We couldn't find any Yoast SEO data in your database. This could mean:</p>
                <ul style="text-align: left; max-width: 400px; margin: 20px auto;">
                    <li>Yoast SEO was never installed</li>
                    <li>No posts have been optimized with Yoast</li>
                    <li>Yoast data has already been removed</li>
                </ul>
            </div>
        </div>
        <?php else: ?>

        <!-- Import Status -->
        <div class="requestdesk-card">
            <h2>Data Available for Import</h2>

            <div class="import-status-box">
                <div class="import-status-item">
                    <div class="count"><?php echo esc_html($total_posts); ?></div>
                    <div class="label">Posts with Yoast Data</div>
                </div>
                <div class="import-status-item">
                    <div class="count"><?php echo count($import_summary); ?></div>
                    <div class="label">Data Types to Import</div>
                </div>
            </div>

            <h3>Data Types Summary</h3>
            <table class="import-summary-table">
                <thead>
                    <tr>
                        <th>Data Type</th>
                        <th>Yoast Field</th>
                        <th>RequestDesk Field</th>
                        <th>Posts with Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($import_summary as $item): ?>
                    <tr>
                        <td><?php echo esc_html($item['label']); ?></td>
                        <td><code><?php echo esc_html($item['yoast_key']); ?></code></td>
                        <td><code><?php echo esc_html($item['requestdesk_key']); ?></code></td>
                        <td><?php echo esc_html($item['count']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Import Options -->
        <div class="requestdesk-card">
            <h2>Import Options</h2>

            <div class="import-options">
                <label>
                    <input type="checkbox" id="import-overwrite" value="1">
                    <strong>Overwrite existing RequestDesk data</strong>
                    <span style="color: #666;">(If unchecked, posts that already have RequestDesk SEO data will be skipped)</span>
                </label>
            </div>

            <!-- Progress Bar -->
            <div class="import-progress" id="import-progress">
                <div class="import-progress-bar">
                    <div class="import-progress-fill" id="progress-fill"></div>
                </div>
                <div class="import-progress-text" id="progress-text">
                    Importing... <span id="progress-count">0</span> / <?php echo esc_html($total_posts); ?> posts
                </div>
            </div>

            <!-- Results -->
            <div class="import-results" id="import-results"></div>

            <!-- Import Button -->
            <p>
                <button type="button" id="start-import" class="button button-primary button-hero">
                    Start Import
                </button>
                <span id="import-status" style="margin-left: 15px; color: #666;"></span>
            </p>

            <p class="description">
                <strong>Note:</strong> This process will copy Yoast SEO data to RequestDesk fields.
                The original Yoast data will not be deleted. You can safely run this multiple times.
            </p>
        </div>

        <?php endif; ?>

        <!-- After Import -->
        <div class="requestdesk-card">
            <h2>After Importing</h2>
            <ol>
                <li><strong>Verify the import:</strong> Check a few posts to ensure SEO data was imported correctly.</li>
                <li><strong>Disable Yoast SEO features:</strong> Go to RequestDesk Settings → SEO and uncheck "Disable SEO when Yoast is active".</li>
                <li><strong>Deactivate Yoast SEO:</strong> Once verified, you can deactivate Yoast SEO to prevent conflicts.</li>
                <li><strong>Test your site:</strong> Use Google's Rich Results Test to verify schema markup is correct.</li>
            </ol>
            <p>
                <a href="<?php echo admin_url('admin.php?page=requestdesk-settings'); ?>" class="button">
                    Go to RequestDesk Settings
                </a>
            </p>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var isImporting = false;
        var totalPosts = <?php echo (int) $total_posts; ?>;
        var processedPosts = 0;
        var totalImported = 0;
        var totalSkipped = 0;

        $('#start-import').on('click', function() {
            if (isImporting) return;

            isImporting = true;
            processedPosts = 0;
            totalImported = 0;
            totalSkipped = 0;

            $(this).prop('disabled', true).text('Importing...');
            $('#import-progress').show();
            $('#import-results').hide();

            runImportBatch(0);
        });

        function runImportBatch(offset) {
            var overwrite = $('#import-overwrite').is(':checked');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'requestdesk_yoast_import_execute',
                    nonce: '<?php echo wp_create_nonce('requestdesk_yoast_import'); ?>',
                    batch_size: 50,
                    offset: offset,
                    overwrite: overwrite ? 'true' : 'false'
                },
                success: function(response) {
                    if (response.success) {
                        processedPosts += response.data.results.processed;
                        totalImported += response.data.results.imported;
                        totalSkipped += response.data.results.skipped;

                        var progress = (processedPosts / totalPosts) * 100;
                        $('#progress-fill').css('width', progress + '%');
                        $('#progress-count').text(processedPosts);

                        if (response.data.complete) {
                            importComplete();
                        } else {
                            runImportBatch(response.data.offset);
                        }
                    } else {
                        importError(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    importError('Network error: ' + error);
                }
            });
        }

        function importComplete() {
            isImporting = false;
            $('#start-import').prop('disabled', false).text('Start Import');
            $('#import-progress').hide();

            $('#import-results')
                .removeClass('error')
                .html(
                    '<strong>Import Complete!</strong><br>' +
                    'Processed: ' + processedPosts + ' posts<br>' +
                    'Fields imported: ' + totalImported + '<br>' +
                    'Fields skipped: ' + totalSkipped + ' (already had RequestDesk data)'
                )
                .show();
        }

        function importError(message) {
            isImporting = false;
            $('#start-import').prop('disabled', false).text('Start Import');
            $('#import-progress').hide();

            $('#import-results')
                .addClass('error')
                .html('<strong>Import Error:</strong> ' + message)
                .show();
        }
    });
    </script>
    <?php
}
