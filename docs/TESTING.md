# Testing and audit process

How this plugin gets checked before it goes on a client site, and every week after.
Written 2026-09-17, after the Yoast work for Support for Stepdads.

Two rules hold the whole thing up:

- **A client site is not Content Cucumber.** Content Cucumber runs with site
  modules ON (`requestdesk_is_cc_site()`), a custom theme, and years of stored
  data. A client runs site modules OFF, a stock theme, and Yoast. Passing on
  contentcucumber.com proves nothing about a client install, which is how
  Content Cucumber's own service list ended up printing on a plain site
  (fixed in 2.47.2) and how an unauthenticated audit-capture endpoint reached
  every install (see `docs/audits/2026-09-17-technical-deficiency-audit.md`).
- **Test what the client will install.** Build the release zip and install it
  through WordPress. Testing the working tree skips the packaging step, and
  the packaging step is where a missing file shows up.

## The test bed

`~/LocalSites/wprequestdesk` (LocalWP, site id `5Gy2uXfdw`): stock
Twenty Twenty-Five, Yoast SEO free, site modules OFF, a handful of posts
including one with connector FAQ data and one with a Yoast FAQ block.
Start it through Local, or through Local's own API when it answers 502:

```bash
I="$HOME/Library/Application Support/Local/graphql-connection-info.json"
T=$(python3 -c "import json;print(json.load(open('$I'))['authToken'])")
curl -s http://127.0.0.1:4000/graphql -H "Authorization: Bearer $T" \
  -H 'Content-Type: application/json' \
  -d '{"query":"mutation { startSite(id: \"5Gy2uXfdw\") { id status } }"}'
```

WP-CLI against it needs Local's PHP socket:

```bash
SOCK="$HOME/Library/Application Support/Local/run/5Gy2uXfdw/mysql/mysqld.sock"
cd ~/LocalSites/wprequestdesk/app/public
php -d mysqli.default_socket="$SOCK" /usr/local/bin/wp <args>
```

Use WP-CLI object commands only (`post meta get`, `option get`, `post list`).
Never the SQL client, raw SQL, or `wp db` — a safety hook blocks them, and
anything done that way cannot travel to production.

## The pass

Each script lives in `tests/`. Run them against the zip, not the working tree.

1. **Build and install the release zip.**
   `git archive --format=zip --prefix=requestdesk-connector/ -o plugin-releases/requestdesk-connector-vX.Y.Z.zip HEAD`,
   drop `docs/` from it, then `wp plugin install <zip> --force`.
2. **Page output, four states.** `tests/capture.py <outdir>` saves normalized
   HTML plus a schema and meta-tag summary for 7 URLs (home, a post with FAQ
   data, a post with a Yoast FAQ block, a plain post, a page, a category, an
   author). Capture and diff:
   - Yoast active, default mode, against the previous release
   - Yoast deactivated, against the previous release (must be byte-identical
     unless the release intends a change)
   - `yoast_mode = off`, against the same site with the connector deactivated
     (must be byte-identical — that is what the off switch promises)
   - `yoast_mode = yoast`, Yoast's own values back, connector FAQ still added
3. **Settings, through the real form.** `tests/admin.py <pw_file> <mode>`
   logs in and submits the AEO settings form, so the save path is exercised
   rather than the option written directly. It also loads seven admin screens
   and reports PHP error text.
4. **Publishing.** `tests/publish_test.py` then `tests/publish_verify.py`
   cover the job the connector exists for: API key saved through the settings
   form, connection test (200 with the key, 401 without), publish with a
   featured image, category, tag and author, FAQ through `/aeo-qa`, one
   `ld+json` block with the FAQ inside Yoast's graph, one of each meta tag,
   an update that keeps the URL and creates no duplicate, the post editor
   with both Yoast and RequestDesk boxes, and the post in Yoast's sitemap.
5. **Schema identity.** `tests/schema-identity-check.py <site-url>` reads the
   site's JSON-LD the way an answer engine does and fails on a duplicate
   `@id`, a doubled singleton type (Organization, WebSite, FAQPage), an
   unparseable block, a reference to an `@id` nothing defines, or the brand
   describing itself more than one way. Zero defects is the bar.
6. **Error log.** Turn on `WP_DEBUG` + `WP_DEBUG_LOG`, empty `debug.log`,
   run the pass, and read it. Zero lines is the bar; PHP deprecations count.
7. **Lifecycle.** Upgrade over an older version (settings, keys and stored
   Q&A must survive), deactivate (output must fall back to clean Yoast),
   delete (site must stay up, and check what is left behind), reinstall.
8. **Roles.** An Editor account: the post editor works with both boxes, and
   the connector's settings and analytics pages return 403.

Undo every write the pass made: delete test posts, media, terms and
temporary users, restore the site's settings option, and restore `wp-config.php`.

## The weekly audit

`brent-start` Monday step, lane background. Two halves:

1. **Re-run the pass above** against the current release on the test bed.
2. **Re-run the deficiency audit.** A read-only agent reads the code for
   security, client-site safety, correctness, performance and maintenance
   defects, with `file:line` evidence for every finding, and writes
   `docs/audits/YYYY-MM-DD-technical-deficiency-audit.md`. Compare against the
   previous file: what got fixed, what is new, what has been open longest.
   The audit prompt that produced the first one is in that file's header.

Findings become items on the `requestdesk-connector-hardening` project.
Nothing is marked done by an agent; Brent closes items.

## What this process has caught

| Release | Found by | Defect |
|---|---|---|
| 2.47.1 | plain-install test | Settings page logged 8 PHP warnings on a fresh install |
| 2.47.2 | plain-install test, Yoast off | Content Cucumber's service catalog printed on any site's home page |
| 2.47.3 | publishing test | Every publish logged 3 PHP deprecation notices |
| 2.48.0 | deficiency audit | Unauthenticated endpoint creating posts and sending mail on every install; Content Cucumber's HubSpot portal written into client sites; `/events/` claimed on every install; a database write on every anonymous pageview; a headless off switch that did nothing |
