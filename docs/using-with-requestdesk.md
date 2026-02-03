# Using with RequestDesk

This guide covers how to publish content from RequestDesk.ai to your WordPress site.

## Prerequisites

Before you begin:

1. Install and activate the RequestDesk Connector plugin
2. Configure your API key in WordPress (see [Configuration Guide](./configuration.md))
3. Have an active RequestDesk.ai account

## Connecting Your WordPress Site

### Step 1: Get Your WordPress Site URL

Your WordPress REST API endpoint is:
```
https://your-site.com/wp-json/requestdesk/v1/posts
```

Replace `your-site.com` with your actual domain.

### Step 2: Get Your API Key

1. Log in to your WordPress admin
2. Go to **RequestDesk > Settings**
3. Find or set your API key in "Security Settings"
4. Copy this key

### Step 3: Add WordPress to RequestDesk

1. Log in to app.requestdesk.ai
2. Go to **Settings > Integrations**
3. Click **Add WordPress Site**
4. Enter your site URL and API key
5. Click **Test Connection**
6. Save the integration

## Publishing Content

### From the RequestDesk Dashboard

1. Create or select content in RequestDesk.ai
2. Click the **Publish** button
3. Select your WordPress site from the dropdown
4. Choose options:
   - Post status (draft, pending, published)
   - Categories
   - Tags
   - Featured image
5. Click **Publish to WordPress**

### Content Mapping

RequestDesk content maps to WordPress as follows:

| RequestDesk Field | WordPress Field |
|-------------------|-----------------|
| Title | Post title |
| Body | Post content |
| Summary | Excerpt |
| Featured image | Featured image |
| Categories | Categories |
| Tags | Tags |
| Ticket ID | Post meta (for tracking) |

### Post Status Options

- **Draft** - Saves as draft for review
- **Pending** - Requires editor approval
- **Published** - Goes live immediately
- **Private** - Visible only to logged-in users

## Workflow Examples

### Content Review Workflow

Best for teams that want to review before publishing:

1. Create content in RequestDesk
2. Publish to WordPress as **Draft**
3. Review in WordPress editor
4. Make any final edits
5. Click Publish in WordPress

### Direct Publishing Workflow

For trusted, pre-approved content:

1. Create and approve content in RequestDesk
2. Publish to WordPress as **Published**
3. Content goes live immediately

### Scheduled Publishing

1. Publish to WordPress as **Draft**
2. In WordPress, set a future publish date
3. WordPress publishes automatically at scheduled time

## Working with Categories and Tags

### Pre-existing Categories

RequestDesk matches category names to existing WordPress categories:

- Exact name match required
- Case-insensitive
- Creates category if it doesn't exist (configurable)

### Tags

Tags are created automatically if they don't exist.

### Best Practice

Create your category structure in WordPress first, then use those exact names in RequestDesk.

## Featured Images

### Automatic Image Handling

When you include a featured image in RequestDesk:

1. Image is downloaded to WordPress
2. Added to Media Library
3. Set as post featured image
4. Original URL preserved in metadata

### Supported Formats

- JPEG
- PNG
- WebP
- GIF

### Image Optimization

Images are stored at original size. Use a WordPress image optimization plugin for compression.

## Sync Tracking

### Viewing Sync History

1. Go to **RequestDesk > Dashboard** in WordPress
2. View recent syncs
3. See status, timestamps, and any errors

### Sync Status Values

- **Success** - Content created/updated successfully
- **Failed** - Error occurred (check error message)
- **Pending** - Sync in progress

### Finding Synced Posts

Each synced post includes:
- Ticket ID in post meta
- Agent ID for tracking
- Sync timestamp

Use WordPress search or filter by meta to find posts from RequestDesk.

## Updating Existing Content

### How Updates Work

When you re-publish content with the same Ticket ID:

1. Plugin finds the existing post
2. Updates content, title, excerpt
3. Preserves WordPress-only data (comments, custom fields)
4. Updates sync timestamp

### Forcing New Post

To create a new post instead of updating:
- Use a different Ticket ID
- Or configure in RequestDesk to always create new

## Troubleshooting Publishing

### Content Not Appearing

1. Check post status (might be Draft)
2. Verify API key is correct
3. Check sync history for errors
4. Ensure user has permission to create posts

### Wrong Category

- Verify category name matches exactly
- Check if category exists in WordPress
- Category names are case-insensitive

### Images Not Loading

- Check if image URL is accessible
- Verify WordPress has write permissions
- Check Media Library for the image

### API Errors

Common API errors:

| Error | Cause | Solution |
|-------|-------|----------|
| 401 Unauthorized | Invalid API key | Check API key in settings |
| 403 Forbidden | Debug mode issue | Disable debug mode |
| 404 Not Found | Wrong endpoint | Verify site URL |
| 500 Server Error | WordPress issue | Check error logs |

See [Troubleshooting Guide](./troubleshooting.md) for more help.

## Best Practices

### Security

- Use HTTPS for your WordPress site
- Keep API keys secure
- Disable debug mode in production
- Regularly rotate API keys

### Content

- Review drafts before publishing
- Set up categories in WordPress first
- Use consistent naming conventions
- Include featured images for better engagement

### Performance

- Use caching plugins
- Optimize images
- Monitor sync frequency

## Next Steps

- [Explore AEO/GEO features](./AEO-GEO-Comprehensive-Guide.md)
- [Troubleshooting](./troubleshooting.md)
