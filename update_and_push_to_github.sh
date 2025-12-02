#!/usr/bin/env bash
# Goal: integrate origin while keeping LOCAL content on conflicts, handle unrelated histories, then push.
set -euo pipefail

# --- Configuration ---
URL="https://github.com/Mondial-IT/bm_data_modules.git"
DIR="bm_data_modules"
BRANCH="dev-d9-conversion-to-d11"

#--------


# Safely capture the first argument ($1) as the commit message.
# Use ${1:-} which expands to the value of $1 if set, or an empty string otherwise.     
# This prevents the 'unbound variable' error from 'set -u'.
COMMIT_MESSAGE="${1:-}"

# Require we're somewhere under .../git_root/...
if [[ "$PWD" != *"/git_root/"* ]]; then
  echo "Error: not under /git_root/ (PWD=$PWD)" >&2
  exit 1
fi

# Compute the absolute /git_root prefix from PWD.
ROOT="${PWD%%/git_root/*}/git_root"
SCRIPT="${ROOT}/.scripts/push-to-github.sh"

# Shift the positional arguments once to remove the commit message ($1)
# so that only other potential arguments remain in "$@".
shift || true

# Call the central script, passing the message explicitly and any remaining args.       
exec bash "$SCRIPT" \
  --url "$URL" \
  --dir "$DIR" \
  --branch "$BRANCH" \
  --message "$COMMIT_MESSAGE" \
  "$@"
