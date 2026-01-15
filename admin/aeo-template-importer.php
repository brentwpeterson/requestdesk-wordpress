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

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label for="template_type" style="font-weight: 600; display: block; margin-bottom: 8px;">Select Template:</label>
                        <select name="template_type" id="template_type" class="regular-text" required>
                            <option value="">Choose Template...</option>
                            <option value="aeo_homepage">🎯 AEO Homepage Template</option>
                            <option value="service_page">🔧 Service Page Template</option>
                            <option value="aeo_about_page">📋 AEO About Page Template</option>
                            <option value="auto_detect">🔍 Auto-Detect from CSV</option>
                        </select>
                        <p style="font-size: 12px; color: #666; margin-top: 5px;">💡 <strong>Tip:</strong> Use "Auto-Detect" if your CSV has a <code>template_type</code> column (homepage, about, open_base, service)</p>
                    </div>

                    <div>
                        <label for="csv_file" style="font-weight: 600; display: block; margin-bottom: 8px;">Upload CSV File:</label>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required style="width: 100%;">
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
                        <h4 style="margin: 0 0 10px 0;">📥 Download Example:</h4>
                        <a href="<?php echo REQUESTDESK_PLUGIN_URL . 'admin/aeo-universal-template-csv-example.csv'; ?>" class="button button-secondary" download="aeo-universal-template-example.csv">
                            <span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Download Universal CSV Template
                        </a>
                        <p style="font-size: 12px; color: #666; margin-top: 5px;">Contains examples for both Homepage and About page templates</p>
                        <p style="font-size: 12px; color: #666; margin: 5px 0 0 0;">Use this as a starting point for your content</p>
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
                default:
                    return array(
                        'success' => false,
                        'message' => 'Invalid template_type in CSV: ' . $csv_template_type . '. Valid types: homepage, about, open_base, service'
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
?>