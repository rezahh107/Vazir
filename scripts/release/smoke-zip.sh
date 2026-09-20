#!/usr/bin/env bash
set -euo pipefail

ROOT="${1:-.}"
ZIP="${2:-}"
VERSION="${3:-}"
WP_PATH="${4:-/tmp/vazir-release-wordpress}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=release-lib.sh
source "$SCRIPT_DIR/release-lib.sh"

release_require_command wp
release_require_command curl
[[ -f "$ZIP" ]] || release_fail "ZIP does not exist: $ZIP"
release_is_production_version "$VERSION" || release_fail "Invalid smoke-test version: $VERSION"

WP_VERSION="${VAZIR_RELEASE_WP_VERSION:-7.1}"
DB_NAME="${VAZIR_RELEASE_DB_NAME:-wordpress}"
DB_USER="${VAZIR_RELEASE_DB_USER:-root}"
DB_PASS="${VAZIR_RELEASE_DB_PASS:-vazir-release-root}"
DB_HOST="${VAZIR_RELEASE_DB_HOST:-127.0.0.1:3306}"
BASE_URL="${VAZIR_RELEASE_BASE_URL:-http://127.0.0.1:8090}"
ADMIN_USER="${VAZIR_RELEASE_ADMIN_USER:-admin}"
ADMIN_PASS="${VAZIR_RELEASE_ADMIN_PASSWORD:-admin-password}"

rm -rf "$WP_PATH"
mkdir -p "$WP_PATH"
wp core download --version="$WP_VERSION" --path="$WP_PATH" --force
wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST" --path="$WP_PATH" --skip-check
wp core install --url="$BASE_URL" --title='Vazir Release Artifact Smoke' --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASS" --admin_email='vazir-release@example.invalid' --skip-email --path="$WP_PATH"
wp plugin install "$ZIP" --activate --force --path="$WP_PATH"

ACTUAL_VERSION="$(wp plugin get "$VAZIR_RELEASE_SLUG" --field=version --path="$WP_PATH")"
[[ "$ACTUAL_VERSION" == "$VERSION" ]] || release_fail "Installed plugin version mismatch: expected=$VERSION actual=$ACTUAL_VERSION"
wp plugin status "$VAZIR_RELEASE_SLUG" --path="$WP_PATH" >/dev/null
wp eval-file "$ROOT/tests/release/installed-smoke.php" --use-include --path="$WP_PATH"

wp language core install fa_IR --activate --path="$WP_PATH"
ADMIN_ID="$(wp user get "$ADMIN_USER" --field=ID --path="$WP_PATH")"
wp user meta update "$ADMIN_ID" admin_color midnight --path="$WP_PATH" >/dev/null
wp eval '$options = VazirFontPlugin::get_options(); $options["font_weights"] = ["400", "700"]; VazirFontPlugin::update_options($options);' --path="$WP_PATH"

printf 'VAZIR_RELEASE_ZIP_SMOKE_PASS version=%s wordpress=%s path=%s\n' "$VERSION" "$WP_VERSION" "$WP_PATH"
