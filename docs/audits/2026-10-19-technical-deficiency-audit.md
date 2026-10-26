# RequestDesk Connector, Technical Deficiency Audit

Repo: `/Users/brent/scripts/CB-Workspace/requestdesk-wordpress` (main, clean, HEAD `2ce48fe`, v2.63.0)
Scope: read-only. No files modified except this report, no git writes, no database access, no network writes, no WP-CLI.
Method: full read of `requestdesk-connector.php`, `uninstall.php`, `includes/class-requestdesk-{api,headless-api,aeo-core,yoast-schema,yoast-meta,yoast-schema-piece,event,comment-link-stripper,claude-integration,frontend-qa,homepage-hero,stats-bar,indexnow,push,asset-hub,audit-capture}.php` and the changed regions of the rest of `includes/`, `admin/{settings-page,aeo-settings-page,headless-settings-page,homepage-hero-settings-page,stats-bar-settings-page,aeo-meta-boxes,aeo-template-enhanced,aeo-template-importer}.php`, `tools/events/import-events.php`; `git log --oneline v2.47.0..HEAD` (29 commits) and `git diff v2.47.0..HEAD` for every changed file; repo-wide greps for every "never called / never written / never read" claim, every `permission_callback`, every `wp_ajax_nopriv`, and every Content Cucumber, Talk Commerce or staff string in a file a client install loads; `unzip -l` of the newest release zip.

Context assumed throughout: a **client install** = site modules OFF (`requestdesk_is_cc_site()` false), stock theme, Yoast SEO active. Content Cucumber is the one site where CC-specific paths look correct.

Severity counts: **BLOCKER 1, HIGH 9, MEDIUM 19, LOW 17** (46 findings, 23 carried over from 2026-09-17, 23 new).

---

## BLOCKER for a client install

### B1. Saving the AEO settings tab switches a client to "RequestDesk wins", which overwrites the client's Yoast Organization and WebSite identity
**`admin/aeo-settings-page.php:32`**, **`:54`**, **`:277-281`**; consumer **`includes/class-requestdesk-yoast-schema.php:302-339`** (`apply_organization`) and **`:349-361`** (`apply_website`).

2.48.0 fixed the 09-17 B3 in one place only: `RequestDesk_Yoast_Schema::mode()` now returns `'yoast'` on a non-CC install when nothing is stored (`class-requestdesk-yoast-schema.php:105-115`). The settings form never learned that:

- `:277` computes the radio state as `in_array($settings['yoast_mode'], ['yoast','off']) ? ... : 'requestdesk'`, so an unset value renders **"RequestDesk wins (recommended)"** pre-checked (`:280-281`).
- `:32` saves `'requestdesk'` for any POST that is not `yoast` or `off`.
- `:54` (the fallback array) also says `'requestdesk'`.

So a client admin who opens Settings, AEO tab, and saves any unrelated setting (turning off FAQ schema, changing a title) writes `yoast_mode = requestdesk`. From then on `requestdesk_wins()` is true and `apply_organization()` replaces Yoast's configured `Organization.name` and `description` with the WordPress site title and tagline (`:310-315`, via `generate_organization_schema()`), and `apply_website()` does the same to the WebSite node (`:353-358`). That is the exact 09-17 B3 damage, now one routine Save away, with the UI calling it recommended. `RequestDesk_Yoast_Meta` also begins replacing Yoast's per-post title and description with `_requestdesk_seo_*` values (`class-requestdesk-yoast-meta.php:113-136`).

What it breaks: a client's legal company name and written company description in Yoast silently become "Site Title" and, on many sites, "Just another WordPress site", with no notice.

Smallest fix: in `admin/aeo-settings-page.php`, derive the pre-checked radio from `RequestDesk_Yoast_Schema::mode()` instead of the literal fallback at `:277`, change `:32` to keep the stored value (or `RequestDesk_Yoast_Schema::mode()`) when no valid radio is posted, change `:54` to omit `yoast_mode`, and drop "(recommended)" from `:281` on non-CC installs.

---

## HIGH

### H1. `/publish` without a `post_id` silently overwrites any existing post with the same slug, and reports a create
`includes/class-requestdesk-api.php:1519-1535`, response `:1691`.

The create path computes `$check_slug = $slug ?: sanitize_title($title)` (`:1521`), looks up a `post` with that `name` in **any** status including `publish` (`:1522-1527`), and if one exists calls `wp_update_post($post_data)` on it (`:1532`): title, content, status, excerpt, date and author are replaced. The response is still `201` with no `created`/`updated` flag (`:1691`). On a client install the existing post may be the client's own article, written in wp-admin, that happens to share a title with a RequestDesk piece ("Contact Us", "Pricing", "About"). Revisions are the only recovery, and a site with `WP_POST_REVISIONS` off has none. Fix: on a slug collision without an explicit `post_id`, either return `409` naming the existing ID, or only adopt a post that carries `_requestdesk_ticket_id` (i.e. one RequestDesk created); add `'action' => 'created'|'updated'` to the response.

### H2. Comment Link Stripper writes model output into comments with no sanitizing (stored XSS path when enabled)
`includes/class-requestdesk-comment-link-stripper.php:201-203`, `:233-245`, `:348-378`; `includes/class-requestdesk-claude-integration.php:313-347`.

When `strip_comment_links` is on and a Claude key is set, the commenter's text goes to Claude (`:358`) and Claude's reply becomes `comment_content` via `wp_update_comment()` (`:244`). The only guard is "not more than 10 bytes longer than the input" (`:367`). `wp_update_comment()` runs `pre_comment_content` kses only for a user without `unfiltered_html`; the moderator clicking Approve in wp-admin is an Administrator or Editor, who has it on a single site, so the reply is stored unfiltered, and WordPress does not kses comments on output. A commenter controls the prompt input, so a comment written as instructions ("return this text with X replaced by a script tag") can produce markup that is stored the moment a moderator approves it. Fix: run `wp_kses($final, wp_kses_allowed_html('pre_comment'))` (or `wp_filter_kses`) before `:233`, and reject the Claude result outright if it contains `<` where the regex-pass input did not.

### H3. The keyless `/public/events` routes write to `wp_options` on every anonymous call and inflate the usage count reported to RequestDesk
`includes/class-requestdesk-headless-api.php:233-266` (`permission_callback => '__return_true'`), `:359` and `:412` (`self::increment_request_count()`), `:24-29` (raw `UPDATE {$wpdb->options}`), `:372-377` (`posts_per_page => -1`); sync at `requestdesk-connector.php:509-562`.

These routes are registered on every install whose headless switch is on (the default), including clients with no events. Each anonymous request runs an `UPDATE` on the options table, and the 5-minute cron then posts the accumulated count to `app.requestdesk.ai/api/usage/headless-sync` under the client's key. A bot looping on `/wp-json/requestdesk/v1/public/events` produces one database write per request and an arbitrary usage figure for the client; the 60-second `Cache-Control` (`:341-348`) only helps where a CDN honors it. On a client install `rd_event` is deliberately admin-only (`class-requestdesk-event.php:316-326`), yet these routes still publish any `publish`-status event to anyone, which contradicts the comment at `:226-231` that they repeat "the public /events/ pages". Fix: do not count `/public/` requests (drop `increment_request_count()` when `get_route()` starts with `/requestdesk/v1/public/`), register the two public routes only when `requestdesk_is_cc_site()`, and cap the query at `per_page`.

### H4. Content Cucumber's hero copy is still seeded into every install and is the shortcode default
`requestdesk-connector.php:648-679`, `includes/class-requestdesk-homepage-hero.php:37-66`, `admin/homepage-hero-settings-page.php:69-99`, rendered at `class-requestdesk-homepage-hero.php:211` and `:227`.

2.48.0 emptied the HubSpot ids and the stats, but left the rest of Content Cucumber's hero: headline "Humans Writing Content", form heading "Let's write your success story!", the SEO paragraph "Humans in the loop... human editors reviewing every piece", and the terminal lines "No AI slop.", "Human writers standing by.", "All written by real humans." A client who places `[requestdesk_homepage_hero]` publishes CC's positioning as their own claims, the same defect class as 09-17 H1. `requestdesk_clear_seeded_cc_defaults()` (`requestdesk-connector.php:243-279`) does not clear these. Fix: default `headline`, `form_heading`, `seo_text` and `terminal_sequences` to empty in all three places and render nothing when `headline` is empty; extend the one-time cleanup to exact matches of these strings.

### H5. Bulk operations load every post and make network calls inside one request (carried, 09-17 H3)
`includes/class-requestdesk-push.php:226` (`-1`), loop `:230-241`; `admin/aeo-settings-page.php:1033`, `:1075`, `:1097`, `:1119`. Unchanged. On a large client site these time out mid-loop with partial writes and no result. Fix: batch with an offset carried in the redirect, or `wp_schedule_single_event` per batch.

### H6. Daily cron monitors process the whole site in one request (carried, 09-17 H4)
`includes/class-requestdesk-citation-tracker.php:354`, `includes/class-requestdesk-freshness-tracker.php:399`. Unchanged. Fix: cap `posts_per_page` at a batch size; the `NOT EXISTS` meta query already resumes.

### H7. Literal PHP tags written into imported page content (carried, 09-17 H7)
`admin/aeo-template-enhanced.php:43` opens a nowdoc; `:52`, `:53`, `:56` still contain `<?php echo ... ?>`. Unchanged. Fix: heredoc with interpolation, or `str_replace` placeholders.

### H8. `CREATE TABLE` on every push (carried, 09-17 H8)
`includes/class-requestdesk-push.php:355`. Unchanged; `requestdesk_activate()` (`requestdesk-connector.php:571-617`) still creates only two of the three tables. Fix: move the DDL into activation.

### H9. Constructors register hooks, so data-only use duplicates them (carried, 09-17 H9)
Hooked `new RequestDesk_AEO_Core()` remains at `includes/class-requestdesk-aeo-core.php:943`, `class-requestdesk-push.php:392`, `class-requestdesk-frontend-qa.php:147`, `:375`, `:457`, `admin/aeo-meta-boxes.php:58`, `:199`, `:503`, `admin/aeo-settings-page.php:68`, `:710`, `:1036`, `admin/aeo-bulk-optimizer.php:64`. Only `class-requestdesk-yoast-schema.php:202` passes `false`. The theme helpers still do `new RequestDesk_Frontend_QA()` (`class-requestdesk-frontend-qa.php:485`, `:503`, `:521`), each adding another `the_content` filter. Fix: pass `false` at data-only sites or make both classes singletons.

---

## MEDIUM

### M1. Main API key compared with `!==` (carried, 09-17 M1)
`includes/class-requestdesk-api.php:2069`. The AEO, event and headless verifiers all use `hash_equals` (`class-requestdesk-aeo-core.php:780`, `class-requestdesk-event.php:89`, `class-requestdesk-headless-api.php:1119`); the one guarding `/publish`, `/comments`, `/post-author`, `/image-alt` and `/podcast-meta` does not. Fix: `hash_equals($api_key, (string) $provided_key)`.

### M2. API keys accepted as a URL query parameter (carried, 09-17 M2, wider now)
`includes/class-requestdesk-api.php:2066`, `class-requestdesk-aeo-core.php:777`, `class-requestdesk-headless-api.php:1114`. Query strings land in access logs and `Referer`. Fix: header only.

### M3. Claude connection test persists the candidate key with no rollback guarantee (carried, 09-17 M4)
`requestdesk-connector.php:776-787`. Unchanged. Fix: `try/finally`, or pass the key into `test_connection()`.

### M4. Headless counter written with raw SQL, read through the object cache (carried, 09-17 M5)
`includes/class-requestdesk-headless-api.php:24-29`, `requestdesk-connector.php:513` and `:558-561`. Unchanged; H3 makes it hotter. Fix: delete the `options`/`alloptions` cache entries after each raw write, or move the counter out of options.

### M5. AJAX schema preview has no capability check (carried, 09-17 M6)
`admin/aeo-meta-boxes.php:852-879`. Unchanged. Fix: `current_user_can('edit_post', $post_id)` after `:859`.

### M6. AEO AJAX handlers still authorize by role, not by post (remainder of 09-17 H2)
`includes/class-requestdesk-aeo-core.php:869-909` (`edit_posts`, then any `$_POST['post_id']`), `includes/class-requestdesk-freshness-tracker.php:557-580` (`ajax_mark_fresh` runs `wp_update_post` on any ID), `includes/class-requestdesk-citation-tracker.php:453-467`. The nonce they check is minted for anyone who can open `post-new.php` (`class-requestdesk-aeo-core.php:914-928`), which includes Contributors. A Contributor can rerun optimization on any post (two Claude calls each, and its FAQ data rewritten), or bump any post's modified date, which also fires `save_post`, IndexNow and RequestDesk push hooks. 2.48.0 fixed the REST callback (`:734-758`) but not these siblings. Fix: replace `edit_posts` with `edit_post, $post_id` in all five handlers.

### M7. FAQPage schema is generated and printed on a client's posts with no opt-in
Activation defaults `auto_optimize_on_publish => true`, `generate_faq_schema => true` (`requestdesk-connector.php:632-634`); `publish_post` schedules optimization (`class-requestdesk-aeo-core.php:30`, `:90-97`); `optimize_post()` extracts Q&A from the body and stores `faq_data` (`:185-221`); emitted standalone at `:418-432` or into Yoast's graph at `class-requestdesk-yoast-schema.php:187-224` (the safe `'yoast'` mode still adds it, `:105-108`). A client who installed the connector to receive RequestDesk posts gets FAQPage JSON-LD added to every published post that has question-shaped headings, a structured-data change they never chose. Fix: default `auto_optimize_on_publish` and `generate_faq_schema` to false when `!requestdesk_is_cc_site()`.

### M8. "Auto-optimize on update" is ignored: every edit of a published post re-optimizes it
`includes/class-requestdesk-aeo-core.php:30` hooks `publish_post`, and `:90-97` schedules optimization whenever `auto_optimize_on_publish` is on. WordPress fires `publish_post` on every save of a post whose new status is `publish` (`wp_transition_post_status` fires `{$new_status}_{$post_type}` unconditionally), not only on first publish. So `auto_optimize_on_update` (default false, `requestdesk-connector.php:633`; honored only in `handle_post_save`, `:82-84`) has no effect: each update of a published post queues `optimize_post()`, which with a Claude key makes two API calls (`class-requestdesk-content-analyzer.php:43-44`, `:77-78`). Fix: in `handle_post_publish()`, accept the third `$old_status` argument and return when it is `'publish'`.

### M9. Unslashed data passed to core write functions, so backslashes are stripped
`includes/class-requestdesk-api.php:1513`, `:1532`, `:1539` (`wp_update_post`/`wp_insert_post` with raw `$post_data`), `:625` (`wp_update_comment` from `/comments/{id}`), `includes/class-requestdesk-comment-link-stripper.php:244`. All of these core functions `wp_unslash()` their input. Published content containing a backslash (code samples, JSON, regex, Windows paths, LaTeX) loses it. `upsert_event()` does this correctly with `wp_slash()` (`:2014`, `:2016`), which shows the convention is known. Fix: wrap each array in `wp_slash()`.

### M10. Comment Link Stripper allowlists Content Cucumber's and Talk Commerce's domains on client sites, not the client's own
`includes/class-requestdesk-comment-link-stripper.php:112-116` (`contentcucumber.com`, `talk-commerce.com`, `requestdesk.ai`), used at `:220`, `:311`, `:358`; placeholder link `:81`, applied at `:221-241`. With the feature on, a client's commenter linking to the client's own article loses the link, while a comment linking to contentcucumber.com keeps it, and every flagged commenter's Website field is repointed to a requestdesk.ai blog post, an outbound vendor link inserted into the client's pages. Off by default, so not a blocker. Fix: default the allowlist to `wp_parse_url(home_url(), PHP_URL_HOST)` (plus the CC/TC/RD domains only when `requestdesk_is_cc_site()`), and default the placeholder link to `''` on non-CC installs.

### M11. Activation defaults for `requestdesk_settings` are never written on a fresh install
`requestdesk-connector.php:185` calls `requestdesk_is_cc_site()` at file load, which on a fresh install runs `requestdesk_seed_site_modules_setting()` (`:46`) and `update_option('requestdesk_settings', ['enable_site_modules' => false])` (`:80-81`). WordPress includes the plugin file before firing the activation hook, so by the time `requestdesk_activate()` runs `add_option('requestdesk_settings', [...])` (`:620-627`) the option exists and `add_option` is a no-op. `default_post_status`, `claude_model`, `allowed_post_types` and `debug_mode` are never stored; this is the origin of the fresh-install undefined-key warnings 2.47.1 patched at the read sites. Fix: in `requestdesk_activate()`, merge defaults into the existing value with `update_option(..., array_merge($defaults, (array) get_option(...)))`.

### M12. Audit Capture is now dead on every site, Content Cucumber included
`requestdesk-connector.php:165` loads `class-requestdesk-audit-capture.php` on CC only, and commit `f978961` removed `'RequestDesk_Audit_Capture'` from `$aeo_classes` (`:328-341`). The file does not self-instantiate (its constructor `:42-48` registers the shortcode, CPT and route), and a grep of this repo and of `wordpress-sites/` finds no `new RequestDesk_Audit_Capture`. On Content Cucumber the audit landing shortcode now renders as literal text and `cc-audit/v1/request` no longer exists. Not a client defect, but the 09-17 B1 fix removed the feature rather than gating it. Fix: add `'RequestDesk_Audit_Capture'` back to `$aeo_classes` (the `class_exists` guard keeps it off client installs).

### M13. The release zip ships the Talk Commerce server's deploy notes, and no zip exists after 2.48.0
`unzip -l plugin-releases/requestdesk-connector-v2.48.0.zip` lists `DEPLOY.md` and `sync-all.sh`. `DEPLOY.md:12` and `:37` name the Lightsail IP, instance and container, and the SSH key file name; on a client install the file is web-readable at `/wp-content/plugins/requestdesk-connector/DEPLOY.md`. `plugin-releases/` has nothing newer than 2.48.0 while the plugin is at 2.63.0, so a client installing from a zip gets a build missing 15 releases of fixes. Fix: exclude `DEPLOY.md`, `sync-all.sh`, `docs/`, `tests/`, `tools/`, `todo/`, `.claude/`, `.impeccable/` from the zip, and cut a 2.63.0 zip.

### M14. "Import Default AEO Homepage Template" inserts Content Cucumber's stats and sales rep's booking link
`admin/aeo-template-importer.php:441` inserts `requestdesk_get_aeo_template_content()` unmodified (no CSV replacement pass). That template contains "60,000+ projects delivered", "55M+", "4.9/5" (`admin/aeo-template-enhanced.php:38`, `:374`, `:526`, `:542`, `:558`) and a button to `https://meetings.hubspot.com/isaac-morey/meeting` (`:657`). The CSV path replaces both (`aeo-template-importer.php:727-741`); the one-click path does not. Saved as a draft, so a client has to publish it, but the copy is CC's claims and the button books a meeting with CC staff. Fix: replace the stats with `[CUSTOMIZE]` placeholders and the meeting URL with `#contact` in the template itself.

### M15. Two options are read but have no writer (carried, 09-17 M8)
`includes/class-requestdesk-comparison-table.php:38`, `includes/class-requestdesk-schema-generator.php:1290`. Unchanged.

### M16. `RequestDesk_Post_Handler` is dead and loaded on every install (carried, 09-17 M9)
`requestdesk-connector.php:90`; the class name appears nowhere else. Unchanged. Fix: delete it.

### M17. Feature defaults written out three times (carried, 09-17 M10)
Hero: `requestdesk-connector.php:648-679`, `class-requestdesk-homepage-hero.php:36-66`, `admin/homepage-hero-settings-page.php:68-99`. AEO DDL: `requestdesk-connector.php:598-615`, `admin/settings-page.php:15-32`. H4 is the current cost of this. Fix: one `defaults()` per feature.

### M18. Push loop reads keys that may not exist (carried, 09-17 M11, push half)
`includes/class-requestdesk-push.php:232-238` still reads `$result['success']` on a `false` or `skipped` result. The publish half is effectively fixed: `$ticket_id`/`$agent_id` are now assigned at `class-requestdesk-api.php:1399-1400`, before anything that can throw. Fix: `is_array($result) && !empty($result['success'])`.

### M19. Combined settings page executes every tab, and the default OG image setting does nothing on Yoast sites (carried, 09-17 M12 and M13)
`requestdesk-connector.php:416-440` (buffer and regex-strip), unchanged. `admin/settings-page.php:676` writes `default_og_image`; `RequestDesk_Yoast_Meta` still never reads it (`class-requestdesk-yoast-meta.php:194`). Fix as in 09-17.

---

## LOW

### L1. Nonce read from superglobals without `isset` (carried, 09-17 L1)
`requestdesk-connector.php:760`, `:769`; `admin/aeo-meta-boxes.php:811`, `:854`. The partner, asset-hub and audit-capture instances are CC-only now.

### L2. Nonce checked before capability (carried, 09-17 L2)
`requestdesk-connector.php:758-767`.

### L3. Two key generators for keys of equal power (carried, 09-17 L3)
`requestdesk-connector.php:746` (`random_bytes`), `admin/headless-settings-page.php:23` (`wp_generate_password`). Both keys now open every headless route (`class-requestdesk-headless-api.php:1084-1092`).

### L4. Empty hook callbacks (carried, 09-17 L4)
`includes/class-requestdesk-aeo-core.php:31`/`:935-937`, `includes/class-requestdesk-frontend-qa.php:20`/`:34-36`.

### L5. Headless admin menu registered twice (carried, 09-17 L5)
`admin/headless-settings-page.php:170`, `requestdesk-connector.php:305-307`.

### L6. Unescaped echoes in admin output (carried, 09-17 L8)
`admin/settings-page.php:115`, `:118`, `:127`, `:134`, `:141`, `:476`, `:482`.

### L7. Featured-image fetch, residual SSRF (09-17 M3, downgraded)
`includes/class-requestdesk-api.php:1852`. `download_url()` fetches through `wp_safe_remote_get()`, whose `wp_http_validate_url()` already refuses loopback and RFC 1918 hosts and non-standard ports, so the 09-17 description overstated it. What remains is any range core does not list (link-local `169.254.0.0/16`, where cloud metadata lives); the fetch is blind and the result must parse as an image. Verify against the WordPress version in use. Fix: reject resolved link-local addresses before `download_url`.

### L8. The iframe host allowlist is bypassed by an iframe with no closing tag
`includes/class-requestdesk-api.php:52-61` only matches `<iframe ...>...</iframe>`; `allow_embed_iframes()` (`:37-47`) then lets kses keep any `<iframe src="https://any-host">` that lacks `</iframe>` (and permits `style`). API-key gated, so the caller is already trusted to publish, but the allowlist is the stated control. Fix: run the host check inside a `wp_kses_allowed_html` attribute filter, or match `<iframe\b[^>]*>` alone.

### L9. Uninstall leaves some of the plugin's own data
`uninstall.php:37-59` omits `requestdesk_cc_defaults_cleared` (written at `requestdesk-connector.php:247`); `:68-81` omits `_requestdesk_aeo_last_update`, `_requestdesk_citation_count`, `_requestdesk_freshness_data`, `_requestdesk_last_synced`, `_requestdesk_manual_qa_pairs`; comment meta `_requestdesk_links_stripped_*` and `_requestdesk_author_*_original` are not addressed.

### L10. An "Events" admin menu with Talk Commerce placeholders appears on every client
`includes/class-requestdesk-event.php:321-322` (`show_ui`, `show_in_menu` true regardless of site modules); placeholders `:214` ("Brent Peterson and Isaac Morey") and `:265` ("Brent and Isaac will be at Shoptalk Fall..."). Fix: `show_in_menu => $public`, neutral placeholders.

### L11. IndexNow key file never matches on a subdirectory install
`includes/class-requestdesk-indexnow.php:81` compares the request path to `'/' . $key . '.txt'`, while `get_key_file_url()` (`:91-93`) advertises `home_url('/' . $key . '.txt')`. With WordPress in `/blog`, the advertised URL 404s and IndexNow rejects submissions. Fix: compare against `wp_parse_url($this->get_key_file_url(), PHP_URL_PATH)`. `get_key()` (`:65-72`) can also write the option on an anonymous request before any admin visit.

### L12. JSON-LD printed with `JSON_UNESCAPED_SLASHES` and without `JSON_HEX_TAG`
`includes/class-requestdesk-aeo-core.php:413`, `:427-430`; `class-requestdesk-schema-generator.php:1371`. A `</script>` inside a stored FAQ answer would close the block. I found no current source of a literal `</script>` (manual answers pass `wp_kses_post`, `:819`), so this is hardening. Fix: `wp_json_encode($x, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES)`.

### L13. Stale or false text in code and admin copy
`admin/settings-page.php:482` documents `POST requestdesk/v1/posts` (no such route) with `post_status` (the arg is `status`); `admin/settings-page.php:364` Debug Mode copy says it "disables API key validation", which no verifier implements (`class-requestdesk-api.php:2052-2077`); `class-requestdesk-yoast-meta.php:5-6` still calls `'requestdesk'` "the default"; `requestdesk_activation_complete` is written (`requestdesk-connector.php:701`) for an "auto-updater" that does not exist.

### L14. `/aeo-status` loads every published post, then paginates in PHP
`includes/class-requestdesk-aeo-core.php:627-635` (no `LIMIT`), `:693`.

### L15. Unverified: `RequestDesk_Yoast_Schema_Piece::$identifier` (carried, 09-17 L9)
`includes/class-requestdesk-yoast-schema-piece.php:34`. Still cannot be checked without Yoast source in the repo.

### L16. Unverified: PHP 8.1 null-to-string beyond the publish path (carried, 09-17 L10)
`admin/aeo-template-importer.php` and the analyzer/detector were again read only in part.

### L17. Unverified: auto-approved comments may never reach the link stripper
The docblock (`class-requestdesk-comment-link-stripper.php:11-12`, `:171-172`) and the settings copy say an Akismet or returning-commenter auto-approval is covered. The hook is `transition_comment_status` (`:119`). **Checked:** every call path inside this repo. **Remains unverified:** whether core's `wp_insert_comment()` fires `transition_comment_status` for a comment inserted already approved; if it does not, those comments keep their links. Confirm on a test site before relying on the claim.

---

## The five to fix before a client install, in order

1. **B1: make the AEO settings form respect `RequestDesk_Yoast_Schema::mode()`.** Three lines in `admin/aeo-settings-page.php` (`:32`, `:54`, `:277`). Until then the 09-17 B3 fix holds only until a client saves the AEO tab once.
2. **H1: stop `/publish` from adopting an unrelated post by slug.** `class-requestdesk-api.php:1519-1535`. A RequestDesk article can overwrite a client's own published post and report a create.
3. **H3: stop counting and serving `/public/events` on client installs.** Anonymous writes per request, plus a usage figure anyone can inflate.
4. **H4 + M14: finish removing Content Cucumber's copy.** Hero text in three places, stats and Isaac Morey's booking link in the one-click template.
5. **M7 + M8: make AEO opt-in on client installs and stop re-optimizing on every edit.** Two default flips and a one-line `$old_status` check.

**Next, before the install is called clean:** H2 (sanitize model output before it becomes a comment), M6 (object-level checks on the AEO AJAX handlers), M9 (`wp_slash` on core writes), and M13 (strip `DEPLOY.md` from the zip and cut a current release).

---

## Delta vs 2026-09-17

The 09-17 audit (v2.47.3) had 40 findings. Since then: 29 commits, v2.47.3 to v2.63.0. The fixes landed almost entirely in `f978961` and `8101073` (2.48.0, same day as the audit). Nothing from the 09-17 MEDIUM or LOW lists was fixed in the 28 commits after that.

**Tally: 8 fixed, 2 no longer reachable on a client, 2 withdrawn as incorrect, 5 changed (one of them reopened as the new B1), 23 still open.**

### Fixed
| 09-17 | Proof |
|---|---|
| B1 Unauthenticated audit-capture endpoint | `f978961`; file moved to `$cc_only_files`, `requestdesk-connector.php:165`. (Over-fixed: see new M12.) |
| B2 Audit Capture CC copy on clients | `f978961`; same line. |
| B5 `rd_event` claims `/events/` | `f978961`; `class-requestdesk-event.php:316-326` (public, archive and rewrite only when `requestdesk_is_cc_site()`); loader comment corrected `requestdesk-connector.php:128-132`. |
| B6 CC HubSpot portal/form | `f978961` defaults emptied (`requestdesk-connector.php:652-653`, `class-requestdesk-homepage-hero.php:40-44`, `admin/homepage-hero-settings-page.php:72-74`, `admin/aeo-template-enhanced.php:153-158`, `admin/aeo-template-importer.php:2654-2715`); `8101073` clears stored values on existing installs (`requestdesk-connector.php:243-268`). |
| B7 Database write on every pageview | `f978961`; `get_aeo_data_readonly()` `class-requestdesk-aeo-core.php:347-367`, used at `:423` and `:443`. |
| B8 Headless switch ignored | `f978961`; early return `class-requestdesk-headless-api.php:38-41`. |
| H1 CC stats seeded | `f978961`/`8101073`; `requestdesk-connector.php:685-692`, `class-requestdesk-stats-bar.php:31-48`, cleanup `requestdesk-connector.php:270-278`. |
| H5 Orphan cron hooks, no uninstall | `f978961`; `requestdesk-connector.php:711-723` clears all four hooks; `uninstall.php` added. Gaps remain as new L9. |

### No longer reachable on a client install
| 09-17 | Status |
|---|---|
| L6 `sprintf` on shortcode text | Code unchanged (`class-requestdesk-audit-capture.php:168`), file CC-only since `f978961`. |
| L7 `$_SERVER` read without guard | Same file, same status. |

### Withdrawn (the 09-17 finding was wrong)
| 09-17 | Why |
|---|---|
| H6 `utm_source=contentcucumber` on client clicks | `RequestDesk_Asset_Hub` is instantiated only when `requestdesk_is_cc_site()` (`class-requestdesk-asset-hub.php:988-990`), and has been since `5923455` (2026-06-24), so the code never runs on a client. The 09-17 audit cited `:989` without the guard on `:988`. The literal is still at `:871` if the gate is ever flipped. |
| M7 Unauthenticated impression beacon | Same gate; the `wp_ajax_nopriv` hook is registered only inside that constructor (`:89`). |

### Changed
| 09-17 | Now |
|---|---|
| B3 Default Yoast mode overwrites client identity | Fixed in `RequestDesk_Yoast_Schema::mode()` (`f978961`, `class-requestdesk-yoast-schema.php:105-115`), **reopened** through the settings form. Now **B1**. |
| B4 Yoast meta keys have no writer | No longer a blocker: inert on a client while the mode is `'yoast'`. `/publish` now writes `_requestdesk_seo_title` and `_requestdesk_seo_description` (`629f3c1`, 2.49.0; `class-requestdesk-api.php:1613-1630`). The other nine keys still have no loaded writer; `admin/seo-meta-boxes.php`, `includes/class-requestdesk-seo-core.php`, `includes/class-requestdesk-yoast-importer.php` and `admin/yoast-import-page.php` are still absent from `$plugin_files`. Becomes live again whenever B1 flips the mode. |
| H2 AEO endpoints authorize by role | REST callback fixed (`f978961`, `class-requestdesk-aeo-core.php:734-758`). The AJAX siblings were not: now **M6**. |
| M3 Featured-image SSRF | Overstated; core already blocks loopback and RFC 1918. Downgraded to **L7**. |
| M11 Error paths read undefined variables | Publish half effectively fixed by assignment order (`class-requestdesk-api.php:1399-1400`); push half open as **M18**. |

### Still open (23)
H3 (now H5), H4 (now H6), H7 (H7), H8 (H8), H9 (H9); M1 (M1), M2 (M2, now also in the AEO verifier), M4 (M3), M5 (M4), M6 (M5), M8 (M15), M9 (M16), M10 (M17), M12 and M13 (M19); L1 (L1), L2 (L2), L3 (L3), L4 (L4), L5 (L5), L8 (L6), L9 (L15, unverified), L10 (L16, unverified).

### New in this audit (23)
B1 (reopened B3); H1, H2, H3, H4; M6 (H2 remainder), M7, M8, M9, M10, M11, M12, M13, M14; L7 (downgraded M3), L8, L9, L10, L11, L12, L13, L14, L17. Of these, H1, H3, M9, L8 and the slashing in `/comments` arrived with the 2.48.1 to 2.63.0 API work; H2, M10 and L17 arrived with the Comment Link Stripper (2.51.0 to 2.57.0); H3's public routes arrived with the 2.63.0 Content Cucumber merge.

### Open longest
**M1, the timing-unsafe compare on the main API key** (`class-requestdesk-api.php:2069`), together with M2 (`:2066`, key accepted as a query parameter) and M16 (the dead `RequestDesk_Post_Handler`). `git blame` dates all three to `1cf5a66` (2025-10-19, the v2.0.0 import), a year ago today, and all were written up on 2026-09-17. M1 is the one that matters: it guards every write route, and the other three verifiers in the same plugin already use `hash_equals`, so the fix is one line.
