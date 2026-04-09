#!/usr/bin/env bash
#
# deploy.sh — Zero-downtime deployment for MyImouto (VPS target)
#
# Usage:
#   ./scripts/deploy.sh              # deploy from local archive
#   ./scripts/deploy.sh --help       # show help
#
# Reads optional config from scripts/.env.deploy (not versioned).
#
set -euo pipefail

# ─── Configuration (override via .env.deploy or environment) ─────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env.deploy"
if [[ -f "$ENV_FILE" ]]; then
    # shellcheck source=/dev/null
    source "$ENV_FILE"
fi

DEPLOY_USER="${DEPLOY_USER:-vps}"
DEPLOY_HOST="${DEPLOY_HOST:-}"
DEPLOY_BASE="${DEPLOY_BASE:-/home/vps}"
PROJECT_NAME="${PROJECT_NAME:-myimouto}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
SMOKE_URL="${SMOKE_URL:-http://127.0.0.1}"
WEB_GROUP="${WEB_GROUP:-www-data}"

# ─── Derived paths ───────────────────────────────────────────────────────────
RELEASES_DIR="${DEPLOY_BASE}/releases"
CURRENT_LINK="${DEPLOY_BASE}/${PROJECT_NAME}"
SHARED_DIR="${DEPLOY_BASE}/shared"
RELEASE_NAME="${PROJECT_NAME}-$(date +%Y%m%d%H%M%S)"
RELEASE_DIR="${RELEASES_DIR}/${RELEASE_NAME}"

# ─── Functions ───────────────────────────────────────────────────────────────
usage() {
    echo "Usage: $0 [--help]"
    echo ""
    echo "Deploys MyImouto to a VPS using a release-directory strategy."
    echo "Configure via scripts/.env.deploy or environment variables:"
    echo "  DEPLOY_USER   (default: vps)"
    echo "  DEPLOY_HOST   (required for remote deploy, empty = local)"
    echo "  DEPLOY_BASE   (default: /home/vps)"
    echo "  PROJECT_NAME  (default: myimouto)"
    echo "  PHP_BIN       (default: php)"
    echo "  COMPOSER_BIN  (default: composer)"
    echo "  KEEP_RELEASES (default: 5)"
    echo "  SMOKE_URL     (default: http://127.0.0.1)"
    exit 0
}

log() { echo "[deploy] $*"; }
fail() { echo "[deploy] ERROR: $*" >&2; exit 1; }

run_remote() {
    if [[ -n "$DEPLOY_HOST" ]]; then
        ssh "${DEPLOY_USER}@${DEPLOY_HOST}" "$@"
    else
        eval "$@"
    fi
}

upload() {
    local src="$1" dest="$2"
    if [[ -n "$DEPLOY_HOST" ]]; then
        scp "$src" "${DEPLOY_USER}@${DEPLOY_HOST}:${dest}"
    else
        cp "$src" "$dest"
    fi
}

# ─── Pre-flight checks ──────────────────────────────────────────────────────
preflight() {
    log "Pre-flight checks..."

    # Verify sudo access (passwordless)
    if ! run_remote "sudo -n true 2>/dev/null"; then
        fail "Passwordless sudo not configured. Add sudoers rule:
  ${DEPLOY_USER} ALL=(ALL) NOPASSWD: /usr/bin/chown, /usr/bin/find, /usr/bin/chmod"
    fi

    # Verify PHP
    run_remote "${PHP_BIN} -v" | head -n 1
    run_remote "${COMPOSER_BIN} --version"

    # Ensure release dir structure exists
    run_remote "mkdir -p '${RELEASES_DIR}' '${SHARED_DIR}'"

    log "Pre-flight OK."
}

# ─── Build archive ───────────────────────────────────────────────────────────
build_archive() {
    local archive="/tmp/${RELEASE_NAME}.tar.gz"
    log "Building archive: ${archive}"

    local project_root
    project_root="$(cd "${SCRIPT_DIR}/.." && pwd)"

    tar -czf "$archive" \
        --exclude='.git' \
        --exclude='vendor' \
        --exclude='node_modules' \
        --exclude='tmp/*' \
        --exclude='log/*' \
        --exclude='public/data/*' \
        --exclude='public/assets' \
        --exclude='coverage' \
        --exclude='.phpunit.cache' \
        --exclude='scripts/.env.deploy' \
        -C "$(dirname "$project_root")" \
        "$(basename "$project_root")"

    echo "$archive"
}

# ─── Deploy steps ────────────────────────────────────────────────────────────
deploy() {
    # 1. Build and upload
    local archive
    archive="$(build_archive)"
    log "Uploading to ${RELEASES_DIR}/"
    upload "$archive" "${RELEASES_DIR}/${RELEASE_NAME}.tar.gz"
    rm -f "$archive"

    # 2. Extract
    log "Extracting release: ${RELEASE_DIR}"
    run_remote "mkdir -p '${RELEASE_DIR}' && tar -xzf '${RELEASES_DIR}/${RELEASE_NAME}.tar.gz' --strip-components=1 -C '${RELEASE_DIR}' && rm -f '${RELEASES_DIR}/${RELEASE_NAME}.tar.gz'"

    # 3. Link shared resources
    log "Linking shared resources..."
    run_remote "
        mkdir -p '${SHARED_DIR}/config' '${SHARED_DIR}/log' '${SHARED_DIR}/tmp' '${SHARED_DIR}/data'

        # config/config.php (site-specific, not versioned)
        if [[ -f '${SHARED_DIR}/config/config.php' ]]; then
            ln -sfn '${SHARED_DIR}/config/config.php' '${RELEASE_DIR}/config/config.php'
        fi

        # config/database.yml
        if [[ -f '${SHARED_DIR}/config/database.yml' ]]; then
            ln -sfn '${SHARED_DIR}/config/database.yml' '${RELEASE_DIR}/config/database.yml'
        fi

        # Persistent directories
        rm -rf '${RELEASE_DIR}/log' '${RELEASE_DIR}/tmp/cache'
        ln -sfn '${SHARED_DIR}/log' '${RELEASE_DIR}/log'
        ln -sfn '${SHARED_DIR}/tmp' '${RELEASE_DIR}/tmp'

        # Data directory (uploads, avatars)
        if [[ -d '${SHARED_DIR}/data' ]]; then
            ln -sfn '${SHARED_DIR}/data' '${RELEASE_DIR}/public/data'
        fi
    "

    # 4. Install dependencies
    log "Installing dependencies..."
    run_remote "cd '${RELEASE_DIR}' && ${COMPOSER_BIN} install --no-interaction --prefer-dist --no-dev"

    # 4b. Install Node.js build tools (PROJ-32: terser, clean-css-cli)
    if run_remote "command -v npm >/dev/null 2>&1"; then
        log "Installing npm build tools..."
        run_remote "cd '${RELEASE_DIR}' && npm install --omit=dev 2>&1 | tail -n 1"
    else
        log "npm not found — asset minification will use fallback (no compression)"
    fi

    # 5. Build assets
    log "Building assets..."
    run_remote "cd '${RELEASE_DIR}' && ${COMPOSER_BIN} run assets:build"

    # 6. Run migrations
    log "Running database migrations..."
    run_remote "cd '${RELEASE_DIR}' && ${PHP_BIN} config/boot.php db:migrate"

    # 7. Fix permissions
    log "Fixing permissions..."
    run_remote "
        sudo chown -R '${DEPLOY_USER}:${WEB_GROUP}' '${RELEASE_DIR}'
        sudo find '${RELEASE_DIR}' -type d -exec chmod 775 {} \;
        sudo find '${RELEASE_DIR}' -type f -exec chmod 664 {} \;
    "

    # 8. Smoke test
    # Nginx routes via the project symlink, so we must temporarily switch it
    # to test the new release. On failure we immediately roll back.
    log "Running smoke test (switching symlink temporarily)..."
    local prev_target
    prev_target=$(run_remote "readlink -f '${CURRENT_LINK}' 2>/dev/null || echo ''")
    run_remote "ln -sfn '${RELEASE_DIR}' '${CURRENT_LINK}'"

    local http_code
    http_code=$(run_remote "curl -s -o /dev/null -w '%{http_code}' '${SMOKE_URL}/post'")

    if [[ "$http_code" != "200" && "$http_code" != "301" && "$http_code" != "302" ]]; then
        log "Smoke test failed (HTTP ${http_code}). Rolling back symlink..."
        if [[ -n "$prev_target" && -d "$prev_target" ]]; then
            run_remote "ln -sfn '${prev_target}' '${CURRENT_LINK}'"
            log "Rolled back to $(basename "${prev_target}")"
        else
            local prev
            prev=$(run_remote "ls -1t '${RELEASES_DIR}' | grep -v '${RELEASE_NAME}' | head -n 1")
            if [[ -n "$prev" ]]; then
                run_remote "ln -sfn '${RELEASES_DIR}/${prev}' '${CURRENT_LINK}'"
                log "Rolled back to ${prev}"
            fi
        fi
        fail "Smoke test returned HTTP ${http_code}. Release ${RELEASE_NAME} NOT activated."
    fi

    log "Smoke test passed (HTTP ${http_code})."

    # 9. Symlink already points to new release from smoke test step
    log "Release active: $(run_remote "readlink -f '${CURRENT_LINK}'")"

    # 10. Cleanup old releases
    cleanup_releases
}

# ─── Cleanup ─────────────────────────────────────────────────────────────────
cleanup_releases() {
    log "Cleaning up old releases (keeping ${KEEP_RELEASES})..."
    run_remote "
        cd '${RELEASES_DIR}'
        ls -1t | tail -n +$((KEEP_RELEASES + 1)) | while read -r old; do
            echo '[deploy] Removing old release: '\$old
            rm -rf '${RELEASES_DIR}/'\$old
        done
    "
}

# ─── Main ────────────────────────────────────────────────────────────────────
main() {
    if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
        usage
    fi

    log "Starting deployment: ${RELEASE_NAME}"
    preflight
    deploy
    log "Deployment complete."
}

main "$@"
