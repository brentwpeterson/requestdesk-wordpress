# RequestDesk Connector — Technical Deficiency Audit

Repo: `/Users/brent/scripts/CB-Workspace/requestdesk-wordpress` (main, clean, v2.47.3)
Scope: read-only. No files modified, no git writes, no database access.
Method: full read of `requestdesk-connector.php`, `includes/class-requestdesk-{api,headless-api,aeo-core,yoast-schema,yoast-meta,yoast-schema-piece,audit-capture,asset-hub,push,frontend-qa,indexnow,freshness-tracker,citation-tracker,claude-integration,homepage-hero,stats-bar,comparison-table,child-grid,post-handler,schema-generator}.php`, `admin/{settings-page,headless-settings-page,aeo-settings-page,aeo-meta-boxes,aeo-template-importer,aeo-template-enhanced,homepage-hero-settings-page,seo-meta-boxes,yoast-import-page}.php`, plus repo-wide greps for every claim of "never called" / "never written".

Context assumed throughout: a **client install** = site modules OFF (`requestdesk_is_cc_site()` false), Yoast SEO active.

---

## BLOCKER for a client install

### B1. Unauthenticated public REST endpoint creates posts and triggers mail on every install
**`includes/class-requestdesk-audit-capture.php:72-104`** (`permission_callback => '__return_true'` at line 76)

`cc-audit/v1/request` accepts `POST {email, url, source}` from anyone on the internet — no API key, no nonce, no rate limit, no captcha, no origin check. Each call runs `create_request_post()` (`:220-244`) which `wp_insert_post()`s a published `cc_audit_request` row with the caller's IP and User-Agent, then `notify()` (`:246-263`) sends a `wp_mail()` to the site's `admin_email`.

`RequestDesk_Audit_Capture` is loaded unconditionally (`requestdesk-connector.php:121`) and constructed unconditionally (`requestdesk-connector.php:273`), so this route exists on every client site.

What it breaks: an attacker (or a bot sweeping `/wp-json/`) can fill the client's `wp_posts` table without limit and force the site to emit unbounded outbound mail, which puts the client's sending domain/IP at risk with their host. There is no admin control to turn the route off.

Smallest fix: move `class-requestdesk-audit-capture.php` from `$plugin_files` into `$cc_only_files` (`requestdesk-connector.php:150-172`) and drop `'RequestDesk_Audit_Capture'` from `$aeo_classes` (`:273`). If the endpoint must stay reachable, add a shared-secret or nonce check plus a per-IP throttle in `register_routes()`.

---

### B2. The Audit Capture module is Content Cucumber's, ships to clients, and says so in visitor-facing copy
**`includes/class-requestdesk-audit-capture.php:3` ("Content Cucumber audit-request capture"), `:18` (`cc_audit_request`), `:51-69` (registers the CPT with `show_in_menu => true`, `menu_position => 31`), `:119` (`'heading' => 'Your Content Cucumber audit'`), `:280-288` (adds an "Audit Capture Settings" submenu)**

The file's own comment at `:27` acknowledges "This plugin installs on sites that are not Content Cucumber" — and fixed only the from-address (`:33-40`). Everything else is still CC's.

What it breaks: a client admin opens wp-admin and sees an "Audit Requests" menu they did not ask for, holding leads captured from a shortcode whose default `<h2>` reads "Your Content Cucumber audit". This is the exact failure documented in the loader comment about Talk Commerce and case studies (`requestdesk-connector.php:161-167`), repeated in a module that was never added to the gate.

Smallest fix: same as B1 — move it into `$cc_only_files`.

---

### B3. On a Yoast client site, the plugin's default mode rewrites the client's Organization and WebSite identity
**`includes/class-requestdesk-yoast-schema.php:98-104`** (`mode()` returns `'requestdesk'` unless the option says otherwise), **`:290-327`** (`apply_organization`), **`:336-349`** (`apply_website`), registered unconditionally at **`requestdesk-connector.php:192-197`**.

`requestdesk_aeo_settings['yoast_mode']` is never written by the activation defaults (`requestdesk-connector.php:567-578` has no `yoast_mode` key), so a fresh client install resolves to `'requestdesk'`. That makes `requestdesk_wins()` true, and:

- `apply_organization()` (`:298-303`) replaces Yoast's `Organization.name` with `get_bloginfo('name')` and `Organization.description` with `get_bloginfo('description')` (via `RequestDesk_Schema_Generator::generate_organization_schema()`, `includes/class-requestdesk-schema-generator.php:874-902`).
- `apply_website()` (`:340-347`) does the same to the WebSite node.

What it breaks: Yoast's site-representation settings (a deliberately configured legal company name, a written company description) are silently overwritten by the WordPress site title and tagline — and the tagline on a large fraction of sites is still "Just another WordPress site". The client's structured data degrades the moment the plugin activates, with no notice and no opt-in.

Smallest fix: default `mode()` to `'off'` when Yoast is active and `requestdesk_is_cc_site()` is false — i.e. change `:103` to return `'requestdesk'` only when `requestdesk_is_cc_site()`, else `'off'`. Ship the client install with `yoast_mode = off` and let the admin opt in.

---

### B4. The Yoast meta takeover (2.47.0) reads meta keys that nothing in the shipped plugin can write
**`includes/class-requestdesk-yoast-meta.php:113-263`** reads `_requestdesk_seo_title`, `_requestdesk_seo_description`, `_requestdesk_canonical_url`, `_requestdesk_noindex`, `_requestdesk_nofollow`, `_requestdesk_og_title`, `_requestdesk_og_description`, `_requestdesk_og_image`, `_requestdesk_twitter_title`, `_requestdesk_twitter_description`, `_requestdesk_twitter_image`.

Repo-wide grep for writers of those eleven keys returns exactly two files:
- `admin/seo-meta-boxes.php:440-474` (`update_post_meta` / `delete_post_meta`)
- `includes/class-requestdesk-yoast-importer.php:24-41` (the Yoast→RequestDesk key map)

**Neither file is ever loaded.** They are absent from `$plugin_files` (`requestdesk-connector.php:87-133`) and `$cc_only_files` (`:150-172`), there is no autoloader, and no `require`/`include` anywhere references them. `admin/settings-page.php:582-585` states this outright: *"class-requestdesk-seo-core.php registers it, but that class is not in the plugin's load list."* Same for `includes/class-requestdesk-seo-core.php` and `admin/yoast-import-page.php`.

What it breaks: on a client install the useful half of the Yoast integration is inert — no title, description, canonical, robots, OG or Twitter value will ever differ from what Yoast already produced, because the source values cannot be created. The class doc at `:26` even tells the reader the values "were imported from Yoast by `RequestDesk_Yoast_Importer`", a class the plugin never loads. Meanwhile the half that *does* fire is B3, which only damages. Net effect of `yoast_mode = requestdesk` on a client: all cost, no benefit.

Smallest fix: add `admin/seo-meta-boxes.php`, `includes/class-requestdesk-seo-core.php`, `includes/class-requestdesk-yoast-importer.php` and `admin/yoast-import-page.php` to `$plugin_files` and wire the import page into the menu — or, if that engine is not wanted, delete the four files and strip `RequestDesk_Yoast_Meta` down to the paths that have real inputs.

---

### B5. `rd_event` claims a public `/events/` URL on every install, and the loader comment says it does not
**`includes/class-requestdesk-event.php:173-187`**: `'public' => true`, `'has_archive' => 'events'`, `'rewrite' => array('slug' => 'events', ...)`.
**`requestdesk-connector.php:128-129`**: *"Shared for the same reason as video: rd_event registers no public URL."*

That comment was true at 2.45.0 and was falsified by commit `9ac118b` ("rd_event: events are public posts at /events/<slug>/"). `RequestDesk_Video` still honours it (`includes/class-requestdesk-video.php:118-121`: `public => false`, `rewrite => false`); `RequestDesk_Event` no longer does. `maybe_flush_rewrites()` (`:190-196`) then flushes rewrite rules on every version change.

What it breaks: activating on a client site publishes an empty `https://client.com/events/` archive and takes the `events` permalink slug. If the client already has a page or a third-party plugin at `/events/`, the CPT rewrite can shadow it. This is the identical regression the loader documents for `cc_case_study` on Talk Commerce (`requestdesk-connector.php:160-167`).

Smallest fix: gate the public registration — register `rd_event` with `public => false, has_archive => false, rewrite => false` unless `requestdesk_is_cc_site()`, and correct the comment at `:128-129`.

---

### B6. Content Cucumber's HubSpot portal and form are written into the client's database on activation
**`requestdesk-connector.php:585-586`** — `add_option('requestdesk_homepage_hero_settings', [... 'hubspot_portal_id' => '39487190', 'hubspot_form_id' => '3c945309-67c6-4812-ab65-c7280682e005' ...])` runs in `requestdesk_activate()`.

The same pair is hardcoded in three more places:
- `includes/class-requestdesk-homepage-hero.php:40-41` (shortcode defaults, rendered at `:237-241`)
- `admin/homepage-hero-settings-page.php:72-73` (admin defaults)
- `admin/aeo-template-enhanced.php:153-157` (baked into the "Enhanced AEO Homepage" page template the Template Importer inserts as real post content)
- `admin/aeo-template-importer.php:2710-2711` (lead-magnet page builder — hardcodes portal `39487190` while accepting the form id from the CSV)

What it breaks: a client who drops `[requestdesk_homepage_hero]` on a page, or clicks "Import Template" in the Template Importer (`admin/aeo-template-importer.php:17-27`, `manage_options`), publishes a lead form that submits into **Content Cucumber's** HubSpot portal. Client leads land in CC's CRM. That is a data-handling incident, not a cosmetic bug.

Note the inconsistency: `admin/aeo-template-importer.php:3078-3090` (contact page builder) reads `hubspot_portal_id` from the CSV correctly. Only the lead-magnet and enhanced-homepage builders hardcode it.

Smallest fix: default all four `hubspot_portal_id` / `hubspot_form_id` values to `''`, have the hero shortcode render nothing when they are empty (`includes/class-requestdesk-homepage-hero.php:231` already guards on `!empty`), and change `admin/aeo-template-enhanced.php:153-157` and `admin/aeo-template-importer.php:2710-2711` to a `[CUSTOMIZE]` placeholder.

---

### B7. Every anonymous front-end pageview writes to the database
**`includes/class-requestdesk-aeo-core.php:353-397`** — `output_schema_markup()` is hooked to `wp_head` (`:28`) and at `:386-387` calls `$this->get_aeo_data($post_id)` on every `is_single()`/`is_page()` request.
**`:300-331`** — `get_aeo_data()` `SELECT`s the row and, when there is none, `$wpdb->insert()`s one (`:312-320`) and then recurses (`:322`).

The plugin knows this is wrong. `includes/class-requestdesk-frontend-qa.php:79-81` carries the comment: *"Deliberately a direct read rather than `RequestDesk_AEO_Core::get_aeo_data()`, which INSERTs a row when none exists — that would write to the database on every anonymous pageview."* The fix was applied to the stylesheet guard and not to the `wp_head` emitter it was describing.

The same path is reached through Yoast: `includes/class-requestdesk-yoast-schema.php:190-191` builds a data-only core and calls `get_clean_faq_schema()` → `get_aeo_data()` (`:406-412`) on every Yoast graph build.

What it breaks: a `SELECT` plus a conditional `INSERT` on every uncached page view. Behind full-page cache it is invisible; on a client site with a cold cache, a crawler, or no page cache it is a write per request, which defeats read replicas, defeats `DISALLOW_FILE_MODS`-style read-only hardening, and grows `wp_requestdesk_aeo_data` by one row per URL ever viewed.

Smallest fix: add a read-only variant (`get_aeo_data_readonly()` that returns `null` instead of inserting) and use it from `output_schema_markup()` (`:387`), `get_clean_faq_schema()` (`:407`) and the three `RequestDesk_Frontend_QA` call sites (`:147`, `:375`, `:457`). Keep the insert on the write paths only.

---

### B8. "Enable Headless API" is written but never read — switching it off does nothing
**`admin/headless-settings-page.php:14`** writes `requestdesk_headless_settings['enabled']`; **`:51`** renders the checkbox labelled *"Allow external frontends to fetch content via API"*.

Repo-wide grep: `requestdesk_headless_settings` is read in exactly three places — `includes/class-requestdesk-headless-api.php:918` (for the key only, `:926`), `requestdesk-connector.php:456-467` (key and URL), and the settings page itself. **The `enabled` key is never consulted.** `RequestDesk_Headless_API::register_routes()` (`:34-218`) registers all eight routes unconditionally, and `verify_api_key()` (`:916-967`) never checks it.

What it breaks: a client admin unticks the box, saves, sees "Headless API settings saved!", and believes the API is closed. All eight routes remain live and still serve content to any holder of either key. A security control that reports success and does nothing is worse than no control.

Smallest fix: early-return from `register_routes()` when `($headless_settings['enabled'] ?? true)` is false, or fail `verify_api_key()` on it.

---

## HIGH

### H1. Content Cucumber's business metrics are seeded as the client's stats
`requestdesk-connector.php:617-619`, `includes/class-requestdesk-stats-bar.php:33-35`, `admin/stats-bar-settings-page.php:54-56`

`"60,000 + Projects Delivered"`, `"55 Million + Words Written"`, `"4.9/5 Average Project Rating"` are written into the client's `requestdesk_stats_bar_settings` option at activation and used as the shortcode defaults. A client who places `[requestdesk_stats_bar]` publishes CC's numbers as their own — a factual claim about their business that is not true. Fix: default `'stats' => array()` and render nothing when empty (the `empty($settings['stats'])` fallback at `class-requestdesk-stats-bar.php:48-50` currently re-injects the CC defaults and must go too).

### H2. AEO REST endpoints authorize by role, not by object — any contributor can write to any post
`includes/class-requestdesk-aeo-core.php:698-714` (`check_aeo_permissions` → `current_user_can('edit_posts')`), used as the `permission_callback` for all four AEO routes (`:473`, `:486`, `:509`, `:536`).

`rest_set_qa_pairs()` (`:752-792`) takes an arbitrary `post_id` from the URL and never re-checks `current_user_can('edit_post', $post_id)`. A Contributor — who by design cannot edit anyone else's post — can write manual Q&A onto **any** published post or page. Those pairs are emitted as FAQPage JSON-LD in `<head>` (`:389-396`), and, when `auto_display_qa_frontend` is on, as HTML in `the_content`. Answers pass through `wp_kses_post` (`:775`), which permits anchors, so this is arbitrary link injection into any page on the site. `rest_optimize_content()` (`:673-686`) has the same gap and additionally burns Claude API credit on a post the caller cannot edit. Fix: replace the role check with `current_user_can('edit_post', (int) $request->get_param('post_id'))` in the two post-scoped callbacks.

### H3. Bulk operations load every post and make network calls inside the loop, synchronously
- `includes/class-requestdesk-push.php:213-270` — `handle_bulk_sync()`: `posts_per_page => -1` (`:226`) then a `wp_remote_post` with a 30s timeout per post (`:236`, via `push_to_requestdesk` → `send_to_requestdesk` `:103-110`).
- `admin/aeo-settings-page.php:1026-1063` (`full_rescan`), `:1066-1086` (`citations_rescan`), `:1089-1106` (`freshness_rescan`), `:1109+` (`claude_rescan`) — all `posts_per_page => -1` with per-post work inside the loop; `claude_rescan` adds an Anthropic API call per post.

All run inside a single admin POST. On any site past a few hundred posts this hits `max_execution_time` mid-loop: partial state written, no progress indicator, no resume, and the transient summary (`class-requestdesk-push.php:255-261`) never gets set so the admin sees a white screen and cannot tell what completed. Fix: batch with `posts_per_page` + an offset carried in the redirect, or push each batch into `wp_schedule_single_event`.

### H4. Daily cron monitors process the entire site in one request
`includes/class-requestdesk-citation-tracker.php:349-375` (`monitor_citations`, `posts_per_page => -1` at `:354`) and `includes/class-requestdesk-freshness-tracker.php:390-439` (`monitor_content_freshness`, `-1` at `:399`), both scheduled `daily` from the constructors (`:21-22` and `:25-26`).

On first run every published post and page matches the `NOT EXISTS` meta query, so the whole corpus is loaded into memory and each row gets `get_post_meta` + `update_post_meta`. On a WP-Cron site this runs inside a visitor's page request. Fix: cap `posts_per_page` at a batch size (e.g. 50) — the meta query already makes the job naturally resumable across runs.

### H5. Deactivation leaves two daily cron hooks scheduled; there is no `uninstall.php`
`requestdesk-connector.php:645-648` clears only `requestdesk_sync_headless_counts`. `requestdesk_freshness_monitor` and `requestdesk_citation_monitor` (scheduled at `class-requestdesk-freshness-tracker.php:25-26` and `class-requestdesk-citation-tracker.php:21-22`) are never cleared, so after deactivation WP-Cron keeps firing two orphan hooks daily forever.

There is no `uninstall.php` in the repo root (confirmed by directory listing). Deleting the plugin leaves behind, in full:

*Options* — `requestdesk_settings`, `requestdesk_aeo_settings`, `requestdesk_seo_settings`, `requestdesk_headless_settings`, `requestdesk_headless_api_count`, `requestdesk_homepage_hero_settings`, `requestdesk_stats_bar_settings`, `requestdesk_comparison_table_settings`, `requestdesk_local_business_settings`, `requestdesk_activation_complete`, `requestdesk_event_rewrite_version`, `requestdesk_freshness_alerts`, `requestdesk_indexnow_key`, `requestdesk_indexnow_enabled`, `requestdesk_indexnow_post_types`, `requestdesk_indexnow_log`, `requestdesk_audit_capture_settings`, `requestdesk_qr_redirect_map` (CC), `cc_case_study_data_version` (CC). Two of these hold secrets: `requestdesk_settings['api_key']`, `['claude_api_key']`, `['promote_api_key']` and `requestdesk_headless_settings['api_key']`.

*Custom tables* — `{prefix}requestdesk_sync_log` and `{prefix}requestdesk_aeo_data` (created in `requestdesk_activate()`, `:512` and `:533`; `aeo_data` is also re-created on demand at `admin/settings-page.php:9-38`), plus `{prefix}requestdesk_push_log` (created lazily by `includes/class-requestdesk-push.php:355`, so it is not even in the activation path).

*Post meta* — `_requestdesk_ticket_id`, `_requestdesk_agent_id`, `_requestdesk_last_push`, `_requestdesk_push_status`, `_requestdesk_aeo_score`, `_requestdesk_freshness_score`, `_requestdesk_freshness_status`, `_requestdesk_freshness_updated`, `_requestdesk_citation_stats`, `_requestdesk_citation_updated`, `_requestdesk_aeo_analyzed`, `_requestdesk_schema_data`, `_requestdesk_schema_generated`, `_requestdesk_schema_overrides`, the eleven `_requestdesk_*` SEO keys from B4, `_rd_asset_*` (asset hub), `_cc_audit_*` (audit capture), plus all `rd_event` / `rd_video` field meta.

*Content* — `cc_audit_request` posts, `rd_video` posts, `rd_event` posts (and `cc_partner` / `cc_case_study` where modules were on); `rd_video_placement` terms.

*Scheduled events* — the three named above plus any pending `requestdesk_process_aeo_optimization` singles.

Fix: add `uninstall.php` that drops the three tables, deletes the options, `delete_post_meta_by_key()` for each key, and `wp_clear_scheduled_hook()` for all four hooks; and extend `requestdesk_deactivate()` to clear the two tracker hooks.

### H6. A client site's outbound ad clicks are tagged `utm_source=contentcucumber`
`includes/class-requestdesk-asset-hub.php:871`, inside `handle_ad_click()` (`:850-880`).

`RequestDesk_Asset_Hub` is loaded unconditionally (`requestdesk-connector.php:122`) and self-instantiates (`class-requestdesk-asset-hub.php:989`). Every click on a client's own brand asset redirects to the client's own destination URL with `utm_source=contentcucumber` appended, so the client's (or their advertiser's) analytics attributes their traffic to Content Cucumber. Fix: derive from `wp_parse_url(home_url(), PHP_URL_HOST)` or make it filterable.

### H7. Literal PHP tags get written into imported page content
`admin/aeo-template-enhanced.php:43` opens a **nowdoc** (`<<<'EOD'`), which does not interpolate. Lines `:52`, `:53` and `:56` inside it contain `<?php echo esc_url(home_url()); ?>`, `<?php echo esc_attr(REQUESTDESK_VERSION); ?>` and `<?php echo esc_url(wp_upload_dir()['baseurl']); ?>`. The heredoc closes at `:115`, and the string is returned as post content via `requestdesk_get_aeo_template_content()` (`admin/aeo-template-importer.php:762-777`).

An imported template page therefore ships a JSON-LD `Organization` block whose `url`, `version` and `logo.url` are the literal strings `<?php echo esc_url(home_url()); ?>`. The schema is invalid and the PHP source is visible in the page's HTML. Fix: switch those three lines to a heredoc (`<<<EOD`) with real interpolation, or `str_replace` placeholders after the fact.

### H8. `CREATE TABLE` runs on every push
`includes/class-requestdesk-push.php:352-362` — `log_push()` issues `CREATE TABLE IF NOT EXISTS` before every single `$wpdb->insert`. That is a DDL round-trip per synced post (so, per post in the `-1` bulk loop of H3). It is also why `requestdesk_push_log` is absent from `requestdesk_activate()` and therefore from any uninstall accounting. Fix: create the table in `requestdesk_activate()` alongside the other two (`requestdesk-connector.php:508-554`) and delete the DDL from `log_push()`.

### H9. Constructors have hook side effects, so data-only use registers duplicate hooks
`includes/class-requestdesk-aeo-core.php:22-40` registers `wp_head`, `save_post`, `publish_post`, `wp_enqueue_scripts`, two `wp_ajax_*` and `rest_api_init` in the constructor. The class grew a `$register_hooks = false` escape hatch for the Yoast piece (`:16-25`), and exactly one of the ~17 call sites uses it (`class-requestdesk-yoast-schema.php:190`). Every other `new RequestDesk_AEO_Core()` — `frontend-qa.php:147`, `:375`, `:457`; `push.php:392`; `aeo-meta-boxes.php:58`, `:199`, `:503`; `aeo-settings-page.php:68`, `:710`, `:1036`; `aeo-bulk-optimizer.php:64` — registers another full set. WordPress keys object callbacks by `spl_object_hash`, so these are *not* deduplicated.

The visible case is `RequestDesk_Frontend_QA`: its constructor adds `the_content` at priority 20 (`class-requestdesk-frontend-qa.php:28`), and the three theme helpers each do `new RequestDesk_Frontend_QA()` (`:485`, `:503`, `:521`). A theme that calls `requestdesk_has_qa_pairs()` in a template gets a **second** `the_content` callback and the Q&A block renders twice. Fix: make these singletons (`::instance()`), or pass `false` at every data-only call site.

---

## MEDIUM

### M1. Main API key compared with `!==` instead of `hash_equals`
`includes/class-requestdesk-api.php:1136`. The headless class gets this right (`class-requestdesk-headless-api.php:956` uses `hash_equals`); the main one, which guards `/publish`, `/events` and `/update-featured-image`, does not. Same-file inconsistency, and a timing side channel on the more privileged key. Fix: `hash_equals($api_key, (string) $provided_key)`.

### M2. API keys accepted as a URL query parameter
`includes/class-requestdesk-api.php:1133` and `class-requestdesk-headless-api.php:951` both fall back to `$request->get_param('api_key')`; `admin/headless-settings-page.php:125` documents it as a supported method. Query strings land in web-server access logs, proxy logs, browser history and `Referer` headers. Fix: drop the param fallback and keep the header paths, or at minimum stop advertising it.

### M3. Featured-image fetch is an authenticated SSRF
`includes/class-requestdesk-api.php:975-1033` — `set_featured_image_from_url()` calls `download_url($image_url)` (`:987`) on a caller-supplied URL with no host/scheme allowlist and no private-range block. Reachable from `/publish` (`:790-792`) and `/update-featured-image` (`:942`). API-key-gated, so the realistic threat is a leaked key turning the client's server into an internal-network probe. Fix: reject non-http(s) schemes and resolved private/loopback addresses before `download_url`.

### M4. Claude connection test writes the submitted key into the live site option with no rollback guarantee
`requestdesk-connector.php:683-722` — `update_option('requestdesk_settings', $test_settings)` at `:705`, test at `:708-709`, restore at `:712`. There is no `try/finally`. If `new RequestDesk_Claude_Integration()` or `test_connection()` throws a `Throwable` (not caught anywhere in this function), the restore never runs and the site is left permanently configured with whatever key was typed into the test box. There is also a window where a concurrent request reads the temporary key. Fix: wrap `:708-712` in `try { ... } finally { update_option('requestdesk_settings', $original_settings); }`, or better, pass the candidate key into `test_connection()` instead of persisting it.

### M5. Headless request counter bypasses the object cache
`includes/class-requestdesk-headless-api.php:24-29` increments via raw SQL against `{$wpdb->options}`; `requestdesk-connector.php:450` reads it back with `get_option()` and `:495-498` decrements with raw SQL again. `requestdesk_headless_api_count` is added via `add_option` (`:629`) and so is autoloaded and cached. On any site with a persistent object cache (Redis/Memcached — common on managed client hosts) `get_option()` returns a stale value indefinitely, so the cron either syncs the wrong number or never fires. Silent, and the sync reports success. Fix: `wp_cache_delete('requestdesk_headless_api_count', 'options')` (and `alloptions`) after each raw write, or move the counter to its own table/transient.

### M6. AJAX schema preview has no capability check
`admin/aeo-meta-boxes.php:851-879` — `requestdesk_ajax_get_schema_preview()` verifies the nonce (`:854`) and then reads and generates schema for any `post_id` with no `current_user_can`. Compare the sibling handler at `:809-846`, which does check `edit_post` (`:819`). The nonce is minted only on the post editor screen (`:750`), which limits exposure, but the control is simply missing. Fix: add `if (!current_user_can('edit_post', $post_id)) { wp_send_json_error('Permission denied'); }` after `:859`.

### M7. Unauthenticated post-meta writes from the ad impression beacon
`includes/class-requestdesk-asset-hub.php:886-898` — `ajax_impression()` is registered for `wp_ajax_nopriv` (`:89`) with no nonce and no rate limit. Anyone can POST up to 12 attachment ids per request and inflate `_rd_asset_impressions` without bound. Low impact (counters only, ids are validated as flagged attachments at `:892`), but it is an unauthenticated write loop. Fix: add a per-page nonce, or a transient-based throttle.

### M8. Two options are read but have no writer anywhere
- `requestdesk_comparison_table_settings` — read at `includes/class-requestdesk-comparison-table.php:38`; grep finds no `update_option` for it and no admin screen. `comparison_table_shortcode()` therefore always short-circuits at `:71` with `<!-- requestdesk_comparison_table: no rows configured -->`. The feature ships unusable.
- `requestdesk_local_business_settings` — read at `includes/class-requestdesk-schema-generator.php:1290`; no writer. The LocalBusiness schema branch always merges an empty array.

Fix: build the missing admin UI or remove the dead shortcode/branch.

### M9. `RequestDesk_Post_Handler` is dead and duplicates the publish path
`includes/class-requestdesk-post-handler.php:7` — the class name appears nowhere else in the repo (verified by full-repo grep). The file is loaded on every install (`requestdesk-connector.php:90`). Its `handle_post_data` / `create_post` / `update_post` are a second, divergent implementation of what `RequestDesk_API::publish_content()` (`class-requestdesk-api.php:653-919`) actually does — including the ticket-id lookup that `publish_content` does not have. 147 lines that can drift and will confuse the next reader. Fix: delete the file and its `$plugin_files` entry.

### M10. Defaults for the same feature are written out three times
Hero: `requestdesk-connector.php:581-612`, `includes/class-requestdesk-homepage-hero.php:36-66`, `admin/homepage-hero-settings-page.php:68-99`.
Stats bar: `requestdesk-connector.php:615-626`, `includes/class-requestdesk-stats-bar.php:30-42`, `admin/stats-bar-settings-page.php:52-60`.
AEO table DDL: `requestdesk-connector.php:535-552` and `admin/settings-page.php:15-32`.
Every one of these must be edited in lockstep; B6 is what happens when they are not. Fix: one `public static function defaults()` per feature, called from all three sites.

### M11. Error paths read variables that may not exist
- `includes/class-requestdesk-api.php:910-918` — the `catch` block passes `$ticket_id` and `$agent_id` to `log_sync()`. Both are assigned at `:666-667`; an exception raised at `:658-665` (before them) reaches the catch with both undefined → PHP 8 warning inside the error handler.
- `includes/class-requestdesk-push.php:232-244` — `$result['success']` is read first, but `push_to_requestdesk()` returns `false` when the post is not published (`:33-35`) and `array('status' => 'skipped', ...)` with **no** `success` key when already synced (`:43`). So every skipped post emits "Undefined array key 'success'" and every unpublished one emits "Trying to access array offset on value of type bool", on each of up to N posts in the bulk loop.

Fix: initialise `$ticket_id`/`$agent_id` to `''` before the `try`; test `is_array($result) && !empty($result['success'])` in the push loop.

### M12. The combined settings page executes every tab on every load
`requestdesk-connector.php:336-437` buffers `requestdesk_settings_page()` (`:353-355`) and `requestdesk_aeo_settings_page()` (`:369-371`), then strips the wrapper with two regexes (`:358-359`, `:374-375`) and echoes the result (`:361`, `:377`).

Three consequences: (a) the AEO tab's analytics run on every load of the page even when the admin only wants the General tab — `admin/aeo-settings-page.php:68-73` constructs three classes and runs two unbounded `postmeta` scans (`class-requestdesk-citation-tracker.php:394`, `class-requestdesk-freshness-tracker.php:460`, neither with a `LIMIT`); (b) `requestdesk_settings_page()` runs `SHOW TABLES LIKE` and possibly `dbDelta` on every load (`admin/settings-page.php:11-35`); (c) `preg_replace('/<\/div>\s*$/', '', ...)` blindly removes the last closing `</div>`, so any change to either sub-page's markup silently unbalances the tab container. Fix: render tabs on demand (query arg or AJAX) rather than buffering all five.

### M13. The one SEO setting a client can change does nothing on a client site
`admin/settings-page.php:596-640` — the SEO tab writes `requestdesk_seo_settings['default_og_image']`. Its own docblock (`:582-588`) says the consumer is *"the cucumber-gp-child theme"*. `RequestDesk_Yoast_Meta` never reads it — `og_image_url()` (`class-requestdesk-yoast-meta.php:193-196`) reads only `_requestdesk_og_image` post meta, which per B4 has no writer. So on a Yoast client site the admin sets a default social card, sees a preview, and nothing changes. Fix: have `RequestDesk_Yoast_Meta::og_image_url()` fall back to `get_option('requestdesk_seo_settings')['default_og_image']`.

---

## LOW

### L1. Nonce values read from superglobals without `isset`
`requestdesk-connector.php:685` (`$_POST['nonce']`), `admin/aeo-meta-boxes.php:811` (`$_POST['nonce']`), `:854` (`$_GET['nonce']`), `includes/class-requestdesk-audit-capture.php:145` (guarded upstream at `:123`, so fine), `includes/class-requestdesk-partner.php:408`/`:650`, `includes/class-requestdesk-asset-hub.php:828`, `includes/class-requestdesk-video.php:221`. Each unguarded one emits an "Undefined array key" warning on PHP 8 when the field is absent — noise in the client's debug log, and on a site with `display_errors` on it can corrupt an AJAX JSON response. Fix: `isset()` guard or `check_ajax_referer()`.

### L2. Nonce checked before capability
`requestdesk-connector.php:683-692` verifies the nonce (`:685`) and only then checks `manage_options` (`:689`). The sibling handler right above it gets the order right (`:665-669`). Convention is capability first. No exploit, but it means an unauthorized user can probe nonce validity.

### L3. Two different key generators for two keys of equal sensitivity
`requestdesk-connector.php:671` mints the main API key from `random_bytes(32)`; `admin/headless-settings-page.php:23` mints the headless key from `wp_generate_password(32, false)`. Both keys authorize the same headless routes (`class-requestdesk-headless-api.php:925-928`). Fix: use `random_bytes` for both.

### L4. Empty hook callbacks
`includes/class-requestdesk-aeo-core.php:888-893` (`enqueue_frontend_scripts()` — body is a comment) is hooked to `wp_enqueue_scripts` at `:31`. `includes/class-requestdesk-frontend-qa.php:33-35` (`init_frontend_qa()` — empty) is hooked to `init` at `:20`. Two no-op callbacks on every request. Fix: delete both.

### L5. The headless admin menu is registered twice
`admin/headless-settings-page.php:169-171` adds `requestdesk_headless_add_admin_menu` to `admin_menu` at priority 20 at file-load time; `requestdesk-connector.php:242-244` adds the same function to the same hook at the same priority. WordPress deduplicates identical string callbacks, so it is harmless — but it means the loader and the file disagree about who owns registration. Fix: keep one.

### L6. `sprintf` on shortcode-supplied text
`includes/class-requestdesk-audit-capture.php:168` — `sprintf($atts['confirm_message'], ...)`. `confirm_message` is a shortcode attribute an editor can set; a stray `%` (e.g. "We're auditing %s — 100% free") raises an uncaught `ValueError` on PHP 8 and fatals the page. Fix: `str_replace('%s', ...)`.

### L7. `$_SERVER` keys read without guard
`includes/class-requestdesk-audit-capture.php:123` reads `$_SERVER['REQUEST_METHOD']` unconditionally. Harmless under a web SAPI, a warning under CLI/WP-CLI rendering. `includes/class-requestdesk-indexnow.php:72` gets this right.

### L8. Unescaped echoes in admin output
`requestdesk-connector.php:112`/`:115` (`REQUESTDESK_VERSION`), `:124`/`:131`/`:138` — actually `admin/settings-page.php:112`, `:115`, `:124`, `:131`, `:138` echo `REQUESTDESK_VERSION` and `get_rest_url()` raw; `admin/settings-page.php:451` echoes `$sync->post_id` from the sync-log table raw. All are constants or integer columns, so no injection path exists today, but they are the pattern that becomes one. `admin/headless-settings-page.php:141` prints the live API key in plain text inside a `<pre>` (behind `manage_options`, so acceptable, but shoulder-surfable).

### L9. Unverified — `RequestDesk_Yoast_Schema_Piece::$identifier`
`includes/class-requestdesk-yoast-schema-piece.php:34` assigns `$this->identifier` on a subclass of Yoast's `Abstract_Schema_Piece`. I could not verify whether that abstract class declares an `$identifier` property, because Yoast SEO is not vendored in this repo. If it does not, PHP 8.2+ raises a `Deprecated: Creation of dynamic property` notice on every Yoast graph build on a client site. **Checked:** the whole repo for a Yoast source copy (none present) and every other reference to the class (only `class-requestdesk-yoast-schema.php:158-161`). **Remains unverified:** the parent class's property declaration. Confirm against the Yoast version the client runs before shipping.

### L10. Unverified — PHP 8.1 null-to-string deprecations beyond the publish path
`publish_content()` was hardened for this at `class-requestdesk-api.php:655-661` (the `$str` closure). I spot-checked the other REST callbacks and the loaded admin pages and found no further unguarded null-into-`sanitize_*` on a required-false parameter. **Remains unverified:** `admin/aeo-template-importer.php` (3,490 lines) and `includes/class-requestdesk-content-analyzer.php` / `class-requestdesk-content-detector.php` were read only in part; a systematic pass with `WP_DEBUG` on a PHP 8.2 install would be the reliable check.

---

## The five to fix before a client install, in order

1. **B1 + B2 — move `class-requestdesk-audit-capture.php` behind the CC gate.** One line moved between two arrays in `requestdesk-connector.php` closes an unauthenticated post-creation and mail-send endpoint on every client site and removes Content Cucumber's name from a client's admin and front end. Highest severity, smallest change.

2. **B6 — strip Content Cucumber's HubSpot portal `39487190` and form `3c945309-…` from all five locations.** `requestdesk-connector.php:585-586`, `class-requestdesk-homepage-hero.php:40-41`, `admin/homepage-hero-settings-page.php:72-73`, `admin/aeo-template-enhanced.php:153-157`, `admin/aeo-template-importer.php:2710-2711`. Until this is done, a client using the hero shortcode or the template importer routes their own leads into CC's CRM. Do H1 (stats bar) in the same pass — same class of defect, same files.

3. **B3 + B4 — decide what `yoast_mode` means on a client, then make it true.** Today the default is `'requestdesk'`, which means the plugin degrades the client's Yoast Organization/WebSite schema while the meta half of the feature cannot possibly fire, because all four files that write `_requestdesk_*` SEO meta are absent from the load list. Either load those four files and ship the feature whole, or default the mode to `'off'` for non-CC installs. Shipping it half-wired is worse than shipping neither half.

4. **B5 — stop `rd_event` from claiming `/events/` on a client site.** `class-requestdesk-event.php:173-187`. Activating the plugin should not create a public URL on a site that has no events, and the loader comment at `requestdesk-connector.php:128-129` currently tells the next maintainer that it doesn't.

5. **B7 — remove the database write from `wp_head`.** `class-requestdesk-aeo-core.php:387` (and the Yoast path at `class-requestdesk-yoast-schema.php:190`). A read-only `get_aeo_data` variant is a contained change, and the codebase already documents exactly why it is needed at `class-requestdesk-frontend-qa.php:79-81`.

**Next, before the install is called clean:** B8 (the Enable Headless API toggle that does nothing), H2 (contributor can write FAQ schema to any post), and H5 (`uninstall.php` plus the two orphan cron hooks) — a plugin a client can uninstall cleanly is table stakes, and right now deleting it leaves three tables, nineteen options including four API keys, and four scheduled events behind.
