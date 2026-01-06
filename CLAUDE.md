# RequestDesk WordPress Plugin - Claude Instructions

## Project Overview

**Plugin Name:** RequestDesk Connector
**Git Repo:** `/Users/brent/scripts/CB-Workspace/requestdesk-wordpress/`
**Current Version:** Check `requestdesk-connector.php` for `Version:` header

## CRITICAL: Development Workflow

### Source of Truth

```
CANONICAL SOURCE: /Users/brent/scripts/CB-Workspace/requestdesk-wordpress/
```

**ALL code changes MUST be made in the workspace git repo, NOT in LocalWP.**

### LocalWP is for TESTING ONLY

| Location | Purpose | Git? |
|----------|---------|------|
| `CB-Workspace/requestdesk-wordpress/` | Development & commits | YES |
| `LocalSites/.../requestdesk-connector/` | Local testing only | NO |

### Development Process

1. **Edit code** in `CB-Workspace/requestdesk-wordpress/`
2. **Copy to LocalWP** for testing:
   ```bash
   # Sync changes to LocalWP for testing
   rsync -av --exclude='.git' --exclude='debug.log' --exclude='plugin-releases' \
     /Users/brent/scripts/CB-Workspace/requestdesk-wordpress/ \
     /Users/brent/LocalSites/contentcucumber/app/public/wp-content/plugins/requestdesk-connector/
   ```
3. **Test** in browser at `http://contentcucumber.local/wp-admin/`
4. **Commit** changes in `CB-Workspace/requestdesk-wordpress/`
5. **Deploy** via S3 update system (see Deployment section)

### NEVER DO THIS

- Edit files directly in `LocalSites/.../requestdesk-connector/`
- Make changes in LocalWP without syncing back to git repo
- Put planning/todo files in `wordpress-sites/` repo

## Todo & Planning Files

**All plugin planning goes in THIS repo:**

```
/Users/brent/scripts/CB-Workspace/requestdesk-wordpress/todo/
├── current/
│   └── feature/
│       └── [task-name]/
│           ├── README.md
│           ├── [task-name]-plan.md
│           ├── progress.log
│           ├── debug.log
│           ├── notes.md
│           ├── architecture-map.md
│           └── user-documentation.md
└── completed/
```

## Deployment

### S3 Update System

The plugin uses an S3-based auto-update system:

1. **Bump version** in `requestdesk-connector.php`:
   ```php
   * Version: 2.5.2  // Increment this
   ```

2. **Update CHANGELOG.md** with changes

3. **Create release zip:**
   ```bash
   cd /Users/brent/scripts/CB-Workspace/requestdesk-wordpress
   ./create-release.sh  # Or manually zip
   ```

4. **Upload to S3:**
   - Bucket: `cb-wordpress-plugin-updates`
   - Files: `requestdesk-connector.zip` and `update-info.json`

5. **Sites auto-update** on next WP cron check

### LocalWP Push (Alternative)

For quick testing on live Content Cucumber site:
1. Make changes in LocalWP
2. Use LocalWP "Push" feature
3. **IMPORTANT:** Sync changes back to git repo immediately

## Key Admin Pages

| Page | URL Path | File |
|------|----------|------|
| Dashboard | `?page=requestdesk-aeo-analytics` | `admin/aeo-settings-page.php` |
| Bulk Optimizer | `?page=requestdesk-aeo-bulk-optimizer` | `admin/aeo-bulk-optimizer.php` |
| Settings | `?page=requestdesk-aeo-settings` | `admin/aeo-settings-page.php` |
| Template Importer | `?page=requestdesk-template-importer` | `admin/template-importer.php` |

## File Structure

```
requestdesk-wordpress/
├── admin/                    # Admin UI pages
│   ├── aeo-settings-page.php # Dashboard & Settings
│   ├── aeo-bulk-optimizer.php
│   └── template-importer.php
├── includes/                 # Core functionality
├── assets/                   # CSS, JS, images
├── plugin-releases/          # Release zips for S3
├── todo/                     # Planning & task tracking
├── requestdesk-connector.php # Main plugin file
├── CHANGELOG.md
└── CLAUDE.md                 # This file
```

## Related Projects

| Project | Relationship |
|---------|-------------|
| `cb-requestdesk` | Backend API that plugin connects to |
| `wordpress-sites` | Content Cucumber site content (NOT plugin code) |
| `LocalSites/contentcucumber` | Local testing environment |

## Common Issues

### Flywheel WAF Blocking

The live site's WAF can block some REST API calls. Always test locally first.

### CSS Inconsistencies

Known issue: `/contentbasis/` and `/shopify-content-services/` pages have CSS differences. Tracked separately.

### Empty Paragraphs on Import

CSV imports sometimes insert empty `<p></p>` tags. Known issue, not yet fixed.
