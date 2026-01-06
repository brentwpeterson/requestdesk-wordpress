#!/bin/bash
# Sync requestdesk-wordpress repo to LocalWP for testing
#
# USAGE: ./sync-to-localwp.sh
#
# This copies files FROM the git repo TO LocalWP.
# Always edit in the git repo, then run this to test.

SOURCE="/Users/brent/scripts/CB-Workspace/requestdesk-wordpress/"
DEST="/Users/brent/LocalSites/contentcucumber/app/public/wp-content/plugins/requestdesk-connector/"

echo "Syncing requestdesk-wordpress to LocalWP..."
echo "Source: $SOURCE"
echo "Dest:   $DEST"
echo ""

rsync -av --delete \
  --exclude='.git' \
  --exclude='debug.log' \
  --exclude='plugin-releases' \
  --exclude='todo' \
  --exclude='.DS_Store' \
  "$SOURCE" "$DEST"

echo ""
echo "Done! Test at: http://contentcucumber.local/wp-admin/"
