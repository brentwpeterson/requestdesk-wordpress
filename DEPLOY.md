# Deploying this plugin

This repo is the RequestDesk Connector plugin. It reaches four WordPress
installs by four different mechanisms, and none of them is `git push`.

Pushing to GitHub deploys nothing. Read this before assuming a change is live.

## Where a change here actually goes

| Site | What it is | How this plugin gets there | Whose hands |
|---|---|---|---|
| **tc.requestdesk.ai** | Talk Commerce WordPress backend. Docker container `wp-talk-commerce` on AWS Lightsail (`23.20.52.57`, instance `wp-talk-commerce-v3`). | `wp-infra/deploy-tc-wp-plugin.sh requestdesk-connector --go` -- rsync to the box, then `docker cp` into the container. **NOT Flywheel. NOT ECS. NOT GitHub Actions.** | runnable from here |
| **contentcucumber.com** | Content Cucumber production. | LocalWP / Flywheel **Magic Sync**, from the LocalWP tree. Not from this repo. | **Brent only** |
| **talk-commerce.local** | TC LocalWP dev site. | Nothing to run. The plugin dir is a **symlink into this repo**, so it changes the instant you commit here. | automatic |
| **contentcucumber.local** | CC LocalWP dev site. | A **real directory**, separate from this repo. Moves only when someone copies files. | manual |

`talk-commerce.com` is a **different thing**: the public Astro SSR site on
ECS/Fargate, deployed from `astro-sites`. It reads the WP headless API. This
plugin never deploys there. Do not confuse the two.

## Deploying to Talk Commerce live

```bash
./wp-infra/deploy-tc-wp-plugin.sh requestdesk-connector          # DRY RUN, touches nothing
./wp-infra/deploy-tc-wp-plugin.sh requestdesk-connector --go     # actually deploy
```

The script rsyncs this repo to `/tmp` on the Lightsail box, `docker cp`s it
into `wp-talk-commerce:/var/www/html/wp-content/plugins/requestdesk-connector/`,
then prints the version it landed so you can confirm.

- No flag is a dry run. It prints the exact commands and exits. Use it.
- SSH is `ssh -i ~/.ssh/lightsail-default.pem ubuntu@23.20.52.57`.
- First-time install needs activating in wp-admin -> Plugins.
- PHP changes sometimes need `docker restart wp-talk-commerce` on the box.
- `requestdesk-podcast` deploys the same way, same script, different argument.

Server, S3 uploads mount, and known failure modes (403 on images, I/O error,
permission denied) are in memory `Talk Commerce WordPress Infrastructure`.
Fuller deploy table lives in the `brand-talkcommerce` skill.

## Two things that surprise people

### Talk Commerce has no staging gap

Both of TC's local plugin paths are symlinks to this repo:

```
/Users/brent/LocalSites/talk-commerce/.../plugins/requestdesk-connector      -> this repo
/Users/brent/scripts/CB-Workspace/wordpress-sites/talk-commerce/plugins/...  -> this repo
```

So a commit here changes TC's local site immediately, with nothing in
between. There is no step where you get to test first unless you deliberately
take one. The live box is still a real deploy, but local TC is not a
safety net -- it is the same files.

### Content Cucumber is a real directory, so it drifts

CC deploys through LocalWP and never reads this repo. Its plugin tree at
`/Users/brent/LocalSites/contentcucumber/.../plugins/requestdesk-connector`
is a real directory that only moves when someone copies files by hand.

That means **CC racing ahead of this repo is the default behaviour, not an
accident.** It has happened three times:

- 2.24.1 vs 2.35.0 (eleven versions, the whole AEO write path)
- 2.35.0 vs 2.36.1 (reopened within hours of the first reconcile)
- 2.36.1 vs 2.37.0 (reopened the same day as the second)

Each reconcile is manual and lasts until the next CC commit. If you are
reading this because the versions disagree again, that is the reason.

## sync-all.sh

`sync-all.sh` is local plumbing, not a deploy. It is an `rsync --delete` from
this repo into the LocalWP trees.

It carries a guard that refuses to sync when the destination is on a **newer**
version than the source, because that is exactly when the delete destroys
work. Override with `FORCE_SYNC=1` only when you mean to roll a site back.

Two things worth knowing before you run it:

1. **The TC destinations resolve back to this repo.** All four listed
   destinations resolve to only two real places -- TC's two both `realpath`
   to this repo itself, so syncing TC rsyncs the source into its own
   directory. The excludes protect `.git`, `todo`, `plugin-releases` and the
   script itself, so in practice it is a no-op, but it is not doing what the
   site list implies.
2. **Only the CC destination is a real copy**, and CC is usually the tree
   that is *ahead*, which is what the guard exists to catch.

Keep the guard in both copies of this file. There is a copy in the Content
Cucumber repo, and a guard that exists in only one place is not a guard.
