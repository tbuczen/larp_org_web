#!/usr/bin/env bash
# Creates the GitHub issues from the drafts in this folder.
# Requires the GitHub CLI (gh), logged in with access to the repo.
set -euo pipefail
REPO="${REPO:-tbuczen/larp_org_web}"
cd "$(dirname "$0")"
for f in [0-9][0-9]-*.md; do
  title="$(head -n1 "$f" | sed 's/^# //')"
  tail -n +2 "$f" > /tmp/issue-body.md
  echo "Creating: $title"
  gh issue create --repo "$REPO" --title "$title" --body-file /tmp/issue-body.md
done
