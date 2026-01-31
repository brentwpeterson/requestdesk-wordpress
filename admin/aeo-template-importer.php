<?php
/**
 * RequestDesk AEO Template Importer
 *
 * Professional template importer for AEO-optimized homepage templates
 * Integrated with RequestDesk plugin for seamless user experience
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add submenu page for Template Importer
 */
function requestdesk_add_template_importer_menu() {
    add_submenu_page(
        'requestdesk-aeo-analytics',
        'AEO Template Importer',
        'Template Importer',
        'manage_options',
        'requestdesk-template-importer',
        'requestdesk_template_importer_page'
    );
}
add_action('admin_menu', 'requestdesk_add_template_importer_menu', 20);

/**
 * Template Importer Page
 */
function requestdesk_template_importer_page() {
    // Handle CSV template import
    if (isset($_POST['import_csv_template']) && wp_verify_nonce($_POST['requestdesk_csv_template_nonce'], 'requestdesk_csv_template_import')) {
        $template_type = sanitize_text_field($_POST['template_type']);

        // Handle file upload
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $result = requestdesk_import_csv_template($template_type, $_FILES['csv_file']);
        } else {
            $result = array(
                'success' => false,
                'message' => 'Please select a CSV file to upload.'
            );
        }

        if ($result['success']) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<h3>🎉 Template Imported Successfully!</h3>';
            echo '<p><strong>Page ID:</strong> ' . $result['page_id'] . '</p>';
            echo '<p><strong>Page Title:</strong> ' . esc_html($result['page_title']) . '</p>';
            echo '<p><strong>Template:</strong> ' . esc_html($result['template_name']) . '</p>';
            echo '<p><a href="' . get_edit_post_link($result['page_id']) . '" class="button button-primary">Edit Page</a> ';
            echo '<a href="' . get_permalink($result['page_id']) . '" class="button" target="_blank">Preview Page</a></p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<h3>❌ Import Failed</h3>';
            echo '<p>' . esc_html($result['message']) . '</p>';
            echo '</div>';
        }
    }

    // Handle legacy template import (for backwards compatibility)
    if (isset($_POST['import_template']) && wp_verify_nonce($_POST['requestdesk_template_nonce'], 'requestdesk_template_import')) {
        $template_type = sanitize_text_field($_POST['template_type']);
        $result = requestdesk_import_template($template_type);

        if ($result['success']) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<h3>🎉 Template Imported Successfully!</h3>';
            echo '<p><strong>Page ID:</strong> ' . $result['page_id'] . '</p>';
            echo '<p><strong>Template:</strong> ' . esc_html($result['template_name']) . '</p>';
            echo '<p><a href="' . get_edit_post_link($result['page_id']) . '" class="button button-primary">Edit Template</a> ';
            echo '<a href="' . get_permalink($result['page_id']) . '" class="button" target="_blank">Preview Template</a></p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<h3>❌ Import Failed</h3>';
            echo '<p>' . esc_html($result['message']) . '</p>';
            echo '</div>';
        }
    }

    ?>
    <div class="wrap">
        <h1><span class="dashicons dashicons-download" style="margin-right: 10px;"></span>Universal AEO Template Importer</h1>

        <!-- CSV Upload Section -->
        <div class="card" style="max-width: none; margin-bottom: 20px;">
            <h2>🚀 CSV-Powered Template System</h2>
            <p>Upload a CSV file with your content to automatically generate AEO-optimized pages. Each import creates a new page with your custom content.</p>

            <form method="post" action="" enctype="multipart/form-data" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px;">
                <?php wp_nonce_field('requestdesk_csv_template_import', 'requestdesk_csv_template_nonce'); ?>

                <input type="hidden" name="template_type" value="auto_detect">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label for="csv_file" style="font-weight: 600; display: block; margin-bottom: 8px;">Upload CSV File:</label>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required style="width: 100%;">
                        <p style="font-size: 12px; color: #666; margin-top: 5px;">The template type is auto-detected from the <code>template_type</code> column in your CSV (homepage, about, service, leadmagnet, generateblocks).</p>
                    </div>

                    <div style="display: flex; align-items: end;">
                        <input type="submit" name="import_csv_template" class="button button-primary button-large" value="Import Template" style="width: 100%; height: 40px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <div>
                        <h4 style="margin: 0 0 10px 0;">📋 CSV Requirements:</h4>
                        <ul style="margin: 5px 0; font-size: 14px;">
                            <li>✅ Headers must match template fields exactly</li>
                            <li>✅ One row of data per template</li>
                            <li>✅ URLs should include http:// or https://</li>
                            <li>✅ Text fields can include basic HTML</li>
                        </ul>
                    </div>

                    <div>
                        <h4 style="margin: 0 0 10px 0;">Download Examples:</h4>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <a href="<?php echo REQUESTDESK_PLUGIN_URL . 'admin/example-csv-homepage.csv'; ?>" class="button button-secondary" download="example-csv-homepage.csv">
                                <span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Homepage Template
                            </a>
                            <a href="<?php echo REQUESTDESK_PLUGIN_URL . 'admin/example-csv-about.csv'; ?>" class="button button-secondary" download="example-csv-about.csv">
                                <span class="dashicons dashicons-download" style="vertical-align: middle;"></span> About Page Template
                            </a>
                            <a href="<?php echo REQUESTDESK_PLUGIN_URL . 'admin/example-csv-service.csv'; ?>" class="button button-secondary" download="example-csv-service.csv">
                                <span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Service Page Template
                            </a>
                            <a href="<?php echo REQUESTDESK_PLUGIN_URL . 'admin/example-csv-landing-page.csv'; ?>" class="button button-secondary" download="example-csv-landing-page.csv">
                                <span class="dashicons dashicons-download" style="vertical-align: middle;"></span> General Landing Page
                            </a>
                            <a href="<?php echo REQUESTDESK_PLUGIN_URL . 'admin/example-csv-leadmagnet.csv'; ?>" class="button button-secondary" download="example-csv-leadmagnet.csv">
                                <span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Lead Magnet Page
                            </a>
                            <a href="<?php echo REQUESTDESK_PLUGIN_URL . 'admin/example-csv-generateblocks.csv'; ?>" class="button button-secondary" download="example-csv-generateblocks.csv">
                                <span class="dashicons dashicons-download" style="vertical-align: middle;"></span> GenerateBlocks Landing Page
                            </a>
                        </div>
                        <p style="font-size: 12px; color: #666; margin-top: 8px;">Each file has one row with the <code>template_type</code> pre-filled for auto-detection.</p>
                    </div>
                </div>
            </form>
        </div>

        <!-- Template Gallery -->
        <div class="card" style="max-width: none;">
            <h2>📚 Available Templates</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 20px;">

                <!-- AEO Homepage Template -->
                <div class="card" style="margin: 0; background: #fff;">
                    <h3>🎯 AEO Homepage Template</h3>
                    <div style="padding: 15px; background: #f8f9fa; border-radius: 4px; margin: 15px 0;">
                        <h4>AEO/GEO Features:</h4>
                        <ul style="margin: 10px 0; font-size: 13px;">
                            <li>✅ Complete Schema markup (Organization, FAQ, Service, Review)</li>
                            <li>✅ Answer Engine optimized FAQ section</li>
                            <li>✅ E-E-A-T trust signals and testimonials</li>
                            <li>✅ Content freshness with dynamic blog posts</li>
                            <li>✅ Internal linking strategy</li>
                            <li>✅ Mobile-first responsive design</li>
                        </ul>
                    </div>
                    <div style="text-align: center; padding: 10px;">
                        <span class="button button-primary" style="cursor: default;">Available Now</span>
                    </div>
                </div>

                <!-- Coming Soon Templates -->
                <div class="card" style="margin: 0; background: #f9f9f9; opacity: 0.7;">
                    <h3>🔧 Service Page Template</h3>
                    <div style="padding: 15px; background: #fff; border-radius: 4px; margin: 15px 0;">
                        <h4>Planned Features:</h4>
                        <ul style="margin: 10px 0; font-size: 13px; color: #666;">
                            <li>📝 Service-specific schema markup</li>
                            <li>💼 Pricing table integration</li>
                            <li>📊 Benefits and features grid</li>
                            <li>🎯 Conversion-optimized CTAs</li>
                            <li>📞 Contact form integration</li>
                        </ul>
                    </div>
                    <div style="text-align: center; padding: 10px;">
                        <span class="button" style="cursor: default;">Coming Soon</span>
                    </div>
                </div>

                <div class="card" style="margin: 0; background: #f9f9f9; opacity: 0.7;">
                    <h3>📋 About Page Template</h3>
                    <div style="padding: 15px; background: #fff; border-radius: 4px; margin: 15px 0;">
                        <h4>Planned Features:</h4>
                        <ul style="margin: 10px 0; font-size: 13px; color: #666;">
                            <li>👥 Team member profiles</li>
                            <li>🏢 Company timeline and history</li>
                            <li>🎖️ Awards and certifications</li>
                            <li>📈 Company statistics</li>
                            <li>🎯 Mission and values</li>
                        </ul>
                    </div>
                    <div style="text-align: center; padding: 10px;">
                        <span class="button" style="cursor: default;">Coming Soon</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Legacy Import (Hidden by default) -->
        <details style="margin-top: 20px;">
            <summary style="cursor: pointer; font-weight: 600; padding: 10px; background: #f0f0f1; border-radius: 4px;">🔧 Legacy Template Import (No CSV)</summary>
            <div style="padding: 20px; background: #f8f9fa; border-radius: 4px; margin-top: 10px;">
                <p style="color: #666; font-style: italic;">Import the default Content Cucumber template without CSV customization:</p>
                <form method="post" action="">
                    <?php wp_nonce_field('requestdesk_template_import', 'requestdesk_template_nonce'); ?>
                    <input type="hidden" name="template_type" value="aeo_homepage">
                    <input type="submit" name="import_template" class="button button-secondary" value="Import Default AEO Homepage Template">
                </form>
            </div>
        </details>

        <div class="card">
            <h3>ℹ️ Important Notes</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4>✅ Safe Import Process</h4>
                    <ul>
                        <li>Templates created as <strong>draft pages</strong></li>
                        <li>No existing content is modified</li>
                        <li>Duplicate detection prevents conflicts</li>
                        <li>Easy to review before publishing</li>
                    </ul>
                </div>
                <div>
                    <h4>🔧 After Import</h4>
                    <ul>
                        <li>Review and customize content</li>
                        <li>Update company-specific information</li>
                        <li>Set as homepage when ready</li>
                        <li>Configure schema markup for full SEO</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card">
            <h3>📋 Next Steps After Import</h3>
            <ol>
                <li><strong>Review Template:</strong> Edit the imported page to customize content</li>
                <li><strong>Update Content:</strong> Replace placeholder text with your specific information</li>
                <li><strong>Set as Homepage:</strong> Go to Settings → Reading → Homepage displays → A static page</li>
                <li><strong>Configure SEO:</strong> Add schema markup using your preferred SEO plugin</li>
                <li><strong>Test Performance:</strong> Check mobile responsiveness and page speed</li>
            </ol>
        </div>
    </div>

    <style>
        .card h3 {
            margin-top: 0;
            color: #1d2327;
            display: flex;
            align-items: center;
        }
        .card ul {
            list-style-type: none;
            padding-left: 0;
        }
        .card ul li {
            padding: 4px 0;
            position: relative;
            padding-left: 20px;
        }
        .card ul li::before {
            content: "•";
            color: #135e96;
            font-weight: bold;
            position: absolute;
            left: 0;
        }
    </style>
    <?php
}

/**
 * Import CSV template function
 */
function requestdesk_import_csv_template($template_type, $csv_file) {
    global $wpdb;

    try {
        // Parse CSV data first to check for template_type column
        $csv_data = requestdesk_parse_csv_file($csv_file['tmp_name']);
        if (!$csv_data || empty($csv_data)) {
            return array(
                'success' => false,
                'message' => 'Could not parse CSV file or file is empty.'
            );
        }

        // Auto-detect template type from CSV if auto_detect is selected OR template_type column exists
        if ($template_type === 'auto_detect' || (isset($csv_data['template_type']) && !empty($csv_data['template_type']))) {
            // Check if template_type column exists in CSV
            if (!isset($csv_data['template_type']) || empty($csv_data['template_type'])) {
                return array(
                    'success' => false,
                    'message' => 'Auto-detect selected but CSV file is missing "template_type" column or it is empty. Please add template_type column with value: homepage, about'
                );
            }
            $csv_template_type = sanitize_text_field($csv_data['template_type']);

            // Convert CSV template type to internal format
            switch (strtolower($csv_template_type)) {
                case 'homepage':
                    $detected_type = 'aeo_homepage';
                    break;
                case 'about':
                case 'about_page':
                    $detected_type = 'aeo_about_page';
                    break;
                case 'open_base':
                case 'service':
                case 'service_page':
                    $detected_type = 'service_page';
                    break;
                    break;
                case 'leadmagnet':
                case 'leadmagnet_page':
                case 'lead_magnet':
                    $detected_type = 'leadmagnet_page';
                    break;
                case 'generateblocks':
                case 'gb':
                    $detected_type = 'generateblocks';
                    break;
                default:
                    return array(
                        'success' => false,
                        'message' => 'Invalid template_type in CSV: ' . $csv_template_type . '. Valid types: homepage, about, open_base, service, leadmagnet, generateblocks'
                    );
            }

            // Override the selected template type with CSV detected type
            $template_type = $detected_type;
        }

        // Validate CSV file for the determined template type
        $validation_result = requestdesk_validate_csv_file($csv_file, $template_type);
        if (!$validation_result['success']) {
            return $validation_result;
        }

        // Import template with CSV data
        switch ($template_type) {
            case 'aeo_homepage':
                return requestdesk_import_aeo_homepage_csv($csv_data);
            case 'aeo_about_page':
                return requestdesk_import_aeo_about_csv($csv_data);
            case 'service_page':
                return requestdesk_import_service_page_csv($csv_data);
            case 'leadmagnet_page':
                return requestdesk_import_leadmagnet_csv($csv_data);
            case 'generateblocks':
                return requestdesk_import_generateblocks_csv($csv_data);
            default:
                return array(
                    'success' => false,
                    'message' => 'Unknown template type: ' . $template_type
                );
        }
    } catch (Exception $e) {
        return array(
            'success' => false,
            'message' => 'Exception occurred: ' . $e->getMessage()
        );
    }
}

/**
 * Import template function (legacy support)
 */
function requestdesk_import_template($template_type) {
    global $wpdb;

    try {
        switch ($template_type) {
            case 'aeo_homepage':
                return requestdesk_import_aeo_homepage();
            case 'aeo_about_page':
                return requestdesk_import_aeo_about();
            default:
                return array(
                    'success' => false,
                    'message' => 'Unknown template type: ' . $template_type
                );
        }
    } catch (Exception $e) {
        return array(
            'success' => false,
            'message' => 'Exception occurred: ' . $e->getMessage()
        );
    }
}

/**
 * Import AEO Homepage Template
 */
function requestdesk_import_aeo_homepage() {
    global $wpdb;

    // Check if template already exists (exclude trashed posts)
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'page' AND post_status != 'trash'",
        'AEO Homepage Template'
    ));

    if ($existing) {
        return array(
            'success' => false,
            'message' => 'AEO Homepage Template already exists (ID: ' . $existing . '). Delete existing template first if you want to reimport.'
        );
    }

    // Get the template content
    $template_content = requestdesk_get_aeo_template_content();

    // Prepare page data
    $current_time = current_time('mysql');
    $current_time_gmt = current_time('mysql', 1);

    $page_data = array(
        'post_author' => get_current_user_id(),
        'post_date' => $current_time,
        'post_date_gmt' => $current_time_gmt,
        'post_content' => $template_content,
        'post_title' => 'AEO Homepage Template',
        'post_excerpt' => 'AEO-optimized homepage template with GenerateBlocks structure for improved search engine visibility and conversion optimization.',
        'post_status' => 'draft',
        'comment_status' => 'closed',
        'ping_status' => 'closed',
        'post_password' => '',
        'post_name' => 'aeo-homepage-template',
        'to_ping' => '',
        'pinged' => '',
        'post_modified' => $current_time,
        'post_modified_gmt' => $current_time_gmt,
        'post_content_filtered' => '',
        'post_parent' => 0,
        'guid' => '',
        'menu_order' => 0,
        'post_type' => 'page',
        'post_mime_type' => '',
        'comment_count' => 0
    );

    // Insert the page
    $result = $wpdb->insert($wpdb->posts, $page_data);

    if ($result !== false) {
        $page_id = $wpdb->insert_id;

        // Update GUID
        $wpdb->update(
            $wpdb->posts,
            array('guid' => get_permalink($page_id)),
            array('ID' => $page_id)
        );

        // Add to AEO tracking (optional)
        $aeo_table = $wpdb->prefix . 'requestdesk_aeo_data';
        if ($wpdb->get_var("SHOW TABLES LIKE '$aeo_table'") == $aeo_table) {
            $wpdb->insert(
                $aeo_table,
                array(
                    'post_id' => $page_id,
                    'content_type' => 'page',
                    'aeo_score' => 85, // Pre-optimized score
                    'optimization_status' => 'optimized',
                    'ai_questions' => json_encode([
                        'How long does it take to see SEO results?',
                        'What makes Content Cucumber different from other agencies?',
                        'Do you work with businesses in my industry?'
                    ]),
                    'created_at' => $current_time,
                    'updated_at' => $current_time
                ),
                array('%d', '%s', '%d', '%s', '%s', '%s', '%s')
            );
        }

        return array(
            'success' => true,
            'page_id' => $page_id,
            'template_name' => 'AEO Homepage Template'
        );
    } else {
        return array(
            'success' => false,
            'message' => 'Database insertion failed: ' . $wpdb->last_error
        );
    }
}

/**
 * Validate CSV file
 */
function requestdesk_validate_csv_file($csv_file, $template_type) {
    // Check file upload errors
    if ($csv_file['error'] !== UPLOAD_ERR_OK) {
        return array(
            'success' => false,
            'message' => 'File upload error: ' . $csv_file['error']
        );
    }

    // Check file extension
    $file_extension = strtolower(pathinfo($csv_file['name'], PATHINFO_EXTENSION));
    if ($file_extension !== 'csv') {
        return array(
            'success' => false,
            'message' => 'Please upload a CSV file. File extension: ' . $file_extension
        );
    }

    // Check file size (max 1MB)
    if ($csv_file['size'] > 1048576) {
        return array(
            'success' => false,
            'message' => 'File size too large. Maximum allowed: 1MB'
        );
    }

    return array('success' => true);
}

/**
 * Parse CSV file
 */
function requestdesk_parse_csv_file($csv_path) {
    $csv_data = array();

    if (($handle = fopen($csv_path, 'r')) !== false) {
        // Get headers from first row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return false;
        }

        // Get data from second row
        $data = fgetcsv($handle);
        if (!$data) {
            fclose($handle);
            return false;
        }

        // Combine headers with data
        $csv_data = array_combine($headers, $data);
        fclose($handle);
    }

    return $csv_data;
}

/**
 * Import AEO Homepage with CSV data
 */
function requestdesk_import_aeo_homepage_csv($csv_data) {
    global $wpdb;

    // Validate required fields
    $required_fields = array('company_name', 'hero_headline', 'service_1_title');
    foreach ($required_fields as $field) {
        if (empty($csv_data[$field])) {
            return array(
                'success' => false,
                'message' => 'Required field missing: ' . $field
            );
        }
    }

    // Generate unique page title
    $company_name = sanitize_text_field($csv_data['company_name']);
    $timestamp = current_time('Y-m-d H:i');
    $page_title = $company_name . ' Homepage - ' . $timestamp;
    $page_slug = sanitize_title($company_name . '-homepage-' . current_time('Y-m-d-H-i'));

    // Get template content and replace placeholders
    $template_content = requestdesk_get_aeo_template_with_csv($csv_data);

    // Prepare page data
    $current_time = current_time('mysql');
    $current_time_gmt = current_time('mysql', 1);

    $page_data = array(
        'post_author' => get_current_user_id(),
        'post_date' => $current_time,
        'post_date_gmt' => $current_time_gmt,
        'post_content' => $template_content,
        'post_title' => $page_title,
        'post_excerpt' => 'AEO-optimized homepage for ' . $company_name . ' with custom content from CSV import.',
        'post_status' => 'draft',
        'comment_status' => 'closed',
        'ping_status' => 'closed',
        'post_password' => '',
        'post_name' => $page_slug,
        'to_ping' => '',
        'pinged' => '',
        'post_modified' => $current_time,
        'post_modified_gmt' => $current_time_gmt,
        'post_content_filtered' => '',
        'post_parent' => 0,
        'guid' => '',
        'menu_order' => 0,
        'post_type' => 'page',
        'post_mime_type' => '',
        'comment_count' => 0
    );

    // Insert the page
    $result = $wpdb->insert($wpdb->posts, $page_data);

    if ($result !== false) {
        $page_id = $wpdb->insert_id;

        // Update GUID
        $wpdb->update(
            $wpdb->posts,
            array('guid' => get_permalink($page_id)),
            array('ID' => $page_id)
        );

        // Add to AEO tracking (optional)
        $aeo_table = $wpdb->prefix . 'requestdesk_aeo_data';
        if ($wpdb->get_var("SHOW TABLES LIKE '$aeo_table'") == $aeo_table) {
            $wpdb->insert(
                $aeo_table,
                array(
                    'post_id' => $page_id,
                    'content_type' => 'page',
                    'aeo_score' => 90, // Higher score for CSV-optimized content
                    'optimization_status' => 'optimized',
                    'ai_questions' => json_encode(array(
                        $csv_data['faq_1_question'] ?? 'How can we help your business?',
                        $csv_data['faq_2_question'] ?? 'What makes your company different?',
                        $csv_data['faq_3_question'] ?? 'What industries do you serve?'
                    )),
                    'created_at' => $current_time,
                    'updated_at' => $current_time
                ),
                array('%d', '%s', '%d', '%s', '%s', '%s', '%s')
            );
        }

        return array(
            'success' => true,
            'page_id' => $page_id,
            'page_title' => $page_title,
            'template_name' => 'AEO Homepage Template (CSV)'
        );
    } else {
        return array(
            'success' => false,
            'message' => 'Database insertion failed: ' . $wpdb->last_error
        );
    }
}

/**
 * Get AEO Template Content with CSV replacement
 */
function requestdesk_get_aeo_template_with_csv($csv_data) {
    // Load the base template
    $template_content = requestdesk_get_enhanced_aeo_template();

    // Replace placeholders with CSV data
    $replacements = array(
        // Company Information
        '[CUSTOMIZE: Add your business name]' => $csv_data['company_name'] ?? 'Your Company',
        'Content Cucumber' => $csv_data['company_name'] ?? 'Your Company',
        'https://contentcucumber.com' => $csv_data['company_url'] ?? 'https://yourwebsite.com',

        // Hero Section
        'We drive organic growth with SEO, AI, GEO and content marketing' => $csv_data['hero_headline'] ?? 'Your compelling headline here',
        'Wordsmiths, Designers, Devs &amp; More.' => $csv_data['hero_subheadline'] ?? 'Your tagline here',
        'Your On-Demand Creative Partner' => $csv_data['company_tagline'] ?? 'Your Company Tagline',
        'Let\'s write your success story!' => $csv_data['hero_cta_text'] ?? 'Let\'s grow your business!',

        // Services
        'SEO Optimization' => $csv_data['service_1_title'] ?? 'Service 1',
        'Comprehensive search engine optimization to improve your rankings and organic visibility. We optimize on-page elements, technical SEO, and content strategy.' => $csv_data['service_1_description'] ?? 'Description of your first service',
        'Content Marketing' => $csv_data['service_2_title'] ?? 'Service 2',
        'High-quality, engaging content that resonates with your audience. Our expert writers create blog posts, articles, and web copy that drives results.' => $csv_data['service_2_description'] ?? 'Description of your second service',
        'AI-Powered Insights' => $csv_data['service_3_title'] ?? 'Service 3',
        'Advanced AI tools and analytics to optimize content performance and identify growth opportunities. Data-driven strategies for maximum ROI.' => $csv_data['service_3_description'] ?? 'Description of your third service',

        // FAQ Section
        'How long does it take to see SEO results?' => $csv_data['faq_1_question'] ?? 'Common question 1?',
        'SEO results typically begin showing within 3-6 months, with significant improvements visible after 6-12 months. Our proven strategies focus on sustainable, long-term growth rather than quick fixes. Content Cucumber\'s data-driven approach ensures consistent progress toward your organic traffic goals.' => $csv_data['faq_1_answer'] ?? 'Answer to your first common question.',
        'What makes Content Cucumber different from other agencies?' => $csv_data['faq_2_question'] ?? 'Common question 2?',
        'We combine human expertise with AI-powered insights to deliver exceptional results. Our dedicated team approach ensures consistency, while our proprietary tools provide data-driven optimization that most agencies cannot match. With 60,000+ projects delivered and a 4.9/5 rating, we focus on measurable ROI.' => $csv_data['faq_2_answer'] ?? 'Answer to your second common question.',
        'Do you work with businesses in my industry?' => $csv_data['faq_3_question'] ?? 'Common question 3?',
        'We work with businesses across all industries, from e-commerce and SaaS to professional services and manufacturing. Our team has experience creating effective content strategies for diverse markets and audiences, with proven success in both B2B and B2C environments.' => $csv_data['faq_3_answer'] ?? 'Answer to your third common question.',
        'What services do you offer?' => $csv_data['faq_4_question'] ?? 'Common question 4?',
        'We offer comprehensive digital marketing services including SEO optimization, content marketing, AI-powered analytics, technical SEO audits, copywriting, and strategic consulting. Our full-service approach ensures all aspects of your digital presence work together for maximum impact.' => $csv_data['faq_4_answer'] ?? 'Answer to your fourth common question.',

        // Testimonials
        'Content Cucumber transformed our organic traffic from 500 to over 10,000 monthly visitors. Their strategic approach and consistent quality have been game-changing for our business.' => $csv_data['testimonial_1_text'] ?? 'Great testimonial from a satisfied customer about the results they achieved.',
        'Sarah Johnson, CEO of TechStart Inc.' => $csv_data['testimonial_1_author'] ?? 'Client Name, Title',
        'The team at Content Cucumber delivers consistently high-quality content that resonates with our audience. Our engagement rates have never been higher.' => $csv_data['testimonial_2_text'] ?? 'Another positive testimonial highlighting specific benefits.',
        'Michael Chen, Marketing Director' => $csv_data['testimonial_2_author'] ?? 'Another Client, Title',

        // Company Stats
        '60,000+' => $csv_data['stat_1_number'] ?? '1,000+',
        'Projects Delivered' => $csv_data['stat_1_label'] ?? 'Projects Completed',
        '55M+' => $csv_data['stat_2_number'] ?? '500K+',
        'Words Written' => $csv_data['stat_2_label'] ?? 'Words Created',
        '★ 4.9/5' => $csv_data['stat_3_number'] ?? '★ 5.0/5',
        'Average Rating' => $csv_data['stat_3_label'] ?? 'Customer Rating',

        // Contact Information
        '[CUSTOMIZE: +1-XXX-XXX-XXXX]' => $csv_data['company_phone'] ?? '+1-555-123-4567',
        '[CUSTOMIZE: LinkedIn URL]' => $csv_data['company_linkedin'] ?? 'https://linkedin.com/company/yourcompany',
        '[CUSTOMIZE: Twitter URL]' => $csv_data['company_twitter'] ?? 'https://twitter.com/yourcompany',
        'https://meetings.hubspot.com/isaac-morey/meeting' => $csv_data['hero_cta_url'] ?? '#contact',
        'Schedule Your Free Consultation' => $csv_data['hero_cta_text'] ?? 'Get Started Today',

        // About section
        'Founded with a mission to democratize world-class content marketing, Content Cucumber combines human creativity with AI-powered insights. Our team of expert writers, strategists, and developers work together to deliver measurable results for businesses of all sizes.' => $csv_data['about_description'] ?? 'Your company description and mission statement goes here.',

        // Meta description
        'Content Cucumber delivers expert SEO, content marketing, and AI-powered digital strategies. Drive organic growth with our proven team of writers, designers, and developers. 60,000+ projects delivered. Get your free consultation today.' => $csv_data['meta_description'] ?? 'Your optimized meta description for search engines.',
    );

    // Apply replacements
    foreach ($replacements as $search => $replace) {
        $template_content = str_replace($search, esc_html($replace), $template_content);
    }

    return $template_content;
}

/**
 * Get AEO Template Content
 */
function requestdesk_get_aeo_template_content() {
    // Load the comprehensive enhanced template
    $template_file = REQUESTDESK_PLUGIN_DIR . 'admin/aeo-template-enhanced.php';

    if (file_exists($template_file)) {
        include_once $template_file;
        if (function_exists('requestdesk_get_enhanced_aeo_template')) {
            return requestdesk_get_enhanced_aeo_template();
        }
    }

    // If enhanced template is not available, return a basic fallback
    return '<!-- wp:heading {"level":1,"style":{"color":{"text":"#ff0000"}}} -->
<h1 class="wp-block-heading has-text-color" style="color:#ff0000">🚀 AEO/GEO OPTIMIZED HOMEPAGE TEMPLATE</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"background":"#fff3cd","text":"#856404"},"spacing":{"padding":{"top":"20px","bottom":"20px","left":"20px","right":"20px"}},"border":{"radius":"8px","color":"#ffeaa7","width":"1px"}}} -->
<p class="has-text-color has-background has-border-color" style="border-color:#ffeaa7;border-width:1px;border-radius:8px;background-color:#fff3cd;color:#856404;padding-top:20px;padding-bottom:20px;padding-left:20px;padding-right:20px"><strong>⚠️ Enhanced Template Missing:</strong> The comprehensive AEO template could not be loaded. This is a basic fallback. Please ensure aeo-template-enhanced.php is properly installed.</p>
<!-- /wp:paragraph -->';
}

/**
 * Import AEO About Page Template
 */
function requestdesk_import_aeo_about() {
    global $wpdb;

    // Check if template already exists (exclude trashed posts)
    $existing_page = $wpdb->get_var($wpdb->prepare("
        SELECT ID FROM {$wpdb->posts}
        WHERE post_title = %s
        AND post_type = 'page'
        AND post_status != 'trash'
        LIMIT 1
    ", 'About Us - AEO Template'));

    if ($existing_page) {
        return array(
            'success' => false,
            'message' => 'About page template already exists (ID: ' . $existing_page . '). Delete the existing page first or use the CSV import to create a new customized page.'
        );
    }

    // Get template content
    $template_content = requestdesk_get_about_template();

    // Prepare page data
    $current_time = current_time('mysql');
    $current_time_gmt = current_time('mysql', 1);

    $page_data = array(
        'post_author' => get_current_user_id(),
        'post_date' => $current_time,
        'post_date_gmt' => $current_time_gmt,
        'post_content' => $template_content,
        'post_title' => 'About Us - AEO Template',
        'post_excerpt' => 'AEO-optimized About page template with comprehensive schema markup and E-E-A-T signals.',
        'post_status' => 'draft',
        'comment_status' => 'closed',
        'ping_status' => 'closed',
        'post_password' => '',
        'post_name' => 'about-aeo-template',
        'to_ping' => '',
        'pinged' => '',
        'post_modified' => $current_time,
        'post_modified_gmt' => $current_time_gmt,
        'post_content_filtered' => '',
        'post_parent' => 0,
        'guid' => '',
        'menu_order' => 0,
        'post_type' => 'page',
        'post_mime_type' => '',
        'comment_count' => 0
    );

    // Insert the page
    $result = $wpdb->insert($wpdb->posts, $page_data);

    if ($result !== false) {
        $page_id = $wpdb->insert_id;

        // Update GUID
        $wpdb->update(
            $wpdb->posts,
            array('guid' => get_permalink($page_id)),
            array('ID' => $page_id)
        );

        // Add to AEO tracking (optional)
        $aeo_table = $wpdb->prefix . 'requestdesk_aeo_data';
        if ($wpdb->get_var("SHOW TABLES LIKE '$aeo_table'") == $aeo_table) {
            $wpdb->insert(
                $aeo_table,
                array(
                    'post_id' => $page_id,
                    'content_type' => 'page',
                    'aeo_score' => 85, // High score for optimized template
                    'optimization_status' => 'optimized',
                    'ai_questions' => json_encode(array(
                        'What makes this company different?',
                        'How long has the company been in business?',
                        'What industries do they work with?'
                    )),
                    'created_at' => $current_time,
                    'updated_at' => $current_time
                ),
                array('%d', '%s', '%d', '%s', '%s', '%s', '%s')
            );
        }

        return array(
            'success' => true,
            'page_id' => $page_id,
            'template_name' => 'AEO About Page Template'
        );
    } else {
        return array(
            'success' => false,
            'message' => 'Database insertion failed: ' . $wpdb->last_error
        );
    }
}

/**
 * Import AEO About Page with CSV data
 */
function requestdesk_import_aeo_about_csv($csv_data) {
    global $wpdb;

    // Validate required fields
    $required_fields = array('company_name', 'company_mission', 'founder_name');
    foreach ($required_fields as $field) {
        if (empty($csv_data[$field])) {
            return array(
                'success' => false,
                'message' => 'Required field missing: ' . $field
            );
        }
    }

    // Generate unique page title
    $company_name = sanitize_text_field($csv_data['company_name']);
    $timestamp = current_time('Y-m-d H:i');
    $page_title = 'About ' . $company_name . ' - ' . $timestamp;
    $page_slug = sanitize_title('about-' . $company_name . '-' . current_time('Y-m-d-H-i'));

    // Get template content and replace placeholders
    $template_content = requestdesk_get_about_template_with_csv($csv_data);

    // Prepare page data
    $current_time = current_time('mysql');
    $current_time_gmt = current_time('mysql', 1);

    $page_data = array(
        'post_author' => get_current_user_id(),
        'post_date' => $current_time,
        'post_date_gmt' => $current_time_gmt,
        'post_content' => $template_content,
        'post_title' => $page_title,
        'post_excerpt' => 'About ' . $company_name . ' - Our mission, team, and values. Learn about our company story and what drives our success.',
        'post_status' => 'draft',
        'comment_status' => 'closed',
        'ping_status' => 'closed',
        'post_password' => '',
        'post_name' => $page_slug,
        'to_ping' => '',
        'pinged' => '',
        'post_modified' => $current_time,
        'post_modified_gmt' => $current_time_gmt,
        'post_content_filtered' => '',
        'post_parent' => 0,
        'guid' => '',
        'menu_order' => 0,
        'post_type' => 'page',
        'post_mime_type' => '',
        'comment_count' => 0
    );

    // Insert the page
    $result = $wpdb->insert($wpdb->posts, $page_data);

    if ($result !== false) {
        $page_id = $wpdb->insert_id;

        // Update GUID
        $wpdb->update(
            $wpdb->posts,
            array('guid' => get_permalink($page_id)),
            array('ID' => $page_id)
        );

        // Add to AEO tracking (optional)
        $aeo_table = $wpdb->prefix . 'requestdesk_aeo_data';
        if ($wpdb->get_var("SHOW TABLES LIKE '$aeo_table'") == $aeo_table) {
            $wpdb->insert(
                $aeo_table,
                array(
                    'post_id' => $page_id,
                    'content_type' => 'page',
                    'aeo_score' => 92, // Higher score for CSV-customized content
                    'optimization_status' => 'optimized',
                    'ai_questions' => json_encode(array(
                        $csv_data['faq_1_question'] ?? 'What makes this company different?',
                        $csv_data['faq_2_question'] ?? 'How long has the company been in business?',
                        $csv_data['faq_3_question'] ?? 'What industries do they work with?'
                    )),
                    'created_at' => $current_time,
                    'updated_at' => $current_time
                ),
                array('%d', '%s', '%d', '%s', '%s', '%s', '%s')
            );
        }

        return array(
            'success' => true,
            'page_id' => $page_id,
            'page_title' => $page_title,
            'template_name' => 'AEO About Page Template (CSV)'
        );
    } else {
        return array(
            'success' => false,
            'message' => 'Database insertion failed: ' . $wpdb->last_error
        );
    }
}

/**
 * Get About template content with CSV data replacements
 */
function requestdesk_get_about_template_with_csv($csv_data) {
    // Load the base About template
    $template_content = requestdesk_get_about_template();

    // Replace placeholders with CSV data
    $replacements = array(
        // Company Information
        '[CUSTOMIZE: Add your business name]' => $csv_data['company_name'] ?? 'Your Company',
        'Content Cucumber' => $csv_data['company_name'] ?? 'Your Company',
        'https://contentcucumber.com' => $csv_data['company_url'] ?? 'https://yourwebsite.com',

        // Mission and Story
        'We drive sustainable business growth through expert content marketing, SEO optimization, and AI-powered digital strategies. [CUSTOMIZE: Add your company mission statement here]' => $csv_data['company_mission'] ?? 'Your company mission statement here.',
        'Content Cucumber was born from a simple observation: businesses were struggling to cut through the digital noise.' => $csv_data['company_story_intro'] ?? 'Your company story begins here.',
        'Our founder, [CUSTOMIZE: Founder Name], recognized that the future of marketing lay in the perfect fusion of human creativity and artificial intelligence.' => str_replace('[CUSTOMIZE: Founder Name]', $csv_data['founder_name'] ?? 'Our founder', $csv_data['company_story_body'] ?? 'Your company story continues here.'),
        '[CUSTOMIZE: Add 2-3 paragraphs about your company\'s specific journey, key milestones, challenges overcome, and what drives your mission. Include specific dates, achievements, and growth metrics to build credibility.]' => $csv_data['company_story_conclusion'] ?? 'Share your company\'s journey, milestones, and what drives your mission here.',

        // Team Information
        '[CUSTOMIZE: Founder Name]' => $csv_data['founder_name'] ?? 'Founder Name',
        '[CUSTOMIZE: CEO Name]' => $csv_data['founder_name'] ?? 'CEO Name',
        '[CUSTOMIZE: CTO Name]' => $csv_data['cto_name'] ?? 'CTO Name',
        'CEO & Founder' => $csv_data['founder_title'] ?? 'CEO & Founder',
        'Chief Technology Officer' => $csv_data['cto_title'] ?? 'Chief Technology Officer',
        '[CUSTOMIZE: 2-3 sentences about CEO background, expertise, and key achievements. Include relevant certifications or industry recognition.]' => $csv_data['founder_bio'] ?? 'Background and achievements of the CEO.',
        '[CUSTOMIZE: 2-3 sentences about CTO background, technical expertise, and innovations. Include relevant technical certifications or achievements.]' => $csv_data['cto_bio'] ?? 'Background and technical expertise of the CTO.',

        // Photo URLs
        'https://contentcucumber.com/wp-content/uploads/team-placeholder.jpg' => $csv_data['ceo_photo_url'] ?? 'https://contentcucumber.com/wp-content/uploads/team-placeholder.jpg',

        // Values
        'Results-Driven' => $csv_data['value_1_title'] ?? 'Results-Driven',
        'Every strategy we develop is anchored in measurable outcomes. We believe in transparent reporting and data-driven decision making that delivers real ROI for our clients.' => $csv_data['value_1_description'] ?? 'Every strategy we develop is anchored in measurable outcomes.',
        'Partnership' => $csv_data['value_2_title'] ?? 'Partnership',
        'We don\'t just work for you—we work with you. Our collaborative approach ensures that every campaign aligns perfectly with your business goals and brand values.' => $csv_data['value_2_description'] ?? 'We work collaboratively with our clients.',
        'Innovation' => $csv_data['value_3_title'] ?? 'Innovation',
        'We stay ahead of industry trends and leverage cutting-edge AI tools to give our clients a competitive advantage in the digital marketplace.' => $csv_data['value_3_description'] ?? 'We leverage cutting-edge tools and industry trends.',

        // Statistics
        '60,000+' => $csv_data['stat_1_number'] ?? '1,000+',
        'Projects Delivered' => $csv_data['stat_1_label'] ?? 'Projects Completed',
        '4.9/5' => $csv_data['stat_2_number'] ?? '5.0/5',
        'Client Satisfaction' => $csv_data['stat_2_label'] ?? 'Client Rating',
        '1,000+' => $csv_data['stat_3_number'] ?? '500+',
        'Companies Served' => $csv_data['stat_3_label'] ?? 'Companies Helped',
        '6+ Years' => $csv_data['stat_4_number'] ?? '5+ Years',
        'Industry Experience' => $csv_data['stat_4_label'] ?? 'Experience',

        // FAQ Section
        'What makes Content Cucumber different from other agencies?' => $csv_data['faq_1_question'] ?? 'What makes your company different?',
        'We combine human expertise with AI-powered insights to deliver exceptional results. Our proprietary methodology and data-driven approach ensure measurable ROI for every client engagement.' => $csv_data['faq_1_answer'] ?? 'Answer about what makes your company different.',
        'How long has Content Cucumber been in business?' => $csv_data['faq_2_question'] ?? 'How long has your company been in business?',
        'Since 2018, we\'ve been helping businesses achieve sustainable growth through strategic content marketing and SEO optimization. Our experience spans over 60,000 successful projects.' => $csv_data['faq_2_answer'] ?? 'Information about your company history and experience.',
        'What industries do you work with?' => $csv_data['faq_3_question'] ?? 'What industries do you serve?',
        'We serve clients across all industries, from e-commerce and SaaS to professional services and manufacturing. Our diverse experience allows us to adapt our strategies to any market.' => $csv_data['faq_3_answer'] ?? 'Description of the industries you serve.',
        '[CUSTOMIZE: Common Question About Your Company]' => $csv_data['faq_4_question'] ?? 'Common question about your company?',
        '[CUSTOMIZE: Provide a comprehensive answer that addresses common concerns or questions your prospects have about your company, services, or approach.]' => $csv_data['faq_4_answer'] ?? 'Answer to common questions about your company.',

        // Awards and Recognition
        '[CUSTOMIZE: Industry Award 1]' => $csv_data['industry_award_1'] ?? 'Industry Award 1',
        '[CUSTOMIZE: Industry Award 2]' => $csv_data['industry_award_2'] ?? 'Industry Award 2',

        // Contact Information and URLs
        '[CUSTOMIZE: Contact Page URL]' => $csv_data['contact_page_url'] ?? '/contact',
        '[CUSTOMIZE: Services Page URL]' => $csv_data['services_page_url'] ?? '/services',

        // Meta Information
        'Learn about Content Cucumber\'s mission to drive business growth through expert content marketing and SEO. Meet our team of experienced writers, designers, and strategists. Trusted by 1,000+ companies worldwide.' => $csv_data['meta_description'] ?? 'Learn about our mission and meet our team.',
        '2018' => $csv_data['founding_date'] ?? '2020',
        '25' => $csv_data['team_size'] ?? '10',
        'Worldwide' => $csv_data['area_served'] ?? 'United States'
    );

    // Apply replacements
    foreach ($replacements as $search => $replace) {
        $template_content = str_replace($search, $replace, $template_content);
    }

    return $template_content;
}

/**
 * Get About template content (legacy or enhanced)
 */
function requestdesk_get_about_template() {
    // Try to load enhanced template first
    if (function_exists('requestdesk_get_about_aeo_template')) {
        return requestdesk_get_about_aeo_template();
    }

    // If enhanced template is not available, return a basic fallback
    return '<!-- wp:heading {"level":1,"style":{"color":{"text":"#ff0000"}}} -->
<h1 class="wp-block-heading has-text-color" style="color:#ff0000">🚀 AEO/GEO OPTIMIZED ABOUT PAGE TEMPLATE</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"background":"#fff3cd","text":"#856404"},"spacing":{"padding":{"top":"20px","bottom":"20px","left":"20px","right":"20px"}},"border":{"radius":"8px","color":"#ffeaa7","width":"1px"}}} -->
<p class="has-text-color has-background has-border-color" style="border-color:#ffeaa7;border-width:1px;border-radius:8px;background-color:#fff3cd;color:#856404;padding-top:20px;padding-bottom:20px;padding-left:20px;padding-right:20px"><strong>⚠️ Enhanced About Template Missing:</strong> The comprehensive AEO About template could not be loaded. This is a basic fallback. Please ensure aeo-template-about.php is properly installed.</p>
<!-- /wp:paragraph -->';
}

/**
 * Import Service Page with CSV data
 */
function requestdesk_import_service_page_csv($csv_data) {
    global $wpdb;

    // Map universal template fields to service page fields when present
    // This allows CSVs using the universal format (hero_headline, service_1_title, etc.)
    // to work with the service page builder
    $field_map = array(
        'title'            => 'hero_headline',
        'subtitle'         => 'hero_subheadline',
        'hero_tagline'     => 'company_tagline',
        'page_slug'        => 'hero_headline',  // will be sanitized into a slug
    );
    foreach ($field_map as $service_field => $universal_field) {
        if (empty($csv_data[$service_field]) && !empty($csv_data[$universal_field])) {
            $csv_data[$service_field] = $csv_data[$universal_field];
        }
    }

    // Map service blocks to sections
    for ($i = 1; $i <= 3; $i++) {
        if (empty($csv_data["section_{$i}_heading"]) && !empty($csv_data["service_{$i}_title"])) {
            $csv_data["section_{$i}_heading"] = $csv_data["service_{$i}_title"];
        }
        if (empty($csv_data["section_{$i}_content"]) && !empty($csv_data["service_{$i}_description"])) {
            $csv_data["section_{$i}_content"] = $csv_data["service_{$i}_description"];
        }
    }

    // Map about_description to section 4 if no section_4 exists
    if (empty($csv_data['section_4_heading']) && !empty($csv_data['about_description'])) {
        $csv_data['section_4_content'] = $csv_data['about_description'];
    }

    // Map FAQ content to section 5 if no section_5 exists
    if (empty($csv_data['section_5_heading'])) {
        $faq_html = '';
        for ($i = 1; $i <= 4; $i++) {
            $q = $csv_data["faq_{$i}_question"] ?? '';
            $a = $csv_data["faq_{$i}_answer"] ?? '';
            if (!empty($q) && !empty($a)) {
                $faq_html .= '<h3>' . esc_html($q) . '</h3><p>' . esc_html($a) . '</p>';
            }
        }
        if (!empty($faq_html)) {
            $csv_data['section_5_heading'] = 'Frequently Asked Questions';
            $csv_data['section_5_content'] = $faq_html;
        }
    }

    // Map hero CTA to bottom CTA section
    if (empty($csv_data['cta_heading']) && !empty($csv_data['hero_headline'])) {
        $csv_data['cta_heading'] = $csv_data['hero_cta_text'] ?? 'Get Started';
        $csv_data['cta_button_text'] = $csv_data['hero_cta_text'] ?? 'Get Started';
        $csv_data['cta_button_url'] = $csv_data['hero_cta_url'] ?? '#';
    }

    // Get page title and slug from CSV
    $page_title = sanitize_text_field($csv_data['title'] ?? 'Service Page');
    $page_slug = sanitize_title($csv_data['page_slug'] ?? 'service-page');

    // Check if page with this slug already exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'page' AND post_status != 'trash'",
        $page_slug
    ));

    if ($existing) {
        // Add timestamp to make unique
        $page_slug = $page_slug . '-' . current_time('Y-m-d-H-i');
    }

    // Build the page content using GenerateBlocks structure
    $template_content = requestdesk_build_service_page_content($csv_data);

    // Prepare page data
    $current_time = current_time('mysql');
    $current_time_gmt = current_time('mysql', 1);

    $page_data = array(
        'post_author' => get_current_user_id(),
        'post_date' => $current_time,
        'post_date_gmt' => $current_time_gmt,
        'post_content' => $template_content,
        'post_title' => $page_title,
        'post_excerpt' => sanitize_text_field($csv_data['meta_description'] ?? ''),
        'post_status' => 'draft',
        'comment_status' => 'closed',
        'ping_status' => 'closed',
        'post_password' => '',
        'post_name' => $page_slug,
        'to_ping' => '',
        'pinged' => '',
        'post_modified' => $current_time,
        'post_modified_gmt' => $current_time_gmt,
        'post_content_filtered' => '',
        'post_parent' => 0,
        'guid' => '',
        'menu_order' => 0,
        'post_type' => 'page',
        'post_mime_type' => '',
        'comment_count' => 0
    );

    // Insert the page
    $result = $wpdb->insert($wpdb->posts, $page_data);

    if ($result !== false) {
        $page_id = $wpdb->insert_id;

        // Update GUID
        $wpdb->update(
            $wpdb->posts,
            array('guid' => get_permalink($page_id)),
            array('ID' => $page_id)
        );

        // Set page to GP Canvas template for full-width layout
        update_post_meta($page_id, '_wp_page_template', 'page-builder-canvas.php');

        // Set GeneratePress layout options for full-width
        update_post_meta($page_id, '_generate_sidebar_layout', 'no-sidebar');
        update_post_meta($page_id, '_generate_content_width', 'full-width');

        // Auto-enable landing page styling (removes header, enables full-width hero)
        update_post_meta($page_id, '_requestdesk_landing_page', true);

        // Set Yoast SEO meta if available
        if (!empty($csv_data['meta_title'])) {
            update_post_meta($page_id, '_yoast_wpseo_title', sanitize_text_field($csv_data['meta_title']));
        }
        if (!empty($csv_data['meta_description'])) {
            update_post_meta($page_id, '_yoast_wpseo_metadesc', sanitize_text_field($csv_data['meta_description']));
        }

        return array(
            'success' => true,
            'page_id' => $page_id,
            'page_title' => $page_title,
            'template_name' => 'Service Page Template'
        );
    } else {
        return array(
            'success' => false,
            'message' => 'Database insertion failed: ' . $wpdb->last_error
        );
    }
}

/**
 * Build Service Page Content from CSV data
 * Uses native WordPress Group blocks for maximum compatibility
 */
function requestdesk_build_service_page_content($csv_data) {
    $content = '';

    // Hero Section - Dark navy background
    $hero_tagline = esc_html($csv_data['hero_tagline'] ?? '');
    $title = esc_html($csv_data['title'] ?? 'Service Page');
    $subtitle = esc_html($csv_data['subtitle'] ?? '');
    $hero_cta_text = esc_html($csv_data['hero_cta_text'] ?? 'Get Started');
    $hero_cta_url = esc_url($csv_data['hero_cta_url'] ?? '#');
    $hero_image_url = esc_url($csv_data['hero_image_url'] ?? '');
    $hero_image_alt = esc_attr($csv_data['hero_image_alt'] ?? '');

    // Hero using wp:cover for full-width background
    $content .= '<!-- wp:cover {"customOverlayColor":"#1e3a5f","isUserOverlayColor":true,"minHeight":500,"align":"full","style":{"spacing":{"padding":{"top":"80px","bottom":"80px","left":"40px","right":"40px"}}}} -->
<div class="wp-block-cover alignfull" style="padding-top:80px;padding-right:40px;padding-bottom:80px;padding-left:40px;min-height:500px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim" style="background-color:#1e3a5f"></span><div class="wp-block-cover__inner-container"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"width":"60%"} -->
<div class="wp-block-column" style="flex-basis:60%"><!-- wp:paragraph {"style":{"color":{"text":"#4ecdc4"}},"fontSize":"small"} -->
<p class="has-text-color has-small-font-size" style="color:#4ecdc4"><strong>' . $hero_tagline . '</strong></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"style":{"color":{"text":"#ffffff"}}} -->
<h1 class="wp-block-heading has-text-color" style="color:#ffffff">' . $title . '</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"text":"#ffffff"}}} -->
<p class="has-text-color" style="color:#ffffff">' . $subtitle . '</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"style":{"color":{"background":"#4ecdc4","text":"#ffffff"}}} -->
<div class="wp-block-button"><a class="wp-block-button__link has-text-color has-background wp-element-button" href="' . $hero_cta_url . '" style="color:#ffffff;background-color:#4ecdc4">' . $hero_cta_text . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"40%"} -->
<div class="wp-block-column" style="flex-basis:40%">';

    if (!empty($hero_image_url)) {
        $content .= '<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="' . $hero_image_url . '" alt="' . $hero_image_alt . '"/></figure>
<!-- /wp:image -->';
    }

    $content .= '</div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div></div>
<!-- /wp:cover -->';

    // Sections 1-5 with alternating backgrounds using wp:group
    $backgrounds = array(
        1 => '#ffffff',
        2 => '#f5f5f5',
        3 => '#ffffff',
        4 => '#f5f5f5',
        5 => '#ffffff',
    );

    for ($i = 1; $i <= 5; $i++) {
        $heading_key = "section_{$i}_heading";
        $content_key = "section_{$i}_content";

        if (!empty($csv_data[$heading_key]) || !empty($csv_data[$content_key])) {
            $section_heading = esc_html($csv_data[$heading_key] ?? '');
            $section_content = wp_kses_post($csv_data[$content_key] ?? '');
            $bg_color = $backgrounds[$i];

            $content .= '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"60px","bottom":"60px","left":"40px","right":"40px"}},"color":{"background":"' . $bg_color . '"}}} -->
<div class="wp-block-group alignfull has-background" style="background-color:' . $bg_color . ';padding-top:60px;padding-right:40px;padding-bottom:60px;padding-left:40px"><!-- wp:group {"layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group">';

            if (!empty($section_heading)) {
                $content .= '<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">' . $section_heading . '</h2>
<!-- /wp:heading -->';
            }

            if (!empty($section_content)) {
                // Convert HTML to WordPress blocks
                $content .= requestdesk_html_to_blocks($section_content);
            }

            $content .= '</div>
<!-- /wp:group --></div>
<!-- /wp:group -->';
        }
    }

    // CTA Section - Green background
    $cta_heading = esc_html($csv_data['cta_heading'] ?? '');
    $cta_text = esc_html($csv_data['cta_text'] ?? '');
    $cta_button_text = esc_html($csv_data['cta_button_text'] ?? 'Get Started');
    $cta_button_url = esc_url($csv_data['cta_button_url'] ?? '#');

    if (!empty($cta_heading) || !empty($cta_text)) {
        $content .= '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"60px","bottom":"60px","left":"40px","right":"40px"}},"color":{"background":"#4ecdc4"}}} -->
<div class="wp-block-group alignfull has-background" style="background-color:#4ecdc4;padding-top:60px;padding-right:40px;padding-bottom:60px;padding-left:40px"><!-- wp:group {"layout":{"type":"constrained","contentSize":"800px"}} -->
<div class="wp-block-group"><!-- wp:heading {"textAlign":"center","level":2,"style":{"color":{"text":"#1e3a5f"}}} -->
<h2 class="wp-block-heading has-text-align-center has-text-color" style="color:#1e3a5f">' . $cta_heading . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"color":{"text":"#1e3a5f"}}} -->
<p class="has-text-align-center has-text-color" style="color:#1e3a5f">' . $cta_text . '</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"style":{"color":{"background":"#ffffff","text":"#1e3a5f"}}} -->
<div class="wp-block-button"><a class="wp-block-button__link has-text-color has-background wp-element-button" href="' . $cta_button_url . '" style="color:#1e3a5f;background-color:#ffffff">' . $cta_button_text . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->';
    }

    return $content;
}

/**
 * Convert HTML content to WordPress blocks
 * Handles <p>, <ul>, <ol>, <h2>, <h3>, <h4> tags
 */
function requestdesk_html_to_blocks($html) {
    $output = '';

    // If already WordPress blocks, return as-is
    if (strpos(trim($html), '<!-- wp:') === 0) {
        return $html;
    }

    // Use DOMDocument to parse HTML
    $dom = new DOMDocument();
    // Suppress warnings for HTML5 tags and encode entities
    @$dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $body = $dom->getElementsByTagName('div')->item(0);
    if (!$body) {
        // Fallback: wrap in paragraph
        return '<!-- wp:paragraph -->
<p>' . $html . '</p>
<!-- /wp:paragraph -->';
    }

    foreach ($body->childNodes as $node) {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = trim($node->textContent);
            if (!empty($text)) {
                $output .= '<!-- wp:paragraph -->
<p>' . esc_html($text) . '</p>
<!-- /wp:paragraph -->

';
            }
            continue;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }

        $tagName = strtolower($node->nodeName);
        $innerHTML = '';
        foreach ($node->childNodes as $child) {
            $innerHTML .= $dom->saveHTML($child);
        }

        switch ($tagName) {
            case 'p':
                $output .= '<!-- wp:paragraph -->
<p>' . $innerHTML . '</p>
<!-- /wp:paragraph -->

';
                break;

            case 'h2':
                $output .= '<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">' . $innerHTML . '</h2>
<!-- /wp:heading -->

';
                break;

            case 'h3':
                $output .= '<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">' . $innerHTML . '</h3>
<!-- /wp:heading -->

';
                break;

            case 'h4':
                $output .= '<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">' . $innerHTML . '</h4>
<!-- /wp:heading -->

';
                break;

            case 'ul':
                $output .= '<!-- wp:list -->
<ul class="wp-block-list">' . $innerHTML . '</ul>
<!-- /wp:list -->

';
                break;

            case 'ol':
                $output .= '<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list">' . $innerHTML . '</ol>
<!-- /wp:list -->

';
                break;

            default:
                // Wrap unknown tags in paragraph
                $output .= '<!-- wp:paragraph -->
<p>' . $dom->saveHTML($node) . '</p>
<!-- /wp:paragraph -->

';
                break;
        }
    }

    return $output;
}

/**
 * Import GenerateBlocks Landing Page from CSV data
 * Creates a draft page with all applicable GB V2 sections
 */
function requestdesk_import_generateblocks_csv($csv_data) {
    global $wpdb;

    // Get page title and slug from CSV
    $page_title = sanitize_text_field($csv_data['page_title'] ?? 'GenerateBlocks Landing Page');
    $page_slug = sanitize_title($csv_data['page_slug'] ?? 'gb-landing-page');

    // Check if page with this slug already exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'page' AND post_status != 'trash'",
        $page_slug
    ));

    if ($existing) {
        // Add timestamp to make unique
        $page_slug = $page_slug . '-' . current_time('Y-m-d-H-i');
    }

    // Build the page content using GenerateBlocks V2 structure
    $template_content = requestdesk_build_generateblocks_page_content($csv_data);

    // Prepare page data
    $current_time = current_time('mysql');
    $current_time_gmt = current_time('mysql', 1);

    $page_data = array(
        'post_author' => get_current_user_id(),
        'post_date' => $current_time,
        'post_date_gmt' => $current_time_gmt,
        'post_content' => $template_content,
        'post_title' => $page_title,
        'post_excerpt' => sanitize_text_field($csv_data['meta_description'] ?? ''),
        'post_status' => 'draft',
        'comment_status' => 'closed',
        'ping_status' => 'closed',
        'post_password' => '',
        'post_name' => $page_slug,
        'to_ping' => '',
        'pinged' => '',
        'post_modified' => $current_time,
        'post_modified_gmt' => $current_time_gmt,
        'post_content_filtered' => '',
        'post_parent' => 0,
        'guid' => '',
        'menu_order' => 0,
        'post_type' => 'page',
        'post_mime_type' => '',
        'comment_count' => 0
    );

    // Insert the page
    $result = $wpdb->insert($wpdb->posts, $page_data);

    if ($result !== false) {
        $page_id = $wpdb->insert_id;

        // Update GUID
        $wpdb->update(
            $wpdb->posts,
            array('guid' => get_permalink($page_id)),
            array('ID' => $page_id)
        );

        // Set page to GP Canvas template for full-width layout
        update_post_meta($page_id, '_wp_page_template', 'page-builder-canvas.php');

        // Set GeneratePress layout options for full-width
        update_post_meta($page_id, '_generate_sidebar_layout', 'no-sidebar');
        update_post_meta($page_id, '_generate_content_width', 'full-width');

        // Auto-enable landing page styling (removes header, enables full-width hero)
        update_post_meta($page_id, '_requestdesk_landing_page', true);

        // Set Yoast SEO meta if available
        if (!empty($csv_data['meta_title'])) {
            update_post_meta($page_id, '_yoast_wpseo_title', sanitize_text_field($csv_data['meta_title']));
        }
        if (!empty($csv_data['meta_description'])) {
            update_post_meta($page_id, '_yoast_wpseo_metadesc', sanitize_text_field($csv_data['meta_description']));
        }

        return array(
            'success' => true,
            'page_id' => $page_id,
            'page_title' => $page_title,
            'template_name' => 'GenerateBlocks Landing Page'
        );
    } else {
        return array(
            'success' => false,
            'message' => 'Database insertion failed: ' . $wpdb->last_error
        );
    }
}

/**
 * Build GenerateBlocks Landing Page Content from CSV data
 * Uses GenerateBlocks V2 block markup for all sections
 *
 * Sections (rendered if CSV data is present):
 *   1. Hero (hero)
 *   2. Stats (stat)
 *   3. Feature List (feat)
 *   4. Card Grid (card)
 *   5. Services (serv)
 *   6. Pricing (pric) - from template file
 *   7. Testimonials (test)
 *   8. FAQ (faq0)
 *   9. Comparison Table (comp)
 *  10. Timeline (time)
 *  11. Team Grid (team)
 *  12. CTA Banner (bcta)
 *  13. Text Section (txts)
 */
function requestdesk_build_generateblocks_page_content($csv_data) {
    $content = '';

    // =========================================================================
    // Section 1: Hero (dark background #0a0a0a)
    // =========================================================================
    if (!empty($csv_data['hero_headline'])) {
        $hero_headline = esc_html($csv_data['hero_headline']);
        $hero_subtitle_1 = esc_html($csv_data['hero_subtitle_1'] ?? '');
        $hero_subtitle_2 = esc_html($csv_data['hero_subtitle_2'] ?? '');
        $hero_subtitle_3 = esc_html($csv_data['hero_subtitle_3'] ?? '');
        $hero_cta_text = esc_html($csv_data['hero_cta_text'] ?? 'Get Started');
        $hero_cta_url = esc_url($csv_data['hero_cta_url'] ?? '#');

        // Hero outer section (full-width breakout)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"hero001","tagName":"section","styles":{"paddingTop":"5rem","paddingBottom":"5rem","backgroundColor":"#0a0a0a","width":"100vw","position":"relative","left":"50%","right":"50%","marginLeft":"-50vw","marginRight":"-50vw"},"css":".gb-element-hero001{padding-top:5rem;padding-bottom:5rem;background-color:#0a0a0a;width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw}"} -->
<section class="gb-element gb-element-hero001">';

        // Hero inner container (centered, max-width)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"hero002","tagName":"div","styles":{"maxWidth":"900px","marginLeft":"auto","marginRight":"auto","paddingLeft":"1.5rem","paddingRight":"1.5rem","textAlign":"center"},"css":".gb-element-hero002{max-width:900px;margin-left:auto;margin-right:auto;padding-left:1.5rem;padding-right:1.5rem;text-align:center}"} -->
<div class="gb-element gb-element-hero002">';

        // H1 Headline
        $content .= '<!-- wp:generateblocks/text {"uniqueId":"hero003a","tagName":"h1","styles":{"fontSize":"clamp(2rem, 5vw, 3.25rem)","fontWeight":"900","letterSpacing":"-0.03em","color":"#ffffff","marginBottom":"1.25rem","lineHeight":"1.1"},"css":".gb-text-hero003a{font-size:clamp(2rem, 5vw, 3.25rem);font-weight:900;letter-spacing:-0.03em;color:#ffffff;margin-bottom:1.25rem;line-height:1.1}"} -->
<h1 class="gb-text gb-text-hero003a">' . $hero_headline . '</h1>
<!-- /wp:generateblocks/text -->';

        // H2 Subtitle 1
        if (!empty($csv_data['hero_subtitle_1'])) {
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"hero004a","tagName":"h2","styles":{"fontSize":"clamp(1.25rem, 3vw, 1.75rem)","fontWeight":"600","color":"#ffffff","marginBottom":"1rem","lineHeight":"1.3"},"css":".gb-text-hero004a{font-size:clamp(1.25rem, 3vw, 1.75rem);font-weight:600;color:#ffffff;margin-bottom:1rem;line-height:1.3}"} -->
<h2 class="gb-text gb-text-hero004a">' . $hero_subtitle_1 . '</h2>
<!-- /wp:generateblocks/text -->';
        }

        // P Subtitle 2
        if (!empty($csv_data['hero_subtitle_2'])) {
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"hero005a","tagName":"p","styles":{"fontSize":"1.125rem","color":"rgba(255,255,255,0.8)","marginBottom":"0.75rem","lineHeight":"1.6","maxWidth":"700px","marginLeft":"auto","marginRight":"auto"},"css":".gb-text-hero005a{font-size:1.125rem;color:rgba(255,255,255,0.8);margin-bottom:0.75rem;line-height:1.6;max-width:700px;margin-left:auto;margin-right:auto}"} -->
<p class="gb-text gb-text-hero005a">' . $hero_subtitle_2 . '</p>
<!-- /wp:generateblocks/text -->';
        }

        // P Subtitle 3
        if (!empty($csv_data['hero_subtitle_3'])) {
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"hero006a","tagName":"p","styles":{"fontSize":"1rem","color":"rgba(255,255,255,0.65)","marginBottom":"2rem","lineHeight":"1.6","maxWidth":"650px","marginLeft":"auto","marginRight":"auto"},"css":".gb-text-hero006a{font-size:1rem;color:rgba(255,255,255,0.65);margin-bottom:2rem;line-height:1.6;max-width:650px;margin-left:auto;margin-right:auto}"} -->
<p class="gb-text gb-text-hero006a">' . $hero_subtitle_3 . '</p>
<!-- /wp:generateblocks/text -->';
        }

        // CTA Button
        if (!empty($csv_data['hero_cta_text'])) {
            $content .= '<!-- wp:generateblocks/element {"uniqueId":"hero007","tagName":"div","styles":{"textAlign":"center","marginTop":"1.5rem"},"css":".gb-element-hero007{text-align:center;margin-top:1.5rem}"} -->
<div class="gb-element gb-element-hero007">';

            $content .= '<!-- wp:generateblocks/text {"uniqueId":"hero007a","tagName":"a","htmlAttributes":[{"attribute":"href","value":"' . $hero_cta_url . '"}],"styles":{"display":"inline-block","padding":"1rem 2.5rem","backgroundColor":"#c0392b","color":"#ffffff","borderRadius":"2rem","fontSize":"1.125rem","fontWeight":"700","textDecoration":"none","letterSpacing":"0.01em"},"css":".gb-text-hero007a{display:inline-block;padding:1rem 2.5rem;background-color:#c0392b;color:#ffffff;border-radius:2rem;font-size:1.125rem;font-weight:700;text-decoration:none;letter-spacing:0.01em;transition:all 0.3s}.gb-text-hero007a:hover{background-color:#a33024;transform:translateY(-2px);box-shadow:0 4px 12px rgba(192,57,43,0.3)}"} -->
<a class="gb-text gb-text-hero007a" href="' . $hero_cta_url . '">' . $hero_cta_text . '</a>
<!-- /wp:generateblocks/text -->';

            $content .= '</div>
<!-- /wp:generateblocks/element -->';
        }

        // Close hero inner container
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close hero section
        $content .= '</section>
<!-- /wp:generateblocks/element -->';
    }

    // =========================================================================
    // Section 2: Stats (dark background #0a0a0a, grid of stat cards)
    // =========================================================================
    $has_stats = false;
    for ($i = 1; $i <= 4; $i++) {
        if (!empty($csv_data["stat_{$i}_number"])) {
            $has_stats = true;
            break;
        }
    }

    if ($has_stats) {
        // Count stats to determine grid layout
        $stat_count = 0;
        for ($i = 1; $i <= 4; $i++) {
            if (!empty($csv_data["stat_{$i}_number"])) {
                $stat_count++;
            }
        }
        $stat_grid_cols = 'repeat(' . $stat_count . ', 1fr)';

        // Stats outer section (full-width breakout)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"stat001","tagName":"section","styles":{"paddingTop":"4rem","paddingBottom":"4rem","backgroundColor":"#0a0a0a","width":"100vw","position":"relative","left":"50%","right":"50%","marginLeft":"-50vw","marginRight":"-50vw"},"css":".gb-element-stat001{padding-top:4rem;padding-bottom:4rem;background-color:#0a0a0a;width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw}"} -->
<section class="gb-element gb-element-stat001">';

        // Stats inner container
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"stat002","tagName":"div","styles":{"maxWidth":"1200px","marginLeft":"auto","marginRight":"auto","paddingLeft":"1.5rem","paddingRight":"1.5rem"},"css":".gb-element-stat002{max-width:1200px;margin-left:auto;margin-right:auto;padding-left:1.5rem;padding-right:1.5rem}"} -->
<div class="gb-element gb-element-stat002">';

        // Stats grid (adapts columns to number of stats, 2 columns mobile)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"stat003","tagName":"div","styles":{"display":"grid","gridTemplateColumns":"' . $stat_grid_cols . '","gap":"1.5rem"},"css":".gb-element-stat003{display:grid;grid-template-columns:' . $stat_grid_cols . ';gap:1.5rem}@media(max-width:768px){.gb-element-stat003{grid-template-columns:repeat(2, 1fr)}}"} -->
<div class="gb-element gb-element-stat003">';

        // Stat cards
        for ($i = 1; $i <= 4; $i++) {
            $stat_number = esc_html($csv_data["stat_{$i}_number"] ?? '');
            $stat_label = esc_html($csv_data["stat_{$i}_label"] ?? '');

            if (empty($stat_number)) {
                continue;
            }

            $card_id = 'stat' . str_pad($i + 3, 3, '0', STR_PAD_LEFT);

            // Stat card with glass effect
            $content .= '<!-- wp:generateblocks/element {"uniqueId":"' . $card_id . '","tagName":"div","styles":{"backgroundColor":"rgba(255,255,255,0.05)","border":"1px solid rgba(255,255,255,0.1)","borderRadius":"1rem","padding":"2rem 1.5rem","textAlign":"center"},"css":".gb-element-' . $card_id . '{background-color:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:1rem;padding:2rem 1.5rem;text-align:center;transition:all 0.3s}.gb-element-' . $card_id . ':hover{transform:translateY(-4px);background-color:rgba(255,255,255,0.08);box-shadow:0 8px 32px rgba(0,0,0,0.3)}"} -->
<div class="gb-element gb-element-' . $card_id . '">';

            // Stat number (large, accent color)
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $card_id . 'a","tagName":"span","styles":{"display":"block","fontSize":"clamp(2rem, 4vw, 2.75rem)","fontWeight":"900","color":"#c0392b","marginBottom":"0.5rem","letterSpacing":"-0.02em","lineHeight":"1.1"},"css":".gb-text-' . $card_id . 'a{display:block;font-size:clamp(2rem, 4vw, 2.75rem);font-weight:900;color:#c0392b;margin-bottom:0.5rem;letter-spacing:-0.02em;line-height:1.1}"} -->
<span class="gb-text gb-text-' . $card_id . 'a">' . $stat_number . '</span>
<!-- /wp:generateblocks/text -->';

            // Stat label (small, uppercase, muted white)
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $card_id . 'b","tagName":"span","styles":{"display":"block","fontSize":"0.8125rem","fontWeight":"600","color":"rgba(255,255,255,0.6)","textTransform":"uppercase","letterSpacing":"0.08em"},"css":".gb-text-' . $card_id . 'b{display:block;font-size:0.8125rem;font-weight:600;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:0.08em}"} -->
<span class="gb-text gb-text-' . $card_id . 'b">' . $stat_label . '</span>
<!-- /wp:generateblocks/text -->';

            // Close stat card
            $content .= '</div>
<!-- /wp:generateblocks/element -->';
        }

        // Close stats grid
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close inner container
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close stats section
        $content .= '</section>
<!-- /wp:generateblocks/element -->';
    }

    // =========================================================================
    // Section 3: Feature List (light bg #f5f5f3, 3-column grid with images)
    // =========================================================================
    $features_heading = esc_html($csv_data['features_heading'] ?? '');
    $has_features = false;
    for ($i = 1; $i <= 4; $i++) {
        if (!empty($csv_data["feature_{$i}_title"])) {
            $has_features = true;
            break;
        }
    }

    if ($has_features) {
        // Count features to determine grid layout
        $feat_count = 0;
        for ($i = 1; $i <= 4; $i++) {
            if (!empty($csv_data["feature_{$i}_title"])) {
                $feat_count++;
            }
        }
        $grid_cols = ($feat_count > 3) ? 'repeat(2, 1fr)' : 'repeat(3, 1fr)';
        $grid_cols_css = ($feat_count > 3) ? 'repeat(2, 1fr)' : 'repeat(3, 1fr)';

        // Features outer section (full-width breakout)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"feat001","tagName":"section","styles":{"paddingTop":"4rem","paddingBottom":"4rem","backgroundColor":"#f5f5f3","width":"100vw","position":"relative","left":"50%","right":"50%","marginLeft":"-50vw","marginRight":"-50vw"},"css":".gb-element-feat001{padding-top:4rem;padding-bottom:4rem;background-color:#f5f5f3;width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw}"} -->
<section class="gb-element gb-element-feat001">';

        // Features inner container
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"feat002","tagName":"div","styles":{"maxWidth":"1200px","marginLeft":"auto","marginRight":"auto","paddingLeft":"1.5rem","paddingRight":"1.5rem"},"css":".gb-element-feat002{max-width:1200px;margin-left:auto;margin-right:auto;padding-left:1.5rem;padding-right:1.5rem}"} -->
<div class="gb-element gb-element-feat002">';

        // Section heading
        if (!empty($features_heading)) {
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"feat002a","tagName":"h2","styles":{"fontSize":"clamp(1.75rem, 4vw, 2.5rem)","fontWeight":"900","letterSpacing":"-0.03em","color":"#0a0a0a","textAlign":"center","marginBottom":"2.5rem"},"css":".gb-text-feat002a{font-size:clamp(1.75rem, 4vw, 2.5rem);font-weight:900;letter-spacing:-0.03em;color:#0a0a0a;text-align:center;margin-bottom:2.5rem}"} -->
<h2 class="gb-text gb-text-feat002a">' . $features_heading . '</h2>
<!-- /wp:generateblocks/text -->';
        }

        // Responsive grid (2x2 for 4 items, 3-col for 3 or fewer)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"feat003","tagName":"div","styles":{"display":"grid","gridTemplateColumns":"' . $grid_cols . '","gap":"2rem"},"css":".gb-element-feat003{display:grid;grid-template-columns:' . $grid_cols_css . ';gap:2rem}@media(max-width:768px){.gb-element-feat003{grid-template-columns:1fr}}"} -->
<div class="gb-element gb-element-feat003">';

        // Feature cards
        for ($i = 1; $i <= 4; $i++) {
            $feat_title = esc_html($csv_data["feature_{$i}_title"] ?? '');
            $feat_desc = esc_html($csv_data["feature_{$i}_description"] ?? '');
            $feat_img = esc_url($csv_data["feature_{$i}_image_url"] ?? '');

            if (empty($feat_title) && empty($feat_desc)) {
                continue;
            }

            $card_id = 'feat' . str_pad($i + 3, 3, '0', STR_PAD_LEFT);

            // Card container
            $content .= '<!-- wp:generateblocks/element {"uniqueId":"' . $card_id . '","tagName":"div","styles":{"backgroundColor":"#ffffff","borderRadius":"1rem","overflow":"hidden","boxShadow":"0 1px 3px rgba(0,0,0,0.08)"},"css":".gb-element-' . $card_id . '{background-color:#ffffff;border-radius:1rem;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);transition:all 0.3s}.gb-element-' . $card_id . ':hover{transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,0,0,0.12)}"} -->
<div class="gb-element gb-element-' . $card_id . '">';

            // Card image
            if (!empty($feat_img)) {
                $feat_img_alt = esc_attr($csv_data["feature_{$i}_title"] ?? '');
                $content .= '<!-- wp:generateblocks/element {"uniqueId":"' . $card_id . 'img","tagName":"div","styles":{"overflow":"hidden","height":"220px"},"css":".gb-element-' . $card_id . 'img{overflow:hidden;height:220px}.gb-element-' . $card_id . 'img img{width:100%;height:100%;object-fit:cover}"} -->
<div class="gb-element gb-element-' . $card_id . 'img"><img src="' . $feat_img . '" alt="' . $feat_img_alt . '" style="width:100%;height:100%;object-fit:cover" /></div>
<!-- /wp:generateblocks/element -->';
            }

            // Card text container
            $content .= '<!-- wp:generateblocks/element {"uniqueId":"' . $card_id . 'txt","tagName":"div","styles":{"padding":"1.5rem"},"css":".gb-element-' . $card_id . 'txt{padding:1.5rem}"} -->
<div class="gb-element gb-element-' . $card_id . 'txt">';

            // Card title
            if (!empty($feat_title)) {
                $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $card_id . 'a","tagName":"h3","styles":{"fontSize":"1.25rem","fontWeight":"700","color":"#0a0a0a","marginBottom":"0.75rem"},"css":".gb-text-' . $card_id . 'a{font-size:1.25rem;font-weight:700;color:#0a0a0a;margin-bottom:0.75rem}"} -->
<h3 class="gb-text gb-text-' . $card_id . 'a">' . $feat_title . '</h3>
<!-- /wp:generateblocks/text -->';
            }

            // Card description
            if (!empty($feat_desc)) {
                $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $card_id . 'b","tagName":"p","styles":{"fontSize":"0.9375rem","color":"#5c5c5c","lineHeight":"1.6","marginBottom":"0"},"css":".gb-text-' . $card_id . 'b{font-size:0.9375rem;color:#5c5c5c;line-height:1.6;margin-bottom:0}"} -->
<p class="gb-text gb-text-' . $card_id . 'b">' . $feat_desc . '</p>
<!-- /wp:generateblocks/text -->';
            }

            // Close card text container
            $content .= '</div>
<!-- /wp:generateblocks/element -->';

            // Close card
            $content .= '</div>
<!-- /wp:generateblocks/element -->';
        }

        // Close grid
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close inner container
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close features section
        $content .= '</section>
<!-- /wp:generateblocks/element -->';
    }

    // =========================================================================
    // Section 4: Card Grid (white bg #ffffff, 3-column simple cards)
    // =========================================================================
    $cards_heading = esc_html($csv_data['cards_heading'] ?? '');
    $has_cards = false;
    for ($i = 1; $i <= 3; $i++) {
        if (!empty($csv_data["card_{$i}_title"])) {
            $has_cards = true;
            break;
        }
    }

    if ($has_cards || !empty($cards_heading)) {
        // Cards outer section (full-width breakout)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"card001","tagName":"section","styles":{"paddingTop":"4rem","paddingBottom":"4rem","backgroundColor":"#ffffff","width":"100vw","position":"relative","left":"50%","right":"50%","marginLeft":"-50vw","marginRight":"-50vw"},"css":".gb-element-card001{padding-top:4rem;padding-bottom:4rem;background-color:#ffffff;width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw}"} -->
<section class="gb-element gb-element-card001">';

        // Cards inner container
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"card002","tagName":"div","styles":{"maxWidth":"1200px","marginLeft":"auto","marginRight":"auto","paddingLeft":"1.5rem","paddingRight":"1.5rem"},"css":".gb-element-card002{max-width:1200px;margin-left:auto;margin-right:auto;padding-left:1.5rem;padding-right:1.5rem}"} -->
<div class="gb-element gb-element-card002">';

        // Section heading
        if (!empty($cards_heading)) {
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"card002a","tagName":"h2","styles":{"fontSize":"clamp(1.75rem, 4vw, 2.5rem)","fontWeight":"900","letterSpacing":"-0.03em","color":"#0a0a0a","textAlign":"center","marginBottom":"2.5rem"},"css":".gb-text-card002a{font-size:clamp(1.75rem, 4vw, 2.5rem);font-weight:900;letter-spacing:-0.03em;color:#0a0a0a;text-align:center;margin-bottom:2.5rem}"} -->
<h2 class="gb-text gb-text-card002a">' . $cards_heading . '</h2>
<!-- /wp:generateblocks/text -->';
        }

        // 3-column grid
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"card003","tagName":"div","styles":{"display":"grid","gridTemplateColumns":"repeat(3, 1fr)","gap":"2rem"},"css":".gb-element-card003{display:grid;grid-template-columns:repeat(3, 1fr);gap:2rem}@media(max-width:768px){.gb-element-card003{grid-template-columns:1fr}}"} -->
<div class="gb-element gb-element-card003">';

        // Card items
        for ($i = 1; $i <= 3; $i++) {
            $card_title = esc_html($csv_data["card_{$i}_title"] ?? '');
            $card_desc = esc_html($csv_data["card_{$i}_description"] ?? '');

            if (empty($card_title)) {
                continue;
            }

            $cid = 'card' . str_pad($i + 3, 3, '0', STR_PAD_LEFT);

            // Card container
            $content .= '<!-- wp:generateblocks/element {"uniqueId":"' . $cid . '","tagName":"div","styles":{"backgroundColor":"#ffffff","borderRadius":"1rem","padding":"2rem","boxShadow":"0 2px 8px rgba(0,0,0,0.06)","border":"1px solid #e8e8e8"},"css":".gb-element-' . $cid . '{background-color:#ffffff;border-radius:1rem;padding:2rem;box-shadow:0 2px 8px rgba(0,0,0,0.06);border:1px solid #e8e8e8;transition:all 0.3s}.gb-element-' . $cid . ':hover{transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,0,0,0.1)}"} -->
<div class="gb-element gb-element-' . $cid . '">';

            // Card title
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $cid . 'a","tagName":"h3","styles":{"fontSize":"1.25rem","fontWeight":"700","color":"#0a0a0a","marginBottom":"0.75rem"},"css":".gb-text-' . $cid . 'a{font-size:1.25rem;font-weight:700;color:#0a0a0a;margin-bottom:0.75rem}"} -->
<h3 class="gb-text gb-text-' . $cid . 'a">' . $card_title . '</h3>
<!-- /wp:generateblocks/text -->';

            // Card description
            if (!empty($card_desc)) {
                $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $cid . 'b","tagName":"p","styles":{"fontSize":"0.9375rem","color":"#5c5c5c","lineHeight":"1.6","marginBottom":"0"},"css":".gb-text-' . $cid . 'b{font-size:0.9375rem;color:#5c5c5c;line-height:1.6;margin-bottom:0}"} -->
<p class="gb-text gb-text-' . $cid . 'b">' . $card_desc . '</p>
<!-- /wp:generateblocks/text -->';
            }

            // Close card
            $content .= '</div>
<!-- /wp:generateblocks/element -->';
        }

        // Close grid
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close inner container
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close cards section
        $content .= '</section>
<!-- /wp:generateblocks/element -->';
    }

    // =========================================================================
    // Section 5: Services (light bg #f5f5f3, 3x2 grid)
    // =========================================================================
    $services_heading = esc_html($csv_data['services_heading'] ?? '');
    $has_services = false;
    for ($i = 1; $i <= 6; $i++) {
        if (!empty($csv_data["service_{$i}_title"])) {
            $has_services = true;
            break;
        }
    }

    if ($has_services || !empty($services_heading)) {
        // Services outer section (full-width breakout)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"serv001","tagName":"section","styles":{"paddingTop":"4rem","paddingBottom":"4rem","backgroundColor":"#f5f5f3","width":"100vw","position":"relative","left":"50%","right":"50%","marginLeft":"-50vw","marginRight":"-50vw"},"css":".gb-element-serv001{padding-top:4rem;padding-bottom:4rem;background-color:#f5f5f3;width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw}"} -->
<section class="gb-element gb-element-serv001">';

        // Services inner container
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"serv002","tagName":"div","styles":{"maxWidth":"1200px","marginLeft":"auto","marginRight":"auto","paddingLeft":"1.5rem","paddingRight":"1.5rem"},"css":".gb-element-serv002{max-width:1200px;margin-left:auto;margin-right:auto;padding-left:1.5rem;padding-right:1.5rem}"} -->
<div class="gb-element gb-element-serv002">';

        // Section heading
        if (!empty($services_heading)) {
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"serv002a","tagName":"h2","styles":{"fontSize":"clamp(1.75rem, 4vw, 2.5rem)","fontWeight":"900","letterSpacing":"-0.03em","color":"#0a0a0a","textAlign":"center","marginBottom":"2.5rem"},"css":".gb-text-serv002a{font-size:clamp(1.75rem, 4vw, 2.5rem);font-weight:900;letter-spacing:-0.03em;color:#0a0a0a;text-align:center;margin-bottom:2.5rem}"} -->
<h2 class="gb-text gb-text-serv002a">' . $services_heading . '</h2>
<!-- /wp:generateblocks/text -->';
        }

        // 3-column grid (2 rows = 6 cards)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"serv003","tagName":"div","styles":{"display":"grid","gridTemplateColumns":"repeat(3, 1fr)","gap":"1.5rem"},"css":".gb-element-serv003{display:grid;grid-template-columns:repeat(3, 1fr);gap:1.5rem}@media(max-width:768px){.gb-element-serv003{grid-template-columns:1fr}}"} -->
<div class="gb-element gb-element-serv003">';

        // Service cards
        for ($i = 1; $i <= 6; $i++) {
            $serv_title = esc_html($csv_data["service_{$i}_title"] ?? '');
            $serv_desc = esc_html($csv_data["service_{$i}_description"] ?? '');
            $serv_detail = esc_html($csv_data["service_{$i}_detail"] ?? '');

            if (empty($serv_title)) {
                continue;
            }

            $sid = 'serv' . str_pad($i + 3, 3, '0', STR_PAD_LEFT);

            // Card container with border
            $content .= '<!-- wp:generateblocks/element {"uniqueId":"' . $sid . '","tagName":"div","styles":{"backgroundColor":"#ffffff","borderRadius":"0.75rem","padding":"1.75rem","border":"1px solid #e5e5e5"},"css":".gb-element-' . $sid . '{background-color:#ffffff;border-radius:0.75rem;padding:1.75rem;border:1px solid #e5e5e5;transition:all 0.3s}.gb-element-' . $sid . ':hover{border-color:#c0392b;box-shadow:0 4px 16px rgba(0,0,0,0.08)}"} -->
<div class="gb-element gb-element-' . $sid . '">';

            // Card title
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $sid . 'a","tagName":"h3","styles":{"fontSize":"1.125rem","fontWeight":"700","color":"#0a0a0a","marginBottom":"0.75rem"},"css":".gb-text-' . $sid . 'a{font-size:1.125rem;font-weight:700;color:#0a0a0a;margin-bottom:0.75rem}"} -->
<h3 class="gb-text gb-text-' . $sid . 'a">' . $serv_title . '</h3>
<!-- /wp:generateblocks/text -->';

            // Card description
            if (!empty($serv_desc)) {
                $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $sid . 'b","tagName":"p","styles":{"fontSize":"0.9375rem","color":"#5c5c5c","lineHeight":"1.6","marginBottom":"0.5rem"},"css":".gb-text-' . $sid . 'b{font-size:0.9375rem;color:#5c5c5c;line-height:1.6;margin-bottom:0.5rem}"} -->
<p class="gb-text gb-text-' . $sid . 'b">' . $serv_desc . '</p>
<!-- /wp:generateblocks/text -->';
            }

            // Card detail
            if (!empty($serv_detail)) {
                $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $sid . 'c","tagName":"p","styles":{"fontSize":"0.875rem","color":"#7a7a7a","lineHeight":"1.5","marginBottom":"0","fontStyle":"italic"},"css":".gb-text-' . $sid . 'c{font-size:0.875rem;color:#7a7a7a;line-height:1.5;margin-bottom:0;font-style:italic}"} -->
<p class="gb-text gb-text-' . $sid . 'c">' . $serv_detail . '</p>
<!-- /wp:generateblocks/text -->';
            }

            // Close card
            $content .= '</div>
<!-- /wp:generateblocks/element -->';
        }

        // Close grid
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close inner container
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close services section
        $content .= '</section>
<!-- /wp:generateblocks/element -->';
    }

    // =========================================================================
    // Section 6: Testimonials (light bg #f5f5f3, 3-column grid)
    // =========================================================================
    $testimonials_heading = esc_html($csv_data['testimonials_heading'] ?? '');
    $has_testimonials = false;
    for ($i = 1; $i <= 3; $i++) {
        if (!empty($csv_data["testimonial_{$i}_quote"])) {
            $has_testimonials = true;
            break;
        }
    }

    if ($has_testimonials) {
        // Testimonials outer section (full-width breakout)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"test001","tagName":"section","styles":{"paddingTop":"4rem","paddingBottom":"4rem","backgroundColor":"#f5f5f3","width":"100vw","position":"relative","left":"50%","right":"50%","marginLeft":"-50vw","marginRight":"-50vw"},"css":".gb-element-test001{padding-top:4rem;padding-bottom:4rem;background-color:#f5f5f3;width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw}"} -->
<section class="gb-element gb-element-test001">';

        // Testimonials inner container
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"test002","tagName":"div","styles":{"maxWidth":"1200px","marginLeft":"auto","marginRight":"auto","paddingLeft":"1.5rem","paddingRight":"1.5rem"},"css":".gb-element-test002{max-width:1200px;margin-left:auto;margin-right:auto;padding-left:1.5rem;padding-right:1.5rem}"} -->
<div class="gb-element gb-element-test002">';

        // Section heading
        if (!empty($testimonials_heading)) {
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"test002a","tagName":"h2","styles":{"fontSize":"clamp(1.75rem, 4vw, 2.5rem)","fontWeight":"900","letterSpacing":"-0.03em","color":"#0a0a0a","textAlign":"center","marginBottom":"2.5rem"},"css":".gb-text-test002a{font-size:clamp(1.75rem, 4vw, 2.5rem);font-weight:900;letter-spacing:-0.03em;color:#0a0a0a;text-align:center;margin-bottom:2.5rem}"} -->
<h2 class="gb-text gb-text-test002a">' . $testimonials_heading . '</h2>
<!-- /wp:generateblocks/text -->';
        }

        // 3-column grid
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"test003","tagName":"div","styles":{"display":"grid","gridTemplateColumns":"repeat(3, 1fr)","gap":"2rem"},"css":".gb-element-test003{display:grid;grid-template-columns:repeat(3, 1fr);gap:2rem}@media(max-width:768px){.gb-element-test003{grid-template-columns:1fr}}"} -->
<div class="gb-element gb-element-test003">';

        // Testimonial cards
        for ($i = 1; $i <= 3; $i++) {
            $test_quote = esc_html($csv_data["testimonial_{$i}_quote"] ?? '');
            $test_author = esc_html($csv_data["testimonial_{$i}_author"] ?? '');
            $test_role = esc_html($csv_data["testimonial_{$i}_role"] ?? '');

            if (empty($test_quote)) {
                continue;
            }

            $tid = 'test' . str_pad($i + 3, 3, '0', STR_PAD_LEFT);

            // Testimonial card
            $content .= '<!-- wp:generateblocks/element {"uniqueId":"' . $tid . '","tagName":"div","styles":{"backgroundColor":"#ffffff","borderRadius":"1rem","padding":"2rem","boxShadow":"0 2px 8px rgba(0,0,0,0.06)","position":"relative"},"css":".gb-element-' . $tid . '{background-color:#ffffff;border-radius:1rem;padding:2rem;box-shadow:0 2px 8px rgba(0,0,0,0.06);position:relative}"} -->
<div class="gb-element gb-element-' . $tid . '">';

            // Quote mark decoration
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $tid . 'q","tagName":"span","styles":{"display":"block","fontSize":"3rem","color":"#c0392b","lineHeight":"1","marginBottom":"0.5rem","fontFamily":"Georgia, serif"},"css":".gb-text-' . $tid . 'q{display:block;font-size:3rem;color:#c0392b;line-height:1;margin-bottom:0.5rem;font-family:Georgia, serif}"} -->
<span class="gb-text gb-text-' . $tid . 'q">&ldquo;</span>
<!-- /wp:generateblocks/text -->';

            // Quote text
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $tid . 'a","tagName":"p","styles":{"fontSize":"1rem","color":"#333333","lineHeight":"1.7","marginBottom":"1.5rem","fontStyle":"italic"},"css":".gb-text-' . $tid . 'a{font-size:1rem;color:#333333;line-height:1.7;margin-bottom:1.5rem;font-style:italic}"} -->
<p class="gb-text gb-text-' . $tid . 'a">' . $test_quote . '</p>
<!-- /wp:generateblocks/text -->';

            // Author name
            if (!empty($test_author)) {
                $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $tid . 'b","tagName":"span","styles":{"display":"block","fontSize":"0.9375rem","fontWeight":"700","color":"#0a0a0a","marginBottom":"0.25rem"},"css":".gb-text-' . $tid . 'b{display:block;font-size:0.9375rem;font-weight:700;color:#0a0a0a;margin-bottom:0.25rem}"} -->
<span class="gb-text gb-text-' . $tid . 'b">' . $test_author . '</span>
<!-- /wp:generateblocks/text -->';
            }

            // Author role
            if (!empty($test_role)) {
                $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $tid . 'c","tagName":"span","styles":{"display":"block","fontSize":"0.8125rem","color":"#7a7a7a"},"css":".gb-text-' . $tid . 'c{display:block;font-size:0.8125rem;color:#7a7a7a}"} -->
<span class="gb-text gb-text-' . $tid . 'c">' . $test_role . '</span>
<!-- /wp:generateblocks/text -->';
            }

            // Close testimonial card
            $content .= '</div>
<!-- /wp:generateblocks/element -->';
        }

        // Close grid
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close inner container
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close testimonials section
        $content .= '</section>
<!-- /wp:generateblocks/element -->';
    }

    // =========================================================================
    // Section 8: FAQ (white bg #ffffff, 2-column grid)
    // =========================================================================
    $faq_heading = esc_html($csv_data['faq_heading'] ?? '');
    $has_faq = false;
    for ($i = 1; $i <= 6; $i++) {
        if (!empty($csv_data["faq_{$i}_question"])) {
            $has_faq = true;
            break;
        }
    }

    if ($has_faq) {
        // FAQ outer section (full-width breakout)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"faq0001","tagName":"section","styles":{"paddingTop":"4rem","paddingBottom":"4rem","backgroundColor":"#ffffff","width":"100vw","position":"relative","left":"50%","right":"50%","marginLeft":"-50vw","marginRight":"-50vw"},"css":".gb-element-faq0001{padding-top:4rem;padding-bottom:4rem;background-color:#ffffff;width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw}"} -->
<section class="gb-element gb-element-faq0001">';

        // FAQ inner container
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"faq0002","tagName":"div","styles":{"maxWidth":"1200px","marginLeft":"auto","marginRight":"auto","paddingLeft":"1.5rem","paddingRight":"1.5rem"},"css":".gb-element-faq0002{max-width:1200px;margin-left:auto;margin-right:auto;padding-left:1.5rem;padding-right:1.5rem}"} -->
<div class="gb-element gb-element-faq0002">';

        // Section heading
        if (!empty($faq_heading)) {
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"faq0002a","tagName":"h2","styles":{"fontSize":"clamp(1.75rem, 4vw, 2.5rem)","fontWeight":"900","letterSpacing":"-0.03em","color":"#0a0a0a","textAlign":"center","marginBottom":"2.5rem"},"css":".gb-text-faq0002a{font-size:clamp(1.75rem, 4vw, 2.5rem);font-weight:900;letter-spacing:-0.03em;color:#0a0a0a;text-align:center;margin-bottom:2.5rem}"} -->
<h2 class="gb-text gb-text-faq0002a">' . $faq_heading . '</h2>
<!-- /wp:generateblocks/text -->';
        }

        // 2-column grid
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"faq0003","tagName":"div","styles":{"display":"grid","gridTemplateColumns":"repeat(2, 1fr)","gap":"2rem"},"css":".gb-element-faq0003{display:grid;grid-template-columns:repeat(2, 1fr);gap:2rem}@media(max-width:768px){.gb-element-faq0003{grid-template-columns:1fr}}"} -->
<div class="gb-element gb-element-faq0003">';

        // FAQ items
        for ($i = 1; $i <= 6; $i++) {
            $faq_question = esc_html($csv_data["faq_{$i}_question"] ?? '');
            $faq_answer = esc_html($csv_data["faq_{$i}_answer"] ?? '');

            if (empty($faq_question)) {
                continue;
            }

            $fid = 'faq0' . str_pad($i + 3, 3, '0', STR_PAD_LEFT);

            // FAQ item container
            $content .= '<!-- wp:generateblocks/element {"uniqueId":"' . $fid . '","tagName":"div","styles":{"backgroundColor":"#f8f9fa","borderRadius":"0.75rem","padding":"1.75rem","border":"1px solid #e8e8e8"},"css":".gb-element-' . $fid . '{background-color:#f8f9fa;border-radius:0.75rem;padding:1.75rem;border:1px solid #e8e8e8}"} -->
<div class="gb-element gb-element-' . $fid . '">';

            // Question
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $fid . 'a","tagName":"h3","styles":{"fontSize":"1.0625rem","fontWeight":"700","color":"#0a0a0a","marginBottom":"0.75rem"},"css":".gb-text-' . $fid . 'a{font-size:1.0625rem;font-weight:700;color:#0a0a0a;margin-bottom:0.75rem}"} -->
<h3 class="gb-text gb-text-' . $fid . 'a">' . $faq_question . '</h3>
<!-- /wp:generateblocks/text -->';

            // Answer
            if (!empty($faq_answer)) {
                $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $fid . 'b","tagName":"p","styles":{"fontSize":"0.9375rem","color":"#5c5c5c","lineHeight":"1.6","marginBottom":"0"},"css":".gb-text-' . $fid . 'b{font-size:0.9375rem;color:#5c5c5c;line-height:1.6;margin-bottom:0}"} -->
<p class="gb-text gb-text-' . $fid . 'b">' . $faq_answer . '</p>
<!-- /wp:generateblocks/text -->';
            }

            // Close FAQ item
            $content .= '</div>
<!-- /wp:generateblocks/element -->';
        }

        // Close grid
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close inner container
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close FAQ section
        $content .= '</section>
<!-- /wp:generateblocks/element -->';
    }

    // =========================================================================
    // Section 9: Comparison Table (light bg #f5f5f3)
    // =========================================================================
    if (!empty($csv_data['comparison_col1_name'])) {
        $comp_heading = esc_html($csv_data['comparison_heading'] ?? '');
        $comp_col1 = esc_html($csv_data['comparison_col1_name']);
        $comp_col2 = esc_html($csv_data['comparison_col2_name'] ?? '');

        // Comparison outer section (full-width breakout)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"comp001","tagName":"section","styles":{"paddingTop":"4rem","paddingBottom":"4rem","backgroundColor":"#f5f5f3","width":"100vw","position":"relative","left":"50%","right":"50%","marginLeft":"-50vw","marginRight":"-50vw"},"css":".gb-element-comp001{padding-top:4rem;padding-bottom:4rem;background-color:#f5f5f3;width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw}"} -->
<section class="gb-element gb-element-comp001">';

        // Comparison inner container
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"comp002","tagName":"div","styles":{"maxWidth":"900px","marginLeft":"auto","marginRight":"auto","paddingLeft":"1.5rem","paddingRight":"1.5rem"},"css":".gb-element-comp002{max-width:900px;margin-left:auto;margin-right:auto;padding-left:1.5rem;padding-right:1.5rem}"} -->
<div class="gb-element gb-element-comp002">';

        // Section heading
        if (!empty($comp_heading)) {
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"comp002a","tagName":"h2","styles":{"fontSize":"clamp(1.75rem, 4vw, 2.5rem)","fontWeight":"900","letterSpacing":"-0.03em","color":"#0a0a0a","textAlign":"center","marginBottom":"2.5rem"},"css":".gb-text-comp002a{font-size:clamp(1.75rem, 4vw, 2.5rem);font-weight:900;letter-spacing:-0.03em;color:#0a0a0a;text-align:center;margin-bottom:2.5rem}"} -->
<h2 class="gb-text gb-text-comp002a">' . $comp_heading . '</h2>
<!-- /wp:generateblocks/text -->';
        }

        // Table container
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"comp003","tagName":"div","styles":{"backgroundColor":"#ffffff","borderRadius":"1rem","overflow":"hidden","boxShadow":"0 2px 8px rgba(0,0,0,0.06)"},"css":".gb-element-comp003{background-color:#ffffff;border-radius:1rem;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06)}"} -->
<div class="gb-element gb-element-comp003">';

        // Header row
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"comp004","tagName":"div","styles":{"display":"grid","gridTemplateColumns":"1fr 1fr 1fr","backgroundColor":"#0a0a0a","padding":"1rem 1.5rem"},"css":".gb-element-comp004{display:grid;grid-template-columns:1fr 1fr 1fr;background-color:#0a0a0a;padding:1rem 1.5rem}"} -->
<div class="gb-element gb-element-comp004">';

        // Feature header
        $content .= '<!-- wp:generateblocks/text {"uniqueId":"comp004a","tagName":"span","styles":{"fontSize":"0.875rem","fontWeight":"700","color":"#ffffff","textTransform":"uppercase","letterSpacing":"0.05em"},"css":".gb-text-comp004a{font-size:0.875rem;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.05em}"} -->
<span class="gb-text gb-text-comp004a">Feature</span>
<!-- /wp:generateblocks/text -->';

        // Col1 header
        $content .= '<!-- wp:generateblocks/text {"uniqueId":"comp004b","tagName":"span","styles":{"fontSize":"0.875rem","fontWeight":"700","color":"#ffffff","textTransform":"uppercase","letterSpacing":"0.05em","textAlign":"center"},"css":".gb-text-comp004b{font-size:0.875rem;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.05em;text-align:center}"} -->
<span class="gb-text gb-text-comp004b">' . $comp_col1 . '</span>
<!-- /wp:generateblocks/text -->';

        // Col2 header
        $content .= '<!-- wp:generateblocks/text {"uniqueId":"comp004c","tagName":"span","styles":{"fontSize":"0.875rem","fontWeight":"700","color":"#ffffff","textTransform":"uppercase","letterSpacing":"0.05em","textAlign":"center"},"css":".gb-text-comp004c{font-size:0.875rem;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.05em;text-align:center}"} -->
<span class="gb-text gb-text-comp004c">' . $comp_col2 . '</span>
<!-- /wp:generateblocks/text -->';

        // Close header row
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Data rows
        for ($i = 1; $i <= 6; $i++) {
            $row_feature = esc_html($csv_data["comparison_row_{$i}_feature"] ?? '');
            $row_col1 = esc_html($csv_data["comparison_row_{$i}_col1"] ?? '');
            $row_col2 = esc_html($csv_data["comparison_row_{$i}_col2"] ?? '');

            if (empty($row_feature)) {
                continue;
            }

            $rid = 'comp' . str_pad($i + 4, 3, '0', STR_PAD_LEFT);
            $bg_color = ($i % 2 === 0) ? '#f8f9fa' : '#ffffff';

            // Row
            $content .= '<!-- wp:generateblocks/element {"uniqueId":"' . $rid . '","tagName":"div","styles":{"display":"grid","gridTemplateColumns":"1fr 1fr 1fr","padding":"1rem 1.5rem","backgroundColor":"' . $bg_color . '","borderBottom":"1px solid #eee"},"css":".gb-element-' . $rid . '{display:grid;grid-template-columns:1fr 1fr 1fr;padding:1rem 1.5rem;background-color:' . $bg_color . ';border-bottom:1px solid #eee}"} -->
<div class="gb-element gb-element-' . $rid . '">';

            // Feature name
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $rid . 'a","tagName":"span","styles":{"fontSize":"0.9375rem","fontWeight":"600","color":"#0a0a0a"},"css":".gb-text-' . $rid . 'a{font-size:0.9375rem;font-weight:600;color:#0a0a0a}"} -->
<span class="gb-text gb-text-' . $rid . 'a">' . $row_feature . '</span>
<!-- /wp:generateblocks/text -->';

            // Col1 value
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $rid . 'b","tagName":"span","styles":{"fontSize":"0.9375rem","color":"#5c5c5c","textAlign":"center"},"css":".gb-text-' . $rid . 'b{font-size:0.9375rem;color:#5c5c5c;text-align:center}"} -->
<span class="gb-text gb-text-' . $rid . 'b">' . $row_col1 . '</span>
<!-- /wp:generateblocks/text -->';

            // Col2 value
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $rid . 'c","tagName":"span","styles":{"fontSize":"0.9375rem","color":"#5c5c5c","textAlign":"center"},"css":".gb-text-' . $rid . 'c{font-size:0.9375rem;color:#5c5c5c;text-align:center}"} -->
<span class="gb-text gb-text-' . $rid . 'c">' . $row_col2 . '</span>
<!-- /wp:generateblocks/text -->';

            // Close row
            $content .= '</div>
<!-- /wp:generateblocks/element -->';
        }

        // Close table container
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close inner container
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close comparison section
        $content .= '</section>
<!-- /wp:generateblocks/element -->';
    }

    // =========================================================================
    // Section 10: Timeline (white bg #ffffff, vertical timeline)
    // =========================================================================
    $timeline_heading = esc_html($csv_data['timeline_heading'] ?? '');
    $has_timeline = false;
    for ($i = 1; $i <= 6; $i++) {
        if (!empty($csv_data["timeline_{$i}_title"])) {
            $has_timeline = true;
            break;
        }
    }

    if ($has_timeline) {
        // Timeline outer section (full-width breakout)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"time001","tagName":"section","styles":{"paddingTop":"4rem","paddingBottom":"4rem","backgroundColor":"#ffffff","width":"100vw","position":"relative","left":"50%","right":"50%","marginLeft":"-50vw","marginRight":"-50vw"},"css":".gb-element-time001{padding-top:4rem;padding-bottom:4rem;background-color:#ffffff;width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw}"} -->
<section class="gb-element gb-element-time001">';

        // Timeline inner container
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"time002","tagName":"div","styles":{"maxWidth":"800px","marginLeft":"auto","marginRight":"auto","paddingLeft":"1.5rem","paddingRight":"1.5rem"},"css":".gb-element-time002{max-width:800px;margin-left:auto;margin-right:auto;padding-left:1.5rem;padding-right:1.5rem}"} -->
<div class="gb-element gb-element-time002">';

        // Section heading
        if (!empty($timeline_heading)) {
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"time002a","tagName":"h2","styles":{"fontSize":"clamp(1.75rem, 4vw, 2.5rem)","fontWeight":"900","letterSpacing":"-0.03em","color":"#0a0a0a","textAlign":"center","marginBottom":"2.5rem"},"css":".gb-text-time002a{font-size:clamp(1.75rem, 4vw, 2.5rem);font-weight:900;letter-spacing:-0.03em;color:#0a0a0a;text-align:center;margin-bottom:2.5rem}"} -->
<h2 class="gb-text gb-text-time002a">' . $timeline_heading . '</h2>
<!-- /wp:generateblocks/text -->';
        }

        // Timeline items container with vertical line
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"time003","tagName":"div","styles":{"position":"relative","paddingLeft":"2.5rem"},"css":".gb-element-time003{position:relative;padding-left:2.5rem}.gb-element-time003::before{content:\"\";position:absolute;left:0.75rem;top:0;bottom:0;width:2px;background-color:#e5e5e5}"} -->
<div class="gb-element gb-element-time003">';

        // Timeline items
        for ($i = 1; $i <= 6; $i++) {
            $time_title = esc_html($csv_data["timeline_{$i}_title"] ?? '');
            $time_desc = esc_html($csv_data["timeline_{$i}_description"] ?? '');
            $time_date = esc_html($csv_data["timeline_{$i}_date"] ?? '');

            if (empty($time_title)) {
                continue;
            }

            $tmid = 'time' . str_pad($i + 3, 3, '0', STR_PAD_LEFT);

            // Timeline item with dot
            $content .= '<!-- wp:generateblocks/element {"uniqueId":"' . $tmid . '","tagName":"div","styles":{"position":"relative","paddingBottom":"2rem","paddingLeft":"1rem"},"css":".gb-element-' . $tmid . '{position:relative;padding-bottom:2rem;padding-left:1rem}.gb-element-' . $tmid . '::before{content:\"\";position:absolute;left:-2rem;top:0.4rem;width:12px;height:12px;border-radius:50%;background-color:#c0392b;border:2px solid #ffffff;box-shadow:0 0 0 2px #c0392b}"} -->
<div class="gb-element gb-element-' . $tmid . '">';

            // Date label
            if (!empty($time_date)) {
                $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $tmid . 'c","tagName":"span","styles":{"display":"inline-block","fontSize":"0.75rem","fontWeight":"700","color":"#c0392b","textTransform":"uppercase","letterSpacing":"0.05em","marginBottom":"0.5rem","backgroundColor":"rgba(192,57,43,0.08)","padding":"0.25rem 0.75rem","borderRadius":"1rem"},"css":".gb-text-' . $tmid . 'c{display:inline-block;font-size:0.75rem;font-weight:700;color:#c0392b;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;background-color:rgba(192,57,43,0.08);padding:0.25rem 0.75rem;border-radius:1rem}"} -->
<span class="gb-text gb-text-' . $tmid . 'c">' . $time_date . '</span>
<!-- /wp:generateblocks/text -->';
            }

            // Title
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $tmid . 'a","tagName":"h3","styles":{"fontSize":"1.125rem","fontWeight":"700","color":"#0a0a0a","marginBottom":"0.5rem"},"css":".gb-text-' . $tmid . 'a{font-size:1.125rem;font-weight:700;color:#0a0a0a;margin-bottom:0.5rem}"} -->
<h3 class="gb-text gb-text-' . $tmid . 'a">' . $time_title . '</h3>
<!-- /wp:generateblocks/text -->';

            // Description
            if (!empty($time_desc)) {
                $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $tmid . 'b","tagName":"p","styles":{"fontSize":"0.9375rem","color":"#5c5c5c","lineHeight":"1.6","marginBottom":"0"},"css":".gb-text-' . $tmid . 'b{font-size:0.9375rem;color:#5c5c5c;line-height:1.6;margin-bottom:0}"} -->
<p class="gb-text gb-text-' . $tmid . 'b">' . $time_desc . '</p>
<!-- /wp:generateblocks/text -->';
            }

            // Close timeline item
            $content .= '</div>
<!-- /wp:generateblocks/element -->';
        }

        // Close timeline items container
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close inner container
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close timeline section
        $content .= '</section>
<!-- /wp:generateblocks/element -->';
    }

    // =========================================================================
    // Section 11: Team Grid (light bg #f5f5f3, card grid)
    // =========================================================================
    $team_heading = esc_html($csv_data['team_heading'] ?? '');
    $has_team = false;
    for ($i = 1; $i <= 6; $i++) {
        if (!empty($csv_data["team_{$i}_name"])) {
            $has_team = true;
            break;
        }
    }

    if ($has_team) {
        // Team outer section (full-width breakout)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"team001","tagName":"section","styles":{"paddingTop":"4rem","paddingBottom":"4rem","backgroundColor":"#f5f5f3","width":"100vw","position":"relative","left":"50%","right":"50%","marginLeft":"-50vw","marginRight":"-50vw"},"css":".gb-element-team001{padding-top:4rem;padding-bottom:4rem;background-color:#f5f5f3;width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw}"} -->
<section class="gb-element gb-element-team001">';

        // Team inner container
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"team002","tagName":"div","styles":{"maxWidth":"1200px","marginLeft":"auto","marginRight":"auto","paddingLeft":"1.5rem","paddingRight":"1.5rem"},"css":".gb-element-team002{max-width:1200px;margin-left:auto;margin-right:auto;padding-left:1.5rem;padding-right:1.5rem}"} -->
<div class="gb-element gb-element-team002">';

        // Section heading
        if (!empty($team_heading)) {
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"team002a","tagName":"h2","styles":{"fontSize":"clamp(1.75rem, 4vw, 2.5rem)","fontWeight":"900","letterSpacing":"-0.03em","color":"#0a0a0a","textAlign":"center","marginBottom":"2.5rem"},"css":".gb-text-team002a{font-size:clamp(1.75rem, 4vw, 2.5rem);font-weight:900;letter-spacing:-0.03em;color:#0a0a0a;text-align:center;margin-bottom:2.5rem}"} -->
<h2 class="gb-text gb-text-team002a">' . $team_heading . '</h2>
<!-- /wp:generateblocks/text -->';
        }

        // 3-column grid
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"team003","tagName":"div","styles":{"display":"grid","gridTemplateColumns":"repeat(3, 1fr)","gap":"2rem"},"css":".gb-element-team003{display:grid;grid-template-columns:repeat(3, 1fr);gap:2rem}@media(max-width:768px){.gb-element-team003{grid-template-columns:1fr}}"} -->
<div class="gb-element gb-element-team003">';

        // Team member cards
        for ($i = 1; $i <= 6; $i++) {
            $team_name = esc_html($csv_data["team_{$i}_name"] ?? '');
            $team_role = esc_html($csv_data["team_{$i}_role"] ?? '');
            $team_img = esc_url($csv_data["team_{$i}_image_url"] ?? '');

            if (empty($team_name)) {
                continue;
            }

            $mid = 'team' . str_pad($i + 3, 3, '0', STR_PAD_LEFT);

            // Team member card
            $content .= '<!-- wp:generateblocks/element {"uniqueId":"' . $mid . '","tagName":"div","styles":{"backgroundColor":"#ffffff","borderRadius":"1rem","overflow":"hidden","boxShadow":"0 2px 8px rgba(0,0,0,0.06)","textAlign":"center"},"css":".gb-element-' . $mid . '{background-color:#ffffff;border-radius:1rem;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);text-align:center;transition:all 0.3s}.gb-element-' . $mid . ':hover{transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,0,0,0.1)}"} -->
<div class="gb-element gb-element-' . $mid . '">';

            // Team member image
            if (!empty($team_img)) {
                $team_img_alt = esc_attr($csv_data["team_{$i}_name"] ?? '');
                $content .= '<!-- wp:generateblocks/element {"uniqueId":"' . $mid . 'img","tagName":"div","styles":{"overflow":"hidden","height":"250px"},"css":".gb-element-' . $mid . 'img{overflow:hidden;height:250px}.gb-element-' . $mid . 'img img{width:100%;height:100%;object-fit:cover}"} -->
<div class="gb-element gb-element-' . $mid . 'img"><img src="' . $team_img . '" alt="' . $team_img_alt . '" style="width:100%;height:100%;object-fit:cover" /></div>
<!-- /wp:generateblocks/element -->';
            }

            // Text container
            $content .= '<!-- wp:generateblocks/element {"uniqueId":"' . $mid . 'txt","tagName":"div","styles":{"padding":"1.5rem"},"css":".gb-element-' . $mid . 'txt{padding:1.5rem}"} -->
<div class="gb-element gb-element-' . $mid . 'txt">';

            // Name
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $mid . 'a","tagName":"h3","styles":{"fontSize":"1.125rem","fontWeight":"700","color":"#0a0a0a","marginBottom":"0.25rem"},"css":".gb-text-' . $mid . 'a{font-size:1.125rem;font-weight:700;color:#0a0a0a;margin-bottom:0.25rem}"} -->
<h3 class="gb-text gb-text-' . $mid . 'a">' . $team_name . '</h3>
<!-- /wp:generateblocks/text -->';

            // Role
            if (!empty($team_role)) {
                $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $mid . 'b","tagName":"p","styles":{"fontSize":"0.875rem","color":"#7a7a7a","marginBottom":"0"},"css":".gb-text-' . $mid . 'b{font-size:0.875rem;color:#7a7a7a;margin-bottom:0}"} -->
<p class="gb-text gb-text-' . $mid . 'b">' . $team_role . '</p>
<!-- /wp:generateblocks/text -->';
            }

            // Close text container
            $content .= '</div>
<!-- /wp:generateblocks/element -->';

            // Close team member card
            $content .= '</div>
<!-- /wp:generateblocks/element -->';
        }

        // Close grid
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close inner container
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close team section
        $content .= '</section>
<!-- /wp:generateblocks/element -->';
    }

    // =========================================================================
    // Section 12: CTA Banner (dark bg #0a0a0a)
    // =========================================================================
    if (!empty($csv_data['cta_heading'])) {
        $cta_heading = esc_html($csv_data['cta_heading']);
        $cta_text = esc_html($csv_data['cta_text'] ?? '');
        $cta_url = esc_url($csv_data['cta_url'] ?? '#');

        // CTA outer section (full-width breakout)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"bcta001","tagName":"section","styles":{"paddingTop":"4rem","paddingBottom":"4rem","backgroundColor":"#0a0a0a","width":"100vw","position":"relative","left":"50%","right":"50%","marginLeft":"-50vw","marginRight":"-50vw"},"css":".gb-element-bcta001{padding-top:4rem;padding-bottom:4rem;background-color:#0a0a0a;width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw}"} -->
<section class="gb-element gb-element-bcta001">';

        // CTA inner container
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"bcta002","tagName":"div","styles":{"maxWidth":"800px","marginLeft":"auto","marginRight":"auto","paddingLeft":"1.5rem","paddingRight":"1.5rem","textAlign":"center"},"css":".gb-element-bcta002{max-width:800px;margin-left:auto;margin-right:auto;padding-left:1.5rem;padding-right:1.5rem;text-align:center}"} -->
<div class="gb-element gb-element-bcta002">';

        // CTA heading
        $content .= '<!-- wp:generateblocks/text {"uniqueId":"bcta002a","tagName":"h2","styles":{"fontSize":"clamp(1.75rem, 4vw, 2.5rem)","fontWeight":"900","letterSpacing":"-0.03em","color":"#ffffff","marginBottom":"2rem","lineHeight":"1.2"},"css":".gb-text-bcta002a{font-size:clamp(1.75rem, 4vw, 2.5rem);font-weight:900;letter-spacing:-0.03em;color:#ffffff;margin-bottom:2rem;line-height:1.2}"} -->
<h2 class="gb-text gb-text-bcta002a">' . $cta_heading . '</h2>
<!-- /wp:generateblocks/text -->';

        // CTA button
        $cta_button_text = !empty($cta_text) ? $cta_text : $cta_heading;
        $content .= '<!-- wp:generateblocks/text {"uniqueId":"bcta003a","tagName":"a","htmlAttributes":[{"attribute":"href","value":"' . $cta_url . '"}],"styles":{"display":"inline-block","padding":"1rem 2.5rem","backgroundColor":"#c0392b","color":"#ffffff","borderRadius":"2rem","fontSize":"1.125rem","fontWeight":"700","textDecoration":"none","letterSpacing":"0.01em"},"css":".gb-text-bcta003a{display:inline-block;padding:1rem 2.5rem;background-color:#c0392b;color:#ffffff;border-radius:2rem;font-size:1.125rem;font-weight:700;text-decoration:none;letter-spacing:0.01em;transition:all 0.3s}.gb-text-bcta003a:hover{background-color:#a33024;transform:translateY(-2px);box-shadow:0 4px 12px rgba(192,57,43,0.3)}"} -->
<a class="gb-text gb-text-bcta003a" href="' . $cta_url . '">' . $cta_button_text . '</a>
<!-- /wp:generateblocks/text -->';

        // Close inner container
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close CTA section
        $content .= '</section>
<!-- /wp:generateblocks/element -->';
    }

    // =========================================================================
    // Section 13: Text Section (white bg #ffffff)
    // =========================================================================
    if (!empty($csv_data['text_heading']) || !empty($csv_data['text_body'])) {
        $text_heading = esc_html($csv_data['text_heading'] ?? '');
        $text_body = wp_kses_post($csv_data['text_body'] ?? '');

        // Text outer section (full-width breakout)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"txts001","tagName":"section","styles":{"paddingTop":"4rem","paddingBottom":"4rem","backgroundColor":"#ffffff","width":"100vw","position":"relative","left":"50%","right":"50%","marginLeft":"-50vw","marginRight":"-50vw"},"css":".gb-element-txts001{padding-top:4rem;padding-bottom:4rem;background-color:#ffffff;width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw}"} -->
<section class="gb-element gb-element-txts001">';

        // Text inner container
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"txts002","tagName":"div","styles":{"maxWidth":"800px","marginLeft":"auto","marginRight":"auto","paddingLeft":"1.5rem","paddingRight":"1.5rem"},"css":".gb-element-txts002{max-width:800px;margin-left:auto;margin-right:auto;padding-left:1.5rem;padding-right:1.5rem}"} -->
<div class="gb-element gb-element-txts002">';

        // Heading
        if (!empty($text_heading)) {
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"txts002a","tagName":"h2","styles":{"fontSize":"clamp(1.75rem, 4vw, 2.5rem)","fontWeight":"900","letterSpacing":"-0.03em","color":"#0a0a0a","textAlign":"center","marginBottom":"1.5rem"},"css":".gb-text-txts002a{font-size:clamp(1.75rem, 4vw, 2.5rem);font-weight:900;letter-spacing:-0.03em;color:#0a0a0a;text-align:center;margin-bottom:1.5rem}"} -->
<h2 class="gb-text gb-text-txts002a">' . $text_heading . '</h2>
<!-- /wp:generateblocks/text -->';
        }

        // Body text
        if (!empty($text_body)) {
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"txts003a","tagName":"p","styles":{"fontSize":"1.0625rem","color":"#333333","lineHeight":"1.8","marginBottom":"0"},"css":".gb-text-txts003a{font-size:1.0625rem;color:#333333;line-height:1.8;margin-bottom:0}"} -->
<p class="gb-text gb-text-txts003a">' . $text_body . '</p>
<!-- /wp:generateblocks/text -->';
        }

        // Close inner container
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close text section
        $content .= '</section>
<!-- /wp:generateblocks/element -->';
    }

    return $content;
}

/**
 * Import Lead Magnet page from CSV data
 * Creates a WordPress page using GenerateBlocks V2 markup
 * Layout: Split Hero (headline + form), Testimonial, 3 Benefit Cards
 */
function requestdesk_import_leadmagnet_csv($csv_data) {
    global $wpdb;

    // Get page title and slug from CSV
    $page_title = sanitize_text_field($csv_data['page_title'] ?? 'Lead Magnet');
    $page_slug = sanitize_title($csv_data['page_slug'] ?? 'lead-magnet');

    // Check if page with this slug already exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'page' AND post_status != 'trash'",
        $page_slug
    ));

    if ($existing) {
        // Add timestamp to make unique
        $page_slug = $page_slug . '-' . current_time('Y-m-d-H-i');
    }

    // Build the page content using GenerateBlocks V2 structure
    $template_content = requestdesk_build_leadmagnet_page_content($csv_data);

    // Prepare page data
    $current_time = current_time('mysql');
    $current_time_gmt = current_time('mysql', 1);

    $page_data = array(
        'post_author' => get_current_user_id(),
        'post_date' => $current_time,
        'post_date_gmt' => $current_time_gmt,
        'post_content' => $template_content,
        'post_title' => $page_title,
        'post_excerpt' => sanitize_text_field($csv_data['meta_description'] ?? ''),
        'post_status' => 'draft',
        'comment_status' => 'closed',
        'ping_status' => 'closed',
        'post_password' => '',
        'post_name' => $page_slug,
        'to_ping' => '',
        'pinged' => '',
        'post_modified' => $current_time,
        'post_modified_gmt' => $current_time_gmt,
        'post_content_filtered' => '',
        'post_parent' => 0,
        'guid' => '',
        'menu_order' => 0,
        'post_type' => 'page',
        'post_mime_type' => '',
        'comment_count' => 0
    );

    // Insert the page
    $result = $wpdb->insert($wpdb->posts, $page_data);

    if ($result !== false) {
        $page_id = $wpdb->insert_id;

        // Update GUID
        $wpdb->update(
            $wpdb->posts,
            array('guid' => get_permalink($page_id)),
            array('ID' => $page_id)
        );

        // Set page to GP Canvas template for full-width layout
        update_post_meta($page_id, '_wp_page_template', 'page-builder-canvas.php');

        // Set GeneratePress layout options for full-width
        update_post_meta($page_id, '_generate_sidebar_layout', 'no-sidebar');
        update_post_meta($page_id, '_generate_content_width', 'full-width');

        // Auto-enable landing page styling (removes header, enables full-width hero)
        update_post_meta($page_id, '_requestdesk_landing_page', true);

        // Set Yoast SEO meta if available
        if (!empty($csv_data['meta_title'])) {
            update_post_meta($page_id, '_yoast_wpseo_title', sanitize_text_field($csv_data['meta_title']));
        }
        if (!empty($csv_data['meta_description'])) {
            update_post_meta($page_id, '_yoast_wpseo_metadesc', sanitize_text_field($csv_data['meta_description']));
        }

        return array(
            'success' => true,
            'page_id' => $page_id,
            'page_title' => $page_title,
            'template_name' => 'Lead Magnet Template'
        );
    } else {
        return array(
            'success' => false,
            'error' => 'Failed to insert page into database'
        );
    }
}

/**
 * Build Lead Magnet Page Content from CSV data
 * Uses GenerateBlocks V2 block markup for all sections
 *
 * Sections:
 *   1. Split Hero - 2-column grid: headline/subheadline/CTA left, HubSpot form right
 *   2. Testimonial - Full-width centered quote with author
 *   3. Benefit Cards - 3-column grid with title + description per card
 *
 * Unique ID prefix: lm (lead magnet)
 */
function requestdesk_build_leadmagnet_page_content($csv_data) {
    $content = '';

    // =========================================================================
    // Section 1: Split Hero (dark background #0a0a0a, 2-column grid)
    // Left: headline, subheadline, description, CTA button
    // Right: HubSpot form embed
    // =========================================================================
    $hero_headline = esc_html($csv_data['hero_headline'] ?? 'Download the Free Resource');
    $hero_subheadline = esc_html($csv_data['hero_subheadline'] ?? '');
    $hero_description = esc_html($csv_data['hero_description'] ?? '');
    $hero_cta_text = esc_html($csv_data['hero_cta_text'] ?? 'Download Now');
    $hero_cta_url = esc_url($csv_data['hero_cta_url'] ?? '#');
    $hubspot_form_id = sanitize_text_field($csv_data['hubspot_form_id'] ?? '');

    // Hero outer section (full-width breakout)
    $content .= '<!-- wp:generateblocks/element {"uniqueId":"lm001","tagName":"section","styles":{"paddingTop":"5rem","paddingBottom":"5rem","backgroundColor":"#0a0a0a","width":"100vw","position":"relative","left":"50%","right":"50%","marginLeft":"-50vw","marginRight":"-50vw"},"css":".gb-element-lm001{padding-top:5rem;padding-bottom:5rem;background-color:#0a0a0a;width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw}"} -->
<section class="gb-element gb-element-lm001">';

    // Hero inner container - 2-column CSS grid
    $content .= '<!-- wp:generateblocks/element {"uniqueId":"lm002","tagName":"div","styles":{"display":"grid","gridTemplateColumns":"2fr 3fr","maxWidth":"1400px","marginLeft":"auto","marginRight":"auto","paddingLeft":"2rem","paddingRight":"2rem","gap":"3rem","alignItems":"center"},"css":".gb-element-lm002{display:grid;grid-template-columns:2fr 3fr;max-width:1400px;margin-left:auto;margin-right:auto;padding-left:2rem;padding-right:2rem;gap:3rem;align-items:center}@media(max-width:968px){.gb-element-lm002{grid-template-columns:1fr;gap:2.5rem;padding-top:2rem;padding-bottom:2rem}}"} -->
<div class="gb-element gb-element-lm002">';

    // ---- Left Column: Text + CTA ----
    $content .= '<!-- wp:generateblocks/element {"uniqueId":"lm003","tagName":"div","styles":{"display":"flex","flexDirection":"column","gap":"1.5rem"},"css":".gb-element-lm003{display:flex;flex-direction:column;gap:1.5rem}@media(max-width:968px){.gb-element-lm003{text-align:center;align-items:center}}"} -->
<div class="gb-element gb-element-lm003">';

    // H1 Headline
    $content .= '<!-- wp:generateblocks/text {"uniqueId":"lm003a","tagName":"h1","styles":{"fontSize":"clamp(2rem, 5vw, 3.25rem)","fontWeight":"900","letterSpacing":"-0.03em","color":"#ffffff","marginBottom":"0","lineHeight":"1.1"},"css":".gb-text-lm003a{font-size:clamp(2rem, 5vw, 3.25rem);font-weight:900;letter-spacing:-0.03em;color:#ffffff;margin-bottom:0;line-height:1.1}"} -->
<h1 class="gb-text gb-text-lm003a">' . $hero_headline . '</h1>
<!-- /wp:generateblocks/text -->';

    // Subheadline
    if (!empty($csv_data['hero_subheadline'])) {
        $content .= '<!-- wp:generateblocks/text {"uniqueId":"lm003b","tagName":"h2","styles":{"fontSize":"clamp(1.25rem, 3vw, 1.75rem)","fontWeight":"600","color":"rgba(255,255,255,0.9)","marginBottom":"0","lineHeight":"1.3"},"css":".gb-text-lm003b{font-size:clamp(1.25rem, 3vw, 1.75rem);font-weight:600;color:rgba(255,255,255,0.9);margin-bottom:0;line-height:1.3}"} -->
<h2 class="gb-text gb-text-lm003b">' . $hero_subheadline . '</h2>
<!-- /wp:generateblocks/text -->';
    }

    // Description
    if (!empty($csv_data['hero_description'])) {
        $content .= '<!-- wp:generateblocks/text {"uniqueId":"lm003c","tagName":"p","styles":{"fontSize":"1.125rem","color":"rgba(255,255,255,0.75)","lineHeight":"1.6","marginBottom":"0","maxWidth":"500px"},"css":".gb-text-lm003c{font-size:1.125rem;color:rgba(255,255,255,0.75);line-height:1.6;margin-bottom:0;max-width:500px}@media(max-width:968px){.gb-text-lm003c{max-width:100%}}"} -->
<p class="gb-text gb-text-lm003c">' . $hero_description . '</p>
<!-- /wp:generateblocks/text -->';
    }

    // Bouncing arrow pointing to the form
    $content .= '<!-- wp:html -->
<div class="lm-arrow-cta" style="display:flex;align-items:center;gap:0.75rem;margin-top:0.5rem">
  <span style="color:rgba(255,255,255,0.7);font-size:1.125rem;font-weight:600">Fill out the form to get the deck</span>
  <span class="lm-bounce-arrow" style="display:inline-block;font-size:2rem;color:#58c558;animation:lmBounceRight 1.5s ease-in-out infinite">&#10132;</span>
</div>
<style>
@keyframes lmBounceRight{0%,100%{transform:translateX(0)}50%{transform:translateX(12px)}}
@media(max-width:968px){.lm-arrow-cta span.lm-bounce-arrow{animation-name:lmBounceDown;font-size:1.5rem;transform:rotate(90deg)}.lm-arrow-cta{justify-content:center}}
@keyframes lmBounceDown{0%,100%{transform:rotate(90deg) translateX(0)}50%{transform:rotate(90deg) translateX(12px)}}
</style>
<!-- /wp:html -->';

    // Close left column
    $content .= '</div>
<!-- /wp:generateblocks/element -->';

    // ---- Right Column: HubSpot Form Embed ----
    $content .= '<!-- wp:generateblocks/element {"uniqueId":"lm004","tagName":"div","styles":{"borderRadius":"0.75rem","boxShadow":"0 10px 40px rgba(0,0,0,0.3)","aspectRatio":"808/661"},"css":".gb-element-lm004{border-radius:0.75rem;box-shadow:0 10px 40px rgba(0,0,0,0.3);aspect-ratio:808/661}"} -->
<div class="gb-element gb-element-lm004">';

    if (!empty($hubspot_form_id)) {
        // HubSpot form embed via wp:html block (v2 embed format)
        $content .= '<!-- wp:html -->
<script src="https://js.hsforms.net/forms/embed/39487190.js" defer></script>
<div class="hs-form-frame" data-region="na1" data-form-id="' . $hubspot_form_id . '" data-portal-id="39487190"></div>
<!-- /wp:html -->';
    } else {
        // Placeholder when no HubSpot form ID is provided
        $content .= '<!-- wp:generateblocks/text {"uniqueId":"lm004a","tagName":"p","styles":{"fontSize":"1rem","color":"#5c5c5c","textAlign":"center","padding":"2rem 1rem"},"css":".gb-text-lm004a{font-size:1rem;color:#5c5c5c;text-align:center;padding:2rem 1rem}"} -->
<p class="gb-text gb-text-lm004a">Form coming soon. Enter your HubSpot form ID in the CSV to enable the embed.</p>
<!-- /wp:generateblocks/text -->';
    }

    // Close right column (form container)
    $content .= '</div>
<!-- /wp:generateblocks/element -->';

    // Close hero grid
    $content .= '</div>
<!-- /wp:generateblocks/element -->';

    // Close hero section
    $content .= '</section>
<!-- /wp:generateblocks/element -->';

    // =========================================================================
    // Section 2: Testimonial / Social Proof (light bg #f5f5f3)
    // Full-width centered quote with author name and title
    // =========================================================================
    $testimonial_text = esc_html($csv_data['testimonial_text'] ?? '');
    $testimonial_author = esc_html($csv_data['testimonial_author'] ?? '');
    $testimonial_title = esc_html($csv_data['testimonial_title'] ?? '');

    if (!empty($testimonial_text)) {
        // Testimonial outer section (full-width breakout)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"lm005","tagName":"section","styles":{"paddingTop":"4rem","paddingBottom":"4rem","backgroundColor":"#f5f5f3","width":"100vw","position":"relative","left":"50%","right":"50%","marginLeft":"-50vw","marginRight":"-50vw"},"css":".gb-element-lm005{padding-top:4rem;padding-bottom:4rem;background-color:#f5f5f3;width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw}"} -->
<section class="gb-element gb-element-lm005">';

        // Testimonial inner container
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"lm006","tagName":"div","styles":{"maxWidth":"800px","marginLeft":"auto","marginRight":"auto","paddingLeft":"2rem","paddingRight":"2rem","textAlign":"center"},"css":".gb-element-lm006{max-width:800px;margin-left:auto;margin-right:auto;padding-left:2rem;padding-right:2rem;text-align:center}"} -->
<div class="gb-element gb-element-lm006">';

        // Decorative quote mark
        $content .= '<!-- wp:generateblocks/text {"uniqueId":"lm006a","tagName":"span","styles":{"fontSize":"5rem","fontWeight":"900","color":"rgba(192,57,43,0.15)","lineHeight":"1","fontFamily":"Georgia, serif","display":"block","marginBottom":"0.5rem"},"css":".gb-text-lm006a{font-size:5rem;font-weight:900;color:rgba(192,57,43,0.15);line-height:1;font-family:Georgia, serif;display:block;margin-bottom:0.5rem}"} -->
<span class="gb-text gb-text-lm006a">"</span>
<!-- /wp:generateblocks/text -->';

        // Quote text
        $content .= '<!-- wp:generateblocks/text {"uniqueId":"lm006b","tagName":"p","styles":{"fontSize":"1.25rem","color":"#0a0a0a","lineHeight":"1.7","marginBottom":"1.5rem","fontStyle":"italic"},"css":".gb-text-lm006b{font-size:1.25rem;color:#0a0a0a;line-height:1.7;margin-bottom:1.5rem;font-style:italic}"} -->
<p class="gb-text gb-text-lm006b">' . $testimonial_text . '</p>
<!-- /wp:generateblocks/text -->';

        // Author name
        if (!empty($testimonial_author)) {
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"lm006c","tagName":"p","styles":{"fontSize":"1rem","fontWeight":"700","color":"#0a0a0a","marginBottom":"0.25rem"},"css":".gb-text-lm006c{font-size:1rem;font-weight:700;color:#0a0a0a;margin-bottom:0.25rem}"} -->
<p class="gb-text gb-text-lm006c">' . $testimonial_author . '</p>
<!-- /wp:generateblocks/text -->';
        }

        // Author title
        if (!empty($testimonial_title)) {
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"lm006d","tagName":"p","styles":{"fontSize":"0.875rem","color":"#5c5c5c","marginBottom":"0"},"css":".gb-text-lm006d{font-size:0.875rem;color:#5c5c5c;margin-bottom:0}"} -->
<p class="gb-text gb-text-lm006d">' . $testimonial_title . '</p>
<!-- /wp:generateblocks/text -->';
        }

        // Close inner container
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close testimonial section
        $content .= '</section>
<!-- /wp:generateblocks/element -->';
    }

    // =========================================================================
    // Section 3: Benefit Cards (3-column grid, white bg)
    // Each card has a title and description
    // =========================================================================
    $has_benefits = false;
    for ($i = 1; $i <= 3; $i++) {
        if (!empty($csv_data["benefit_{$i}_title"])) {
            $has_benefits = true;
            break;
        }
    }

    if ($has_benefits) {
        // Benefits outer section (full-width breakout)
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"lm007","tagName":"section","styles":{"paddingTop":"4rem","paddingBottom":"4rem","backgroundColor":"#ffffff","width":"100vw","position":"relative","left":"50%","right":"50%","marginLeft":"-50vw","marginRight":"-50vw"},"css":".gb-element-lm007{padding-top:4rem;padding-bottom:4rem;background-color:#ffffff;width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw}"} -->
<section class="gb-element gb-element-lm007">';

        // Benefits inner container
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"lm008","tagName":"div","styles":{"maxWidth":"1200px","marginLeft":"auto","marginRight":"auto","paddingLeft":"1.5rem","paddingRight":"1.5rem"},"css":".gb-element-lm008{max-width:1200px;margin-left:auto;margin-right:auto;padding-left:1.5rem;padding-right:1.5rem}"} -->
<div class="gb-element gb-element-lm008">';

        // Section heading (optional - from cta_heading field)
        $benefits_heading = esc_html($csv_data['cta_heading'] ?? '');
        if (!empty($benefits_heading)) {
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"lm008a","tagName":"h2","styles":{"fontSize":"clamp(1.75rem, 4vw, 2.5rem)","fontWeight":"900","letterSpacing":"-0.03em","color":"#0a0a0a","textAlign":"center","marginBottom":"2.5rem"},"css":".gb-text-lm008a{font-size:clamp(1.75rem, 4vw, 2.5rem);font-weight:900;letter-spacing:-0.03em;color:#0a0a0a;text-align:center;margin-bottom:2.5rem}"} -->
<h2 class="gb-text gb-text-lm008a">' . $benefits_heading . '</h2>
<!-- /wp:generateblocks/text -->';
        }

        // 3-column grid
        $content .= '<!-- wp:generateblocks/element {"uniqueId":"lm009","tagName":"div","styles":{"display":"grid","gridTemplateColumns":"repeat(3, 1fr)","gap":"2rem"},"css":".gb-element-lm009{display:grid;grid-template-columns:repeat(3, 1fr);gap:2rem}@media(max-width:768px){.gb-element-lm009{grid-template-columns:1fr}}"} -->
<div class="gb-element gb-element-lm009">';

        // Benefit cards
        for ($i = 1; $i <= 3; $i++) {
            $benefit_title = esc_html($csv_data["benefit_{$i}_title"] ?? '');
            $benefit_desc = esc_html($csv_data["benefit_{$i}_description"] ?? '');

            if (empty($benefit_title)) {
                continue;
            }

            $card_id = 'lm' . str_pad($i + 9, 3, '0', STR_PAD_LEFT);

            // Card container with border
            $content .= '<!-- wp:generateblocks/element {"uniqueId":"' . $card_id . '","tagName":"div","styles":{"backgroundColor":"#ffffff","borderRadius":"0.75rem","padding":"2rem","border":"1px solid #e5e5e5"},"css":".gb-element-' . $card_id . '{background-color:#ffffff;border-radius:0.75rem;padding:2rem;border:1px solid #e5e5e5;transition:all 0.3s}.gb-element-' . $card_id . ':hover{border-color:#c0392b;box-shadow:0 4px 16px rgba(0,0,0,0.08);transform:translateY(-4px)}"} -->
<div class="gb-element gb-element-' . $card_id . '">';

            // Number badge
            $content .= '<!-- wp:generateblocks/element {"uniqueId":"' . $card_id . 'n","tagName":"div","styles":{"width":"48px","height":"48px","backgroundColor":"rgba(192,57,43,0.1)","borderRadius":"50%","display":"flex","alignItems":"center","justifyContent":"center","marginBottom":"1.25rem"},"css":".gb-element-' . $card_id . 'n{width:48px;height:48px;background-color:rgba(192,57,43,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:1.25rem}"} -->
<div class="gb-element gb-element-' . $card_id . 'n">';

            $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $card_id . 'nn","tagName":"span","styles":{"fontSize":"1.25rem","fontWeight":"700","color":"#c0392b"},"css":".gb-text-' . $card_id . 'nn{font-size:1.25rem;font-weight:700;color:#c0392b}"} -->
<span class="gb-text gb-text-' . $card_id . 'nn">' . $i . '</span>
<!-- /wp:generateblocks/text -->';

            $content .= '</div>
<!-- /wp:generateblocks/element -->';

            // Card title
            $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $card_id . 'a","tagName":"h3","styles":{"fontSize":"1.25rem","fontWeight":"700","color":"#0a0a0a","marginBottom":"0.75rem"},"css":".gb-text-' . $card_id . 'a{font-size:1.25rem;font-weight:700;color:#0a0a0a;margin-bottom:0.75rem}"} -->
<h3 class="gb-text gb-text-' . $card_id . 'a">' . $benefit_title . '</h3>
<!-- /wp:generateblocks/text -->';

            // Card description
            if (!empty($benefit_desc)) {
                $content .= '<!-- wp:generateblocks/text {"uniqueId":"' . $card_id . 'b","tagName":"p","styles":{"fontSize":"0.9375rem","color":"#5c5c5c","lineHeight":"1.6","marginBottom":"0"},"css":".gb-text-' . $card_id . 'b{font-size:0.9375rem;color:#5c5c5c;line-height:1.6;margin-bottom:0}"} -->
<p class="gb-text gb-text-' . $card_id . 'b">' . $benefit_desc . '</p>
<!-- /wp:generateblocks/text -->';
            }

            // Close card
            $content .= '</div>
<!-- /wp:generateblocks/element -->';
        }

        // Close grid
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close inner container
        $content .= '</div>
<!-- /wp:generateblocks/element -->';

        // Close benefits section
        $content .= '</section>
<!-- /wp:generateblocks/element -->';
    }

    // =========================================================================
    // Section 4: Bottom CTA (dark bg #0a0a0a) - repeat CTA for conversion
    // =========================================================================
    $cta_text = esc_html($csv_data['cta_text'] ?? $csv_data['hero_cta_text'] ?? 'Download Now');
    $cta_url = esc_url($csv_data['cta_url'] ?? $csv_data['hero_cta_url'] ?? '#');

    // CTA outer section
    $content .= '<!-- wp:generateblocks/element {"uniqueId":"lm013","tagName":"section","styles":{"paddingTop":"4rem","paddingBottom":"4rem","backgroundColor":"#0a0a0a","width":"100vw","position":"relative","left":"50%","right":"50%","marginLeft":"-50vw","marginRight":"-50vw"},"css":".gb-element-lm013{padding-top:4rem;padding-bottom:4rem;background-color:#0a0a0a;width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw}"} -->
<section class="gb-element gb-element-lm013">';

    // CTA inner container
    $content .= '<!-- wp:generateblocks/element {"uniqueId":"lm014","tagName":"div","styles":{"maxWidth":"800px","marginLeft":"auto","marginRight":"auto","paddingLeft":"1.5rem","paddingRight":"1.5rem","textAlign":"center"},"css":".gb-element-lm014{max-width:800px;margin-left:auto;margin-right:auto;padding-left:1.5rem;padding-right:1.5rem;text-align:center}"} -->
<div class="gb-element gb-element-lm014">';

    // CTA heading
    $content .= '<!-- wp:generateblocks/text {"uniqueId":"lm014a","tagName":"h2","styles":{"fontSize":"clamp(1.75rem, 4vw, 2.5rem)","fontWeight":"900","letterSpacing":"-0.03em","color":"#ffffff","marginBottom":"1.5rem","lineHeight":"1.2"},"css":".gb-text-lm014a{font-size:clamp(1.75rem, 4vw, 2.5rem);font-weight:900;letter-spacing:-0.03em;color:#ffffff;margin-bottom:1.5rem;line-height:1.2}"} -->
<h2 class="gb-text gb-text-lm014a">' . esc_html($csv_data['hero_headline'] ?? 'Get Your Free Download') . '</h2>
<!-- /wp:generateblocks/text -->';

    // CTA button
    $content .= '<!-- wp:generateblocks/text {"uniqueId":"lm014b","tagName":"a","htmlAttributes":[{"attribute":"href","value":"' . $cta_url . '"}],"styles":{"display":"inline-block","padding":"1rem 2.5rem","backgroundColor":"#c0392b","color":"#ffffff","borderRadius":"2rem","fontSize":"1.125rem","fontWeight":"700","textDecoration":"none","letterSpacing":"0.01em"},"css":".gb-text-lm014b{display:inline-block;padding:1rem 2.5rem;background-color:#c0392b;color:#ffffff;border-radius:2rem;font-size:1.125rem;font-weight:700;text-decoration:none;letter-spacing:0.01em;transition:all 0.3s}.gb-text-lm014b:hover{background-color:#a33024;transform:translateY(-2px);box-shadow:0 4px 12px rgba(192,57,43,0.3)}"} -->
<a class="gb-text gb-text-lm014b" href="' . $cta_url . '">' . $cta_text . '</a>
<!-- /wp:generateblocks/text -->';

    // Close inner container
    $content .= '</div>
<!-- /wp:generateblocks/element -->';

    // Close CTA section
    $content .= '</section>
<!-- /wp:generateblocks/element -->';

    return $content;
}
?>