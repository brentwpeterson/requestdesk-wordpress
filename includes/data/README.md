# RequestDesk Connector — Data Importers

This folder holds the JSON files the WordPress admin importers read.

## 🚨 The Filenames Are Hardcoded 🚨

The PHP that powers each importer is hardcoded to read a **specific filename**. Putting your data in a differently-named file means the admin importer will not see it.

| CPT | Active import file (do not rename) | Importer code |
|---|---|---|
| Case Study | `case-studies-import.json` | `class-requestdesk-case-study.php` (lines 522, 632) |
| Technology Partner | `partners-import.json` | `class-requestdesk-partner.php` (lines 402, 512) |

**If you need to add new entries, append them to the existing array in the active file. Do NOT create a parallel file with a different name.** The admin UI will silently keep showing only what's in the canonical file.

## How To Add New Entries (Both CPTs)

1. **Read the active file** (e.g. `case-studies-import.json`).
2. **Append your new objects** to the JSON array. Keep slugs unique. The importer treats matching slugs as updates and missing slugs as new drafts.
3. **Validate JSON** before saving (`python3 -c "import json; json.load(open('case-studies-import.json'))"`).
4. **Refresh the WP admin importer page.** The count at the top of the page (`Found N case study/studies in case-studies-import.json`) is your sanity check.
5. Click **Run Import**.

## Schema (Case Study)

The full canonical schema is shown in the WP admin importer page itself ("JSON Schema" section). Quick reference of required-ish fields:

```json
{
  "slug": "unique-slug",
  "title": "...",
  "status": "draft",
  "excerpt": "...",
  "content": "Optional HTML body — rendered as the post body",
  "client_name": "...",
  "client_url": "",
  "client_logo": "",
  "featured_image": "",
  "length": "...",
  "type": "...",
  "date_started": "YYYY-MM-DD",
  "date_published": "YYYY-MM-DD",
  "last_verified": "YYYY-MM-DD",
  "challenge": "...",
  "approach": "...",
  "results": "...",
  "stats": [{ "value": "...", "label": "..." }],
  "quote": "",
  "quote_name": "",
  "quote_title": "",
  "quote_company": "",
  "quote_photo": "",
  "aeo_summary": "...",
  "team_writer": "",
  "team_editor": "",
  "pinned": false,
  "display_priority": 0,
  "seo_title": "...",
  "seo_description": "...",
  "industries": [],
  "platforms": [],
  "services": [],
  "outcomes": [],
  "sizes": [],
  "years": []
}
```

## Schema (Partner)

See `class-requestdesk-partner.php` for the full field list. Same import pattern as case studies.

## Common Mistakes

- **Creating a new JSON file instead of appending to the canonical one.** The importer will not find it. (How this README came to exist, 2026-04-29.)
- **Duplicate slugs.** The importer will treat them as updates, which silently overwrites fields on the existing entry.
- **Forgetting JSON validation.** A trailing comma breaks the entire file and the admin page shows zero entries.
- **Editing the file while the import is running.** Don't.

## Convenience: Merge Script

When adding multiple entries from a separate draft file:

```python
import json
from pathlib import Path

active = Path('case-studies-import.json')
draft = Path('my-new-drafts.json')

existing = json.loads(active.read_text())
new = json.loads(draft.read_text())

existing_slugs = {e['slug'] for e in existing}
to_add = [e for e in new if e['slug'] not in existing_slugs]

active.write_text(json.dumps(existing + to_add, indent=4) + '\n')
print(f"Merged {len(to_add)} new entries. Total now: {len(existing + to_add)}.")
```

Then delete the draft file. Don't leave orphan import files in this folder.
