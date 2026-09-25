# Changelog

All notable changes to the RequestDesk Connector plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.50.1] - 2026-09-25

### Fixed
- **`/pull-posts` and `/pull-pages` page in a stable order.** Both ordered by `modified DESC` alone and walked the result with an offset. Many posts share one `post_modified` value, and MySQL returns tied rows in no fixed order, so each page query could shuffle the ties across a page boundary: one post falls off the end of a page and never appears, another appears on two pages. On Talk Commerce a 758-post pull returned 758 rows but only 753 distinct posts, and which five were missing changed from run to run. The RequestDesk `wordpress_rag_sync` job alerted for an hour on a post it could never fetch, then passed when the shuffle happened to include it. The order is now `modified DESC, ID DESC`, so every offset walk sees every post exactly once.

### Added
- **`/pull-posts` takes `ids` and `ids_only`.** `ids_only=true` returns just `id`, `published_date` and `modified_date` per post, cheap enough to list a whole site in a handful of calls. `ids=1007,154` returns exactly those posts (up to 100), ignoring `offset` and `modified_since`. Together they let the RequestDesk sync diff the site against its collection and re-fetch only what is missing, instead of alerting and waiting for the next full pass to happen to include the post.

## [2.50.0] - 2026-09-24

### Added
- **`POST /requestdesk/v1/post-author`** sets only the author of one post/page (`post_id`) or many (`post_ids`). `author` accepts a user ID, login, email, slug, or an exact display name; the user must be able to edit posts. It writes `post_author` directly, so title, content, status, categories and `post_modified` are untouched and no save hooks run (unlike `/publish`, which rewrites the whole post). Each post is read back and reported as `author_before` / `author_after` / `ok`; `dry_run` reports without writing. Built to reassign 37 Content Cucumber posts whose author was a deleted user, which left an empty author link that failed accessibility `link-name` checks.
- **`POST /requestdesk/v1/image-alt`** sets alt text on images inside one post/page: `{post_id, images: [{src, alt}], dry_run}`. It edits the matching `<img>` tags with `WP_HTML_Tag_Processor`, updates `htmlAttributes.alt` on GenerateBlocks media blocks so the editor does not flag them invalid, and fills an empty media-library alt when the attachment really is that image. The rest of the content is untouched; a revision is saved first. Site images (`/wp-content/...`) match on path, so a production URL also matches a staging copy with a search-replaced host. Unmatched sources come back in `not_found`. `alt: ""` marks an image decorative.

## [2.49.0] - 2026-09-21

### Fixed
- **RequestDesk SEO meta is stored on publish, and wins over Yoast on the headless API.** `RequestDesk_Yoast_Meta` has made the `_requestdesk_*` values beat Yoast in Yoast's own printed tags since 2.47.0, but two pieces were missing, so on a headless site nothing RequestDesk sent ever reached a reader. First, `/publish` never registered `meta_title`, `meta_description` or `focus_keyphrase` as route args -- the REST controller strips unregistered args, so a caller sending them got `"success": true` and no stored value, with nothing in the response to say otherwise. They are now registered (aliases `seo_title` / `seo_description`), written to `_requestdesk_seo_title`, `_requestdesk_seo_description` and `_requestdesk_focus_keyphrase`, read back, and reported as `seo_meta_set` in the publish response. A field left out of an update leaves the stored value alone, the same asymmetry `content` and `tags` already have. Second, `get_seo_data()` in the headless API checked Yoast, then RankMath, then AIOSEO, and never looked at the `_requestdesk_*` namespace at all, so even a stored value lost to Yoast or to the title-plus-site-name fallback. It now reads RequestDesk first for title, description, keyphrase, canonical, and the OG and Twitter fields, applying the same one-way robots rule as the Yoast filter path -- RequestDesk can add a noindex and never lifts one. Talk Commerce post 8803 went live on 2026-09-21 with a 94-character SEO title and its 300-character excerpt as the meta description because of this.

## [2.48.2] - 2026-09-21

### Fixed
- **Scheduling works: `status=future` no longer publishes immediately.** Sending `status=future` with a future `post_date` to `/publish` set the post live that instant and answered `success`, with `post_date` echoed back as the current time rather than the time asked for. `wp_update_post()` carries a guard -- "Drafts shouldn't be assigned a date unless explicitly done so by the user" -- that fires when the row being updated is a draft whose `post_date_gmt` is still `0000-00-00 00:00:00`, which is every draft this connector creates. With `edit_date` absent it replaced the supplied date with `current_time('mysql')` and blanked `post_date_gmt`; core then read a date inside the next minute and demoted `future` to `publish`. The publish handler now sends `edit_date` whenever an explicit date is supplied, so the date sticks and the post stays scheduled. Content Cucumber post 23206 went live about twelve hours early this way on 2026-09-20, and two Talk Commerce posts did the same on 2026-09-01. Reproduced and verified on LocalWP against 2.48.0.

## [2.48.1] - 2026-09-18

### Fixed
- **Podcast player embeds survive a Connector publish.** `publish_content` ran the body through `wp_kses_post()`, and because Connector requests have no logged-in user, WordPress ran kses again on save. Both passes stripped every `<iframe>`, so audio-only Talk Commerce episode posts went live with no Transistor player (#455 Jason Greenwood). Iframes whose src host is `share.transistor.fm`, YouTube or Vimeo are now allowed for the duration of the publish; any other iframe is removed before kses runs.

## [2.48.0] - 2026-09-17

Client-install hardening. Everything here came out of the technical deficiency audit in `docs/audits/2026-09-17-technical-deficiency-audit.md`, which read 2.47.3 against the question "what happens when this runs on a site that is not Content Cucumber".

### Security
- **Audit Capture is now a site-module module.** It registered `cc-audit/v1/request` with no key, no nonce and no rate limit on every install, and every call created a published post and sent mail to the site's admin. It also put an "Audit Requests" menu and a shortcode headed "Your Content Cucumber audit" on client sites. It now loads only where site modules are on.
- **AEO endpoints check the post, not the role.** `check_aeo_permissions()` asked for `edit_posts`, so a Contributor could write FAQ schema, including its links, onto anyone's published page. A route that names a post now requires `edit_post` on that post.
- **"Enable Headless API" works.** The switch was written by the settings page and read by nothing: unticking it saved, said so, and left all eight routes serving. `register_routes()` now returns early when it is off.
- **Deleting the plugin removes it.** New `uninstall.php` drops the three tables, deletes the nineteen options (four hold API keys) and clears the scheduled events. Deactivation now clears all four hooks, not one. Content (posts and the SEO values an editor typed) is deliberately left alone.

### Fixed for client sites
- **Content Cucumber's HubSpot portal and form no longer ship as defaults.** They were written into the site's options at activation and hardcoded in the hero shortcode, the hero settings page, the enhanced-homepage template and the lead-magnet importer, so a client using any of those sent their own leads into Content Cucumber's CRM. All five now default to empty, the hero renders no form until the ids are set, and the lead-magnet importer reads the portal from its CSV like the contact-page importer already did.
- **Content Cucumber's business numbers no longer ship as defaults.** "60,000 + Projects Delivered", "55 Million + Words Written" and "4.9/5" were seeded as the client's stats and re-injected whenever the setting was empty. The stats bar now starts empty and renders nothing until the site owner fills it in.
- **`rd_event` no longer claims `/events/` on every install.** Public registration and the archive now happen only on site-module installs; elsewhere the post type is admin-only, so activating the plugin adds no URL. The loader comment claiming it registered no public URL has been corrected.
- **A Yoast client site keeps its own identity.** With no mode chosen, non-site-module installs now default to "Yoast wins" instead of "RequestDesk wins", which had been replacing the site's configured Yoast Organization name and description with the WordPress site title and tagline. Content Cucumber still defaults to RequestDesk wins, and the setting still offers all three choices.

### Migration
- **Sites that already have Content Cucumber's values get them cleared once.** Changing the defaults only helps a fresh install, and every site that activated an earlier build carries the seeded HubSpot portal, form and stats in its database. On first load after the update, a non-site-module install clears those three values when they still match the seeded ones exactly, leaving anything the site owner typed untouched, and logs what it cleared.

### Performance
- **No database write on an anonymous pageview.** `get_aeo_data()` inserted a row when none existed and was called from `wp_head`, so viewing a post wrote to the database. Front-end paths now use a new read-only `get_aeo_data_readonly()`; the insert stays on the write paths. The plugin already documented this hazard in `class-requestdesk-frontend-qa.php` without fixing the emitter it described.

### Notes
- Known and deferred: the per-post SEO override values `RequestDesk_Yoast_Meta` reads are written only by four files the plugin has never loaded (`seo-core`, `seo-meta-boxes`, `yoast-importer`, `yoast-import-page`). Until those are loaded and tested, "RequestDesk wins" affects site identity and the FAQ, not per-post titles and descriptions. See finding B4 in the audit.
- The pre-release test pass and the weekly audit are written down in `docs/TESTING.md`; the harness lives in `tests/`.

## [2.47.3] - 2026-09-17

### Added
- **RequestDesk SEO off, for clients who keep Yoast.** AEO Settings, "When Yoast SEO Is Active" has a third choice. With it selected RequestDesk adds nothing to the page head on a Yoast site: no FAQ or case study node in Yoast's graph, no changed titles, descriptions, canonicals or social tags, no site identity, and no standalone schema blocks. Publishing, the REST API and stored FAQ data keep working, so switching back restores the output without re-entering anything.

### Fixed
- **PHP deprecation notices on every publish.** `/publish` passed optional fields that were left out (`featured_image`, `slug`, `excerpt`, dates) to WordPress sanitizers as null, which logs "Passing null ... is deprecated" on PHP 8.1+. Those fields are now read as strings.

### Notes
- Found and built during the pre-install test pass for Support for Stepdads (plain WordPress, Twenty Twenty-Five, Yoast SEO free 28.5, site modules off).

## [2.47.2] - 2026-09-17

### Fixed
- **Home page described Content Cucumber on every site.** Without Yoast, the front page printed a ProfessionalService block whose `knowsAbout` and service catalog are Content Cucumber's own (Growth Marketing, HubSpot Implementation, Loop Marketing, Live Event Content). It now prints only on site-module installs, the same gate the Yoast integration already used. Other sites print no ProfessionalService block.

### Notes
- Found in the plain-install test (Twenty Twenty-Five, site modules off) before the Support for Stepdads install. Present since the block was added; not new in 2.47.

## [2.47.1] - 2026-09-17

### Fixed
- **Settings page PHP warnings on a fresh install.** RequestDesk, Settings read `api_key`, `debug_mode` and `default_post_status` without checking they exist, so a site that had never saved the form logged eight "Undefined array key" warnings on every visit (and showed them on screen where `WP_DEBUG_DISPLAY` is on). Missing keys now read as empty, off and `draft`, the same defaults the plugin uses elsewhere.

### Notes
- Found while testing 2.47.0 on a plain WordPress install (Twenty Twenty-Five, Yoast SEO free 28.5, site modules off) before the Support for Stepdads install. The warnings predate 2.47.0; nothing else changed.

## [2.47.0] - 2026-09-16

### Changed
- **RequestDesk wins over Yoast SEO by default.** With Yoast active, each page still carries one set of meta tags and one schema graph (Yoast prints them), but RequestDesk's values replace Yoast's wherever RequestDesk has one:
  - Meta (`RequestDesk_Yoast_Meta`, new): SEO title, meta description, canonical and og:url, robots noindex/nofollow, og:title, og:description, og:image, twitter title/description/image, from the `_requestdesk_*` post meta. Yoast-style `%%variables%%` in stored values go through `wpseo_replace_vars`. A single post with no description in RequestDesk or Yoast falls back to the excerpt. RequestDesk only adds a robots restriction and never lifts a noindex set elsewhere.
  - Schema (`RequestDesk_Yoast_Schema`): the Organization and WebSite nodes take the WordPress site title and tagline, RequestDesk's social profiles are merged into sameAs, and the logo fills in when Yoast has none. On site-module installs the Organization also carries `ProfessionalService`, `knowsAbout` and the service `hasOfferCatalog` that used to print as a separate front-page block. The case study's `about` and `review` replace Yoast's on its Article.
- **Setting:** AEO Settings, "When Yoast SEO Is Active": RequestDesk wins (default) or Yoast wins. Yoast wins is the 2.46.0 behavior: RequestDesk only adds its FAQ and case study nodes to Yoast's graph and changes none of Yoast's values.

### Removed
- The 2.46.0 "Yoast SEO Compatibility" checkbox (`schema_standalone_with_yoast`), which printed RequestDesk's standalone schema blocks beside Yoast's graph. That setup is the duplicate-entity problem, so it is no longer offered.

### Why
The point of running the connector on a Yoast site is for RequestDesk to own the SEO and identity. 2.46.0 did the opposite and deferred to Yoast. On contentcucumber.com an outside AEO review then found two Organization and two WebSite nodes on one `@id` and FAQPage declared twice, which left answer engines choosing between competing descriptions of the business.

### Notes
- Verified on a local copy of contentcucumber.com with Yoast free 28.5: `schema-identity-check.py` over 46 sitemap pages, zero duplicate `@id`, zero doubled singleton types, one Organization description. RequestDesk title, description, canonical, og:url, og:image and noindex each rendered once and won over Yoast. Switching to Yoast wins through the settings form restored Yoast's values. With Yoast deactivated the output matched 2.45.0.
- Without Yoast nothing in this release runs.
- Yoast Premium was not tested; the filters used are shared by free and Premium.

## [2.46.0] - 2026-09-16

### Added
- **Yoast SEO compatibility for schema.** With Yoast SEO (free or Premium) active, the connector no longer prints its own `application/ld+json` blocks next to Yoast's graph. The FAQPage goes into Yoast's `@graph` through `wpseo_schema_graph_pieces` as its own node (`<permalink>#requestdesk-faq`, `isPartOf` Yoast's WebPage), so the page carries one connected graph. Case study Article (site-module installs) is added the same way (`#requestdesk-case-study`), or its `about` and `review` are merged into Yoast's Article when Yoast prints one for that post type. The home-page ProfessionalService block is not printed on a Yoast site, since Yoast's Organization node is the site entity.
- **Setting:** AEO Settings, Schema Markup Generation, "Yoast SEO Compatibility". Unchecked by default, which defers to Yoast. Checking it prints the old standalone blocks even with Yoast active.

### Why
Installing the connector on a Yoast site (Support for Stepdads, t2373) stacked a second, disconnected schema graph beside Yoast's on every post with FAQ data and on the home page.

### Notes
- Without Yoast nothing changes. On a local WordPress with no Yoast, 2.45.0 and 2.46.0 rendered byte-identical HTML for a post with FAQ data and for the home page.
- A post that already has a Yoast FAQ block keeps only Yoast's FAQ (Yoast turns the WebPage node into FAQPage), so the page never carries two FAQPages.
- Deferral applies only on a request where Yoast built its graph. If Yoast's JSON-LD is switched off (`wpseo_json_ld_output`), the connector prints its standalone blocks as before, so the FAQ is not lost.
- Meta tags: the connector prints no title, description, canonical, robots, Open Graph or Twitter tags of its own (`RequestDesk_SEO_Core` is not in the load list), so there is nothing to duplicate Yoast's. Checked on the rendered page with Yoast active, one of each.
- `RequestDesk_AEO_Core::__construct()` takes an optional `$register_hooks` (default true) so the graph builder can read FAQ data without adding a second `wp_head` callback mid-`wp_head`. `RequestDesk_Case_Study::build_schema()` is the shared schema builder.

## [2.45.0] - 2026-09-14

### Added
- **`rd_event` post type.** One conference or show: start and end dates, venue and address, who is recording, our role, organizer, speakers, the page lead and body, banner and background video, events-list copy for before and after, the form (booking while upcoming, always, or none), and an optional homepage takeover. Headless only, like `rd_video`: an admin screen with no front-end URL, archive or rewrite rules. The admin list shows dates and a derived status (upcoming, past, homepage, recap owed) and flags in red any event missing a start date or city.
- **`GET /requestdesk/v1/headless/events`** (`when` = all, upcoming or past; `per_page` up to 100) and **`GET /requestdesk/v1/headless/events/<slug>`** (includes the rendered body). Upcoming events come back soonest first, then past events most recent first.

### Why
Talk Commerce kept its events in a TypeScript array and one hand-built Astro page per event, and computed the homepage headline from that array at build time. The headline changed only on a deploy, so eTail Boston stayed on the homepage after the show ended. Reading events from WordPress per request means an event added in wp-admin appears with no deploy and leaves the homepage the day after it ends.

### Fixed
- **`rd_video` and `rd_video_placement` never registered (since 2.43.0).** `requestdesk_init()` constructs the module classes inside the `init` action, and the video class hooked its registration onto `init` from there. A callback added at the priority WordPress is already running is skipped, so the post type and taxonomy silently did not exist: no Videos admin screen on any site, while `/headless/videos` answered with an empty list. Both `RequestDesk_Video` and `RequestDesk_Event` now register directly when `init` has already started. Found on Content Cucumber local, where `post_type_exists('rd_video')` returned false with the class loaded.

### Notes
- Upcoming or past is never stored. It is derived from `end_date` on every read, in UTC, and the event counts as past from the day after its end date.
- An event with no start date or city is left out of the API, so a half-filled draft cannot reach a page with no dates.
- Registered in the shared list, not `$cc_only_files`, for the same reason as `rd_video`: no public URL of any kind.
- `RequestDesk_Event::save_values()` applies the editor's sanitizing rules, so an importer seeding events goes through the same path as the meta box.
- **For themes that render WordPress directly:** `RequestDesk_Event::next_homepage_event($today = null, $require_image = false)` returns the next upcoming takeover event, and `format_date_range()` formats dates the same way the Astro side does. contentcucumber.com's `event-feature` homepage section uses it (with `$require_image`) in place of the hand-written eTail Boston block that stayed up after the show.
- Homepage takeover gained a headline and an image field; the API's `hero` now carries `headline` (short name and dates when empty), `image` (falls back to the featured image) and `paragraphs` (the blurb split on blank lines). The admin list flags a takeover event with no image, since image blocks pass over it.
- The body comes back without `wptexturize`, so migrated copy keeps its straight quotes and hyphens.
- **Headless read routes accept the main RequestDesk key as well as the headless key.** The main key already authorizes every write route, so refusing it on reads protected nothing, and on a site with a separate headless key (Talk Commerce) tooling holding the main key could create events but not read them back.
- **`POST /requestdesk/v1/events`** creates or updates one event by slug, with the same API key as `/publish`. Body: `slug`, `title`, optional `content` (omit to keep the body), `status` (default draft), and `meta` keyed as in `RequestDesk_Event::fields()`; only the keys sent are written, and an unknown key is rejected with a 400 rather than ignored. The response returns the event as the headless API shows it (`null` if it still lacks a start date or city). Events live in the database, which some sites never push to production, so this is how an event reaches a live site without wp-admin. It shipped late: the post type went out with a read API only.
- **Events are public posts.** Each event is a page at `/events/<slug>/`, rendered by the theme's single template, with an `/events/` archive (`rewrite` uses `with_front => false` so a `/blog/%postname%/` structure does not prefix it). Rewrite rules flush once per plugin version on `wp_loaded`, after every plugin has registered its types. The API returns each event's `permalink`. This replaces the headless-only registration, which gave an event no page on a site that renders WordPress directly.

## [2.44.0] - 2026-08-30

### Added
- **SEO tab on the RequestDesk Settings screen** with a Default OG image field. The `requestdesk_seo_settings` option had no admin screen because `class-requestdesk-seo-core.php` is not in the load list; the cucumber-gp-child theme (1.2.42) now reads `default_og_image` from it for every social-preview fallback, so the field needed a home. Saves merge into the existing option.

## [2.43.0] - 2026-08-20

### Added
- **`rd_video` post type and the `rd_video_placement` taxonomy.** A video library: a title, a YouTube id, an optional caption line, and the placements it belongs to. Headless only. `public => false` with `show_ui => true`, so it has an admin screen and no front-end URL, no archive, and no rewrite rules at all.
- **`GET /requestdesk/v1/headless/videos`.** Takes `placement`, `per_page`, `orderby`, `order`. Default order is `menu_order ASC`, so the running order on a page is set by dragging rows in the admin rather than by editing code. Same API-key auth as the other headless routes.

### Why
Video ids were hardcoded in arrays inside individual Astro pages. Four pages on Talk Commerce held their own copy of the same iframe and the same ids, and they had already drifted apart: different heights, different `allow` lists, and only some marked `loading="lazy"`. Adding one video meant a code change and a ten minute container build.

Placement is a taxonomy rather than a field on the page so the relationship points the right way. A page asks for a term and gets whatever currently carries it, so putting a video on a second page is a checkbox instead of a deploy.

### Notes
- Registered in the shared list, not `$cc_only_files`. The case-study incident in 2.37.1 was a CPT registering a public archive on a site with no content for it; this one registers no public URL of any kind, so an install that never adds a video gets an empty admin screen and nothing more.
- Pasted URLs are normalized to the bare 11-character id on save (`watch?v=`, `youtu.be/`, `/embed/`, `/shorts/`, `/live/`). YouTube answers a malformed embed with a silently broken player rather than an error, so the id is cleaned on the way in rather than trusted.
- The API drops any video with an empty id, so a draft saved before the id was pasted cannot reach a page as an empty player.

## [2.35.0] - 2026-08-01

### Changed
- **The plugin is one tree again.** Between 2.25.0 and 2.35.0 this repo sat at 2.24.1 while Content Cucumber's LocalWP copy grew eleven versions of work, because CC deploys through LocalWP and never reads this repo. Nothing surfaced the split: the shared plugin kept working at Talk Commerce, and CC kept shipping. Everything CC's tree had — the AEO Q&A write path (`/aeo-qa`), the curated-pairs protection from 2.32.1 / 2.33.2, Content Audit, Promote, Admin Columns, QR Redirect, the case-study wizard updates — is now here. The seven case-study seed JSONs that existed only in this repo were kept.
- **CC-only modules are gated at require time.** `content-audit`, `promote`, `admin-columns`, and `qr-redirect` load only when `requestdesk_is_cc_site()` passes, so Talk Commerce doesn't sprout a `/go` redirect or a Promote button it has no use for. The gate is on the `require` rather than inside each class because the `$aeo_classes` loop in `requestdesk_init()` is already `class_exists()`-guarded, and QR Redirect self-instantiates on require. Enable elsewhere with `define('REQUESTDESK_CC_FEATURES', true)`.
- **`sync-all.sh` refuses to clobber a newer destination.** It is an `rsync --delete`; running it any time in the last six weeks would have silently destroyed all eleven versions above. It now compares `REQUESTDESK_VERSION` at both ends and stops if the destination is ahead. `FORCE_SYNC=1` overrides for a deliberate rollback.

### Added
- **`GET /requestdesk/v1/aeo-status`.** Q&A coverage across a post type in one call: per-post `qa_count`, `manual_qa_count`, `needs_faq`, which schema the post currently emits (`FAQPage` / `QAPage` / `none`), AEO score, and when it was last optimized — plus site-level `total_published`, `total_missing`, and `total_curated`. Takes `post_type`, `missing_only`, `per_page`, `page`. Same API-key auth as the rest of the AEO routes.

### Why
`/aeo-data/{id}` answers "what does post N have." Nothing answered "which posts still need work," so finding the gaps meant walking the archive one post at a time. First run on this site: **667 of 750 published posts carry no FAQ schema, and 14 have hand-curated Q&A.** That gap is the thing a backfill is aimed at, and `missing_only=true` is what lets it skip the 83 posts already covered.

`manual_qa_count` is reported separately on purpose. 2.32.1 and 2.33.2 were both about not letting the extractor trample curated pairs; a backfill driven off this endpoint needs to see which posts an editor already touched so it can leave them alone.

## [2.34.0] - 2026-07-30

### Added
- **QR Redirect (`/go`).** One permanent short URL sitting behind every printed QR code, so a code printed for eTail Boston can be repointed to ShopTalk (or anything else) later without a reprint. New class `RequestDesk_QR_Redirect` (`includes/class-requestdesk-qr-redirect.php`) plus a **Settings → QR Redirect** screen. `/go` answers the `default` row; `/go/<key>` answers a named row, which is how a second printed asset gets its own tracking without a second redirect. Default destination is `https://contentcucumber.com/conference-coverage/video/`, and an unmatched key always falls back to `default` — a scanned code must never 404, because a dead QR on a sticker someone kept is worse than no QR.
- **Per-event attribution from a static code.** The redirect appends `utm_source=qr` and `utm_medium=print`, plus `utm_campaign` from the row's Campaign field and `utm_content` for named keys. Changing the campaign value when the destination is repointed keeps each event's scans separated in GA4 even though the printed code never changed. Params already present on the destination URL are never overwritten.

### Safety
- **302, deliberately, and never 301.** A 301 is cached by the browser permanently, so anyone who scanned at one event would keep landing on that event's page forever after a repoint, with the stale mapping living on their device where it cannot be corrected. The redirect also sends `Cache-Control: no-store, no-cache, must-revalidate, max-age=0` and `Pragma: no-cache` so no intermediary (Flywheel edge, CDN, corporate proxy) can cache it either, and `X-Robots-Tag: noindex, nofollow` so the redirect URL never gets indexed in place of its destination.
- **Resolves on `init` at priority 0**, matching on `REQUEST_URI`, rather than registering a rewrite rule. Rewrite rules require a flush that breaks quietly whenever permalinks are re-saved or the site is migrated — which for a URL printed on physical assets would be a silent, unrecoverable failure. Admin, cron, REST, AJAX and WP-CLI requests return early, and matching is on the first path segment only (so `/golf` still 404s).
- **Invalid destinations are rejected, not saved.** A URL that fails `wp_http_validate_url()` raises a settings error instead of being written, because saving a broken destination silently kills every printed code in circulation.

## [2.32.1] - 2026-07-17

### Fixed
- **Auto-optimize erased manually-authored Q&A on every publish/update (critical).** `optimize_post()` regenerates `ai_questions` by *extracting* Q&A from the post body, and for posts not written in Q&A form the extractor returns nothing — so on each `publish_post` / `save_post` (auto-optimize is on by default) it wrote an empty array over any Q&A added via the admin meta box or the `/aeo-qa` endpoint. This silently wiped the Q&A pushed to a post seconds after publishing it (and was the real reason a promoted post showed no FAQPage schema on the front end — not a display-setting difference). Fix: `optimize_post()` now preserves every pair tagged `source: manual`, keeping them and appending only non-duplicate extracted pairs, so hand-authored Q&A survive optimization. Manual Q&A must be re-added to any post where a publish already cleared them.

## [2.32.0] - 2026-07-17

### Added
- **Promote to Live (per-post).** A "Promote to Live" row action on the Posts list and a button in the post editor push a *single* post from this (Local) site to the live site — the granular opposite of a full Magic Sync, which overwrites the entire live database to ship one blog change. v1 **updates an existing live post in place**: it pushes title + content (with `.local`→`.com` URL rewrite) + excerpt + the AEO Q&A pairs by the same post ID, and preserves the live post's status, URL, date, author, taxonomy, and featured image. New class `RequestDesk_Promote` (`includes/class-requestdesk-promote.php`).
- **Promote settings.** New "Promote to Live" settings card: **Live Site URL** and **Live API Key** (the live site's RequestDesk API key — often identical if live was cloned from Local). Stored as `requestdesk_settings['promote_target_url']` / `['promote_api_key']`.

### Safety
- **Identity guard.** Promote refuses to run unless it confirms — via the connector's new API-key'd `GET /post-identity/{id}` endpoint — that a post exists at that ID with a **matching slug**, and it preserves that post's live status. Deliberately uses the `requestdesk/v1` namespace rather than public `wp/v2`, because production locks `wp/v2` down (a theme filter returns 401) while allowing this namespace. This makes it impossible to overwrite the wrong live post or silently create a duplicate. If the live post doesn't exist at that ID, it aborts (v1 does not create new live posts).
- **Deferred, by design:** creating brand-new posts on live, sideloading images newly uploaded on Local (existing images resolve via URL rewrite; the promote warns if a post references local upload URLs), and re-linking Polylang (Spanish) translations.

## [2.31.0] - 2026-07-17

### Added
- **Headless AEO Q&A write access.** New REST endpoint `POST /wp-json/requestdesk/v1/aeo-qa/{post_id}` writes hand-authored FAQ Q&A pairs to a post from RequestDesk / MCP / any API client — the programmatic equivalent of the "AEO Q&A Pairs" admin meta box, no wp-admin login required. Payload: `{ qa_pairs: [{question, answer, confidence?}], mode?: "replace"|"append" }`. Authenticated by the existing RequestDesk API key (`X-RequestDesk-API-Key` header or `api_key` param), the same key the connector sync API uses.

### Fixed
- **AEO endpoints were browser-only.** `check_aeo_permissions()` previously required `current_user_can('edit_posts')` — a cookie session — so `optimize-content` and `aeo-data` could never be driven headlessly (the code even carried a "you might want to tie this to the RequestDesk API key system" TODO). It now accepts **either** a logged-in editor **or** a valid RequestDesk API key.
- **Manual Q&A never emitted `<head>` schema.** The admin meta-box save handler wrote `ai_questions` (visual block) but never regenerated `faq_data`, so hand-entered Q&A rendered visually yet emitted no FAQPage JSON-LD. The new write endpoint regenerates `faq_data` on every write, keeping the visual block and the head schema in sync.

### Why
FAQ Q&A could only be added by a human typing into the wp-admin meta box — no API, no MCP, and the one automated endpoint was locked behind a browser login. This closes that gap so Q&A can be written at scale from tooling, with the schema kept correct automatically.

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
