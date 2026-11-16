# RequestDesk Connector, Technical Deficiency Audit (2026-11-16)

Repo: `/Users/brent/scripts/CB-Workspace/requestdesk-wordpress` (main, HEAD `5b1380e`, v2.66.0)
Scope: read-only. No plugin file modified, no git writes, no database access, no WP-CLI, no network calls. The only file written is this one.
Method: full read of the 2026-10-27 audit (the last one written against current code), the 2026-11-11 audit (as a candidate list only, see below), the 10-19 and 09-17 headers. `git log abef534..5b1380e` (18 commits) and `git diff --stat abef534..5b1380e -- . ':!docs'` (18 files, +2725/-73). Full read of the new files `includes/class-requestdesk-headless-sites.php`, `admin/headless-sites-page.php`, `includes/class-requestdesk-site-chat.php`, `admin/site-chat-settings-page.php`; full read of the new site routes in `includes/class-requestdesk-headless-api.php` (`:274-889`) and its verifier (`:1724-1773`); the full diffs of `class-requestdesk-api.php`, `class-requestdesk-comment-link-stripper.php`, `class-requestdesk-content-audit.php`, `requestdesk-connector.php` and the post-type changes in the AEO files. Every 10-27 item re-located at HEAD by grep and read; line numbers below are current. WordPress core claims checked read-only against the LocalWP Content Cucumber copy (`/Users/brent/LocalSites/contentcucumber/app/public/wp-includes`, `$wp_version = '7.1.3'`); the 6.7.2 copy the 10-27 audit used is no longer at its path. Akismet behavior checked against Akismet 5.7.2 in the same LocalWP install. Two workspace consumers read for how they call the plugin: `requestdesk-app/backend/app/services/wordpress_service.py`, `requestdesk-app/backend/app/models/agent.py`.

Context assumed throughout: a **client install** = site modules OFF (`requestdesk_is_cc_site()` false), stock theme, Yoast SEO active, no headless sites configured unless a finding says otherwise.

Severity counts: **BLOCKER 1, HIGH 8, MEDIUM 25, LOW 26** (60 open: 44 carried from 10-27, 6 reinstated from the 10-26 lineage, 10 new). Fixed since 10-27: **1**.

---

## Baseline defect: the 2026-11-11 audit was written against a stale checkout

`docs/audits/2026-11-11-technical-deficiency-audit.md` states HEAD `0d52bd4`, v2.64.0, and opens with "`git diff --stat 9861456..HEAD` returns nothing, so no plugin source changed". That checkout was the 2026-10-26 merge, 24 commits behind `origin/main`. It never saw the 2026-10-22 fix commits (`a3d0717` M1 `hash_equals`, `7bcf5be` M2 headers-only keys, `b35ea96` M5/M6 `edit_post` checks, `3e4248e` H2 stripper kses, `72c68ee` L8/L12, `999ba07` H3 public-route counter, `91b06b7` A/B rate limit), the 2026-10-27 audit, or 2.65.0 through 2.66.0. Its "0 fixed" is wrong, and it reports as open eight items that were fixed on 10-22:

| 11-11 item | Fixed by | At HEAD |
|---|---|---|
| M1 key compared with `!==` | `a3d0717` | `class-requestdesk-api.php:2204` `hash_equals` |
| M2 keys as query param | `7bcf5be` | no `get_param('api_key')` anywhere in `includes/` |
| M6 schema preview AJAX no cap check | `b35ea96` | `admin/aeo-meta-boxes.php:854`, `:861` `edit_post` |
| N3 AEO AJAX by role | `b35ea96` | `class-requestdesk-aeo-core.php:870-874`, `:894-898` |
| R5 freshness/citation AJAX by role | `b35ea96` | `class-requestdesk-freshness-tracker.php:536-540`, `:560-564`; `class-requestdesk-citation-tracker.php:440-444`, `:457-461` |
| R9 Claude output stored unsanitized | `3e4248e` | `class-requestdesk-comment-link-stripper.php:568-574` |
| R12 JSON-LD without `JSON_HEX_TAG` | `72c68ee` | `class-requestdesk-aeo-core.php:413`, `:429`; `class-requestdesk-schema-generator.php:1371` |
| N14 AEO verifier 500 on array key | `7bcf5be` (side effect) | `class-requestdesk-aeo-core.php:775-778` reads the header only, which is always a string |

So the true baseline is the 2026-10-27 audit. The 11-11 file is used below only as a list of candidates (Part 2). The 11-11 numbering came from the 10-26 report, which had already dropped sixteen 10-19 findings; two numbering lineages now exist. This report keeps the 10-27 IDs and maps the rest onto them.

---

## Part 1. Status of every open 2026-10-27 item

**Result: 1 fixed, 6 changed (still open, scope moved), 38 open unchanged.**

### Fixed

| 10-27 | Commit | Verified at HEAD |
|---|---|---|
| M19 Auto-approved comments never reach the stripper | `7a93b65` (2.65.2) | `class-requestdesk-comment-link-stripper.php:140` hooks `wp_insert_comment`; `maybe_strip_on_insert()` (`:445-454`) runs the same pass when `comment_approved === '1'`. Core fires that action from `wp_insert_comment()` (`wp-includes/comment.php:2234`). The method docblock was corrected (`:218-221`). The class header still claims approval "must never depend on an external API call succeeding" (`:19-21`), which the insert path now contradicts: see M25. |

### Changed (open, scope moved)

| 10-27 | What moved |
|---|---|
| H1 `/publish` adopts any post by slug | The slug lookup now uses the requested type (`class-requestdesk-api.php:1606-1620`, `'post_type' => $post_type` at `:1609`), so headless-site types (2.65.0) are adopted the same way. Still `201` with no created/updated flag (`:1773-1799`). |
| H4 bulk operations unbatched | Same four `posts_per_page => -1` queries (`admin/aeo-settings-page.php:1033`, `:1075`, `:1097`, `:1119`) and `class-requestdesk-push.php:226`, now over `requestdesk_content_post_types()`, which adds every headless-site type. Larger on a multi-site install; unchanged on a client with none. |
| H5 daily crons whole-site | `class-requestdesk-citation-tracker.php:352-354`, `class-requestdesk-freshness-tracker.php:397-399`, same widening. |
| M6 stripper allowlists CC domains | 2.65.3 (`9874c95`) now also deletes bare URLs whose host is not on the list (`class-requestdesk-comment-link-stripper.php:604-618`, `:629-643`). The list is still `contentcucumber.com`, `talk-commerce.com`, `requestdesk.ai` (`:122-126`), so on a client a reader's plain-text link to the client's own article is now removed too, while a plain link to contentcucumber.com survives. |
| M15 combined settings page runs every tab | The Website Chat tab (`requestdesk-connector.php:498-504`) adds its save handler and a blocking outbound HTTP call to every render of Settings > RequestDesk: see M26. The SEO side is wider than 10-27 said: `admin/seo-meta-boxes.php` and `includes/class-requestdesk-seo-core.php` are loaded by nothing (repo grep finds only a docblock mention, `admin/settings-page.php:649`), yet `1f8859e` (2.65.0) edited `admin/seo-meta-boxes.php:32`, a file that never runs. |
| M18 query-key docs, no version or CHANGELOG | Versions now move (2.65.0-2.66.0, `requestdesk-connector.php:6`, `:18`) and CHANGELOG has entries for them. Still open: no CHANGELOG entry names the removed `?api_key=` method or the `hash_equals`/kses security fixes (grep finds only the old 2.x lines at `CHANGELOG.md:365`, `:606`); `admin/headless-settings-page.php:125` and `README.md:148` still tell a client to use `?api_key=`; `class-requestdesk-aeo-core.php:765` still says "or an api_key param". |
| L9 uninstall leaves data | Unchanged file (`uninstall.php:26-81`), and 2.65-2.66 added more it misses: `requestdesk_headless_sites` (it holds each site's API key and webhook URL, while the file's own header says "a deleted plugin must not leave credentials in the database", `:35-36`), `requestdesk_headless_sites_webhook_errors`, `requestdesk_headless_site_last_rebuild_{key}`, `requestdesk_site_chat_settings`, `requestdesk_comment_ip_restore`, `_v2`, `_v3`, and the `requestdesk_headless_site_rebuild` cron event. |

### Open, unchanged (re-read at the cited lines)

| ID | Finding (short) | First seen | Evidence at HEAD |
|---|---|---|---|
| B1 | AEO tab save flips a client to "RequestDesk wins" | 2026-09-17 | `admin/aeo-settings-page.php:32` (save fallback `'requestdesk'`), `:54` (default), `:277` (radio state), `:280-281` ("RequestDesk wins (recommended)"); `class-requestdesk-yoast-schema.php:98-116` `mode()` returns `'yoast'` on non-CC; docblock at `:96` still says `'requestdesk'` is the default |
| H2 | Keyless `/public/events` on every install, full load per hit | 2026-10-19 | `class-requestdesk-headless-api.php:240-273` (`__return_true` `:243`, `:264`); `get_events()` `:995-1000` `posts_per_page => -1` while advertising `per_page`; no `requestdesk_is_cc_site` gate in the file |
| H3 | CC hero copy seeded and default | 2026-10-19 | `requestdesk-connector.php:683-714`; `class-requestdesk-homepage-hero.php:37-62`; `admin/homepage-hero-settings-page.php:69-92`, placeholders `:113`, `:120`; `requestdesk_clear_seeded_cc_defaults()` `requestdesk-connector.php:256-292` does not clear it |
| H6 | Literal PHP tags in imported content | 2026-09-17 | `admin/aeo-template-enhanced.php:43`, `:52`, `:53`, `:56`; also `admin/aeo-template-about.php:54`, `:57`, `:99`, `:105`, `:223`, `:241` (nowdocs at `:8`, `:43`, `:138`), inserted by the one-click About import (`admin/aeo-template-importer.php:806`) |
| H7 | `CREATE TABLE` on every push | 2026-09-17 | `class-requestdesk-push.php:355`; activation creates only the two other tables (`requestdesk-connector.php:614`, `:633`) |
| H8 | Hooked constructors for data-only use | 2026-09-17 | `class-requestdesk-aeo-core.php:943`; `class-requestdesk-push.php:392`; `class-requestdesk-frontend-qa.php:147`, `:375`, `:457`, `:485`, `:503`, `:521`; `admin/aeo-meta-boxes.php:58`, `:199`, `:503`; `admin/aeo-settings-page.php:68`, `:710`, `:1036`; `admin/aeo-bulk-optimizer.php:64`; only `class-requestdesk-yoast-schema.php:202` passes `false` |
| M1 | Claude key test persists candidate key | 2026-09-17 | `requestdesk-connector.php:812-822` (write, `test_connection()` at `:819`, restore, no `try/finally`) |
| M2 | Headless counter raw SQL vs object cache | 2026-09-17 | `class-requestdesk-headless-api.php:32-35`; read `requestdesk-connector.php:548`, subtract `:593-596`; no cache delete |
| M3 | FAQPage schema with no opt-in | 2026-10-19 | `requestdesk-connector.php:667`, `:669` default true |
| M4 | Every edit re-optimizes | 2026-10-19 | `class-requestdesk-aeo-core.php:30` `publish_post`; `:89-97` no old-status check |
| M5 | Unslashed data to core writes | 2026-10-19 | `class-requestdesk-api.php:1598`, `:1617`, `:1624`, `:663`; `class-requestdesk-comment-link-stripper.php:293`. Extended by 2.65.0: `RequestDesk_Headless_Sites::sanitize_field_value()` calls `wp_unslash()` (`class-requestdesk-headless-sites.php:661`) and `write_fields()` then passes the value to `update_post_meta()` (`:722`), which unslashes again, so a field value saved from the editor loses a backslash; `write_author_meta()` does the same (`:786`, `:800`). From `/publish` the value is unslashed twice. |
| M7 | Activation defaults never written | 2026-10-19 | `requestdesk_is_cc_site()` runs at load (`requestdesk-connector.php:198`) and seeds the option (`:76-81`), so `add_option` at `:655` is a no-op |
| M8 | Audit Capture dead on CC | 2026-10-19 | `$aeo_classes` (`requestdesk-connector.php:336-357`) has no `RequestDesk_Audit_Capture`; no `new RequestDesk_Audit_Capture` in the repo |
| M9 | Release ships deploy notes; no current zip | 2026-10-19 | `DEPLOY.md`, `sync-all.sh` tracked at the root; no `.gitattributes` or `.distignore`; `plugin-releases/` is not in this repo; newest tag is `v2.47.0`; `docs/TESTING.md:51` still builds with `git archive ... HEAD` dropping only `docs/` |
| M10 | One-click template inserts CC stats and booking link | 2026-10-19 | `admin/aeo-template-enhanced.php:38`, `:374`, `:526`, `:542`, `:558`, `:657`, inserted unmodified (`admin/aeo-template-importer.php:441`). Extended (10-26 N4): the one-click About import (`:786-806`) inserts `admin/aeo-template-about.php` unmodified, with "60,000 projects", "60,000+", "4.9/5" and "Since 2018" at `:129`, `:273`, `:283`, `:339`, `:357`, `:363` |
| M11 | Two options with no writer | 2026-09-17 | `class-requestdesk-comparison-table.php:38`; `class-requestdesk-schema-generator.php:1290` |
| M12 | Dead `RequestDesk_Post_Handler` loaded everywhere | 2026-09-17 | loaded `requestdesk-connector.php:90`; class name only at its definition |
| M13 | Defaults written three times | 2026-09-17 | hero at `requestdesk-connector.php:683-714`, `class-requestdesk-homepage-hero.php:36-66`, `admin/homepage-hero-settings-page.php:68-99` |
| M14 | Push loop reads missing keys | 2026-09-17 | `class-requestdesk-push.php:237-241` |
| M16 | A/B selector list hides for every visitor | 2026-10-27 | `class-requestdesk-ab-test.php:117-120`; cleaner still allows commas (`admin/ab-test-settings-page.php:19-25`) |
| M17 | A/B rate limit on `REMOTE_ADDR`, sliding window | 2026-10-27 | `class-requestdesk-ab-test.php:225-232` |
| L1 | Superglobals read without `isset` | 2026-09-17 | `requestdesk-connector.php:795`, `:804`; `admin/aeo-meta-boxes.php:811`; also `admin/settings-page.php:44` (from 11-11 L1) |
| L2 | Nonce before capability | 2026-09-17 | `requestdesk-connector.php:795` then `:800` |
| L3 | Two key generators | 2026-09-17 | `requestdesk-connector.php:781`; `admin/headless-settings-page.php:23`; both accepted at `class-requestdesk-headless-api.php:1733-1736`. 2.65.0 adds a third, `wp_generate_password(40, false)` for site keys (`admin/headless-sites-page.php:107`) |
| L4 | Empty hook callbacks | 2026-09-17 | `class-requestdesk-aeo-core.php:31`, `:935-937`; `class-requestdesk-frontend-qa.php:20`, `:34-36` |
| L5 | Headless menu registered twice | 2026-09-17 | `admin/headless-settings-page.php:170`; `requestdesk-connector.php:318-319` |
| L6 | Unescaped admin echoes | 2026-09-17 | `admin/settings-page.php:115`, `:118`, `:127`, `:134`, `:141`, `:476`, `:482` (file unchanged since 10-27) |
| L7 | Featured-image fetch, link-local SSRF | 2026-09-17 | `class-requestdesk-api.php:1960` |
| L8 | iframe allowlist bypasses (two remain) | 2026-10-19 | `class-requestdesk-api.php:52-63`: `\bsrc` matched inside `data-src` or another attribute (`:57`); tag-without-`>` at end of content (`:56`) |
| L10 | Events menu with TC placeholders on clients | 2026-10-19 | `class-requestdesk-event.php:214`, `:265`, `:321-322`; A/B tab CC placeholders `admin/ab-test-settings-page.php:131`, `:147` |
| L11 | IndexNow key file on a subdirectory install | 2026-10-19 | `class-requestdesk-indexnow.php:82`, `:92-93` |
| L12 | Stale or false copy | 2026-10-19 | `admin/settings-page.php:364` (debug "accepts ANY API key"), `:482` (`requestdesk/v1/posts`); `class-requestdesk-yoast-meta.php:5-6`; `requestdesk_activation_complete` `requestdesk-connector.php:736` |
| L13 | `/aeo-status` loads every post | 2026-10-19 | `class-requestdesk-aeo-core.php:627-635`, `:693` |
| L14 | Unverified Yoast `$identifier` | 2026-09-17 | still no Yoast source read |
| L15 | Unverified PHP 8.1 null-to-string | 2026-09-17 | not re-read this week |
| L16 | Invalid A/B selector stops B counting | 2026-10-27 | `assets/js/ab-test.js:26-39`, `:100`, `:117-124` (file unchanged) |
| L17 | A/B counts read-modify-write | 2026-10-27 | `class-requestdesk-ab-test.php:236-241` |

---

## Part 2. The 2026-11-11 R/X candidates re-checked

R1-R16 are 10-19 findings the 10-26 report dropped; the 10-27 audit carried all of them under other IDs. X1 was first seen on 11-11.

| 11-11 | Maps to | Status at HEAD |
|---|---|---|
| R1 `/publish` adopts by slug | H1 | Open, changed (now also headless types) |
| R2 FAQPage no opt-in | M3 | Open |
| R3 re-optimize on every edit | M4 | Open |
| R4 unslashed writes | M5 | Open, extended |
| R5 freshness/citation AJAX by role | 10-27 M6 | Fixed 10-22 (`b35ea96`); 11-11 reported it open |
| R6 auto-approved comments skip stripper | M19 | **Fixed** 2.65.2 (`7a93b65`) |
| R7 release packaging, DEPLOY.md | M9 | Open |
| R8 Audit Capture never constructed | M8 | Open |
| R9 Claude reply stored unsanitized | 10-27 H2 | Fixed 10-22 (`3e4248e`); 11-11 reported it open |
| R10 Events menu, TC placeholders | L10 | Open |
| R11 IndexNow subdirectory | L11 | Open |
| R12 JSON-LD `JSON_HEX_TAG` | 10-27 L12 | Fixed 10-22 (`72c68ee`); the CC-only `class-requestdesk-case-study.php:569` still lacks it (not a client path) |
| R13 `/aeo-status` loads all | L13 | Open |
| R14 iframe with no closing tag | L8 | That bypass fixed (`72c68ee`); two others remain under L8 |
| R15 debug copy claims key bypass | L12 | Open |
| R16 one-click template CC stats | M10 | Open, extended to the About template |
| X1 stale plugin metadata | new to this lineage | Open, carried as **L18** below |

### Items in the 11-11 Part 1 (10-26 lineage) that the 10-27 audit never carried

The 10-27 audit was built from 10-19; the 10-26 audit added items 10-27 did not pick up. Re-read today so they are not lost a second time. Ratings are the 10-26 ratings unless stated.

- **M20 (10-26 N7) Comment REST routes export email and IP; 2.65.4 adds Akismet's submitted IP.** `class-requestdesk-api.php:548`, `:550`, and new `akismet_submitted_ip` (`:560-562`, `:607-614`) plus every IP-repair record (`:599-604`) on `GET /comments`. Key-gated. On a client install this ships commenters' personal data to the RequestDesk backend with no setting to stop it. MED.
- **M21 (10-26 N9) `/image-alt` can blank a post if the regex fails.** `class-requestdesk-api.php:947` assigns the `preg_replace_callback()` result with no null guard; a PCRE backtrack-limit failure (the pattern is `\{.*?\}` with `/s` over the whole post) returns `null`, which differs from `post_content`, so `:1000-1001` writes it with `$wpdb->update` after saving a revision. The stripper guards the same case. MED.
- **L18 (11-11 X1) Plugin metadata is stale.** `readme.txt:6` `Stable tag: 2.42.0` against 2.66.0; `readme.txt:5` `Tested up to: 6.7`; `Requires at least: 5.0` while `/image-alt` needs `WP_HTML_Tag_Processor` (`class-requestdesk-api.php:925`, 6.2+); the plugin header declares no `Requires at least` / `Requires PHP`.
- **L19 (10-26 N11) Scheduled events with arguments are never cleared.** Deactivation and uninstall call `wp_clear_scheduled_hook($hook)` with no args (`requestdesk-connector.php:755`, `uninstall.php:32`), which removes only argument-less events; AEO jobs are scheduled per post (`class-requestdesk-aeo-core.php:83`, `:95`) and 2.65.0 adds `requestdesk_headless_site_rebuild` per site (`class-requestdesk-headless-sites.php:863`), which neither list names. Fix: `wp_unschedule_hook()`.
- **L20 (10-26 N12) "Default post status" is saved but `/publish` ignores it.** Saved `admin/settings-page.php:44`; `/publish` hardcodes `?: 'draft'` (`class-requestdesk-api.php:1436`).
- **L21 (10-26 N15) A/B HubSpot goal listener does not check `e.origin`.** `assets/js/ab-test.js:108-110`. Any frame on the page can post a fake `hsFormCallback` and count a goal.

Not carried: 10-26 H6/M7 (asset hub `utm_source=contentcucumber` and anonymous impression writes) stay withdrawn, because `RequestDesk_Asset_Hub` is constructed only when `requestdesk_is_cc_site()` and nothing runs on a client.

---

## Part 3. New findings in code added since 2026-10-27

Reviewed for: unauthenticated routes, capability checks, work done on every page view of a client install, and Content Cucumber specifics reaching clients.

What was checked and is clean, so the next audit does not redo it: with no headless sites configured, `RequestDesk_Headless_Sites` registers nothing per request (`:487-499` loops an empty option) and the site switcher returns early (`:933-936`); every new `/headless/sites/...` route has a key `permission_callback` (`class-requestdesk-headless-api.php:289-375`), the config writes require the main key in the header (`:878-889`), discovery strips secrets (`class-requestdesk-headless-sites.php:468-481`), and per-site keys use `hash_equals` (`:841`, `:863`); the Headless Sites screen and the Website Chat tab check `manage_options` and a nonce (`admin/headless-sites-page.php:61`, `:69`; `admin/site-chat-settings-page.php:16`, `:20`); the chat footer tag is off by default, skips admin, feed and REST (`class-requestdesk-site-chat.php:137-143`) and escapes both values (`:144-148`); the 2.65.0 example model and the chat placeholder are neutral (`admin/headless-sites-page.php:34-57`, `admin/site-chat-settings-page.php:70`); the content audit change is CC-only (`requestdesk-connector.php:168`).

### MEDIUM

#### M22. Updating a headless-site item by `post_id` without restating `post_type` files its categories in the main site's taxonomy, rejects its fields, and returns a WordPress URL
`includes/class-requestdesk-api.php:1461-1468`, `:1473-1475`, `:1484-1486`, `:1578-1580`, `:1595`, `:1660-1687`, `:1777`.

`$post_type` defaults to `'post'` when the parameter is absent (`:1461`), so `$site_type` stays null. On the update path the mismatch check only fires when `post_type` was sent (`:1578`), and `:1595` keeps the row's real type. Everything downstream still treats the call as a native post: categories and tags go through `wp_set_post_categories()` / `wp_set_post_tags()` (`:1679`, `:1686`) onto an item whose type is not registered for `category`/`post_tag`, so the relationship is stored but the headless site's own taxonomy is untouched and the site's `/headless/sites/{site}/{type}` output does not change; `fields` fails with "fields apply only to a headless-site post_type" (`:1473-1475`) and `authors` with a similar message (`:1484-1486`), though the post is a headless-site item; `post_url` is `get_permalink()` of a type with no permalink (`:1777`). The RequestDesk app forwards `post_type` only when the caller set it (`requestdesk-app/backend/app/services/wordpress_service.py:519-521`), so an edit-by-ID from a flow that does not carry the type hits this. Not a client-install risk (needs a headless site). Fix: when `post_id` is given and `post_type` is not, resolve `$site_type` from `$existing_post->post_type` before validating `fields`, `authors`, `categories` and `tags`.

#### M23. Headless-site items, terms and every custom field are readable without a key through core REST, including fields marked `api: false`
`includes/class-requestdesk-headless-sites.php:535` (`show_in_rest => true` on every type), `:545-551` (`register_post_meta(..., 'show_in_rest' => true)` for every field, ignoring the field's `api` flag), `:571`, `:599` (taxonomies `show_in_rest`).

Core serves any `show_in_rest` type at `/wp-json/wp/v2/{type}` and lets anyone read a published item (`wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php:1767-1768`, `:1791`), and returns every registered `show_in_rest` meta key with no protected-key check on read (`wp-includes/rest-api/fields/class-wp-rest-meta-fields.php:80-85`). So the plugin's own routes, which need the headless key or the site's key (`class-requestdesk-headless-api.php:861-875`), are not the boundary: the same items, their `_rd_*` field values, values stored under any existing meta key a field names, and the author/taxonomy terms are public. A field configured with `"api": false` to keep it out of the frontend response (`read_fields()` skips it, `:733-735`) is still in the anonymous core response, and the admin copy says "A site key reads only this site's routes" (`admin/headless-sites-page.php:198`). Published content is usually public on the frontend anyway, so the exposure is the hidden fields and the false promise. Only on installs with a headless site. Fix: register meta with `show_in_rest` only when `api` is true (or never, since the plugin's routes serve fields), and set `show_in_rest => false` on the types and taxonomies unless the block editor is needed; if it is, add a `rest_prepare_{type}` / `rest_{type}_query` gate that requires the site key for unauthenticated reads.

#### M24. The comment IP guard and one-time IP repair run on every install with no opt-in, and the repair can overwrite deliberately rewritten IPs
`includes/class-requestdesk-comment-link-stripper.php:152`, `:162`, `:167` (registered in the constructor with no `is_enabled()` check, unlike the author-link filter at `:176`); `keep_stored_author_ip()` `:356-375`; `maybe_restore_author_ips_once()` `:392-433`.

Three behavior changes ship to every client in 2.65.2-2.65.5, whether or not the client ever turned on link stripping (the comment at `:150-151` says so):
1. `wp_update_comment_data` at priority 999 puts the stored IP back on every `wp_update_comment()` from any plugin (`:356-375`). A privacy plugin or moderation tool that anonymizes or corrects an IP through `wp_update_comment()` now silently does nothing. Core's own privacy eraser is not affected, because it writes with `$wpdb->update` (`wp-includes/comment.php:4382`).
2. The one-time repair, on the first admin page load by any user with `moderate_comments`, rewrites `comment_author_IP` to the value in Akismet's `akismet_as_submitted` meta wherever they differ (`:392-433`). That value is core's raw `$_SERVER['REMOTE_ADDR']`, set before `preprocess_comment` (`wp-includes/comment.php:2389-2390`, `:2405`), while the stored IP is what `pre_comment_user_ip` produced afterwards (`:2294`). On a site where that filter truncates IPs for privacy, or resolves a proxy address to the visitor's address, the repair replaces the intended value with the raw one. The plugin's own comments say the production host that prompted this "rewrites the IP in `pre_comment_user_ip`" (`:67-69`, `:362-366`); which of the two cases that is cannot be determined read-only. Akismet expires the meta after 15 days (`akismet/class.akismet.php:916`), so the reach is recent comments.
3. The repair has run three times by design (options `requestdesk_comment_ip_restore`, `_v2`, `_v3`, `:381`), each a full load of every comment holding that meta (`'number' => 0`, `:400-404`) inside one admin request.

What it breaks for a client: comment data the client's own privacy setup chose is changed by a connector that was installed to publish content, and nothing on the settings screen says so. Fix: gate all three hooks on `is_enabled()` (or a separate opt-in), never overwrite an IP that `pre_comment_user_ip` would change today (run the Akismet value through `apply_filters('pre_comment_user_ip', ...)` and skip when it differs from the raw value), and drop the priority-999 override in favor of handling only this plugin's own `/comments/{id}` route.

#### M25. With link stripping on and a Claude key set, a visitor's comment submission now waits on a 30-second Claude call (reinstates 10-26 N6, extended by 2.65.2)
`includes/class-requestdesk-comment-link-stripper.php:140`, `:445-454` → `maybe_strip_links()` `:251` → `maybe_apply_claude_pass()` `:537-547`; `includes/class-requestdesk-claude-integration.php:52-56` (`'timeout' => 30`).

10-26 N6 reported that approving a comment in wp-admin blocks on the Claude pass. The 2.65.2 insert hook moves the same synchronous call into the front-end request of any comment approved on arrival (returning commenter, logged-in user, Akismet pass): the reader's "Post Comment" spins for up to 30 seconds, every time, because the pass runs on every approved comment whether or not the regex found a link (`:250-251`). The same hook fires for `wp_insert_comment()` from importers, so importing approved comments with the feature on makes one Claude call per comment in a single request and strips links from historical comments. The class header promises approval "must never depend on an external API call succeeding" (`:19-21`). Off by default. Fix: on the insert path run the regex pass only and schedule the Claude pass with `wp_schedule_single_event`; skip the Claude pass when the regex pass changed nothing and the text has no dot-word pattern; skip when `WP_IMPORTING` is defined.

#### M26. Website Chat makes a blocking 8-second outbound request on every render of Settings > RequestDesk while switched on
`admin/site-chat-settings-page.php:34-35`; `includes/class-requestdesk-site-chat.php:99-102` (`wp_remote_get`, `'timeout' => 8`); rendered from `requestdesk-connector.php:498-504`.

The combined settings page renders every tab on every load (M15), so the chat tab's `remote_check()` runs whenever an admin opens or saves any tab (General, AEO, Hero, A/B), not only the Website Chat tab. When RequestDesk is slow or unreachable, every Settings > RequestDesk page load stalls for up to 8 seconds, and every load sends a request carrying the site's origin to the configured host. Admin-only, off by default. Fix: run the check only when the chat tab's form was just saved, or on a button, and cache the result in a short transient; longer term, M15.

### LOW

#### L22. Website Chat lowercases and strips the slug, while RequestDesk does not constrain slug format
`admin/site-chat-settings-page.php:24` (`sanitize_key`); RequestDesk's `AgentPublicChat.slug` has no pattern (`requestdesk-app/backend/app/models/agent.py:64`) and is matched exactly (`requestdesk-app/backend/app/routers/public/site_chat.py:95`). A slug with an uppercase letter or a dot is saved altered and never matches; the tab's remote check then reports "no switched-on chat with the slug", which names the altered value. Fix: `sanitize_text_field` plus a pattern that matches RequestDesk's, or constrain the slug on the RequestDesk side.

#### L23. The rebuild webhook uses `wp_remote_post` to an API-settable URL and shows the reply to every editor; a site with no webhook gets a permanent error notice
`includes/class-requestdesk-headless-sites.php:874-888` (`wp_remote_post`, not `wp_safe_remote_post`), `:899-903`, `:906-920`; webhook settable through `POST /headless/sites` with the main key (`class-requestdesk-headless-api.php:295-299`). The URL must be https (`:236-239`) but can name an internal host; a non-2xx reply's first 300 bytes are stored and printed on every admin screen to anyone with `edit_posts`, Contributors included. Separately, `queue_rebuild()` records "No rebuild webhook is configured" on every publish or edit when the site has none (`:858-860`), and only a successful rebuild clears it, so a site whose frontend renders on request (no rebuild needed) carries a red notice on every admin page for every editor indefinitely. Fix: `wp_safe_remote_post`; store the code only; show the notice to `manage_options`; treat "no webhook" as a valid state, not an error.

#### L24. Headless `embed` fields accept an iframe from any host, while `/publish` content is host-allowlisted
`includes/class-requestdesk-headless-sites.php:673-679`. Any user without `unfiltered_html` (Author, Editor on multisite) and every API-key call (no user, so `current_user_can('unfiltered_html')` is false) gets `wp_kses` with `iframe[src]` and `style` allowed and no host check; `/publish` runs `strip_foreign_iframes()` on content (`class-requestdesk-api.php:1435`). The frontend renders the embed, so an Author can place any page in a frame there. Fix: run the same host allowlist on embed values.

#### L25. A site API key or webhook, once set, cannot be removed
`includes/class-requestdesk-headless-sites.php:430-438`: an empty `api_key` or `webhook_url` on update keeps the stored value, and neither the form (`admin/headless-sites-page.php:193-198`) nor the API offers a clear. A leaked site key can only be rotated (`:103-115`); a webhook can be replaced but not dropped; deleting the site is refused while it has content (`:451-460`). Fix: an explicit `null` (API) or a "remove" checkbox clears it.

#### L26. A term failure after the post is written returns 500 "Failed to publish" with the post already saved
`includes/class-requestdesk-api.php:2164-2187` throws from `set_terms_by_name()` after `wp_insert_post`/`wp_update_post` (`:1598-1629`) has run; the catch (`:1801-1808`) reports a failed publish and logs no `post_id`. A caller that retries creates nothing new only because H1 adopts the post by slug. Fix: report `post_id` with a partial-success status, or validate term creation before writing the post.

---

## Part 4. Counts and delta

### Counts (all open items, current ratings)

| Severity | Carried from 10-27 | Reinstated (10-26 lineage) | New | Total |
|---|---|---|---|---|
| BLOCKER | 1 (B1) | 0 | 0 | **1** |
| HIGH | 8 (H1-H8) | 0 | 0 | **8** |
| MEDIUM | 18 (M1-M18) | 2 (M20, M21) | 5 (M22-M26) | **25** |
| LOW | 17 (L1-L17) | 4 (L18-L21) | 5 (L22-L26) | **26** |
| **Total** | **44** | **6** | **10** | **60** |

M25 also absorbs 10-26 N6; it is counted once, as new, because 2.65.2 moved it into the visitor's request.

### Delta since 2026-10-27

**Fixed: 1.** M19 (auto-approved comments now reach the stripper, `7a93b65`). No other 10-27 item changed state. No commit in the range touched `admin/aeo-settings-page.php:20-290`, the hero files, the templates, `uninstall.php`, the A/B files or `class-requestdesk-push.php`.

**New: 10** (M22-M26, L22-L26), all in 2.65.0-2.66.0 code. Plus 6 reinstated from the 10-26 lineage that the 10-27 audit never carried (M20, M21, L18-L21).

**Changed but open: 6** (H1, H4, H5, M6, M15, M18, plus L9 extended).

**Release state:** 2.65.0-2.66.0 have version bumps and CHANGELOG entries, which closes half of M18; there is still no release zip in the repo, no tag after `v2.47.0`, and no export-ignore list (M9), so it cannot be read from the repo which version any client runs.

**Open longest (first reported 2026-09-17, 60 days):** B1; H4, H5, H6, H7, H8; M1, M2, M11, M12, M13, M14, M15; L1-L7, L14, L15. B1 has been the BLOCKER in every audit since 09-17, "fixed" in `mode()` in 2.48.0 and still reopened by the form at the same three literals.

### Top three open items

1. **B1** `admin/aeo-settings-page.php:32`, `:54`, `:277`: one routine Save on the AEO tab switches a client's Yoast site to "RequestDesk wins" and replaces its Organization and WebSite identity. 60 days open; three-line fix.
2. **H1** `class-requestdesk-api.php:1606-1620`: `/publish` without `post_id` overwrites a client's existing post with the same slug, answers `201`, and since 2.65.0 does the same inside headless-site types.
3. **M24** `class-requestdesk-comment-link-stripper.php:152`, `:162`, `:167`, `:392-433`: every client install since 2.65.2 runs a comment-IP override and a one-time IP rewrite from Akismet data, with no setting, which can undo the client's own IP handling.

**Next, cheap:** M26 (move the chat check off the render path), M22 (resolve the type from the existing post), M23 (meta `show_in_rest` only when `api` is true), H3 + M10 (CC copy), M18 (two doc lines and a CHANGELOG note for the 10-22 fixes).

---

## Notes on method

- Core claims were checked against WordPress 7.1.3, not 6.7.2; the 10-27 copy is gone from `/Users/brent/ContentCucumber/`. The plugin still declares `Tested up to: 6.7` (L18). The `transition_comment_status`, REST read-permission and meta-read behaviors cited above have been stable across those versions in my reading, but I did not read a 6.7 copy this week.
- M24's Akismet claims come from Akismet 5.7.2 in the LocalWP install; a client on a different Akismet version may differ on the 15-day meta expiry.
- Whether any client has link stripping or Website Chat switched on, or any headless site configured, was not checked (no database or network access).
- `DEPLOY.md` contents were not re-read; M9 rests on the repo tree and `docs/TESTING.md:51`.
