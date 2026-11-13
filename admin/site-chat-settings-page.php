<?php
/**
 * Website Chat Settings Tab
 *
 * Renders inside the RequestDesk Settings tabbed page. One switch, the chat
 * slug and the RequestDesk URL. See includes/class-requestdesk-site-chat.php.
 *
 * @package RequestDesk
 */

if (!defined('ABSPATH')) {
    exit;
}

function requestdesk_site_chat_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['requestdesk_save_site_chat']) && wp_verify_nonce($_POST['requestdesk_site_chat_nonce'] ?? '', 'requestdesk_site_chat_settings')) {
        $base_url = esc_url_raw(trim(wp_unslash($_POST['site_chat_base_url'] ?? '')));
        update_option(RequestDesk_Site_Chat::OPTION_SETTINGS, array(
            'enabled'  => isset($_POST['site_chat_enabled']),
            'slug'     => sanitize_key($_POST['site_chat_slug'] ?? ''),
            'base_url' => $base_url !== '' ? untrailingslashit($base_url) : '',
        ));
        echo '<div class="notice notice-success"><p>Website chat settings saved. If the site sits behind a page cache, purge it so visitors get the change.</p></div>';
    }

    $settings = RequestDesk_Site_Chat::get_settings();
    $problem  = RequestDesk_Site_Chat::config_error($settings);
    if ($problem !== '') {
        echo '<div class="notice notice-error"><p><strong>The website chat is switched on but not loading.</strong> ' . esc_html($problem) . ' No chat appears on the site until this is fixed.</p></div>';
    }
    ?>

    <form method="post" action="">
        <?php wp_nonce_field('requestdesk_site_chat_settings', 'requestdesk_site_chat_nonce'); ?>

        <div class="card" style="max-width: 900px;">
            <h2>Website Chat</h2>
            <p>Shows a RequestDesk agent's website chat on every page of this site. What the chat answers from, its look and the websites it answers on are set on the agent in RequestDesk. This switch only decides whether the site loads it.</p>
            <table class="form-table">
                <tr>
                    <th scope="row">Show the chat</th>
                    <td>
                        <label>
                            <input type="checkbox" name="site_chat_enabled" value="1" <?php checked(!empty($settings['enabled'])); ?>>
                            Load the website chat on this site
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="site_chat_slug">Chat slug</label></th>
                    <td>
                        <input type="text" id="site_chat_slug" name="site_chat_slug" class="regular-text" value="<?php echo esc_attr($settings['slug']); ?>" placeholder="e.g. my-site-chat">
                        <p class="description">The slug on the agent's Website Chat settings in RequestDesk. Type it in; the grey example is not saved.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="site_chat_base_url">RequestDesk URL</label></th>
                    <td>
                        <input type="url" id="site_chat_base_url" name="site_chat_base_url" class="regular-text" value="<?php echo esc_attr($settings['base_url']); ?>" placeholder="https://app.example.com"><?php /* hardcode-ok: neutral example.com placeholder, not a value */ ?>
                        <p class="description">The address of the RequestDesk app the agent lives in, the one you log in to.</p>
                    </td>
                </tr>
                <?php if ($settings['slug'] !== '' && wp_parse_url($settings['base_url'], PHP_URL_SCHEME) === 'https') : ?>
                <tr>
                    <th scope="row">Preview</th>
                    <td>
                        <a href="<?php echo esc_url(RequestDesk_Site_Chat::preview_url($settings)); ?>" target="_blank" rel="noopener">Try this chat on the RequestDesk preview page</a>
                        <p class="description">The chat answers only on the websites listed on the agent. If this site's address is not on that list, the chat loads here and refuses every message.</p>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            <p class="submit">
                <input type="submit" name="requestdesk_save_site_chat" class="button button-primary" value="Save Website Chat">
            </p>
        </div>
    </form>
    <?php
}
