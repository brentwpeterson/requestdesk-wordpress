# Changelog

All notable changes to the RequestDesk Connector plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.16.0] - 2026-05-14

### Added
- **Audit Capture module** — new `class-requestdesk-audit-capture.php`:
  - Registers a `cc_audit_request` CPT for logging audit requests from newsletter button clicks. Admin UI under "Audit Requests" menu.
  - Settings submenu sets the notification email for new requests.
  - Public landing-page shortcode `[cc_audit_landing]`. Reads `?em=` and `?dom=` query params from the URL: when both are present, captures the click directly with no form; when only email is present, prefills it on a single-field URL form; with neither, shows a two-field fallback.
  - REST endpoint `POST /wp-json/cc-audit/v1/request` for programmatic submission with `{email, url, source}`.
  - Sends a plain-text notification email with a deep link to the CPT record on every new capture.
- Documentation in `docs/audit-capture.md` covering the WP setup steps, HubSpot smart-content button HTML for has-domain and no-domain audiences, REST contract, and fulfillment loop.

### Why
Newsletter subscribers know their email is already on file; making them refill a form to request an audit is needless friction. The button now carries identity into the URL, and subscribers with a known company domain submit with zero clicks past the email button itself. v1 is deliberately MVP — capture + notify, manual audit fulfillment via Claude Code. HMAC link signing, automated audit pipeline, and HubSpot timeline events are tracked as v2 work.

## [2.15.2] - 2026-05-11

### Performance
- **`comparison-table.css` no longer enqueues on every page.** The previous unconditional enqueue in `requestdesk-connector.php` loaded the stylesheet site-wide regardless of whether the page used the `[requestdesk_comparison_table]` shortcode. The shortcode class (`class-requestdesk-comparison-table.php` line 50) already enqueues the stylesheet on-demand when the shortcode renders. The blanket hook was dead weight, render-blocking on every page that did not need it. Removed.
- **`frontend-qa.css` is now conditionally enqueued.** Previously loaded on every singular page (`is_single() || is_page()`). Now checks the AEO `auto_display_qa_frontend` setting AND that the current post has `aeo_data['ai_questions']` registered before enqueueing. Front page is also skipped (matches the existing skip in `auto_append_qa_to_content`). Saves one render-blocking CSS request on every page that does not actually render Q&A content.

### Why
Caught by the contentcucumber.com 2026-05-09 SEO audit, mobile PSI Performance 47, 15 render-blocking stylesheets in `<head>`. These two plugin stylesheets were the easiest to scope back without removing functionality. Removing them from pages that do not need them does not affect rendering on pages that do.

## [2.15.1] - 2026-05-10

### Fixed
- **Duplicate FAQPage JSON-LD on every page where Frontend QA auto-display is enabled.** `RequestDesk_Frontend_QA::render_qa_pairs()` (class-requestdesk-frontend-qa.php) was emitting a JSON-LD `<script>` containing FAQPage AFTER the visible Q&A HTML in the document body. `RequestDesk_AEO_Core::output_schema_markup()` (class-requestdesk-aeo-core.php line 302, hooked to `wp_head`) was already emitting the same FAQPage in the document `<head>` from the same `aeo_data['faq_data']` post meta. Pages ended up with two byte-identical FAQPage scripts, which AI crawlers and Google treat as a structured-data inconsistency.
- **Fix:** removed the JSON-LD `<script>` block at the end of `render_qa_pairs()`. Visible Q&A HTML retains its Schema.org microdata (`itemtype="https://schema.org/Question"` / `Answer`), so AI crawlers and Google extract the same data from the rendered DOM. AEO Core's `wp_head` emission becomes the single source of FAQPage schema.

### Caught by
- contentcucumber.com 2026-05-09 SEO audit. `/contact/` had two byte-identical FAQPage scripts (one in head from AEO Core, one in body from Frontend QA). Affected every page with `auto_display_qa_frontend = true` AND `aeo_data['faq_data']` set.

## [2.14.0] - 2026-04-22

### Added
- **IndexNow submission** — new module `class-requestdesk-indexnow.php`:
  - Auto-generates a site-wide IndexNow key on first activation (UUID v4, stored in `wp_options`)
  - Serves the key verification file at `/{key}.txt` via `template_redirect`
  - Auto-submits URLs to `api.indexnow.org` on post publish and update (hooked on `transition_post_status`)
  - Admin settings page under RequestDesk → IndexNow (enable toggle, post-type selector, key file test, regenerate key)
  - Bulk-submit button for one-shot submission of all published URLs (batches of 1,000 with 2-second pacing)
  - Submission log (last 50 entries) with timestamp, URL count, HTTP response code, and trigger type
- Reaches Bing, Yandex, Seznam, and Naver immediately via the IndexNow aggregator; Google has stated they are evaluating the protocol

### Notes
- IndexNow spec allows 10,000 URLs/day per key; plugin batches + paces to stay safely within limits
- Key file must be publicly reachable on the origin for engines to validate submissions (works behind Cloudflare which passes through by default)

## [2.5.0] - 2026-02-03

### Added
- External Services section in readme.txt for WordPress.org compliance
- Privacy section documenting data handling practices
- PHP 7.4 minimum requirement specification

### Changed
- Prepared for WordPress.org plugin directory submission
- Removed external auto-updater (now uses WordPress.org updates)
- Made templates generic for wider use (removed company-specific references)
- Updated version compatibility (Tested up to WordPress 6.7)

### Removed
- External auto-updater system (class-requestdesk-plugin-updater.php)
- Company-specific content from templates

## [2.4.0] - 2025-12-09

### Added
- **AI-First Schema Markup System** - Automatic content type detection with confidence scoring
- Product/Review schema with WooCommerce integration
- LocalBusiness schema with address/hours detection
- Video schema with YouTube/Vimeo/HTML5 auto-detection
- Course schema with LearnDash/LifterLMS/Tutor LMS integration
- Breadcrumb schema (always recommended for AI)
- Schema Types settings section in AEO Settings
- Detection sensitivity control (40%/60%/80% confidence thresholds)

### Changed
- Enhanced Claude AI prompts for smarter schema suggestions
- Schema types can be individually enabled/disabled

## [2.3.22] - 2025-11-21

### Fixed
- **Critical:** "Generate Q&A Pairs" button not working in post editor
- Missing JavaScript file `assets/js/aeo-admin.js` that prevented AJAX functionality
- Claude model integration with real API model IDs

### Added
- Complete `aeo-admin.js` with proper AJAX handlers for Q&A generation
- Comprehensive debug logging for troubleshooting
- Error handling and user feedback for failed operations

## [2.3.16] - 2025-11-20

### Fixed
- "Enable auto-updates" toggle button not working
- Translation loading issues in auto-update action handlers

### Added
- Proper action handlers for enable/disable auto-update actions
- Success/error notices for auto-update toggle actions

## [2.3.15] - 2025-11-20

### Added
- **Frontend Q&A Display System** - Complete public-facing Q&A pairs display
- `[requestdesk_qa]` shortcode with customizable options
- Optional automatic Q&A display at end of posts/pages
- Full admin control panel for frontend Q&A configuration
- Template functions: `requestdesk_display_qa_pairs()`, `requestdesk_get_qa_pairs()`, `requestdesk_has_qa_pairs()`
- Responsive design with mobile-friendly, dark theme support
- Automatic FAQ schema markup for SEO
- Confidence filtering for Q&A pairs

## [2.3.14] - 2025-11-20

### Fixed
- **Critical:** "133 characters of unexpected output" activation error
- WordPress 6.7.0+ compatibility for translation loading
- Lazy-loaded plugin version data to prevent early translation loading

## [2.3.11] - 2025-11-20

### Fixed
- Auto-updater now safely initializes after activation completes
- Added activation completion flag to prevent early auto-updater initialization

## [2.3.6] - 2025-11-20

### Added
- Auto-update system for plugin updates

## [2.3.1] - 2025-11-13

### Fixed
- `Undefined property: RequestDesk_API::$namespace` error
- WordPress REST route registration compliance
- Empty namespace error for `/update-featured-image` endpoint

### Changed
- All REST routes now use consistent namespace management
- Standardized routes to use `$this->namespace` for maintainability

## [1.1.0] - 2025-10-xx

### Added
- Configurable API key authentication
- Admin interface for API key configuration
- API key validation with clear error messages

### Security
- Exact API key matching with `hash_equals()` for timing attack protection
- Enhanced security warnings for debug mode

### Changed
- **Breaking:** API keys must now be configured in WordPress admin

## [1.0.0] - 2025-10-xx

### Added
- Initial release
- Basic post creation via REST API
- Secure API key authentication
- Category and tag support
- Sync history tracking
- Debug mode for testing
