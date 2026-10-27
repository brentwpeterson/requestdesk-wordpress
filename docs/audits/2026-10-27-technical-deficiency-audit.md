# RequestDesk Connector, Technical Deficiency Audit

Repo: `/Users/brent/scripts/CB-Workspace/requestdesk-wordpress` (main, clean, HEAD `7bcf5be`, v2.64.0; local main is 7 commits ahead of the local `origin/main` ref, not fetched because this audit makes no git writes)
Scope: read-only. No files modified except this report, no git writes, no database access, no network writes, no WP-CLI.
Method: full read of the 2026-10-19 audit, the 2026-09-17 header and `docs/TESTING.md`; `git log 4111813..HEAD` (7 commits) and the full `git diff 4111813..HEAD` (9 files); also `git diff 2ce48fe..4111813`, because the 10-19 audit was taken at `2ce48fe` (v2.63.0) and the three commits between that and the audit commit (2.63.1 `post_id` typing, 2.64.0 A/B test module) were never reviewed. Full read of `includes/class-requestdesk-ab-test.php`, `admin/ab-test-settings-page.php`, `assets/js/ab-test.js`; every claimed fix read at HEAD; every carried finding re-located at HEAD by grep and read (line numbers below are current). Repo-wide greps for every "unused / no writer / never called" claim. WordPress core claims checked against a local core copy (WP 6.7.2, `/Users/brent/ContentCucumber/contentcucumber.com/public_html/wp-includes/`). Workspace consumers of the REST routes (`astro-sites/sites/container/*/src/lib/wordpress.ts`, `requestdesk-app/backend/app/services/wordpress_service.py`) checked for query-string keys before judging the M2 fix.

Context assumed throughout: a **client install** = site modules OFF (`requestdesk_is_cc_site()` false), stock theme, Yoast SEO active. Content Cucumber is the one site where CC-specific paths look correct.

Severity counts: **BLOCKER 1, HIGH 8, MEDIUM 19, LOW 17** (45 findings: 40 carried from 2026-10-19, of which 3 are partially fixed and 1 is promoted; 5 new).

---

## DELTA vs 2026-10-19

The 10-19 audit had 46 findings. Since then: 7 commits, all fixes, no version bump (still 2.64.0, no CHANGELOG entry, no release zip).

**Tally: 6 fixed, 3 partially fixed, 1 promoted after verification, 5 new, 37 still open unchanged.**

### (1) Fixed

| 10-19 | Commit | Verified at HEAD |
|---|---|---|
| H2 Comment Link Stripper stores model output unsanitized | `3e4248e` | `class-requestdesk-comment-link-stripper.php:379-385` rejects Claude output with more `<` than the input, then runs `wp_kses($result, wp_kses_allowed_html('pre_comment_content'))`. That context falls to core's `default` branch and returns `$allowedtags`, the same set core applies to comments (`wp-includes/kses.php:912-915`). The regex-only fallback is derived from the stored comment, which core already filtered on insert for an unprivileged commenter. Closed. |
| M1 Main API key compared with `!==` | `a3d0717` | `class-requestdesk-api.php:2070` now `hash_equals((string) $api_key, (string) $provided_key)`. All four verifiers use `hash_equals`. |
| M2 API keys accepted as a query parameter | `7bcf5be` | Removed from all three verifiers: `class-requestdesk-api.php:2067-2068`, `class-requestdesk-aeo-core.php:775-776`, `class-requestdesk-headless-api.php:1120`. The event verifier never had it (`class-requestdesk-event.php:88`). Repo grep finds no remaining `get_param('api_key')`. The workspace consumers send headers. Documentation was not updated: see new **M18**. |
| M5 Schema preview AJAX has no capability check | `b35ea96` | `admin/aeo-meta-boxes.php:854` (isset + unslash), `:861-864` `current_user_can('edit_post', $post_id)`. |
| M6 AEO AJAX handlers authorize by role | `b35ea96` | All six handlers now check `edit_post` on the posted ID before acting: `class-requestdesk-aeo-core.php:870-874`, `:894-898`; `class-requestdesk-freshness-tracker.php:536-540`, `:560-564`; `class-requestdesk-citation-tracker.php:440-444`, `:457-461`. Repo grep: the only remaining `current_user_can('edit_posts')` are `class-requestdesk-push.php:265` (bulk push admin action), the REST fallback branch for no `post_id` (`class-requestdesk-aeo-core.php:744`), and the CC-only case-study wizard. |
| L12 JSON-LD without `JSON_HEX_TAG` | `72c68ee` | Added at `class-requestdesk-aeo-core.php:413`, `:429`, `class-requestdesk-schema-generator.php:1371`. One emitter still lacks it, `class-requestdesk-case-study.php:569`, which loads on Content Cucumber only and was not part of L12. |

### (2) Partially fixed

| 10-19 | Commit | What remains |
|---|---|---|
| H3 Keyless `/public/events` routes | `999ba07` | The count half is fixed: `increment_request_count($request)` returns early for routes under `/requestdesk/v1/public/` (`class-requestdesk-headless-api.php:24-31`), and both public callbacks pass the request (`:366`, `:419`). The routes are still registered on every install with headless on (`:240-273`, `__return_true` at `:243`, `:264`), and the list still loads every event with `posts_per_page => -1` (`:382`) while advertising `per_page` (`:253-259`). Now **H2**. |
| L8 iframe host allowlist bypass | `72c68ee` | The unclosed-iframe case is fixed: the callback now matches the opening tag alone (`class-requestdesk-api.php:56`). Two bypasses remain. Now **L8**, details there. |
| L1 Nonce read without `isset` | `b35ea96` (incidental) | `admin/aeo-meta-boxes.php:854` fixed. `requestdesk-connector.php:774`, `:783` and `admin/aeo-meta-boxes.php:811` remain. Now **L1**. |

### Promoted after verification

| 10-19 | Now |
|---|---|
| L17 Unverified: auto-approved comments may never reach the link stripper | Verified against core 6.7.2: `wp_insert_comment()` (`comment.php:2013`) and `wp_new_comment()` (`:2229-2348`) never call `wp_transition_comment_status()`; only delete (`:1529`), `wp_set_comment_status()` (`:2484`) and `wp_update_comment()` (`:2658`) do. The stripper hooks only `transition_comment_status` (`class-requestdesk-comment-link-stripper.php:119`). Now **M19**. |

### (3) New findings (5)

- **M16** A/B test: a selector list hides elements for every visitor (2.64.0, unreviewed until now).
- **M17** A/B test rate limit (`91b06b7`) keys on `REMOTE_ADDR` with a sliding window, so behind a proxy it is a site-wide cap that stops counting.
- **M18** M2 fix shipped while the client-facing admin page and README still document `?api_key=`, with no version bump or CHANGELOG entry.
- **L16** A/B test: an invalid CSS selector stops all Variant B counting while A keeps counting.
- **L17** A/B counts are read-modify-write on one option, so concurrent events are lost.

The A/B options are also missing from `uninstall.php`; that is folded into **L9**.

### (4) Still open, oldest first

**Open since 2026-09-17 (written up in both earlier audits):**
- **B1** Saving the AEO tab flips a client to "RequestDesk wins" (09-17 B3, fixed in `mode()` and reopened through the form on 10-19).
- **H4** bulk operations unbatched (09-17 H3), **H5** daily cron whole-site (09-17 H4), **H6** literal PHP tags in imported template (09-17 H7), **H7** `CREATE TABLE` on every push (09-17 H8), **H8** hook-registering constructors (09-17 H9).
- **M1** Claude test key persisted (09-17 M4), **M2** headless counter raw SQL vs object cache (09-17 M5), **M11** two options with no writer (09-17 M8), **M12** dead `RequestDesk_Post_Handler` (09-17 M9, code from `1cf5a66`, 2025-10-19, the v2.0.0 import), **M13** defaults written three times (09-17 M10), **M14** push loop reads missing keys (09-17 M11), **M15** combined settings page and OG image (09-17 M12, M13).
- **L1** (partial), **L2**, **L3**, **L4**, **L5**, **L6** (09-17 L1, L2, L3, L4, L5, L8), **L7** featured-image fetch (09-17 M3, downgraded), **L14**, **L15** (09-17 L9, L10, unverified).

**Open since 2026-10-19:**
- **H1** `/publish` adopts any post by slug, **H2** public events routes (partial), **H3** CC hero copy seeded.
- **M3** FAQPage schema with no opt-in, **M4** re-optimize on every update, **M5** unslashed core writes, **M6** stripper allowlists CC domains, **M7** activation defaults never written, **M8** Audit Capture dead on CC, **M9** release zip ships deploy notes and is 16 releases stale, **M10** one-click template inserts CC stats and booking link, **M19** auto-approved comments bypass the stripper (promoted from L17).
- **L8** (partial), **L9**, **L10**, **L11**, **L12**, **L13**.

---

## BLOCKER for a client install

### B1. Saving the AEO settings tab switches a client to "RequestDesk wins", which overwrites the client's Yoast Organization and WebSite identity (open since 2026-09-17)
**`admin/aeo-settings-page.php:32`**, **`:54`**, **`:277-281`**; consumer **`includes/class-requestdesk-yoast-schema.php:302`** (`apply_organization`) and **`:348`** (`apply_website`).

Unchanged since 10-19 (no commit has touched either file since `2ce48fe`). `RequestDesk_Yoast_Schema::mode()` returns `'yoast'` on a non-CC install with nothing stored (`class-requestdesk-yoast-schema.php:98-116`), but the form renders "RequestDesk wins (recommended)" pre-checked when nothing is stored (`:277`, `:280-281`), saves `'requestdesk'` for any POST that is not `yoast`/`off` (`:32`), and its fallback array says `'requestdesk'` (`:54`). One routine Save on the AEO tab writes `yoast_mode = requestdesk`, after which `requestdesk_wins()` (`:128-130`) is true and the client's Yoast Organization name and description become the WordPress site title and tagline.

Smallest fix: derive the radio from `RequestDesk_Yoast_Schema::mode()` at `:277`, keep the stored value when no valid radio is posted at `:32`, drop `yoast_mode` from `:54`, and drop "(recommended)" on non-CC installs.

---

## HIGH

### H1. `/publish` without a `post_id` silently overwrites any existing post with the same slug, and reports a create (open since 2026-10-19)
`includes/class-requestdesk-api.php:1522-1538`, response `:1694`. The create path looks up a `post` by `$check_slug` in any status including `publish` (`:1524-1530`) and `wp_update_post()`s it (`:1535`); the response is still `201` with no created/updated flag. A client's own "Pricing" or "About" post can be replaced by a RequestDesk piece. Fix: on a slug collision without `post_id`, return `409` with the existing ID, or adopt only a post carrying `_requestdesk_ticket_id`; add `action: created|updated` (the event route already does this, `:2042`).

### H2. The keyless `/public/events` routes are registered on every client install and load every event per request (partially fixed in `999ba07`)
`includes/class-requestdesk-headless-api.php:240-273` (`permission_callback => '__return_true'` at `:243`, `:264`), `:378-383` (`posts_per_page => -1`). The anonymous options-table write and the usage inflation are gone (`:24-31`). What remains: on a client install `rd_event` is deliberately admin-only (`class-requestdesk-event.php:316-326`), yet these routes publish any `publish`-status event to anyone, contradicting the route comment (`:233-238`) that they repeat "the public /events/ pages", which a client does not have. The list query ignores `per_page` at the database and filters in PHP, so a site with many events does a full load per anonymous hit. Fix: register the two public routes only when `requestdesk_is_cc_site()`, and pass `per_page` into the query (filter `when` with a meta query, or over-fetch by a bounded factor).

### H3. Content Cucumber's hero copy is still seeded into every install and is the shortcode default (open since 2026-10-19)
`requestdesk-connector.php:662-690`, `includes/class-requestdesk-homepage-hero.php:37-62` (reapplied when sequences are empty, `:75-78`), `admin/homepage-hero-settings-page.php:69-99` (and the placeholder at `:113`); rendered at `class-requestdesk-homepage-hero.php:211`. Headline "Humans Writing Content", "Let's write your success story!", the "Humans in the loop... human editors reviewing every piece" SEO paragraph, and terminal lines "No AI slop.", "Human writers standing by.", "All written by real humans." A client placing `[requestdesk_homepage_hero]` publishes CC's positioning as its own claims. `requestdesk_clear_seeded_cc_defaults()` (`requestdesk-connector.php:247-284`) clears only the HubSpot ids and stats. Fix: empty defaults in all three places, render nothing without a headline, extend the one-time cleanup to exact matches.

### H4. Bulk operations load every post and make network calls inside one request (open since 2026-09-17)
`includes/class-requestdesk-push.php:226` (`-1`), loop `:236-246`; `admin/aeo-settings-page.php:1033`, `:1075`, `:1097`, `:1119`. Unchanged. Fix: batch with an offset carried in the redirect, or one scheduled event per batch.

### H5. Daily cron monitors process the whole site in one request (open since 2026-09-17)
`includes/class-requestdesk-citation-tracker.php:354`, `includes/class-requestdesk-freshness-tracker.php:399`. Unchanged. Fix: cap `posts_per_page`; the `NOT EXISTS` meta query already resumes.

### H6. Literal PHP tags written into imported page content (open since 2026-09-17)
`admin/aeo-template-enhanced.php:43` opens a nowdoc; `:52`, `:53`, `:56` contain `<?php echo ... ?>`, which is stored as text. Unchanged. Fix: heredoc with interpolation, or placeholders plus `str_replace`.

### H7. `CREATE TABLE` on every push (open since 2026-09-17)
`includes/class-requestdesk-push.php:355`. `requestdesk_activate()` (`requestdesk-connector.php:585-631`) still creates only `requestdesk_sync_log` and `requestdesk_aeo_data`. Fix: move the `requestdesk_push_log` DDL into activation.

### H8. Constructors register hooks, so data-only use duplicates them (open since 2026-09-17)
Hooked `new RequestDesk_AEO_Core()` at `includes/class-requestdesk-aeo-core.php:943`, `class-requestdesk-push.php:392`, `class-requestdesk-frontend-qa.php:147`, `:375`, `:457`, `admin/aeo-meta-boxes.php:58`, `:199`, `:503`, `admin/aeo-settings-page.php:68`, `:710`, `:1036`, `admin/aeo-bulk-optimizer.php:64`, and (CC-only) `class-requestdesk-promote.php:251`. Only `class-requestdesk-yoast-schema.php:202` passes `false`. The theme helpers still do `new RequestDesk_Frontend_QA()` (`class-requestdesk-frontend-qa.php:485`, `:503`, `:521`), each adding another `the_content` filter. Fix: pass `false` at data-only sites, or singletons.

---

## MEDIUM

### M1. Claude connection test persists the candidate key with no rollback guarantee (open since 2026-09-17)
`requestdesk-connector.php:789-801`. The candidate key is written to `requestdesk_settings`, `test_connection()` runs, then the original is restored; a fatal or timeout between leaves the candidate stored. Fix: `try/finally`, or pass the key into `test_connection()`.

### M2. Headless counter written with raw SQL, read through the object cache (open since 2026-09-17)
`includes/class-requestdesk-headless-api.php:32-35`, `requestdesk-connector.php:527` and `:572-575`. Less hot now that `/public/` requests are not counted, but on a site with a persistent object cache `get_option()` at `:527` can read a stale value, so the sync posts a wrong count and subtracts it. Fix: `wp_cache_delete('requestdesk_headless_api_count', 'options')` (and `alloptions`) after each raw write, or move the counter out of options.

### M3. FAQPage schema is generated and printed on a client's posts with no opt-in (open since 2026-10-19)
Activation defaults `auto_optimize_on_publish => true`, `generate_faq_schema => true` (`requestdesk-connector.php:646`, `:648`); `publish_post` schedules optimization (`class-requestdesk-aeo-core.php:30`, `:90-97`); `optimize_post()` stores `faq_data` (`:185-221`); printed at `:425-432` or into Yoast's graph (`class-requestdesk-yoast-schema.php:187-224`). Fix: default both to false when `!requestdesk_is_cc_site()`.

### M4. "Auto-optimize on update" is ignored: every edit of a published post re-optimizes it (open since 2026-10-19)
`class-requestdesk-aeo-core.php:30` hooks `publish_post`, which core fires on every save whose new status is `publish`; `handle_post_publish()` (`:90-97`) never checks the old status, so the `auto_optimize_on_update` switch honored at `:82` has no effect, and each update costs two Claude calls when a key is set. Fix: hook `transition_post_status` or accept `$old_status` and return when it was `publish`.

### M5. Unslashed data passed to core write functions, so backslashes are stripped (open since 2026-10-19)
`includes/class-requestdesk-api.php:1516`, `:1535`, `:1542` (`wp_update_post`/`wp_insert_post` with raw `$post_data`), `:628` (`wp_update_comment` from `/comments/{id}`), `includes/class-requestdesk-comment-link-stripper.php:244`. Core `wp_unslash()`es all of these. `upsert_event()` does it right (`class-requestdesk-api.php:2017`, `:2019`). Fix: `wp_slash()` each array.

### M6. Comment Link Stripper allowlists Content Cucumber's and Talk Commerce's domains on client sites, not the client's own (open since 2026-10-19)
`includes/class-requestdesk-comment-link-stripper.php:112-116`, placeholder link `:81`, applied `:221-241`. A client's commenter linking to the client's own article loses the link, while a link to contentcucumber.com survives, and flagged commenters' Website fields are repointed to a requestdesk.ai post. Off by default. Fix: default the allowlist to the site's own host (plus CC/TC/RD only on CC), placeholder `''` on non-CC.

### M7. Activation defaults for `requestdesk_settings` are never written on a fresh install (open since 2026-10-19)
`requestdesk-connector.php:189` calls `requestdesk_is_cc_site()` at file load, which seeds `requestdesk_settings` (`:46`, `:80-81`) before the activation hook runs, so `add_option('requestdesk_settings', ...)` at `:634` is a no-op. Fix: merge defaults into the existing value in `requestdesk_activate()`.

### M8. Audit Capture is dead on every site, Content Cucumber included (open since 2026-10-19)
`requestdesk-connector.php:169` loads the file on CC only; `$aeo_classes` (`:332-345`) does not list `RequestDesk_Audit_Capture`, the file does not self-instantiate (it ends with the class close), and a repo grep finds no `new RequestDesk_Audit_Capture`. Fix: add it back to `$aeo_classes`; the `class_exists` guard keeps it off clients.

### M9. The release process ships the Talk Commerce server's deploy notes, and no zip exists after 2.48.0 (open since 2026-10-19)
`plugin-releases/` newest is still `requestdesk-connector-v2.48.0.zip`; the plugin is at 2.64.0 plus seven unreleased fix commits. `DEPLOY.md` and `sync-all.sh` are still at the repo root, and the documented build (`docs/TESTING.md`, "The pass", step 1) is `git archive ... HEAD` with only `docs/` dropped, so a new zip would again carry them plus `tests/`, `tools/`, `todo/`. (Contents of `DEPLOY.md` were described on 10-19 and not re-read this week.) Fix: an export-ignore list in `.gitattributes` for those paths, then cut a current zip.

### M10. "Import Default AEO Homepage Template" inserts Content Cucumber's stats and a CC staff booking link (open since 2026-10-19)
`admin/aeo-template-importer.php:441` inserts `requestdesk_get_aeo_template_content()` unmodified; the template carries "60,000+ projects delivered", "55M+", "4.9/5" (`admin/aeo-template-enhanced.php:38`, `:374`, `:526`, `:542`, `:558`) and a button to `meetings.hubspot.com/isaac-morey/meeting` (`:657`). Fix: placeholders in the template itself.

### M11. Two options are read but have no writer (open since 2026-09-17)
`includes/class-requestdesk-comparison-table.php:38`, `includes/class-requestdesk-schema-generator.php:1290`. Repo grep: the only other mention of either option is `uninstall.php:45-46`.

### M12. `RequestDesk_Post_Handler` is dead and loaded on every install (open since 2026-09-17)
`requestdesk-connector.php:90`; repo grep finds the class name only at its own definition (`includes/class-requestdesk-post-handler.php:7`). Added in `1cf5a66` (2025-10-19). Fix: delete it.

### M13. Feature defaults written out three times (open since 2026-09-17)
Hero: `requestdesk-connector.php:662-690`, `class-requestdesk-homepage-hero.php:36-66`, `admin/homepage-hero-settings-page.php:68-99`. AEO table DDL: `requestdesk-connector.php:612-631`, `admin/settings-page.php:15-35`. H3 is the current cost. Fix: one `defaults()` per feature.

### M14. Push loop reads keys that may not exist (open since 2026-09-17)
`includes/class-requestdesk-push.php:239-241` reads `$result['success']` and `$result['status']` on whatever `push_to_requestdesk()` returns. Fix: `is_array($result) && !empty($result['success'])`.

### M15. Combined settings page executes every tab, and the default OG image setting does nothing on Yoast sites (open since 2026-09-17)
`requestdesk-connector.php:418-444` (output buffer and regex strip of each tab's wrapper). The new A/B tab is rendered the same way, so its save handler runs during page render (`admin/ab-test-settings-page.php:34-70`). `admin/settings-page.php:676` writes `default_og_image`; the only reader is `includes/class-requestdesk-seo-core.php:473`, `:536`, a file absent from `$plugin_files`, and `RequestDesk_Yoast_Meta::og_image_url()` reads post meta only (`class-requestdesk-yoast-meta.php:193-196`).

### M16. A/B test: a CSS selector list hides that element for every visitor, not only Variant B (new)
`includes/class-requestdesk-ab-test.php:117-120`, printed at `:127`; selectors accepted by `admin/ab-test-settings-page.php:19-25`.

The hide rule prefixes each change's selector with `html[data-rd-ab="b"]:not([data-rd-ab-ready]) ` and joins the changes with commas. `requestdesk_ab_test_clean_selector()` rejects `{ } < ;` but allows a comma, so a change selector such as `.hero h1, .hero p` produces `html[data-rd-ab="b"]:not([data-rd-ab-ready]) .hero h1, .hero p{visibility:hidden!important}`. The prefix scopes only the first part: `.hero p` is hidden for Variant A visitors, and for B visitors after the swap, for as long as the test runs. Nothing un-hides it, because `data-rd-ab-ready` is part of the scoped half only. The JS swap (`assets/js/ab-test.js:31`) handles the list correctly, which makes the defect look like a Variant A layout bug. Shared module, off by default; on Content Cucumber it can blank part of the front page. Fix: wrap each selector in `:is(...)` after the prefix (`... :is(<selector>)`), or reject commas in the cleaner and say so on the tab.

### M17. A/B rate limit keys on `REMOTE_ADDR` with a sliding window: behind a proxy it becomes a site-wide cap, and it is not per minute (new, `91b06b7`)
`includes/class-requestdesk-ab-test.php:225-231`.

1. The bucket is `md5($_SERVER['REMOTE_ADDR'])`. Where the web server sees a CDN, load balancer or shared NAT address instead of the visitor (common behind a full-page cache layer, which the class doc names as the reason the pick runs in the browser, `:9-12`), every visitor shares one bucket: the whole site gets 30 accepted events, then `429` for everyone.
2. `set_transient($bucket, $seen + 1, MINUTE_IN_SECONDS)` runs on every accepted event, and core's `set_transient()` rewrites the timeout to `time() + 60` on every call (`wp-includes/option.php`, `update_option( $transient_timeout, time() + $expiration )`). So the window slides: the counter only resets after 60 quiet seconds from that address. On a shared bucket with steady traffic it never resets, and the test stops counting permanently after the 30th event.
3. Each accepted event now costs three option writes (transient value, transient timeout, counts) instead of one, and each new address adds two `wp_options` rows until the daily expired-transient cleanup. An attacker rotating addresses (IPv6) is not limited.

What it breaks: on Content Cucumber, if `REMOTE_ADDR` is a proxy address, the A/B results stop moving within minutes of a test starting and both variants read as flat, with only a `429` in the browser console. I could not verify which address the CC host puts in `REMOTE_ADDR` without network access; the defect in point 2 holds on any host. Fix: a fixed window (store `array(count, window_start)` and do not extend the TTL), and fall back to no per-address limit, or a much higher site-wide ceiling, when the address is private or matches a configured proxy list.

### M18. The M2 fix removed query-string keys, but the client's admin page and README still document `?api_key=`, and the change is unreleased and unversioned (new)
`admin/headless-settings-page.php:125` ("Query param: `?api_key=your-api-key`", shown to the client's admin under Authentication), `README.md:148` (same line), and the stale docblock `includes/class-requestdesk-aeo-core.php:765` ("or an api_key param"). Version is still `2.64.0` (`requestdesk-connector.php:6`, `:18`) and `CHANGELOG.md` has no entry after `[2.64.0] - 2026-10-14`, although `7bcf5be` is a breaking change for any integrator who followed those instructions, and `a3d0717`/`3e4248e` are security fixes. A client developer who built a headless frontend from the admin page gets `401` after the update with nothing in the changelog to explain it; a site cannot tell a vulnerable 2.64.0 from a fixed one. Workspace consumers are not affected (they send `X-RequestDesk-API-Key`, `astro-sites/sites/container/requestdesk-ai/src/lib/wordpress.ts:253`, `talk-commerce-com/src/lib/wordpress.ts:339`, `requestdesk-app/backend/app/services/wordpress_service.py:91`). Fix: delete the query-param lines from both docs and the docblock, bump to 2.64.1 (or 2.65.0, given the break) with a CHANGELOG entry naming the removed method.

### M19. Auto-approved comments never reach the Comment Link Stripper (promoted from 10-19 L17, now verified)
`includes/class-requestdesk-comment-link-stripper.php:119` hooks only `transition_comment_status`; the docblock claims coverage of auto-approval at `:11-12` and `:174-175` ("including the 'new' -> 'approved' transition an auto-approved comment takes on first insert"). In WordPress 6.7.2, `wp_new_comment()` inserts via `wp_insert_comment()` and fires `comment_post` (`wp-includes/comment.php:2348`) with no `wp_transition_comment_status()` call; that function is called only on delete, `wp_set_comment_status()` and `wp_update_comment()` (`:1529`, `:2484`, `:2658`). So a comment approved on insert (moderation off, a returning commenter under "previously approved" rules, a logged-in user) keeps every link, while the settings copy tells the admin it is covered. Off by default. Fix: also hook `comment_post` and run the same path when `$comment_approved === 1`; correct the docblock.

---

## LOW

### L1. Nonce read from superglobals without `isset` (partially fixed)
`requestdesk-connector.php:774` (`$_POST['nonce']`), `:783` (`$_POST['api_key']`); `admin/aeo-meta-boxes.php:811`. `:854` was fixed in `b35ea96`.

### L2. Nonce checked before capability (open since 2026-09-17)
`requestdesk-connector.php:774-781` (`requestdesk_test_claude_connection`). `requestdesk_generate_api_key()` (`:755-758`) already does capability first.

### L3. Two key generators for keys of equal power (open since 2026-09-17)
`requestdesk-connector.php:760` (`random_bytes`), `admin/headless-settings-page.php:23` (`wp_generate_password`). Both keys open every headless route (`class-requestdesk-headless-api.php:1095-1098`).

### L4. Empty hook callbacks (open since 2026-09-17)
`includes/class-requestdesk-aeo-core.php:31`/`:935-937`, `includes/class-requestdesk-frontend-qa.php:20`/`:34-36`.

### L5. Headless admin menu registered twice (open since 2026-09-17)
`admin/headless-settings-page.php:170`, `requestdesk-connector.php:309-311`.

### L6. Unescaped echoes in admin output (open since 2026-09-17)
`admin/settings-page.php:115`, `:118`, `:127`, `:134`, `:141`, `:476`, `:482`. Also `admin/ab-test-settings-page.php:128`, `:131`, `:136` echo the loop index unescaped (an integer from `foreach`, so harmless; listed for the sweep).

### L7. Featured-image fetch, residual SSRF to link-local (open since 2026-09-17, downgraded on 10-19)
`includes/class-requestdesk-api.php:1855`. `download_url()` uses `wp_safe_remote_get()`, which refuses loopback and RFC 1918; link-local `169.254.0.0/16` is the remainder. API-key gated, blind, must parse as an image. Fix: reject resolved link-local addresses before `download_url`.

### L8. The iframe host allowlist can still be bypassed two ways (partially fixed in `72c68ee`)
`includes/class-requestdesk-api.php:52-63`, kses filter `:37-47`, called at `:1400`.

1. **`src=` inside another attribute.** The host is taken from the first match of `\bsrc\s*=\s*["']...` in the whole tag (`:57`). `\b` matches inside `data-src`, and the pattern also matches text inside another attribute's value. `<iframe data-src="https://www.youtube.com/embed/x" src="https://any-host/">` or `<iframe title="src='https://www.youtube.com/'" src="https://any-host/">` passes the check on the YouTube value, and kses keeps the real `src` (and `title`, `style`).
2. **An opening tag with no `>` at the end of the content.** The callback regex needs `>` (`:56`), but kses tokenizes `<[^>]*(>|$)` (`wp-includes/kses.php:979-997`) and accepts a tag with an optional `>` (`:1170`, `>?$`), then rebuilds it with a closing `>`. So `...<iframe src="https://any-host/"` as the last bytes of `content` is never seen by the host check and comes out of kses as a complete iframe.

API-key gated, so the caller is already trusted to publish; the allowlist is the stated control. Fix: do the host check in a `wp_kses_allowed_html`-time attribute filter, or parse with `WP_HTML_Tag_Processor` (`get_attribute('src')`) on the kses output, and remove any iframe whose `src` host is not listed.

### L9. Uninstall leaves some of the plugin's own data (open since 2026-10-19, extended)
`uninstall.php:37-59` omits `requestdesk_cc_defaults_cleared` (written at `requestdesk-connector.php:251`) and, new in 2.64.0, `requestdesk_ab_test_settings` and `requestdesk_ab_test_counts` (`class-requestdesk-ab-test.php:40-41`); `:68-81` omits `_requestdesk_aeo_last_update`, `_requestdesk_citation_count`, `_requestdesk_freshness_data`, `_requestdesk_last_synced`, `_requestdesk_manual_qa_pairs`; comment meta `_requestdesk_links_stripped_*` and `_requestdesk_author_*_original` are not addressed.

### L10. An "Events" admin menu with Talk Commerce placeholders appears on every client (open since 2026-10-19)
`includes/class-requestdesk-event.php:321-322` (`show_ui`, `show_in_menu` true regardless of site modules); placeholders `:214` ("Brent Peterson and Isaac Morey"), `:265` ("Brent and Isaac will be at Shoptalk Fall..."). The new A/B tab adds two more CC-specific placeholders on client installs, `.cc-hero-bold__headline` and `.cc-hero-bold__cta--primary` (`admin/ab-test-settings-page.php:131`, `:147`).

### L11. IndexNow key file never matches on a subdirectory install (open since 2026-10-19)
`includes/class-requestdesk-indexnow.php:82` compares the path to `'/' . $key . '.txt'`; `get_key_file_url()` (`:92-93`) advertises `home_url('/' . $key . '.txt')`. `get_key()` (`:64-72`) can write the option on an anonymous request.

### L12. Stale or false text in code and admin copy (open since 2026-10-19)
`admin/settings-page.php:482` documents `POST requestdesk/v1/posts` (no such route); `admin/settings-page.php:364` says Debug Mode "disables API key validation", which no verifier implements (`class-requestdesk-api.php:2055-2080`); `class-requestdesk-yoast-meta.php:5-6` calls `'requestdesk'` "the default"; `requestdesk_activation_complete` is written (`requestdesk-connector.php:715`) for an auto-updater that does not exist. The `?api_key=` documentation is now its own finding (M18) because the code changed under it.

### L13. `/aeo-status` loads every published post, then paginates in PHP (open since 2026-10-19)
`includes/class-requestdesk-aeo-core.php:627-635` (no `LIMIT`), `:693` (`array_slice`).

### L14. Unverified: `RequestDesk_Yoast_Schema_Piece::$identifier` (open since 2026-09-17)
`includes/class-requestdesk-yoast-schema-piece.php:33-35`. Still not checkable without Yoast source; no Yoast copy was found next to the local core.

### L15. Unverified: PHP 8.1 null-to-string beyond the publish path (open since 2026-09-17)
`admin/aeo-template-importer.php` and the analyzer/detector were not re-read in full this week (unchanged since 10-19).

### L16. A/B test: an invalid CSS selector stops all Variant B counting while Variant A keeps counting (new)
`assets/js/ab-test.js:26-39`, `:117-124`, `:100`; no syntax check in `admin/ab-test-settings-page.php:19-25`. `document.querySelectorAll()` throws on an invalid selector (for example `.hero >` or `div[`), which aborts `run()` before it sets `data-rd-ab-ready`, sends the view, or attaches goal listeners. For B visitors the elements stay hidden until the 2-second timeout and nothing is counted; A visitors never call `swap()` and are counted normally. The results table then shows A with views and B with none, which reads as a broken split rather than a bad selector. An invalid goal selector throws in the capture listener on every click (`:100`). Fix: wrap each `querySelectorAll`/`closest` in `try/catch` with the existing `console.error`, and validate selectors on save.

### L17. A/B counts are read-modify-write on one option, so concurrent events are lost (new)
`includes/class-requestdesk-ab-test.php:236-241`. `get_option` then `update_option` on `requestdesk_ab_test_counts`; two events in the same instant both read N and write N+1. The rate-limit counter (`:227-231`) has the same race. The class doc already calls the numbers a guide, so LOW. Fix: one counter row per test/variant/kind updated with an atomic `UPDATE ... SET value = value + 1` (the pattern the headless counter uses), with the cache invalidation M2 asks for.

---

## The five to fix before a client install, in order

1. **B1: make the AEO settings form respect `RequestDesk_Yoast_Schema::mode()`.** `admin/aeo-settings-page.php:32`, `:54`, `:277`. Open since 2026-09-17; one Save on the AEO tab still overwrites a client's Yoast company identity.
2. **H1: stop `/publish` from adopting an unrelated post by slug.** `class-requestdesk-api.php:1522-1538`.
3. **H3 + M10: finish removing Content Cucumber's copy.** Hero text in three places; stats and a CC staff booking link in the one-click template.
4. **M3 + M4: make AEO opt-in on client installs and stop re-optimizing on every edit.** Two default flips and an old-status check.
5. **M18 + M9: release what was fixed.** Remove the `?api_key=` instructions, bump the version with a CHANGELOG entry, add export-ignore for `DEPLOY.md`/`sync-all.sh`/`tests`/`tools`/`todo`, and cut a zip. The six fixes in this delta reach no client until then.

**Next:** H2 (gate `/public/events` to CC), M16 and M17 before the next Content Cucumber A/B test is read for a decision, M5 (`wp_slash`), M19 (hook `comment_post`), L8.

---

## Notes on method

- Each fix was read at HEAD, not taken from its commit message. The commit message for `91b06b7` describes the limit as "per address per minute"; the code is a sliding window (M17).
- No finding about the A/B test depends on database state; whether a test is running on any site was not checked (no database or network access).
- `DEPLOY.md` contents were not re-read this week; M9 relies on the 10-19 description for what the file holds, and on the repo tree and `docs/TESTING.md` for the fact that the build still ships it.
