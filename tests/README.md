# tests/

Harness for the pre-release pass described in `docs/TESTING.md`. Python 3, no
dependencies. Everything runs against the LocalWP test site
`http://wprequestdesk.local` (stock theme, Yoast free, site modules off).

- `capture.py <outdir>` — save normalized HTML + schema/meta summary for 7 URLs
- `admin.py <pw_file> screens|requestdesk|yoast|off` — load admin screens, or
  submit the AEO settings form to switch the Yoast mode
- `publish_test.py <pw_file> <api_key> <state_json>` — settings form saves the
  API key, then publish a post through the REST API
- `publish_verify.py <pw_file> <api_key> <state_json>` — FAQ, page output,
  update, editor screen, Yoast sitemap
- `schema-identity-check.py <site-url> [--per-sitemap N]` — duplicate @id,
  doubled singleton types, broken references, self-description drift.
  Also kept at `.claude/local/schema-identity-check.py` in CB-Workspace, where
  the weekly routine calls it for live sites.

The scripts create posts, terms, media and temporary users. Undo all of it when
the pass finishes; `docs/TESTING.md` lists what to restore.
