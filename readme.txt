=== RequestDesk Connector ===
Contributors: requestdesk
Tags: content, api, publishing, automation, seo, schema
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 2.5.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect RequestDesk.ai to WordPress for seamless content publishing with AI-First Schema Markup.

== Description ==

The RequestDesk Connector plugin allows you to publish content from RequestDesk.ai directly to your WordPress site. Content is sent via REST API and created as draft posts for your review.

**NEW in v2.5.0:** Prepared for WordPress.org with improved security and documentation.

= Features =

* **AI-First Schema Markup** - Automatic schema generation optimized for AI search engines
* **8 Schema Types** - Article, FAQ, HowTo, Product, LocalBusiness, Video, Course, Breadcrumb
* **Smart Content Detection** - Auto-detects content type with confidence scoring
* **WooCommerce Integration** - Automatic Product schema for WooCommerce products
* **LMS Integration** - Course schema for LearnDash, LifterLMS, Tutor LMS
* **Video Detection** - Auto-detects YouTube, Vimeo, and HTML5 videos
* Secure API key authentication
* Configurable API key management
* Receive content via REST API
* Create posts as drafts automatically
* Support for categories and tags
* Sync history tracking
* Debug mode for testing (disable in production)

== Installation ==

1. Upload the `requestdesk-connector` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to RequestDesk menu in your WordPress admin
4. **IMPORTANT:** Configure your RequestDesk agent API key in the Security Settings section
5. Configure other settings and note the API endpoint
6. Ensure debug mode is disabled for production use

== Frequently Asked Questions ==

= How do I get an API key? =

API keys are managed through your RequestDesk.ai agent settings. Each agent has its own API key that can be used for authentication.

= What post types are supported? =

Currently, the plugin supports standard WordPress posts. Pages and custom post types will be added in future versions.

= Can I customize the post status? =

Yes, you can set the default post status in the plugin settings. You can also specify the status when sending content from RequestDesk.

== External Services ==

This plugin connects to the following external services:

= RequestDesk.ai =
* URL: https://app.requestdesk.ai
* Purpose: Content syncing and publishing automation
* Data sent: Post content, API key for authentication
* When: Only when you initiate a sync operation
* Terms: https://requestdesk.ai/terms
* Privacy: https://requestdesk.ai/privacy

= Anthropic Claude API (Optional) =
* URL: https://api.anthropic.com
* Purpose: AI-powered content analysis and schema suggestions
* Data sent: Post content for analysis (only when user clicks Analyze)
* When: Only when you explicitly click the "Analyze with Claude" button
* Requires: User's own Claude API key (configured in settings)
* Terms: https://anthropic.com/terms
* Privacy: https://anthropic.com/privacy

== Privacy ==

This plugin:
* Stores API keys locally in your WordPress database (encrypted)
* Sends content to RequestDesk.ai only when you initiate a sync
* Optionally sends content to Claude API only when you click "Analyze"
* Does not collect or transmit data without explicit user action
* Does not track users or collect analytics

== Changelog ==

= 2.5.0 =
* Prepared for WordPress.org submission
* Removed external auto-updater (now uses WordPress.org updates)
* Improved security with enhanced nonce verification
* Added privacy policy and external service disclosures
* Made templates generic for wider use

= 2.4.0 =
* **NEW: AI-First Schema Markup System**
* Added automatic content type detection with confidence scoring
* Added Product/Review schema with WooCommerce integration
* Added LocalBusiness schema with address/hours detection
* Added Video schema with YouTube/Vimeo/HTML5 auto-detection
* Added Course schema with LearnDash/LifterLMS/Tutor LMS integration
* Added Breadcrumb schema (always recommended for AI)
* Enhanced Claude AI prompts for smarter schema suggestions
* New Schema Types settings section in AEO Settings
* Detection sensitivity control (40%/60%/80% confidence thresholds)
* Schema types can be individually enabled/disabled

= 2.3.22 =
* Fixed Claude model integration with real API model IDs
* Frontend Q&A display system improvements
* Auto-update toggle fixes

= 1.1.0 =
* **SECURITY:** Added configurable API key authentication
* **SECURITY:** Exact API key matching with hash_equals() for timing attack protection
* Added admin interface for API key configuration
* Enhanced security warnings for debug mode
* Added API key validation with clear error messages
* Updated settings page with security indicators
* **BREAKING CHANGE:** API keys must now be configured in WordPress admin

= 1.0.0 =
* Initial release
* Basic post creation via REST API
* Category and tag support
* Sync history tracking

== API Documentation ==

= Endpoints =

* POST /wp-json/requestdesk/v1/posts - Create a new post
* GET /wp-json/requestdesk/v1/test - Test connection
* GET /wp-json/requestdesk/v1/sync-status/{ticket_id} - Check sync status

= Authentication =

Include your agent API key in the header:
`X-RequestDesk-API-Key: YOUR_API_KEY`

= Example Request =

```
POST /wp-json/requestdesk/v1/posts
Content-Type: application/json
X-RequestDesk-API-Key: YOUR_API_KEY

{
  "title": "Your Post Title",
  "content": "Post content here",
  "excerpt": "Optional excerpt",
  "ticket_id": "unique_ticket_id",
  "agent_id": "agent_id",
  "post_status": "draft",
  "categories": ["Category 1"],
  "tags": ["tag1", "tag2"]
}
```
