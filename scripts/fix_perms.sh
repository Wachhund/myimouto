#!/usr/bin/env bash
#
# fix_perms.sh — Fix file ownership and permissions for MyImouto
#
# Requires a sudoers rule (no password):
#   vps ALL=(ALL) NOPASSWD: /usr/bin/chown, /usr/bin/find, /usr/bin/chmod
#
# Usage:
#   ./scripts/fix_perms.sh                  # fix current link target
#   ./scripts/fix_perms.sh /path/to/release # fix specific directory
#
set -euo pipefail

# ─── Configuration ───────────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env.deploy"
if [[ -f "$ENV_FILE" ]]; then
    # shellcheck source=/dev/null
    source "$ENV_FILE"
fi

DEPLOY_USER="${DEPLOY_USER:-vps}"
DEPLOY_BASE="${DEPLOY_BASE:-/home/vps}"
PROJECT_NAME="${PROJECT_NAME:-myimouto}"
WEB_GROUP="${WEB_GROUP:-www-data}"

# ─── Functions ───────────────────────────────────────────────────────────────
log() { echo "[fix_perms] $*"; }
fail() { echo "[fix_perms] ERROR: $*" >&2; exit 1; }

# ─── Pre-flight ──────────────────────────────────────────────────────────────
if ! sudo -n true 2>/dev/null; then
    fail "Passwordless sudo not configured. Add sudoers rule:
  ${DEPLOY_USER} ALL=(ALL) NOPASSWD: /usr/bin/chown, /usr/bin/find, /usr/bin/chmod"
fi

# ─── Determine target ───────────────────────────────────────────────────────
TARGET="${1:-}"
if [[ -z "$TARGET" ]]; then
    CURRENT_LINK="${DEPLOY_BASE}/${PROJECT_NAME}"
    if [[ -L "$CURRENT_LINK" ]]; then
        TARGET="$(readlink -f "$CURRENT_LINK")"
    else
        TARGET="$CURRENT_LINK"
    fi
fi

if [[ ! -d "$TARGET" ]]; then
    fail "Target directory does not exist: ${TARGET}"
fi

log "Fixing permissions for: ${TARGET}"

# ─── Apply permissions ───────────────────────────────────────────────────────
sudo chown -R "${DEPLOY_USER}:${WEB_GROUP}" "$TARGET"
sudo find "$TARGET" -type d -exec chmod 775 {} \;
sudo find "$TARGET" -type f -exec chmod 664 {} \;

# ─── Smoke test ──────────────────────────────────────────────────────────────
SMOKE_URL="${SMOKE_URL:-http://127.0.0.1}"
log "Smoke test..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${SMOKE_URL}/post" 2>/dev/null || echo "000")

if [[ "$HTTP_CODE" == "200" || "$HTTP_CODE" == "301" || "$HTTP_CODE" == "302" ]]; then
    log "Smoke test passed (HTTP ${HTTP_CODE})."
else
    log "WARNING: Smoke test returned HTTP ${HTTP_CODE}."
fi

log "Done."
