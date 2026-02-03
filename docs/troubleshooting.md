# Troubleshooting & FAQ

Solutions to common issues and frequently asked questions.

## Common Issues

### Plugin Won't Activate

**Symptoms:** Error message when activating, white screen, or plugin stays deactivated.

**Solutions:**

1. **Check PHP version**
   - Requires PHP 7.4 or higher
   - Check in WordPress: **Tools > Site Health > Info > Server**

2. **Check WordPress version**
   - Requires WordPress 5.0 or higher
   - Update WordPress if needed

3. **Check for conflicts**
   - Deactivate other plugins temporarily
   - Switch to a default theme (Twenty Twenty-Four)
   - Try activating again

4. **Check error logs**
   - Enable WP_DEBUG in wp-config.php
   - Check `/wp-content/debug.log`

### API Key Not Working

**Symptoms:** "Unauthorized" errors, content not syncing.

**Solutions:**

1. **Verify key is saved**
   - Go to **RequestDesk > Settings**
   - Re-enter the API key
   - Click Save Settings

2. **Check for whitespace**
   - Copy/paste can add invisible characters
   - Delete and re-type the key manually

3. **Verify key matches**
   - Compare with key in RequestDesk.ai dashboard
   - Keys are case-sensitive

4. **Check debug mode**
   - Debug mode should be OFF for production
   - Enable only for testing

### Content Not Appearing

**Symptoms:** Sync shows success but post not visible.

**Solutions:**

1. **Check post status**
   - Go to **Posts > All Posts**
   - Check "Drafts" or "Pending" tabs
   - Posts may not be published yet

2. **Check user permissions**
   - API requires user with publish capability
   - Verify in **Users** settings

3. **Check post type**
   - Ensure post type is enabled in plugin settings

4. **Clear cache**
   - Clear any caching plugins
   - Clear CDN cache if applicable

### Schema Not Generating

**Symptoms:** AEO analysis runs but no schema appears.

**Solutions:**

1. **Check content length**
   - Content must meet minimum length (default: 300 chars)
   - Adjust in AEO Settings if needed

2. **Check schema type settings**
   - Go to **RequestDesk > Settings > AEO Settings**
   - Ensure desired schema types are enabled

3. **Check detection sensitivity**
   - Lower sensitivity = fewer matches
   - Try increasing to "High" temporarily

4. **Manual override**
   - Use the post editor meta box
   - Manually select schema type

### Claude API Errors

**Symptoms:** "Analysis failed" or Claude-related errors.

**Solutions:**

1. **Verify API key**
   - Check key at console.anthropic.com
   - Ensure key is active and has credits

2. **Test connection**
   - Go to Settings
   - Click "Test Claude Connection"
   - Check error message

3. **Check rate limits**
   - Anthropic has rate limits
   - Wait and retry if rate limited

4. **Works without Claude**
   - Claude is optional
   - Plugin uses rule-based analysis as fallback

### Images Not Uploading

**Symptoms:** Featured images missing or broken.

**Solutions:**

1. **Check source URL**
   - Image URL must be publicly accessible
   - Try opening URL in browser

2. **Check permissions**
   - WordPress needs write access to uploads folder
   - Check `/wp-content/uploads/` permissions

3. **Check file type**
   - Only JPEG, PNG, WebP, GIF supported
   - Convert other formats first

4. **Check file size**
   - Very large images may timeout
   - Optimize before uploading

### Slow Performance

**Symptoms:** Dashboard slow, analysis takes long time.

**Solutions:**

1. **Reduce batch size**
   - In Bulk Optimizer, process fewer posts at once

2. **Disable auto-optimize**
   - Turn off "Auto-Optimize on Update"
   - Run optimization manually instead

3. **Check server resources**
   - Analysis is CPU-intensive
   - Consider upgrading hosting

4. **Use caching**
   - Install a caching plugin
   - Schema is cached after generation

## Frequently Asked Questions

### General Questions

**Q: Is this plugin free?**

A: Yes, the plugin is free and open source. Some features require a RequestDesk.ai account.

**Q: Do I need a RequestDesk.ai account?**

A: For publishing from RequestDesk, yes. AEO features work standalone without an account.

**Q: Does this work with Gutenberg?**

A: Yes, fully compatible with the block editor.

**Q: Does this work with Classic Editor?**

A: Yes, both editors are supported.

**Q: Can I use this on multiple sites?**

A: Yes, install on as many sites as needed. Each site needs its own API key.

### Security Questions

**Q: Is my API key secure?**

A: Keys are stored in the WordPress database. We recommend using HTTPS and keeping WordPress updated.

**Q: What data is sent to RequestDesk?**

A: Only content you explicitly publish. The plugin never sends data without your action.

**Q: What data is sent to Claude?**

A: Only when you click "Analyze". Post content is sent for analysis. See privacy policy for details.

**Q: Can I use this without external services?**

A: Yes, AEO features work offline. Only publishing to RequestDesk and Claude analysis require external connections.

### AEO/Schema Questions

**Q: Will schema help my SEO?**

A: Schema helps search engines understand your content. It can improve rich snippets and AI visibility.

**Q: What schema types should I enable?**

A: Enable types relevant to your content. Article and FAQ are useful for most sites.

**Q: How often should I re-analyze content?**

A: After significant updates. The plugin can auto-analyze on publish/update.

**Q: Does this replace Yoast/RankMath?**

A: No, this complements SEO plugins. It focuses on schema for AI engines specifically.

### Publishing Questions

**Q: Can I schedule posts from RequestDesk?**

A: Publish as Draft, then use WordPress scheduling.

**Q: Can I update existing posts?**

A: Yes, re-publishing with the same Ticket ID updates the existing post.

**Q: What happens to comments when I update?**

A: Comments are preserved. Only content is updated.

**Q: Can I publish to custom post types?**

A: Yes, enable custom post types in plugin settings.

## Error Messages

### "No API key configured"

The plugin needs an API key to authenticate requests.

**Fix:** Go to **RequestDesk > Settings** and enter your API key.

### "Invalid API key"

The provided API key doesn't match.

**Fix:** Verify the key in your RequestDesk.ai dashboard. Copy and paste carefully.

### "Debug mode is enabled"

Debug mode should only be used for testing.

**Fix:** Disable debug mode in **RequestDesk > Settings** for production.

### "Content too short for analysis"

The post doesn't meet minimum length requirements.

**Fix:** Add more content or lower the minimum in AEO Settings.

### "Schema type not detected"

The plugin couldn't determine appropriate schema.

**Fix:** Manually select a schema type in the post editor, or lower detection sensitivity.

### "Claude API error"

Problem communicating with Claude AI.

**Fix:** Check your Claude API key, verify you have API credits, check rate limits.

### "Failed to download image"

Couldn't fetch the featured image.

**Fix:** Verify the image URL is accessible. Check WordPress upload permissions.

## Getting Help

### Support Channels

- **Email:** support@requestdesk.ai
- **Documentation:** https://requestdesk.ai/documentation

### Reporting Bugs

1. Check this troubleshooting guide first
2. Search existing issues on GitHub
3. If new issue, report with:
   - WordPress version
   - PHP version
   - Plugin version
   - Steps to reproduce
   - Error messages

### Feature Requests

Email suggestions to support@requestdesk.ai or submit via GitHub.

## Debug Mode

For troubleshooting, you can enable debug logging:

1. Go to **RequestDesk > Settings**
2. Enable "Debug Mode"
3. Reproduce the issue
4. Check `/wp-content/debug.log`
5. **Disable debug mode when done**

Debug mode logs:
- API requests and responses
- Schema generation steps
- Claude AI interactions
- Error details

**Warning:** Debug mode may log sensitive information. Disable in production.
