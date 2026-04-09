#!/usr/bin/env bash
#
# inspect.sh — Inspect JSON API responses from MyImouto smoke tests
#
# Usage:
#   ./scripts/inspect.sh                              # default smoke endpoints
#   ./scripts/inspect.sh /tmp/search.json /tmp/count.json  # specific files
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env.deploy"
if [[ -f "$ENV_FILE" ]]; then
    # shellcheck source=/dev/null
    source "$ENV_FILE"
fi

SMOKE_URL="${SMOKE_URL:-http://127.0.0.1}"
TMPDIR="${TMPDIR:-/tmp}"

log() { echo "[inspect] $*"; }

if [[ $# -gt 0 ]]; then
    # Inspect specific files
    FILES=("$@")
else
    # Fetch default smoke endpoints
    log "Fetching smoke endpoints from ${SMOKE_URL}..."
    SEARCH_FILE="${TMPDIR}/myimouto_search.json"
    COUNT_FILE="${TMPDIR}/myimouto_count.json"
    LIMIT_FILE="${TMPDIR}/myimouto_limit.json"

    echo "SEARCH $(curl -s -o "$SEARCH_FILE" -w "%{http_code}" "${SMOKE_URL}/post/index.json?api_version=2&tags=rating:safe")"
    echo "COUNT  $(curl -s -o "$COUNT_FILE" -w "%{http_code}" "${SMOKE_URL}/post/count.json?tags=rating:safe")"
    echo "LIMIT  $(curl -s -o "$LIMIT_FILE" -w "%{http_code}" "${SMOKE_URL}/post/index.json?tags=a+b+c+d+e+f+g")"

    FILES=("$SEARCH_FILE" "$COUNT_FILE" "$LIMIT_FILE")
fi

# Inspect JSON files
python3 - "${FILES[@]}" <<'PY'
import json, sys

for path in sys.argv[1:]:
    label = path.rsplit('/', 1)[-1].replace('.json', '').upper()
    try:
        with open(path) as f:
            d = json.load(f)
        print(f"{label}_KEYS {sorted(d.keys())}")
        print(f"{label}_SUCCESS {d.get('success')}")
        if 'reason' in d:
            print(f"{label}_REASON {d.get('reason')}")
        if 'count' in d:
            print(f"{label}_COUNT {d.get('count')}")
    except Exception as e:
        print(f"{label}_ERROR {e}")
PY
