<?php
/**
 * RequestDesk > Headless Sites
 *
 * Define the extra frontends this install serves. Site details are form
 * fields; the content model (types, taxonomies, fields, menus) is one JSON
 * document per site, validated on save by RequestDesk_Headless_Sites. The same
 * document can be written through POST /requestdesk/v1/headless/sites with the
 * main API key.
 *
 * @since 2.65.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function requestdesk_headless_sites_add_admin_menu() {
    add_submenu_page(
        'requestdesk-aeo-analytics',
        'Headless Sites',
        'Headless Sites',
        'manage_options',
        'requestdesk-headless-sites',
        'requestdesk_headless_sites_page'
    );
}

if (function_exists('add_action')) {
    add_action('admin_menu', 'requestdesk_headless_sites_add_admin_menu', 21);
}

/** A starting content model, shown for a new site. Edit it to suit. */
function requestdesk_headless_sites_example_model() {
    return array(
        'types' => array(
            array(
                'slug' => 'xx_post', 'singular' => 'Post', 'plural' => 'Posts',
                'behavior' => 'post', 'path' => '/blog/{slug}',
                'fields' => array(
                    array('key' => 'subtitle', 'label' => 'Subtitle', 'kind' => 'text'),
                ),
            ),
            array(
                'slug' => 'xx_page', 'singular' => 'Page', 'plural' => 'Pages',
                'behavior' => 'page', 'path' => '/{path}', 'fields' => array(),
            ),
        ),
        'taxonomies' => array(
            array('slug' => 'xx_category', 'singular' => 'Category', 'plural' => 'Categories',
                  'hierarchical' => true, 'role' => 'category', 'types' => array('xx_post')),
        ),
        'menus' => array(
            array('slug' => 'primary', 'label' => 'Primary'),
            array('slug' => 'footer', 'label' => 'Footer'),
        ),
    );
}

function requestdesk_headless_sites_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $notice = '';
    $error  = '';
    $posted = null;

    if (isset($_POST['rdhs_action']) && check_admin_referer('rdhs_sites', 'rdhs_nonce')) {
        $action = sanitize_key($_POST['rdhs_action']);

        if ($action === 'save') {
            $model = json_decode(wp_unslash($_POST['rdhs_model'] ?? ''), true);
            $posted = array(
                'key'          => sanitize_key(wp_unslash($_POST['rdhs_key'] ?? '')),
                'name'         => wp_unslash($_POST['rdhs_name'] ?? ''),
                'frontend_url' => wp_unslash($_POST['rdhs_frontend_url'] ?? ''),
                'description'  => wp_unslash($_POST['rdhs_description'] ?? ''),
                'logo'         => wp_unslash($_POST['rdhs_logo'] ?? ''),
                'og_image'     => wp_unslash($_POST['rdhs_og_image'] ?? ''),
                'favicon'      => wp_unslash($_POST['rdhs_favicon'] ?? ''),
                'webhook_url'  => wp_unslash($_POST['rdhs_webhook_url'] ?? ''),
                'api_key'      => wp_unslash($_POST['rdhs_api_key'] ?? ''),
                'authors'      => !empty($_POST['rdhs_authors']),
            );
            if (!is_array($model)) {
                $error = 'The content model is not valid JSON: ' . json_last_error_msg();
            } else {
                $posted = array_merge($posted, array(
                    'types'      => $model['types'] ?? array(),
                    'taxonomies' => $model['taxonomies'] ?? array(),
                    'menus'      => $model['menus'] ?? array(),
                ));
                $result = RequestDesk_Headless_Sites::save_site($posted);
                if (is_wp_error($result)) {
                    $error = $result->get_error_message();
                } else {
                    $notice = 'Saved ' . $result['name'] . '. Its admin menus appear on the next page load.';
                    $posted = null;
                    $_GET['site'] = $result['key'];
                }
            }
        } elseif ($action === 'generate_key') {
            $key = sanitize_key($_POST['rdhs_key'] ?? '');
            $site = RequestDesk_Headless_Sites::get_site($key);
            if ($site) {
                $site['api_key'] = wp_generate_password(40, false);
                $result = RequestDesk_Headless_Sites::save_site($site);
                if (is_wp_error($result)) {
                    $error = $result->get_error_message();
                } else {
                    $notice = 'New key for ' . $site['name'] . ' (shown once, copy it now): ' . $site['api_key'];
                }
            }
            $_GET['site'] = $key;
        } elseif ($action === 'rebuild') {
            $key = sanitize_key($_POST['rdhs_key'] ?? '');
            $result = RequestDesk_Headless_Sites::fire_rebuild($key);
            if (is_wp_error($result)) {
                $error = 'Rebuild failed: ' . $result->get_error_message();
            } else {
                $notice = 'Rebuild webhook answered OK.';
            }
            $_GET['site'] = $key;
        } elseif ($action === 'delete') {
            $key = sanitize_key($_POST['rdhs_key'] ?? '');
            $result = RequestDesk_Headless_Sites::delete_site($key);
            if (is_wp_error($result)) {
                $error = $result->get_error_message();
                $_GET['site'] = $key;
            } else {
                $notice = 'Deleted site ' . $key . '.';
                unset($_GET['site']);
            }
        }
    }

    $sites   = RequestDesk_Headless_Sites::get_sites();
    $editing = isset($_GET['site']) ? sanitize_key($_GET['site']) : null;
    $is_new  = $editing === 'new';
    $site    = $posted ?: (($editing && !$is_new && isset($sites[$editing])) ? $sites[$editing] : null);
    $base    = admin_url('admin.php?page=requestdesk-headless-sites');
    ?>
    <div class="wrap">
        <h1>Headless Sites <a href="<?php echo esc_url($base . '&site=new'); ?>" class="page-title-action">Add site</a></h1>
        <p>Each headless site is an extra frontend served from this WordPress. Its content lives in its own types, so no other site, and not this site's theme, can show it. Frontends read <code><?php echo esc_html(rest_url('requestdesk/v1/headless/sites/{key}/')); ?></code>.</p>

        <?php if ($notice) : ?><div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
        <?php if ($error) : ?><div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>

        <?php if (!$editing && !$posted) : ?>
            <table class="widefat striped" style="max-width:960px">
                <thead><tr><th>Key</th><th>Name</th><th>Frontend</th><th>Types</th><th>Webhook</th><th>Last rebuild</th></tr></thead>
                <tbody>
                <?php if (!$sites) : ?>
                    <tr><td colspan="6">No headless sites yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($sites as $s) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url($base . '&site=' . $s['key']); ?>"><code><?php echo esc_html($s['key']); ?></code></a></td>
                        <td><?php echo esc_html($s['name']); ?></td>
                        <td><a href="<?php echo esc_url($s['frontend_url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($s['frontend_url']); ?></a></td>
                        <td><?php echo esc_html(implode(', ', array_map(function ($t) { return $t['slug']; }, $s['types']))); ?></td>
                        <td><?php echo $s['webhook_url'] !== '' ? 'set' : '<strong style="color:#b32d2e">none</strong>'; ?></td>
                        <td><?php echo esc_html(get_option('requestdesk_headless_site_last_rebuild_' . $s['key'], '—')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else :
            $model = $site ? array(
                'types' => $site['types'] ?? array(),
                'taxonomies' => $site['taxonomies'] ?? array(),
                'menus' => $site['menus'] ?? array(),
            ) : requestdesk_headless_sites_example_model();
            $stored = ($site && isset($sites[$site['key'] ?? ''])) ? $sites[$site['key']] : null;
            ?>
            <p><a href="<?php echo esc_url($base); ?>">&larr; All headless sites</a></p>
            <form method="post">
                <?php wp_nonce_field('rdhs_sites', 'rdhs_nonce'); ?>
                <input type="hidden" name="rdhs_action" value="save" />
                <table class="form-table">
                    <tr><th><label for="rdhs_key">Key</label></th><td>
                        <input type="text" id="rdhs_key" name="rdhs_key" value="<?php echo esc_attr($site['key'] ?? ''); ?>" class="regular-text" <?php echo $stored ? 'readonly' : ''; ?> pattern="[a-z][a-z0-9]{1,9}" required />
                        <p class="description">2-10 lowercase letters or digits. Used in URLs and type names; cannot change once saved.</p></td></tr>
                    <tr><th><label for="rdhs_name">Name</label></th><td><input type="text" id="rdhs_name" name="rdhs_name" value="<?php echo esc_attr($site['name'] ?? ''); ?>" class="regular-text" required /></td></tr>
                    <tr><th><label for="rdhs_frontend_url">Frontend URL</label></th><td><input type="url" id="rdhs_frontend_url" name="rdhs_frontend_url" value="<?php echo esc_attr($site['frontend_url'] ?? ''); ?>" class="regular-text" required />
                        <p class="description">Canonical URLs are built from this plus each type's path.</p></td></tr>
                    <tr><th><label for="rdhs_description">Description</label></th><td><textarea id="rdhs_description" name="rdhs_description" class="large-text" rows="2"><?php echo esc_textarea($site['description'] ?? ''); ?></textarea></td></tr>
                    <tr><th><label for="rdhs_logo">Logo URL</label></th><td><input type="url" id="rdhs_logo" name="rdhs_logo" value="<?php echo esc_attr($site['logo'] ?? ''); ?>" class="regular-text" /></td></tr>
                    <tr><th><label for="rdhs_og_image">Default OG image URL</label></th><td><input type="url" id="rdhs_og_image" name="rdhs_og_image" value="<?php echo esc_attr($site['og_image'] ?? ''); ?>" class="regular-text" /></td></tr>
                    <tr><th><label for="rdhs_favicon">Favicon URL</label></th><td><input type="url" id="rdhs_favicon" name="rdhs_favicon" value="<?php echo esc_attr($site['favicon'] ?? ''); ?>" class="regular-text" /></td></tr>
                    <tr><th><label for="rdhs_webhook_url">Rebuild webhook</label></th><td>
                        <input type="url" id="rdhs_webhook_url" name="rdhs_webhook_url" value="" class="regular-text" placeholder="<?php echo $stored && $stored['webhook_url'] !== '' ? 'set (leave empty to keep)' : 'https://example.com/build-hook'; ?>" />
                        <p class="description">Called after content on this site is published, changed or unpublished (Amplify, Netlify, Vercel or any URL that accepts a POST). Never shown again after saving.</p></td></tr>
                    <tr><th>Site API key</th><td>
                        <?php echo $stored && $stored['api_key'] !== '' ? 'set' : 'none (the frontend uses the Headless API key)'; ?>
                        <p class="description">Optional. A site key reads only this site's routes.</p></td></tr>
                    <tr><th>Author type</th><td><label><input type="checkbox" name="rdhs_authors" value="1" <?php checked(!empty($site['authors'])); ?> /> Give this site its own authors (name, bio, avatar, title, company, LinkedIn), separate from WordPress users</label></td></tr>
                    <tr><th><label for="rdhs_model">Content model</label></th><td>
                        <textarea id="rdhs_model" name="rdhs_model" class="large-text code" rows="24"><?php echo esc_textarea(wp_json_encode($model, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea>
                        <p class="description">
                            <strong>types</strong>: slug (2-20 chars), singular, plural, behavior <code>post</code> or <code>page</code>, path with <code>{slug}</code> or <code>{path}</code>, fields.
                            <strong>fields</strong>: key, label, kind (<?php echo esc_html(implode(', ', RequestDesk_Headless_Sites::FIELD_KINDS)); ?>), optional meta_key (store under an existing meta key), api (default true), root (also return at the top level).
                            <strong>taxonomies</strong>: slug, singular, plural, hierarchical, role <code>category</code> or <code>tag</code> (returned as categories / tags), types.
                            <strong>menus</strong>: slug, label.
                            A type that holds content cannot be removed or renamed.
                        </p></td></tr>
                </table>
                <?php submit_button($stored ? 'Save site' : 'Create site'); ?>
            </form>

            <?php if ($stored) : ?>
                <hr />
                <form method="post" style="display:inline-block;margin-right:8px">
                    <?php wp_nonce_field('rdhs_sites', 'rdhs_nonce'); ?>
                    <input type="hidden" name="rdhs_key" value="<?php echo esc_attr($stored['key']); ?>" />
                    <button class="button" name="rdhs_action" value="rebuild" <?php disabled($stored['webhook_url'] === ''); ?>>Rebuild frontend now</button>
                    <button class="button" name="rdhs_action" value="generate_key">Generate site API key</button>
                </form>
                <form method="post" style="display:inline-block">
                    <?php wp_nonce_field('rdhs_sites', 'rdhs_nonce'); ?>
                    <input type="hidden" name="rdhs_key" value="<?php echo esc_attr($stored['key']); ?>" />
                    <button class="button button-link-delete" name="rdhs_action" value="delete">Delete site</button>
                    <span class="description">Refused while any of its types holds content.</span>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
