# Changelog

All notable changes to the RequestDesk Connector plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.24.1] - 2026-06-24

### Fixed
- **Brand Assets menu 404 + parent-menu hijack.** The Brand Assets submenu registered at `admin_menu` priority 10, but because the module instantiates at file-load it fired *before* the main RequestDesk menu was built — so its parent (`requestdesk-aeo-analytics`) didn't exist yet. WordPress then made Brand Assets the first submenu (so clicking "RequestDesk" landed on it) and generated a malformed `/wp-admin/requestdesk-brand-assets` URL that 404'd. Registering at priority 11 guarantees the parent exists first; the parent now lands on the AEO Dashboard and Brand Assets resolves to the correct `admin.php?page=requestdesk-brand-assets`.

## [2.24.0] - 2026-06-24

### Added
- **Bundled demo ads.** Two demo creatives ship inside the plugin (`assets/img/demo-ads/`): an 1980s-mullet-teen banner (1200×300) and sidebar (300×600), each labeled with its pixel size. A **"Load demo ads"** button on the Brand Assets page sideloads them into the Media Library, flags them in rotation with the right placement, and links them to the homepage — idempotent (dedupes by a `_rd_demo_ad` marker). Instant, self-contained way to see the rotator working with no manual seeding.

### Why
The ad system was untestable without hand-seeding an asset. Shipping the demos in the module means the feature works out of the box: install, click "Load demo ads," turn on auto-insert / drop the widget, and the rotator is live. The two demo sizes double as the starting size registry (1200×300 banner, 300×600 sidebar).

## [2.23.0] - 2026-06-24

### Added
- **Ad click + impression tracking.** Every ad link routes through a cache-proof click endpoint (`/?rd_ad_click=ID`) that increments a click counter then 302-redirects to the offer — stamping **UTM params** (`utm_source=contentcucumber`, `utm_medium=<placement>_ad`, `utm_campaign=brand_assets`, `utm_content=<id>`) so GA attributes it too. Impressions fire via a `navigator.sendBeacon` to admin-ajax when ads render (survives caching).
- **Per-ad stats in the hub.** Each Brand Assets card shows lifetime **views · clicks · CTR**.
- **Sponsored toggle.** Per-asset "Sponsored" checkbox adds `rel="sponsored nofollow"` to that ad's link (FTC / paid-partner ads).

### Why
Phase 3 (final) of ads-on-blog-posts. Clicks can't be counted at render time because the page HTML is cached — the redirect endpoint is the cache-proof path, and it doubles as the UTM tagger so the same click shows in GA. Impressions via beacon give a real denominator for CTR. Counters live in post meta (lifetime totals); date-range reporting via an events table is the Phase 4 upgrade.

## [2.22.0] - 2026-06-24

### Added
- **Auto-insert banner ads into blog posts.** A `the_content` filter drops a random banner ad after the Nth paragraph of single posts (never pages, archives, feeds, or secondary queries). Configured on the Brand Assets page under **Blog Post Ads**: on/off + "after paragraph N" (posts shorter than N paragraphs are skipped). The inserted slot is the same cache-safe placeholder the rotator JS fills client-side.
- **Per-post opt-out.** A **RequestDesk Ads** meta box on the post editor with "Hide auto-inserted ads in this post" (`_rd_hide_ads`).
- In-content spacing class `.rd-ad-incontent`.

### Why
Phase 2 of ads-on-blog-posts. Hand-placing a shortcode in every post doesn't scale — flip auto-insert on once and every qualifying post gets a banner, with a per-post escape hatch. Sidebar ads stay manual via the widget (one placement, one widget area). Click + impression tracking is Phase 3.

## [2.21.0] - 2026-06-24

### Added
- **Ad placements (banner vs sidebar).** Each rotation asset now has an **Ad placement** select — Any / Banner only / Sidebar only — so a sidebar slot pulls sidebar-shaped creatives and a banner slot pulls banner-shaped ones instead of one flat pool. The rotation pool is bucketed by placement (`banner`, `sidebar`, `all`); an "Any" asset fills either slot.
- **Placement-aware widget + shortcode.** `[requestdesk_random_ad placement="banner|sidebar|all"]` and the widget's new **"Pull from"** dropdown (defaults to Sidebar) each draw from the matching bucket. The localized pools are bucketed so a page can show a banner slot and a sidebar slot drawing from different shapes, all still cache-safe.

### Why
Phase 1 of ads-on-blog-posts. "Banner" and "sidebar" are different shapes; a single rotation pool would squish a wide banner into a sidebar. Tagging each ad with where it belongs lets one engine feed both slots correctly. (Auto-insertion into post bodies and click/impression tracking land in Phases 2-3.)

## [2.20.0] - 2026-06-24

### Added
- **Random Ad rotator.** Turns the Brand Asset Hub into an ads database. Each asset card gets an **"Include in ad rotation"** toggle; opted-in assets form the ad pool. Three placements share one engine: a classic **WP_Widget** ("RequestDesk: Random Ad", droppable in any widget area or the block widget editor as a legacy widget), the **`[requestdesk_random_ad count="1"]` shortcode**, and the widget's own title/count form.
- **Cache-safe random pick.** The eligible-ad pool is localized to the page and the random selection happens in the browser (`assets/js/ad-rotator.js`), so the ad rotates on every page view even behind full-page caching. The first pool item renders server-side as a no-JS fallback. New `assets/css/ad-rotator.css`.

### Why
A static "grab the banner" library is half the ask — the other half is putting an ad on the site that changes on its own. Backing the rotator with the same Media-Library-flagged assets means the ads database and the brand-asset library are one thing; opting an asset into rotation is a checkbox, not a separate upload. Client-side selection is the standard for ad rotators on cached WordPress sites (server-side random gets frozen by the page cache).

## [2.19.0] - 2026-06-24

### Added
- **Brand Asset Hub.** New "Brand Assets" admin page (RequestDesk → Brand Assets) — a grab-and-go library for promo banners, logos, mascots, and brand images. A brand asset is just a Media Library attachment flagged for the hub, so its hosted URL (`wp-content/uploads/...`) is already stable and public; no new storage. Each asset card serves four one-click copies: **Download** (file URL), **Image URL** (for emails), **Embed code** (`<a href="LINK"><img src="URL" alt="ALT" width height></a>` — the affiliate/partner-banner pattern that links back to the offer), and **Shortcode**. Per-asset "Links to" URL + alt text are editable inline.
- **`[requestdesk_brand_asset id="123"]` shortcode** (with optional `link`, `class`, `width` overrides) renders the linked banner into any page or post.
- New module `RequestDesk_Asset_Hub` (`includes/class-requestdesk-asset-hub.php`) + `assets/css/asset-hub.css` + `assets/js/asset-hub.js`. AJAX mark/save/remove, all nonce-protected and `manage_options`-gated.

### Why
Promo banners and brand images were living in a scratch folder (`generated_imgs/`) with no stable home, so reusing them on partner sites, guest posts, emails, or internal CC pages meant hunting down the file and hand-writing the embed each time. The hub gives every asset one stable URL and a copy-paste embed, the same way affiliate programs hand partners a banner. CC-gated via `requestdesk_is_cc_site()` (flip the guard at the bottom of the module to share it with other connector sites).

## [2.16.5] - 2026-05-21

### Added
- **Case Study "Completion" admin column.** New column on Posts → Case Studies surfaces missing fields per row as small chips (photo, industry, platform, service, outcome, stat, excerpt, body, AEO). Rows with all required fields show a green "complete" badge. Drives the production push: at a glance Brent can see which case studies still need work without clicking into each post.
- Helper `RequestDesk_Case_Study::get_completion($post_id)` returns `['complete' => bool, 'missing' => [...]]` and is also reusable from templates / REST endpoints later.

### Why
Case studies need finishing work (photos, AEO summaries, etc.) and there was no surface that showed gaps without opening each post one at a time. Photo-per-card is the visual lift Brent wants on the archive — the column makes "what's missing across the catalog" a single glance instead of a clicking exercise.

## [2.16.4] - 2026-05-21

### Security / Scoping
- **CC-only modules gated by host.** `RequestDesk_Partner` (cc_partner CPT, "CC Partners" admin menu, partner importer) and `RequestDesk_Case_Study` (cc_case_study CPT, "Our Work" admin menu, case-study importer) previously instantiated unconditionally on every site that loaded the plugin. On non-CC sites (e.g. Talk Commerce) this exposed the import UI loaded with CC's partner/case-study data files (which ship inside the plugin), one click away from importing CC content into another site's DB.
- Added `requestdesk_is_cc_site()` helper in `requestdesk-connector.php`. Returns true on `contentcucumber.com`, `www.contentcucumber.com`, or `contentcucumber.local`. Override with `define('REQUESTDESK_CC_FEATURES', true|false)` in `wp-config.php` for staging or future-site cases.
- Bottom-of-file instantiations in `class-requestdesk-partner.php` and `class-requestdesk-case-study.php` now wrapped in the gate.

### Why
Reading the code confirmed every consumer of this plugin (TC and any other site running it) had the full CC partner roster sitting in `includes/data/import/partners/*.json` plus a working admin import page. Not actively leaking, but one mis-click away. Gating the instantiation kills the exposure without touching the data files — those still ship as code so a future CC reinstall can replay them.

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
