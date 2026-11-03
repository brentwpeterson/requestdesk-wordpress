# RequestDesk Connector - Technical Deficiency Audit (2026-10-26)

Repo: `/Users/brent/scripts/CB-Workspace/requestdesk-wordpress` (main, clean, HEAD `9861456`, v2.64.0)
Scope: read-only. No plugin files modified, no git writes, no database access, no WP-CLI, no network calls. The only file written is this one.
Method: full read of `requestdesk-connector.php` (the `$plugin_files` list at `:87-141`, `$cc_only_files` at `:158-187`, class construction in `requestdesk_init()` at `:296-358`), `uninstall.php`, `includes/class-requestdesk-{ab-test,comment-link-stripper}.php`, `admin/ab-test-settings-page.php`, `assets/js/ab-test.js`, and the changed regions of `includes/class-requestdesk-{api,headless-api,aeo-core,yoast-schema,yoast-meta,event,push,asset-hub,homepage-hero,stats-bar,frontend-qa}.php` and `admin/{aeo-settings-page,settings-page,headless-settings-page,aeo-meta-boxes,aeo-template-importer,aeo-template-enhanced,aeo-template-about,homepage-hero-settings-page}.php`. Every finding from the 2026-09-17 audit was re-checked at its current location. Repo-wide greps back every "never written / never read / unauthenticated" claim. `git log --since=2026-09-16` (32 commits) was read to attribute fixes. Two claims about WordPress core and Yoast were checked read-only against the copies installed on the Content Cucumber LocalWP site (`/Users/brent/LocalSites/contentcucumber/app/public`); the Yoast version there was not recorded.

Context assumed throughout: a **client install** = site modules OFF (`requestdesk_is_cc_site()` false), stock theme, Yoast SEO active.

Prior audit: `docs/audits/2026-09-17-technical-deficiency-audit.md` (v2.47.3). Most of its BLOCKERs were addressed in `f978961` (2.48.0, "Client-install hardening") and `8101073` (2.48.0, clearing seeded CC values), with SEO-meta work in `1a0412b` (2.47.3) and `629fa3c` (2.49.0).

---

## Part 1. Status of the 2026-09-17 findings

| ID | Finding (short) | Status | Evidence now |
|---|---|---|---|
| B1 | Unauthenticated `cc-audit/v1/request` | FIXED | `class-requestdesk-audit-capture.php` moved to `$cc_only_files` (`requestdesk-connector.php:163-169`), `f978961`. Only reference is the gated list. |
| B2 | CC audit-capture module ships to clients | FIXED | Same move as B1. |
| B3 | Yoast mode defaults to `requestdesk` on clients | **STILL OPEN (partly fixed)** | Default fixed in `class-requestdesk-yoast-schema.php:98-116` (`f978961`), but the settings form puts it back. See B3 below. |
| B4 | Yoast meta takeover reads keys nothing writes | **STILL OPEN (partly fixed), re-rated MED** | `/publish` now writes title, description and keyphrase (`class-requestdesk-api.php:1613-1631`, `629fa3c`). Eight of the eleven keys still have no loaded writer. See B4 below. |
| B5 | `rd_event` claims `/events/` on every install | FIXED | `class-requestdesk-event.php:316-326` registers public/archive/rewrite only when `requestdesk_is_cc_site()`. Loader comment corrected at `requestdesk-connector.php:132-137`. |
| B6 | CC HubSpot portal/form seeded and hardcoded | FIXED | Defaults empty at `requestdesk-connector.php:666-667`, `class-requestdesk-homepage-hero.php:43-44`, `admin/homepage-hero-settings-page.php:73`; importer reads the CSV at `admin/aeo-template-importer.php:2657`; existing installs cleaned by `requestdesk_clear_seeded_cc_defaults()` (`requestdesk-connector.php:247-283`, `8101073`). |
| B7 | DB write on every anonymous pageview | FIXED | `get_aeo_data_readonly()` at `class-requestdesk-aeo-core.php:347`, used by `output_schema_markup()` (`:423`), `get_clean_faq_schema()` (`:443`) and `class-requestdesk-frontend-qa.php:148,376,458`. |
| B8 | "Enable Headless API" toggle not honored | FIXED | Early return in `class-requestdesk-headless-api.php:35-41`. |
| H1 | CC stats seeded as client stats | FIXED (stats bar) | `class-requestdesk-stats-bar.php:30-35` empty default, no fallback; `requestdesk-connector.php:699-706`; cleanup at `:274-282`. The same numbers remain in the page templates (new finding N4). |
| H2 | AEO REST authorizes by role, not object | FIXED (REST) | `check_aeo_permissions()` checks `edit_post` on the named post (`class-requestdesk-aeo-core.php:734-747`). The AJAX twins were missed (new finding N3). |
| H3 | Bulk ops unbounded and synchronous | STILL OPEN | `class-requestdesk-push.php:226`; `admin/aeo-settings-page.php:1033,1075,1097,1119`. |
| H4 | Daily cron processes whole site | STILL OPEN | `class-requestdesk-citation-tracker.php:354`; `class-requestdesk-freshness-tracker.php:399`. |
| H5 | Orphan crons, no `uninstall.php` | FIXED | `requestdesk_deactivate()` clears four hooks (`requestdesk-connector.php:725-737`); `uninstall.php` added in `f978961`. Gaps in both are new findings N10 and N11. |
| H6 | Client ad clicks tagged `utm_source=contentcucumber` | STILL OPEN | `class-requestdesk-asset-hub.php:871`. |
| H7 | Literal PHP tags written into imported pages | STILL OPEN, and wider | `admin/aeo-template-enhanced.php:43` (nowdoc) with tags at `:52,53,56`; same in `admin/aeo-template-about.php:43` (nowdoc) with tags at `:54,57,99,105,223`. No substitution in the importer. |
| H8 | `CREATE TABLE` on every push | STILL OPEN | `class-requestdesk-push.php:355-363`. |
| H9 | Constructors register hooks for data-only use | STILL OPEN | Only `class-requestdesk-yoast-schema.php:202` passes `false`. Hooked instances still built at `class-requestdesk-frontend-qa.php:147,375,457,485,503,521`, `class-requestdesk-push.php:392`, `admin/aeo-meta-boxes.php:58,199,503`, `admin/aeo-settings-page.php:68,710,1036`, `admin/aeo-bulk-optimizer.php:64`, `class-requestdesk-aeo-core.php:943`. |
| M1 | Main key compared with `!==` | STILL OPEN | `class-requestdesk-api.php:2068`. |
| M2 | API keys accepted as query param | STILL OPEN | `class-requestdesk-api.php:2064-2067`; `class-requestdesk-headless-api.php:1114`; `class-requestdesk-aeo-core.php:776-778`; advertised at `admin/headless-settings-page.php:125`. |
| M3 | Featured-image fetch is SSRF | CLOSED, not a defect as written | `set_featured_image_from_url()` (`class-requestdesk-api.php:1852`) calls `download_url()`, which in core calls `wp_safe_remote_get()` (`wp-admin/includes/file.php:1160,1177` on the LocalWP copy). That path rejects loopback and private-range hosts. No plugin change needed. |
| M4 | Claude key test persists key with no rollback | STILL OPEN | `requestdesk-connector.php:790-801`. |
| M5 | Headless counter bypasses object cache | STILL OPEN | `class-requestdesk-headless-api.php:24-29`; read at `requestdesk-connector.php:527`, decrement at `:572-575`. |
| M6 | Schema preview AJAX has no capability check | STILL OPEN | `admin/aeo-meta-boxes.php:852-879`. |
| M7 | Unauthenticated ad impression writes | STILL OPEN | `class-requestdesk-asset-hub.php:89,886-896`. The click redirect is also an anonymous meta write per GET (`:851-863`). |
| M8 | Two options read with no writer | STILL OPEN | `class-requestdesk-comparison-table.php:38`; `class-requestdesk-schema-generator.php:1290`. The only other references are the `uninstall.php:45-46` deletes. |
| M9 | `RequestDesk_Post_Handler` dead | STILL OPEN | Class name appears only at `class-requestdesk-post-handler.php:7`; still loaded at `requestdesk-connector.php:90`. |
| M10 | Defaults written three times | STILL OPEN | Hero: `requestdesk-connector.php:662-693`, `class-requestdesk-homepage-hero.php:36-69`, `admin/homepage-hero-settings-page.php:68-99`. AEO DDL: `requestdesk-connector.php:612-629` and `admin/settings-page.php:15`. |
| M11 | Error paths read undefined variables | STILL OPEN (half fixed) | (a) FIXED: `$ticket_id`/`$agent_id` assigned at `class-requestdesk-api.php:1399-1400`, before the first `throw` (`:1494`). (b) OPEN: `class-requestdesk-push.php:237-243` and `:278` still read `$result['success']` when `push_to_requestdesk()` returns `false` (`:34-35`) or an array with no `success` key (`:43-44`). |
| M12 | Combined settings page runs every tab | STILL OPEN | `requestdesk-connector.php:419-446`; `SHOW TABLES` / `dbDelta` on every load at `admin/settings-page.php:7,31`. Now six tabs (`:408-415`). |
| M13 | Default OG image does nothing on Yoast sites | STILL OPEN | Written at `admin/settings-page.php:676`; `RequestDesk_Yoast_Meta::og_image_url()` (`class-requestdesk-yoast-meta.php:193-196`) still reads only post meta. The only other reader is the unloaded `class-requestdesk-seo-core.php:473,536`. |
| L1 | Nonce superglobals read without `isset` | STILL OPEN (partly fixed) | Asset hub and video now guarded. Still unguarded: `requestdesk-connector.php:774,783`; `admin/aeo-meta-boxes.php:811,854,859`. |
| L2 | Nonce checked before capability | STILL OPEN | `requestdesk-connector.php:774` then `:779`. |
| L3 | Two key generators | STILL OPEN | `requestdesk-connector.php:760` (`random_bytes`) vs `admin/headless-settings-page.php:23` (`wp_generate_password`). |
| L4 | Empty hook callbacks | STILL OPEN | `class-requestdesk-aeo-core.php:31` hooks `:935-937`; `class-requestdesk-frontend-qa.php:20` hooks `:33-35`. |
| L5 | Headless menu registered twice | STILL OPEN | `requestdesk-connector.php:309-310` and `admin/headless-settings-page.php:170`. |
| L6 | `sprintf` on shortcode text | FIXED on client installs (moot) | File is now CC-only (B1). Code unchanged at `class-requestdesk-audit-capture.php:168`. |
| L7 | `$_SERVER` unguarded in audit capture | FIXED on client installs (moot) | Same as L6. |
| L8 | Unescaped admin echoes | STILL OPEN | `admin/settings-page.php:115,118,127,134,141,476,482,516-518`. |
| L9 | Yoast `$identifier` dynamic property | CLOSED, not a defect | Yoast's `Abstract_Schema_Piece` declares `public $identifier;` (`wordpress-seo/src/generators/schema/abstract-schema-piece.php:34` on the LocalWP copy). |
| L10 | PHP 8.1 null-to-string beyond publish | STILL UNVERIFIED | Not re-run this week; needs a `WP_DEBUG` pass on PHP 8.2. |

Detail is given below only for the open items whose evidence or impact changed.

### B3 (BLOCKER, still open). The AEO settings form writes `yoast_mode = requestdesk` the first time a client admin saves it
`includes/class-requestdesk-yoast-schema.php:110-116` now returns `'yoast'` when the option is unset on a non-CC site. The settings page undoes that:

- `admin/aeo-settings-page.php:277` treats any value other than `yoast`/`off` (including unset) as `'requestdesk'`, so the radio at `:280` renders pre-checked "RequestDesk wins (recommended)" (`:281`).
- `admin/aeo-settings-page.php:32` saves `'requestdesk'` whenever the posted value is not `yoast` or `off`.
- `admin/aeo-settings-page.php:54` uses `'requestdesk'` as the fallback default.

What it breaks: a client admin who opens Settings -> AEO Settings and clicks save for any unrelated reason (for example to turn on the Q&A display) persists `requestdesk`. From then on `apply_organization()` (`class-requestdesk-yoast-schema.php:302-313`) and `apply_website()` (`:348-360`) overwrite Yoast's Organization and WebSite name and description with the WordPress site title and tagline, which is the original B3. Since 2.49.0 `/publish` also writes `_requestdesk_seo_title` and `_requestdesk_seo_description`, so in this mode RequestDesk titles replace the client's Yoast titles. The docblock at `class-requestdesk-yoast-schema.php:96` still calls `'requestdesk'` the default.

Smallest fix: in `admin/aeo-settings-page.php:32,54,277`, derive the unset default from `RequestDesk_Yoast_Schema::mode()` instead of the literal `'requestdesk'`, and drop "(recommended)" on non-CC sites. Correct the docblock at `:96`.

### B4 (re-rated MED, still open). Eight of the eleven SEO keys still have no writer
`/publish` now stores `_requestdesk_seo_title`, `_requestdesk_seo_description` and `_requestdesk_focus_keyphrase` (`class-requestdesk-api.php:1613-1631`). The readers of `_requestdesk_canonical_url` (`class-requestdesk-yoast-meta.php:143`), `_requestdesk_noindex` / `_requestdesk_nofollow` (`:152,155`), `_requestdesk_og_image` (`:194`) and `_requestdesk_twitter_title` / `_description` / `_image` (`:243,252,261`) still have writers only in `admin/seo-meta-boxes.php:440-467` and `includes/class-requestdesk-yoast-importer.php:24-41`, and neither file is in `$plugin_files` or `$cc_only_files`. The class doc at `class-requestdesk-yoast-meta.php:26` still credits the unloaded importer. The headless reader has the same dead branches (`class-requestdesk-headless-api.php:1040,1046,1057`).

What it breaks now: nothing visible to a reader. The cost is maintenance: about 40 lines of filter code that cannot fire, plus a doc that sends the next reader to a class the plugin never loads. Smallest fix: either add the four unloaded files to the load list, or delete the dead readers and the doc reference.

---

## Part 2. New findings

### BLOCKER for a client install

None new. B3 above is the one open BLOCKER.

### HIGH

#### N1. The homepage hero ships Content Cucumber's marketing copy as its defaults, and falls back to it when cleared
`requestdesk-connector.php:663-692` (activation seed), `includes/class-requestdesk-homepage-hero.php:37-66` (shortcode defaults), `admin/homepage-hero-settings-page.php:69-92` (admin defaults).

The 2.48.0 fix emptied the HubSpot ids and left the rest of CC's hero in place: headline `Humans<br>Writing<br>Content`, form heading "Let's write your success story!", an SEO paragraph that says "AI-powered content creation with human editors reviewing every piece", and terminal lines "No AI slop.", "Human writers standing by.", "All written by real humans." `class-requestdesk-homepage-hero.php:77-78` re-injects CC's terminal sequences whenever the saved list is empty, the same fallback pattern H1 removed from the stats bar. `requestdesk_clear_seeded_cc_defaults()` (`requestdesk-connector.php:247-283`) clears the portal, form and stats but not this copy.

What it breaks: a client who places `[requestdesk_homepage_hero]` publishes CC's positioning and a factual claim about how their content is produced (`:211` renders the headline, `:226-227` the SEO paragraph). This is the same class of defect as H1.

Smallest fix: neutral or empty defaults in all three places, remove the `:77-78` fallback, and extend `requestdesk_clear_seeded_cc_defaults()` to clear exact matches of the seeded headline, `seo_text` and terminal sequences. M10 (one `defaults()` per feature) would have prevented this.

#### N2. `/public/events` is unauthenticated on every install, exposes admin-only events, and every hit increments the metered headless counter
`includes/class-requestdesk-headless-api.php:233-264` registers `GET /requestdesk/v1/public/events` and `/public/events/{slug}` with `permission_callback => '__return_true'`. Both callbacks start with `self::increment_request_count()` (`:359`, `:412`), a raw `UPDATE` on `wp_options` (`:24-29`). The cron at `requestdesk-connector.php:523-576` posts that count to `/api/usage/headless-sync` every five minutes.

The justification at `:227-232` says this is "the same information the public /events/ pages already show." That is true only on site-module installs. On a client install `rd_event` is admin-only (`class-requestdesk-event.php:316-326`), so the plugin documents these events as unpublished on the web while serving them to anyone through this route. `get_events()` also loads every published event with `posts_per_page => -1` (`:375`) before slicing.

What it breaks: (1) on a client install, events the client created as admin-only records are readable by anyone without a key; (2) any script hitting the route writes to the database on every uncached request and inflates the usage number reported to RequestDesk for that site. The 60-second cache headers (`:341-348`) do not apply to direct origin hits.

Smallest fix: register the two `public/` routes only when `requestdesk_is_cc_site()` (the same gate the event CPT uses), and do not call `increment_request_count()` from unauthenticated routes.

### MEDIUM

#### N3. The AEO AJAX handlers kept the role-only check that H2 removed from REST
`includes/class-requestdesk-aeo-core.php:868-886` (`ajax_analyze_content`) and `:891-909` (`ajax_optimize_content`) check `current_user_can('edit_posts')` and then act on any `$_POST['post_id']`. `ajax_optimize_content()` calls `optimize_post($post_id, $force)` (`:902`), which writes the AEO row (`:119-125`) and accepts `force` to bypass the minimum-length guard (`:114`). The nonce is minted for anyone who can open `post.php` / `post-new.php` (`:914-926`), which includes Contributors.

What it breaks: a Contributor can re-run optimization, and the Claude spend it triggers, on any post or page on the site. Smallest fix: `current_user_can('edit_post', $post_id)` in both handlers, after reading `post_id`.

#### N4. The page templates present Content Cucumber's numbers as the client's, and the CSV import does not remove all of them
- Plain "Import Template" inserts the template verbatim as a draft (`admin/aeo-template-importer.php:441-456` and `:806-822`).
- `admin/aeo-template-about.php:129` ("With over 60,000 projects completed and a 4.9/5 client satisfaction rating"), `:273`, `:283`, `:339`, `:357`, `:363`; `admin/aeo-template-enhanced.php:38`, `:374`, `:526`, `:558`.
- The CSV path replaces exact strings with `str_replace` (`admin/aeo-template-importer.php:1065-1066`). The map has no key for the `about.php:129` sentence (grep for "What sets us apart" in the importer returns 0), its key at `:1041` covers only the first half of the `about.php:357` paragraph so "With over 60,000 projects completed" survives, and its key at `:1043` ends "projects." while `about.php:363` continues "projects across diverse industries", so it never matches.

What it breaks: a client page built from either template, even through the CSV path, can go live stating CC's project count and rating as the client's. Smallest fix: replace the figures in both template files with `[CUSTOMIZE: ...]` placeholders, which the importer already uses elsewhere.

#### N5. The comment link stripper's allowlist is RequestDesk's family of domains, not the client's own site
`includes/class-requestdesk-comment-link-stripper.php:112-116` allows only `contentcucumber.com`, `talk-commerce.com` and `requestdesk.ai`. `is_allowed_link_host()` (`:305-327`) never adds `home_url()`. When a commenter's Website field is stripped, or a name is replaced, the field is repointed to RequestDesk's explainer post (`:81`, `:221-226`, `:240`).

What it breaks, once a client ticks "Strip links from approved comments" (`:165-168`): a commenter's link to the client's own pages is deleted while a link to contentcucumber.com survives, and stripped comments on the client's site gain a link to requestdesk.ai. Both are filterable (`requestdesk_link_strip_allowlist`, `requestdesk_author_placeholder_link`), but a client has no UI for either. Smallest fix: default the allowlist to the host of `home_url()`, keep the vendor domains only when `requestdesk_is_cc_site()`, and default the placeholder link to `''` on client installs.

#### N6. Approving a comment can block for 30 seconds per comment on a synchronous Claude call
`maybe_strip_links()` runs on `transition_comment_status` (`class-requestdesk-comment-link-stripper.php:119`) and calls `maybe_apply_claude_pass()` (`:202`), which calls `strip_remaining_links()` (`:358`) through `make_request()` with `'timeout' => 30` (`class-requestdesk-claude-integration.php:30,55`). This runs whenever a Claude key is configured, whether or not the regex pass changed anything (`:339-340`).

What it breaks: a bulk approve of 20 comments in wp-admin can take up to 10 minutes in one request and hit `max_execution_time` partway, leaving some comments approved but not stripped. Each approved comment's text is also sent to Anthropic under the client's key, which the setting's label does not say. Smallest fix: run the Claude pass from `wp_schedule_single_event` after approval, or only when the regex pass found nothing and the comment contains a dot-TLD pattern.

#### N7. The comment REST endpoints export commenter email and IP
`format_comment_for_api()` (`includes/class-requestdesk-api.php:516-541`) returns `author_email` (`:528`) and `author_ip` (`:530`) for every comment from `GET /comments` (`:437-448`, up to 100 per page at `:549`) and `GET /comments/{id}`. Combined with M2, the key can be sent in the URL.

What it breaks: personal data about a client's commenters leaves the site in bulk to any key holder, which neither the moderation flow nor the translation flow in the route comments (`:430-436`, `:458-464`) needs. Smallest fix: drop `author_email` and `author_ip` from the payload, or return them only when a `include_pii=1` param is passed.

#### N8. A/B test counts lose updates under concurrent traffic
`record_event()` (`includes/class-requestdesk-ab-test.php:209-232`) reads `requestdesk_ab_test_counts`, increments in PHP, and writes the whole option back (`:224-229`). The route is anonymous (`:195`) and fires once per visitor session (`assets/js/ab-test.js`).

What it breaks: two events that arrive together both read N and both write N+1, so views and goals are undercounted, with no error, on exactly the high-traffic days a test is meant to measure. The class doc (`:26-29`) covers inflation but not loss. Smallest fix: a dedicated row per test/variant/type incremented with `UPDATE ... SET n = n + 1`, or `wp_cache_incr` with a periodic flush.

#### N9. `/image-alt` can blank a post's content if the regex fails
`set_image_alt()` assigns the result of `preg_replace_callback()` straight to `$content` (`includes/class-requestdesk-api.php:909-921`). On a PCRE error that is `null`. `null` goes into `WP_HTML_Tag_Processor` (`:924`), `get_updated_html()` (`:940`) then differs from the stored content, so `$changed` is true (`:960`) and `:963` writes it to `post_content` with a direct `$wpdb->update`, after one revision (`:962`). The comment stripper guards this exact case (`class-requestdesk-comment-link-stripper.php:396-400`); this route does not. I did not reproduce the PCRE failure, so the trigger rate is unknown; the missing guard is in the code.

Smallest fix: `if ($content === null) return new WP_Error('regex_failed', ..., array('status' => 500));` right after `:921`.

### LOW

#### N10. `uninstall.php` misses four options added since 2.48.0
The list at `uninstall.php:37-57` has no `requestdesk_ab_test_settings` or `requestdesk_ab_test_counts` (`class-requestdesk-ab-test.php:40-41`), no `requestdesk_ads_settings` (`class-requestdesk-asset-hub.php:34`), and no `requestdesk_cc_defaults_cleared` (`requestdesk-connector.php:251`). None holds a secret. Fix: add the four names.

#### N11. Deactivation and uninstall do not clear the per-post AEO jobs
`requestdesk_process_aeo_optimization` is scheduled with `array($post_id)` as its args (`class-requestdesk-aeo-core.php:83,95`). `wp_clear_scheduled_hook($hook)` with no args clears only events whose args are `array()`, so `requestdesk-connector.php:728-735` and `uninstall.php:26-33` leave every pending per-post job in cron. Fix: `wp_unschedule_hook('requestdesk_process_aeo_optimization')`.

#### N12. "Default post status" is saved but `/publish` ignores it
`admin/settings-page.php:44` saves `default_post_status`, rendered at `:441-447` as "Default status for posts created from RequestDesk". `publish_content()` hardcodes `?: 'draft'` (`class-requestdesk-api.php:1398`). The only other reader echoes it back in `/test-connection` (`:1014`). Fix: read the setting at `:1398`, or remove the control.

#### N13. The settings page documents a route that does not exist
`admin/settings-page.php:482` shows `POST requestdesk/v1/posts`; no such route is registered (`class-requestdesk-api.php:66-487`, the real one is `/publish` at `:159`). The example body uses `"post_status"` (`:491`); the route reads `status` (`:1398`). An admin who copies it gets a 404. Fix: point it at `/publish` and `status`.

#### N14. `verify_aeo_api_key()` can 500 on a malformed key
`class-requestdesk-aeo-core.php:777-780` passes `$request->get_param('api_key')` to `hash_equals()` without a string cast. `?api_key[]=x` makes it an array and PHP 8 throws a `TypeError`, a 500 instead of a 401. The headless and event verifiers cast (`class-requestdesk-headless-api.php:1119`, `class-requestdesk-event.php:89`). Fix: `(string)` cast, or `is_string()` check.

#### N15. The A/B HubSpot goal listener trusts any frame's `postMessage`
`assets/js/ab-test.js:108-109` counts a goal on any `message` event whose data looks like `hsFormCallback` / `onFormSubmitted`, without checking `e.origin`. Any third-party iframe on the front page (ads, embeds) can post that shape. Impact is limited to goal counts. Fix: require `e.origin` to end in `hsforms.com` or `hubspot.com`.

#### N16. Activation defaults for `requestdesk_settings` never land on a fresh install
At include time `requestdesk-connector.php:189` calls `requestdesk_is_cc_site()`. With no option present, `requestdesk_seed_site_modules_setting()` (`:60-84`) writes `requestdesk_settings` with only `enable_site_modules` (`:80-81`). The activation hook then runs `add_option('requestdesk_settings', ...)` (`:634-641`), which is a no-op because the option exists. Every reader has a `??` fallback (for example `class-requestdesk-claude-integration.php:17`), so nothing breaks today; the defaults block is dead code that reads as live. Fix: merge the defaults into the option in `requestdesk_activate()` with `wp_parse_args` and `update_option`.

---

## Part 3. Counts

New findings: BLOCKER 0, HIGH 2 (N1, N2), MED 7 (N3-N9), LOW 7 (N10-N16).

Still open from 2026-09-17, at current severity: BLOCKER 1 (B3), HIGH 6 (H3, H4, H6, H7, H8, H9), MED 13 (B4 re-rated, M1, M2, M4, M5, M6, M7, M8, M9, M10, M11b, M12, M13), LOW 6 (L1, L2, L3, L4, L5, L8), plus L10 still unverified.

2026-09-17 findings (40): 11 fixed (B1, B2, B5, B6, B7, B8, H1, H2, H5, plus L6 and L7, moot on client installs since the B1 gate), 2 closed as not defects after checking core and Yoast (M3, L9), 26 still open, 1 still unverified (L10). M11 is counted as open because half of it is.

---

## Part 4. Delta since 2026-09-17

**Fixed.** The 2.48.0 hardening (`f978961`, `8101073`) closed six of the eight BLOCKERs in one pass: audit capture is gated, CC's HubSpot ids and stats are gone from defaults and cleaned from existing installs, `rd_event` no longer claims `/events/` on clients, no pageview writes to the database, and the headless switch works. Object-level AEO REST permissions, deactivation cleanup and `uninstall.php` landed in the same commit. 2.49.0 (`629fa3c`) gave three of the Yoast meta keys a real writer.

**New.** Most new defects come from features added after the last audit: the A/B test module (N8, N15), the comment link stripper (N5, N6), the comment, image-alt and podcast endpoints (N7, N9), and the shared events module (N2). Two are leftovers of the September fix: it emptied the HubSpot ids but left CC's hero copy (N1), and it fixed the AEO REST check but not the AJAX twins (N3). B3 is back in practice because the settings form never learned the new default.

**Open longest (since 2026-09-17, 39 days, no change).** H3 and H4 (unbounded bulk and cron loops), H6 (`utm_source=contentcucumber`), H7 (literal PHP tags in templates, now confirmed in a second template), H8 (DDL per push), H9 (hooked constructors), and every MED except M3 and the half of M11 fixed in `1a0412b`.

### Top three to fix before the next client install
1. **B3.** Change three literals in `admin/aeo-settings-page.php:32,54,277` so saving the AEO tab cannot switch a client's Yoast site to "RequestDesk wins."
2. **N2.** Gate `/public/events` behind `requestdesk_is_cc_site()` and stop counting unauthenticated hits (`class-requestdesk-headless-api.php:233-264,359,412`).
3. **N1 with H6.** Strip CC's hero copy and its fallback (`class-requestdesk-homepage-hero.php:37-78`, `requestdesk-connector.php:663-692`) and CC's UTM source (`class-requestdesk-asset-hub.php:871`) in one pass, since both put Content Cucumber's name or voice on a client's public pages.
