# Configuration Guide

This guide covers all configuration options for the RequestDesk Connector plugin.

## Accessing Settings

1. Log in to your WordPress admin dashboard
2. Click **RequestDesk** in the sidebar
3. Click **Settings**

The settings page has two tabs: **General Settings** and **AEO Settings**.

## General Settings

### Security Settings

#### RequestDesk API Key

Your API key authenticates requests from RequestDesk.ai to your WordPress site.

**To get your API key:**
1. Log in to app.requestdesk.ai
2. Go to **Settings > Integrations > WordPress**
3. Copy your API key

**To configure:**
1. Paste your API key in the "Allowed API Key" field
2. Click **Save Settings**

When configured correctly, you'll see: "Secure: API key is configured"

#### Debug Mode

Debug mode is for development and testing only.

- **Enabled:** Logs detailed information, may bypass some security checks
- **Disabled:** Production mode with full security (recommended)

**Warning:** Never enable debug mode on production sites.

### Plugin Settings

#### Default Post Status

Choose the default status for posts created via the API:

- **Draft** (recommended) - Review before publishing
- **Pending Review** - Requires editor approval
- **Published** - Goes live immediately
- **Private** - Only visible to logged-in users

#### Allowed Post Types

Select which post types can receive content from RequestDesk:

- Posts
- Pages
- Custom post types (if registered)

## AEO Settings

AEO (Answer Engine Optimization) settings control AI-First Schema Markup features.

### General AEO Options

#### Enable AEO Features

Master toggle for all AEO functionality.

#### Auto-Optimize on Publish

Automatically analyze and add schema markup when posts are published.

#### Auto-Optimize on Update

Re-analyze content when posts are updated.

### Schema Generation

#### Generate FAQ Schema

Automatically creates FAQPage schema from Q&A content.

#### Extract Q&A Pairs

Uses AI to identify question-answer patterns in your content.

#### Minimum Content Length

Posts shorter than this (in characters) won't be auto-analyzed.
- Default: 300 characters

#### Q&A Extraction Confidence

Minimum confidence score for extracted Q&A pairs (0.0 to 1.0).
- Default: 0.7 (70%)

### Schema Types

Enable or disable specific schema types:

- **Article** - Blog posts and articles
- **FAQ** - Frequently asked questions
- **HowTo** - Step-by-step instructions
- **Product** - E-commerce products (WooCommerce)
- **LocalBusiness** - Business location info
- **Video** - YouTube, Vimeo, HTML5 videos
- **Course** - Online courses (LearnDash, LifterLMS)
- **Breadcrumb** - Site navigation (always recommended)

#### Detection Sensitivity

Controls how aggressively the plugin detects content types:

- **Low (40%)** - Only obvious matches
- **Medium (60%)** - Balanced detection
- **High (80%)** - Aggressive detection

### Content Freshness

#### Monitor Freshness

Track when content was last updated for freshness signals.

#### Freshness Alert Days

Days before content is flagged as potentially stale.
- Default: 90 days

### Citation Tracking

#### Track Citations

Monitor external links and citations in your content for authority signals.

## Claude AI Integration (Optional)

The plugin can use Anthropic's Claude AI for intelligent content analysis.

### Claude API Key

1. Get an API key from console.anthropic.com
2. Enter it in the Claude API Key field
3. Click **Test Connection** to verify

### Claude Model

Select which Claude model to use:

- **Claude Sonnet 4** - Fast, cost-effective (recommended)
- **Claude Opus 4** - Most capable, higher cost

### What Claude Does

When configured, Claude provides:

- Intelligent schema type suggestions
- Content quality analysis
- Q&A pair extraction
- Optimization recommendations

**Note:** Claude is optional. The plugin works without it using rule-based analysis.

## Saving Settings

Always click **Save Settings** after making changes.

Settings are stored in your WordPress database and persist across updates.

## Recommended Configuration

For most sites, we recommend:

```
General Settings:
- API Key: [Your RequestDesk API key]
- Debug Mode: Disabled
- Default Post Status: Draft

AEO Settings:
- Enable AEO Features: Yes
- Auto-Optimize on Publish: Yes
- Generate FAQ Schema: Yes
- Detection Sensitivity: Medium (60%)
- Monitor Freshness: Yes
```

## Next Steps

- [Learn about features](./features.md)
- [Start publishing from RequestDesk](./using-with-requestdesk.md)
- [Explore AEO/GEO optimization](./AEO-GEO-Comprehensive-Guide.md)
