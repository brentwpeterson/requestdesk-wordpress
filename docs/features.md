# Features Overview

The RequestDesk Connector plugin provides powerful content publishing and SEO optimization features for WordPress.

## Core Features

### Content Publishing via API

Receive and publish content directly from RequestDesk.ai to WordPress.

**Capabilities:**
- Create posts and pages via REST API
- Set post status, categories, and tags
- Include featured images
- Support for custom fields
- Sync tracking and history

### Secure API Authentication

Enterprise-grade security for API communications.

**Security features:**
- API key authentication required
- Timing-attack resistant validation (hash_equals)
- Request logging and monitoring
- Debug mode for testing (disable in production)

### Sync History Tracking

Track all content synced from RequestDesk.

**What's tracked:**
- Ticket ID from RequestDesk
- WordPress post ID
- Sync timestamp
- Agent ID
- Success/failure status

## AEO/GEO Optimization

AI-First Schema Markup for modern search engines and AI assistants.

### AI-First Schema Markup

Automatic schema generation optimized for:
- Google Search
- ChatGPT
- Claude
- Perplexity
- Google AI Overviews

### 8 Schema Types Supported

| Schema Type | Auto-Detection | Use Case |
|-------------|---------------|----------|
| Article | Yes | Blog posts, news articles |
| FAQ | Yes | Q&A content, help pages |
| HowTo | Yes | Tutorials, step-by-step guides |
| Product | Yes | E-commerce products |
| LocalBusiness | Yes | Business locations |
| Video | Yes | Embedded videos |
| Course | Yes | Online courses |
| Breadcrumb | Always | Navigation structure |

### Smart Content Detection

The plugin automatically analyzes content to determine appropriate schema:

- **Pattern matching** - Identifies Q&A formats, step lists, pricing
- **Keyword detection** - Finds indicators like "how to", "FAQ", prices
- **Confidence scoring** - Only applies schema above threshold
- **Claude AI analysis** (optional) - Intelligent recommendations

### Platform Integrations

**WooCommerce:**
- Automatic Product schema for all products
- Price, availability, reviews included
- No configuration needed

**LMS Plugins:**
- LearnDash course detection
- LifterLMS integration
- Tutor LMS support
- Automatic Course schema

**Video Platforms:**
- YouTube auto-detection
- Vimeo support
- HTML5 video elements
- VideoObject schema

## Content Analysis Tools

### AEO Dashboard

Central dashboard showing:
- Overall AEO score across content
- Content needing optimization
- Schema coverage statistics
- Freshness alerts

### Bulk Optimizer

Optimize multiple posts at once:
- Select posts by status, category, or date
- Batch analyze content
- Apply schema in bulk
- Track optimization progress

### Post-Level Analysis

In the post editor:
- Real-time AEO score
- Schema preview
- Q&A pair extraction
- Optimization suggestions

## Q&A Features

### Automatic Q&A Extraction

The plugin identifies question-answer patterns in your content:

- Headings formatted as questions
- FAQ sections
- Conversational Q&A
- Interview formats

### Q&A Display Options

Display extracted Q&A on the frontend:

**Shortcode:**
```
[requestdesk_qa]
[requestdesk_qa post_id="123" show_confidence="true"]
```

**Automatic display:**
- Enable in settings to auto-append Q&A to posts
- Customizable styling
- Mobile-responsive design

**Template functions:**
```php
requestdesk_display_qa_pairs($post_id);
requestdesk_get_qa_pairs($post_id);
requestdesk_has_qa_pairs($post_id);
```

### FAQ Schema Generation

Extracted Q&A automatically generates FAQPage schema:
- Appears in Google's "People Also Ask"
- Powers AI assistant responses
- Improves featured snippet chances

## Content Freshness

### Freshness Monitoring

Track content age and update frequency:
- Last modified dates
- Update recommendations
- Stale content alerts

### Freshness Signals

The plugin helps with:
- dateModified schema
- Content update tracking
- Freshness reporting

## Citation Tracking

### External Link Analysis

Monitor outbound links for E-E-A-T signals:
- Citation count per post
- Domain authority indicators
- Broken link detection

### Authority Building

Track citations to authoritative sources:
- Academic references
- Government sites
- Industry publications

## Template System

### AEO-Optimized Templates

Pre-built templates with complete AEO markup:

- **Homepage template** - Full Organization schema
- **About page template** - Person/Organization schema
- **Contact page template** - ContactPoint schema

### CSV Import

Import and customize templates via CSV:
- Bulk template generation
- Variable replacement
- Multi-site deployment

## Claude AI Integration

### Optional AI Enhancement

Connect your own Claude API key for:
- Intelligent schema suggestions
- Content quality analysis
- Natural language Q&A extraction
- Optimization recommendations

### Privacy-First Design

- Your API key, your account
- Data sent only when you click "Analyze"
- No data stored on external servers
- Full control over AI usage

## REST API Endpoints

### Available Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/wp-json/requestdesk/v1/posts` | POST | Create new post |
| `/wp-json/requestdesk/v1/test` | GET | Test connection |
| `/wp-json/requestdesk/v1/sync-status/{id}` | GET | Check sync status |

### Authentication

All endpoints require the `X-RequestDesk-API-Key` header.

## Next Steps

- [Configure the plugin](./configuration.md)
- [Start publishing from RequestDesk](./using-with-requestdesk.md)
- [Deep dive into AEO/GEO](./AEO-GEO-Comprehensive-Guide.md)
