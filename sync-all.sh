#!/bin/bash
# Sync requestdesk-wordpress plugin to all WordPress sites
#
# USAGE: ./sync-all.sh [site]
#
# Sites:
#   all            Sync to all sites (default)
#   talk-commerce  Sync to Talk Commerce only
#   contentcucumber Sync to Content Cucumber only
#
# This copies files FROM this git repo TO each target.
# Always edit here, then run this to push changes out.

SOURCE="/Users/brent/scripts/CB-Workspace/requestdesk-wordpress/"

RSYNC_EXCLUDES=(
  --exclude='.git'
  --exclude='debug.log'
  --exclude='plugin-releases'
  --exclude='todo'
  --exclude='.DS_Store'
  --exclude='logs'
  --exclude='sync-to-localwp.sh'
  --exclude='sync-all.sh'
)

plugin_version_at() {
  # Echo the REQUESTDESK_VERSION defined in a plugin tree, or nothing.
  local dir="$1"
  [ -f "$dir/requestdesk-connector.php" ] || return 0
  grep "define('REQUESTDESK_VERSION'" "$dir/requestdesk-connector.php" \
    | grep -o "'[0-9.]*'" | tr -d "'" | head -1
}

sync_site() {
  local name="$1"
  local dest="$2"

  if [ ! -d "$dest" ]; then
    echo "SKIP: $name - directory not found: $dest"
    return 1
  fi

  # REFUSE TO CLOBBER A NEWER DESTINATION.
  #
  # This is an rsync --delete. Between 2.25.0 and 2.35.0, Content Cucumber's
  # tree grew eleven versions of work (the whole AEO Q&A write path, the QR
  # redirect, content audit, promote, admin columns) while this repo sat at
  # 2.24.1, because CC deploys through LocalWP and never reads this repo.
  # Running this script during that window would have deleted all of it, and
  # nothing would have warned you. Reconciled 2026-08-01; this guard is so the
  # next drift is loud instead of destructive.
  #
  # Override with FORCE_SYNC=1 when you genuinely mean to roll a site back.
  local src_ver dest_ver newer
  src_ver="$(plugin_version_at "${SOURCE%/}")"
  dest_ver="$(plugin_version_at "${dest%/}")"

  if [ -n "$src_ver" ] && [ -n "$dest_ver" ] && [ "$src_ver" != "$dest_ver" ]; then
    newer="$(printf '%s\n%s\n' "$src_ver" "$dest_ver" | sort -V | tail -1)"
    if [ "$newer" = "$dest_ver" ] && [ "${FORCE_SYNC:-0}" != "1" ]; then
      echo "REFUSED: $name"
      echo "  Dest:   $dest"
      echo "  Source is v$src_ver but destination is v$dest_ver (NEWER)."
      echo "  This sync deletes files. Syncing would destroy work that exists"
      echo "  only at the destination. Port those changes into this repo first."
      echo "  Override with: FORCE_SYNC=1 ./sync-all.sh $SITE"
      echo ""
      return 1
    fi
  fi

  echo "Syncing to $name..."
  echo "  Dest: $dest"
  [ -n "$dest_ver" ] && echo "  v$dest_ver -> v$src_ver"
  rsync -av --delete "${RSYNC_EXCLUDES[@]}" "$SOURCE" "$dest"
  echo ""
}

SITE="${1:-all}"

echo "RequestDesk Connector Plugin Sync"
echo "Source: $SOURCE"
echo "Version: $(grep "define('REQUESTDESK_VERSION'" "$SOURCE/requestdesk-connector.php" | grep -o "'[0-9.]*'" | tr -d "'")"
echo ""

case "$SITE" in
  talk-commerce|tc)
    sync_site "Talk Commerce (LocalWP)" \
      "/Users/brent/LocalSites/talk-commerce/app/public/wp-content/plugins/requestdesk-connector/"
    sync_site "Talk Commerce (wordpress-sites)" \
      "/Users/brent/scripts/CB-Workspace/wordpress-sites/talk-commerce/plugins/requestdesk-connector/"
    ;;
  contentcucumber|cc)
    sync_site "Content Cucumber (LocalWP)" \
      "/Users/brent/LocalSites/contentcucumber/app/public/wp-content/plugins/requestdesk-connector/"
    sync_site "Content Cucumber (wordpress-sites)" \
      "/Users/brent/scripts/CB-Workspace/wordpress-sites/contentcucumber/plugins/requestdesk-connector/"
    ;;
  all)
    sync_site "Talk Commerce (LocalWP)" \
      "/Users/brent/LocalSites/talk-commerce/app/public/wp-content/plugins/requestdesk-connector/"
    sync_site "Talk Commerce (wordpress-sites)" \
      "/Users/brent/scripts/CB-Workspace/wordpress-sites/talk-commerce/plugins/requestdesk-connector/"
    sync_site "Content Cucumber (LocalWP)" \
      "/Users/brent/LocalSites/contentcucumber/app/public/wp-content/plugins/requestdesk-connector/"
    sync_site "Content Cucumber (wordpress-sites)" \
      "/Users/brent/scripts/CB-Workspace/wordpress-sites/contentcucumber/plugins/requestdesk-connector/"
    ;;
  *)
    echo "Unknown site: $SITE"
    echo "Usage: ./sync-all.sh [all|talk-commerce|contentcucumber]"
    exit 1
    ;;
esac

echo "Sync complete!"
