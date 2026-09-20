#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 4 ]]; then
  echo "usage: $0 <mode> <version> <approved-sha> <dispatch-sha>" >&2
  exit 64
fi

MODE="$1"
VERSION="$2"
APPROVED_SHA="$3"
DISPATCH_SHA="$4"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=release-lib.sh
source "$SCRIPT_DIR/release-lib.sh"

release_is_exact_commit_sha() {
  [[ "${1:-}" =~ ^[0-9a-f]{40}$ ]]
}

case "$MODE" in
  dry-run)
    [[ -z "$APPROVED_SHA" ]] || release_fail 'approved_sha is publish-only and must be empty for dry-run.'
    if [[ -n "$VERSION" ]]; then
      release_is_production_version "$VERSION" || release_fail "Invalid dry-run production version: $VERSION"
    fi
    ;;
  publish)
    release_is_production_version "$VERSION" || release_fail "Invalid production release version: $VERSION"
    release_is_exact_commit_sha "$APPROVED_SHA" || release_fail 'approved_sha must be exactly 40 lowercase hexadecimal characters.'
    release_is_exact_commit_sha "$DISPATCH_SHA" || release_fail 'dispatch SHA must be exactly 40 lowercase hexadecimal characters.'
    [[ "$APPROVED_SHA" == "$DISPATCH_SHA" ]] || release_fail 'approved_sha does not equal the exact workflow dispatch SHA.'
    ;;
  *)
    release_fail "Unsupported release mode: $MODE"
    ;;
esac

printf 'VAZIR_RELEASE_INPUTS_VALID mode=%s version=%s approved_sha=%s\n' "$MODE" "${VERSION:-UNSET}" "${APPROVED_SHA:-UNSET}"
