# Installation Guide

This guide covers how to install and activate the RequestDesk Connector plugin on your WordPress site.

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- HTTPS enabled (recommended for API security)

## Installation Methods

### Method 1: WordPress Plugin Directory (Recommended)

1. Log in to your WordPress admin dashboard
2. Go to **Plugins > Add New**
3. Search for "RequestDesk Connector"
4. Click **Install Now**
5. Click **Activate**

### Method 2: Manual Upload

1. Download the plugin zip file from WordPress.org or RequestDesk.ai
2. Log in to your WordPress admin dashboard
3. Go to **Plugins > Add New > Upload Plugin**
4. Choose the zip file and click **Install Now**
5. Click **Activate**

### Method 3: FTP/SFTP Upload

1. Download and extract the plugin zip file
2. Connect to your server via FTP/SFTP
3. Upload the `requestdesk-connector` folder to `/wp-content/plugins/`
4. Log in to WordPress admin
5. Go to **Plugins** and activate "RequestDesk Connector"

## Post-Installation

After activation, you'll see a new **RequestDesk** menu item in your WordPress admin sidebar.

### First Steps

1. Go to **RequestDesk > Settings**
2. Enter your RequestDesk API key (get this from your RequestDesk.ai dashboard)
3. Configure your preferred default post status
4. Save settings

See the [Configuration Guide](./configuration.md) for detailed setup instructions.

## Verifying Installation

To verify the plugin is working correctly:

1. Go to **RequestDesk > Settings**
2. Look for the green checkmark next to "API Key Configured"
3. The plugin status should show "Active"

### Testing the Connection

You can test the API connection by sending a test post from RequestDesk.ai:

1. Log in to app.requestdesk.ai
2. Create a test content item
3. Use the WordPress publishing action
4. Check your WordPress Posts for the new draft

## Updating the Plugin

The plugin updates automatically through WordPress.org:

1. Go to **Dashboard > Updates** in WordPress admin
2. If an update is available, click **Update Now**

You can also enable auto-updates:
1. Go to **Plugins**
2. Find "RequestDesk Connector"
3. Click **Enable auto-updates**

## Uninstallation

To remove the plugin:

1. Go to **Plugins** in WordPress admin
2. Deactivate "RequestDesk Connector"
3. Click **Delete**

Note: Uninstalling removes the plugin files but preserves your content. Posts created through RequestDesk remain in WordPress.

## Next Steps

- [Configure the plugin](./configuration.md)
- [Learn about features](./features.md)
- [Start publishing from RequestDesk](./using-with-requestdesk.md)
