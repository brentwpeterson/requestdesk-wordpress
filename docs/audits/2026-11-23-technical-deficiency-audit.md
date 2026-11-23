# 2026-11-23 delta technical deficiency audit (927c2d3..82565a7)

Brent-start step 6.96 (row t2971), Monday run. Last week's full audit is `2026-11-16-technical-deficiency-audit.md`; the 2026-11-19 delta covered 2.67.0-2.68.1 (Offers). This pass covers only the three commits shipped since: cd2f039 (2.69.0 glossary terms, `/glossary` upsert route, `requestdesk_content_post_types` filter), 9ce5002 (2.69.1 offer category pills from live offers only), 82565a7 (meta box help text spelling). Line references are to 82565a7.

**Scope and method.** Read-only review of `git diff 927c2d3..82565a7`: 6 files, +439/-13. `includes/class-requestdesk-glossary.php` (new, 259 lines) read in full; the `/glossary` route and `upsert_glossary` in `includes/class-requestdesk-api.php:330-352` and `:2188-2304`; the filter change in `includes/class-requestdesk-headless-sites.php:1029-1035`; the offer listing change in `includes/class-requestdesk-offer.php:854-886`; the loader in `requestdesk-connector.php`.

**Client-install context.** `class-requestdesk-glossary.php` is in `$cc_only_files` (`requestdesk-connector.php:204`), so the post type, taxonomy, meta box and `/aeo-glossary/` rewrite load on Content Cucumber only. `class-requestdesk-api.php` is shared (`requestdesk-connector.php:88`), so the `/glossary` route is registered on every install and answers 501 there.

## Checked and clean

- **`/glossary` route is key-gated.** `permission_callback => verify_api_key` (`class-requestdesk-api.php:333`). No `__return_true`.
- **Client installs get no glossary write path.** `upsert_glossary` returns 501 when `RequestDesk_Glossary` is not loaded or the post type does not exist (`:2189-2191`).
- **Input is sanitized and whitelisted.** Unknown meta keys refused with 400 (`:2201-2204`); `status` limited by the route enum; content through `wp_kses_post`, excerpt through `sanitize_textarea_field`; each field through `sanitize_value` by type (date regex, lines, textarea, text).
- **Draft lookup does not duplicate.** Statuses are named explicitly so an unauthenticated by-slug lookup still finds drafts (`:2213`).
- **Result is read back, not trusted.** Saved meta and slug compared to the request; mismatches returned (`:2283-2294`).
- **Meta box save** checks the nonce, skips autosave, checks `edit_post` (`class-requestdesk-glossary.php` `save_meta_box`). Admin output escaped with `esc_attr` / `esc_html` / `esc_textarea`.
- **Sources URLs** pass `esc_url_raw` with default protocols (`class-requestdesk-glossary.php:146`), so `javascript:` is dropped.
- **Rewrite flush runs once per rule version**, gated on the `cc_glossary_rewrite_version` option (`:120-124`), CC only. No per-pageview flush.
- **2.69.1 offer pills** remove a `get_terms` call and list only categories held by live, listed offers. No new input, no new query. Behavior change only.

## Findings

| ID | Sev | File:line (82565a7) | Evidence | Why it matters | Suggested fix |
|---|---|---|---|---|---|
| G-1 | low | `includes/class-requestdesk-api.php:2210-2215`, `:2267-2270`, `:2292-2294` | The by-slug lookup lists `publish, draft, pending, private, future` and omits `trash`. With a trashed term holding the slug, `wp_insert_post` runs and WordPress saves the new post as `<slug>-2`. The slug mismatch is reported after the write. | A re-upsert of a term someone trashed creates a second post at `/aeo-glossary/<slug>-2/` and returns `success:false`, so the caller sees a failure while a duplicate draft now exists. CC only. The same lookup pattern sits in the events upsert (`:2074`). | Include `trash` in the lookup and either untrash and update, or refuse with 409 before writing. |
| G-2 | low | `includes/class-requestdesk-api.php:330-352` | `register_rest_route($this->namespace, '/glossary', ...)` in the shared API class. | Every client install now lists a CC-only route in its REST index (key-gated, answers 501). Same class as 11-16 L10 (CC features visible on client installs), much smaller surface. | Register the route only when `class_exists('RequestDesk_Glossary')`. |
| G-3 | low | `includes/class-requestdesk-headless-sites.php:1035` | `return (array) apply_filters('requestdesk_content_post_types', $types);` | Any plugin on a client site can now add post types to the Connector's AEO features, including the API-key author and image-alt routes. Key-gated, so not an exposure; it widens what a leaked main key can touch. | Intersect the filtered list with `get_post_types(array('public' => true))` and document the filter. |

Severity counts: critical 0, high 0, medium 0, low 3 (G-1 to G-3).

## Against 2026-11-19 and 2026-11-16 findings

**Fixed:** none. The delta changes `class-requestdesk-offer.php` only at the category listing (`:854-886`); D-1 to D-10 are untouched.

**Still open, unchanged:** D-1 (Contributor can publish a `/go` open redirect), D-2 (headless read key creates posts and sends mail), D-3 to D-10; all 11-16 items (B1, H1-H8, M1-M26, L1-L26).

**Worsened or extended:**
- **L18** (stale metadata): `readme.txt:6` still says `Stable tag: 2.42.0`, against 2.69.1.
- **M9** (no current release artifact): still no tag after `v2.47.0` (plus `rollback-pre-2.63.1`); 2.67.0 to 2.69.1 untagged.
- **L10** class: G-2 adds one more CC-only surface to client installs.

**Open longest:** B1 and H1-H8 from 2026-09-17 / 2026-11-16, unchanged.

## Test pass (part 1) and live-site audit (part 3), same run

- **Test pass not run.** The wprequestdesk test bed is absent on the Mac Mini (neither `~/LocalSites/wprequestdesk` nor `~/Local Sites/wprequestdesk` exists; fix row t4263).
- **Live-site audit** (`live-technical-audit.py https://contentcucumber.com`, 102 URLs x mobile and desktop): 23 findings (4 high, 17 med, 2 low), against 24 (4 high, 18 med, 2 low) on 2026-11-19. The mobile `/blog/` HIGH (perf 69, LCP 4.8s, CLS 0.25) is no longer a single lab run: the RequestDesk daily job's week-over-week median moved 79 -> 68 and LCP 3.3s -> 5.1s (desktop `/blog/` 99 -> 96, LCP 0.9s -> 1.4s), so it is a confirmed regression in the week from 2026-11-17. The three standing HIGHs persist: `/events/etail-boston/` and `/events/slightly-off-commerce/` canonical to the non-`/events/` URL (and the sitemap lists both redirecting URLs), and mixed content on `/blog/content-marketing-goals/`. Origin response stays about 3.4-3.7s on uncached pages.
