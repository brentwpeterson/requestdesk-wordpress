# Our Work + Partners — Inbox Importer (canonical reference)

**This is the single source of truth for how the `cc_case_study` ("Our Work")
and `cc_partner` importers work.** Code-grounded, verified against
`includes/class-requestdesk-case-study.php` and
`includes/class-requestdesk-partner.php` at plugin **v2.16.3** (2026-05-19).
If a SKILL.md or a memory disagrees with this, the code + this doc win.
Read this before touching either importer. Getting it wrong has wasted
multiple sessions.

---

## The model: a one-file-per-entry inbox

There are two twin importers, identical in mechanics:

| Thing | Our Work / Case Studies | Partners |
|---|---|---|
| Post type | `cc_case_study` (`/our-work/`) | `cc_partner` (`/partners/`) |
| Inbox dir | `includes/data/import/case-studies/` | `includes/data/import/partners/` |
| One file = | one case study object | one partner object |
| Match identity | `slug` (`get_page_by_path`) | `name` (`post_title`) |
| Admin screen | Case Studies → Import Case Studies | Partners → Import Partners |
| Per-entry method | `cs_import_entry()` | `partner_import_entry()` |

**How it behaves:**

- Each entry is **its own JSON file** in the inbox dir. Filename = the slug
  (case studies) or a name-slug (partners). The file holds **one object**
  (a single-element array is also tolerated).
- The import screen scans the inbox dir and shows **only the files
  currently there** — one row per file, each with its own checkbox.
- You import one, several, or all (per-row checkboxes; "check all"
  toggle). This is the "import one at a time" path.
- On import, each processed file **moves out**: success →
  `<inbox>/imported/<timestamp>-<file>`, failure →
  `<inbox>/failed/<timestamp>-<file>`. It leaves the screen.
- Net: the list can never grow past your pending work. An empty inbox is
  normal and correct. This is what scales it to thousands of entries —
  you never review a 700-row table.
- New identity → created as a **draft** ("Will create", green).
  Existing identity → **updated in place** ("Will update", blue);
  `post_status` is **preserved** (a published entry stays published),
  and for partners the logo is only (re)uploaded if none is set.
- Imported files are **archived, never deleted** — `imported/` is a
  full replay log for a future clean rebuild.

**Backward compatible:** a legacy single-array
`includes/data/<case-studies|partners>-import.json` is still read if
present (each element becomes a pending row), and the whole legacy file
is archived to `imported/` once all its entries have been imported. The
2026-05-19 migration split the existing arrays into per-entry files and
archived the legacy arrays, so going forward it is one-file-per-entry.

---

## 🚨 THE TRAP (this is what wastes sessions)

**The importer reads the inbox from the INSTALLED plugin, not the git
repo.** `plugin_dir_path(__FILE__)` resolves to wherever WordPress loaded
`requestdesk-connector` for the running site:

```
/Users/brent/LocalSites/contentcucumber/app/public/wp-content/plugins/requestdesk-connector/
```

Note `LocalSites` with **NO space** (older searches used `Local Sites`
with a space and found nothing, then wrongly concluded "stale page").

**Editing files in `requestdesk-wordpress/` (the git repo) does NOTHING
to the live importer** until those files are in the installed plugin. If
the admin screen shows the wrong list, you edited the wrong copy. Do not
theorize a mechanism from the git JSON — read the code, check the
installed path.

The inbox dir is **not** the entry list. The real list is the CPT in the
WordPress DB. The inbox only holds *pending* work. Never infer the live
set from the inbox — query the DB:

```sql
SELECT ID, post_title, post_name, post_status FROM wp_83rxila95v_posts
WHERE post_type IN ('cc_case_study','cc_partner') ORDER BY post_type, post_title;
```

Also: git is **not** the source of truth for this plugin. The installed
plugin runs code/data never committed to git (e.g. the `silicone-depot`
case study existed installed-only). See memory
`project_cc_plugin_production_ahead_of_git`.

---

## 🚨 THE LANDMINE

**NEVER git→Local resync / reinstall the `requestdesk-connector`
plugin.** A full resync destroys the uncommitted live case-study module
and ~20 partner logos (the 2026-05-14 case-study-deletion incident,
memory `project_cc_plugin_production_ahead_of_git`). Adding entries is
always **additive** — copy individual files, never replace the plugin.

---

## How to add ONE entry (the common case)

You do **not** need the importer for a single entry. Two valid paths:

**Path B — WP admin Add New (preferred for one-off, zero deploy):**
Case Studies → Add New (or Partners → Add New). Fill title, the meta
boxes (the content lives in meta boxes, not the body), excerpt, logo,
publish. No JSON, no importer, no plugin deploy, no resync.

**Path A — the inbox (one file, then import):**
1. Write one `<slug>.json` (schema below).
2. Place it in the **installed** plugin's inbox dir (safe additive copy;
   also commit a copy to the git repo's inbox dir as source-of-record).
3. Drop any logo into `includes/data/logos/`.
4. WP admin → the Import screen → it shows your one new row →
   check it → Run Import → it becomes a draft and the file moves to
   `imported/`.
5. Review the draft, complete fields the importer doesn't set, Publish.

A draft is **not** public until you Publish it (the importer only ever
creates drafts unless the object carries `status: "publish"`).

## How to add MANY at once (bulk seed)

Same as Path A but drop N files into the installed inbox dir, then
select the rows you want and Run Import. Each processed file archives
itself. Only use bulk for genuine batch seeding.

## Safe deploy procedure (when files must reach the installed plugin)

1. Edit/author in the git repo (`requestdesk-wordpress/includes/...`).
2. If PHP changed, bump the version in `requestdesk-connector.php`
   (header + `REQUESTDESK_VERSION`), PATCH level. Data files are not
   versioned.
3. Back up the installed file(s) you replace (`.bak-<timestamp>`).
4. **Copy** the changed files (PHP and/or the new inbox `*.json` + logos)
   into the installed plugin path above. Never resync the whole plugin.
5. `php -l` the deployed PHP. Validate JSON in PHP
   (`json_decode(file_get_contents(...))`) — the importer's own engine,
   not just Python.

---

## JSON schema

The full field list is printed on each Import screen ("JSON Schema"
section). One object per file. Highlights:

- **Case study:** `slug` (identity), `title`, `status`, `excerpt`,
  `content`, `client_name`, `client_url`, `client_logo`,
  `featured_image`, `length`, `type`, `date_*`, `challenge`,
  `approach`, `results`, `stats[]` (`{value,label}`), `quote*`,
  `aeo_summary` (the AI-citation surface — fill it), `team_*`,
  `pinned`, `display_priority`, `seo_*`, and taxonomy arrays
  (`industries`, `platforms`, `services`, `outcomes`, `sizes`, `years`).
- **Partner:** `name` (identity), `website`, `excerpt`, `content`
  (HTML body), `logo_file` (filename in `includes/data/logos/`),
  optional `tagline`, `hero_overlay`. Tier/CTA/Featured/HubSpot IDs are
  set on the post after import, not by the importer.

Content rules (memory `feedback_verify_cc_stats_before_use`,
`brand-brent`): factual only, no fabricated client names, numbers, or
quotes. No em dashes. Reference an existing published entry for voice.

---

## Reference

- Code: `includes/class-requestdesk-case-study.php`,
  `includes/class-requestdesk-partner.php` (v2.16.3 inbox importer)
- Skills: `.claude/skills/cc-case-studies/`, `.claude/skills/cc-partners/`
  (the "How the importer actually works" sections mirror this doc; this
  file is canonical if they ever drift)
- Memory: `project_cc_plugin_production_ahead_of_git` (the landmine),
  `project_cc_case_studies_skill_mirrors_partners` (the model history),
  `feedback_read_code_not_theorize_mechanism`,
  `feedback_verify_cc_stats_before_use`
- Migration that created this model: 2026-05-19, Claude-Rutherford,
  plugin v2.16.2 → v2.16.3.
