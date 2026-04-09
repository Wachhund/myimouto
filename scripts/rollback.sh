#!/usr/bin/env bash
#
# rollback.sh — Roll back to previous MyImouto release
#
# Usage:
#   ./scripts/rollback.sh              # rollback to previous release
#   ./scripts/rollback.sh <release>    # rollback to specific release name
#
set -euo pipefail

# ─── Configuration ───────────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env.deploy"
if [[ -f "$ENV_FILE" ]]; then
    # shellcheck source=/dev/null
    source "$ENV_FILE"
fi

DEPLOY_BASE="${DEPLOY_BASE:-/home/vps}"
PROJECT_NAME="${PROJECT_NAME:-myimouto}"
RELEASES_DIR="${DEPLOY_BASE}/releases"
CURRENT_LINK="${DEPLOY_BASE}/${PROJECT_NAME}"

# ─── Functions ───────────────────────────────────────────────────────────────
log() { echo "[rollback] $*"; }
fail() { echo "[rollback] ERROR: $*" >&2; exit 1; }

# ─── Determine target ───────────────────────────────────────────────────────
REQUESTED="${1:-}"

if [[ -n "$REQUESTED" ]]; then
    # Rollback to a specific release
    if [[ -d "${RELEASES_DIR}/${REQUESTED}" ]]; then
        ROLLBACK_TARGET="${RELEASES_DIR}/${REQUESTED}"
    else
        fail "Release not found: ${RELEASES_DIR}/${REQUESTED}"
    fi
else
    # Find the current release name
    if [[ -L "$CURRENT_LINK" ]]; then
        CURRENT_RELEASE="$(readlink -f "$CURRENT_LINK")"
        CURRENT_NAME="$(basename "$CURRENT_RELEASE")"
    else
        CURRENT_NAME=""
    fi

    # List releases sorted by time (newest first), skip current
    PREVIOUS=$(ls -1t "${RELEASES_DIR}" | while read -r rel; do
        if [[ "$rel" != "$CURRENT_NAME" ]]; then
            echo "$rel"
            break
        fi
    done)

    if [[ -z "$PREVIOUS" ]]; then
        fail "No previous release found to roll back to."
    fi

    ROLLBACK_TARGET="${RELEASES_DIR}/${PREVIOUS}"
fi

if [[ ! -d "$ROLLBACK_TARGET" ]]; then
    fail "Rollback target does not exist: ${ROLLBACK_TARGET}"
fi

# ─── Show current state ─────────────────────────────────────────────────────
log "Current link: $(readlink -f "$CURRENT_LINK" 2>/dev/null || echo 'none')"
log "Rolling back to: ${ROLLBACK_TARGET}"

# ─── Switch symlink ──────────────────────────────────────────────────────────
ln -sfn "$ROLLBACK_TARGET" "$CURRENT_LINK"
log "Symlink updated: ${CURRENT_LINK} -> $(readlink -f "$CURRENT_LINK")"

# ─── Smoke test ──────────────────────────────────────────────────────────────
SMOKE_URL="${SMOKE_URL:-http://127.0.0.1}"
log "Smoke test..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${SMOKE_URL}/post" 2>/dev/null || echo "000")

if [[ "$HTTP_CODE" == "200" || "$HTTP_CODE" == "301" || "$HTTP_CODE" == "302" ]]; then
    log "Smoke test passed (HTTP ${HTTP_CODE})."
else
    log "WARNING: Smoke test returned HTTP ${HTTP_CODE}. Check application status."
fi

log "Rollback complete."
