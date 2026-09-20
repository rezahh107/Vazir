#!/usr/bin/env bash
set -euo pipefail

ROOT="${1:-$(pwd)}"
ROOT="$(cd "$ROOT" && pwd -P)"
source "$ROOT/scripts/release/release-lib.sh"

for command in zip unzip sha256sum php; do release_require_command "$command"; done
release_assert_version_mirrors "$ROOT"
release_assert_vazirmatn_identity "$ROOT"
VERSION="$(release_plugin_version "$ROOT")"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

expect_fail() {
  local label="$1"; shift
  if "$@" >/tmp/vazir-release-negative.out 2>&1; then
    echo "Negative release case unexpectedly passed: $label" >&2
    cat /tmp/vazir-release-negative.out >&2 || true
    exit 1
  fi
}

ZIP1="$(bash "$ROOT/scripts/release/build-release.sh" "$ROOT" "$VERSION" "$TMP/build-a" | head -n1)"
ZIP2="$(bash "$ROOT/scripts/release/build-release.sh" "$ROOT" "$VERSION" "$TMP/build-b" | head -n1)"
SHA1="$(sha256sum "$ZIP1" | awk '{print $1}')"
SHA2="$(sha256sum "$ZIP2" | awk '{print $1}')"
[[ "$SHA1" == "$SHA2" ]] || { echo 'Canonical builds are not byte-reproducible.' >&2; exit 1; }
bash "$ROOT/scripts/release/validate-release.sh" "$ROOT" "$ZIP1" "$VERSION" "$SHA1" >/dev/null

expect_fail 'checksum mismatch' bash "$ROOT/scripts/release/validate-release.sh" "$ROOT" "$ZIP1" "$VERSION" "$(printf '0%.0s' {1..64})"
expect_fail 'invalid version' bash "$ROOT/scripts/release/build-release.sh" "$ROOT" 'not-a-version' "$TMP/invalid-version"

MIRROR_ROOT="$TMP/mirror-root"
mkdir -p "$MIRROR_ROOT"
tar -C "$ROOT" --exclude=.git --exclude=build -cf - . | tar -C "$MIRROR_ROOT" -xf -
perl -0pi -e "s/const VAZIR_FONT_VERSION\s*=\s*'[^']+';/const VAZIR_FONT_VERSION = '9.9.9';/" "$MIRROR_ROOT/vazir-font-wp.php"
expect_fail 'version mirror mismatch' bash "$MIRROR_ROOT/scripts/release/build-release.sh" "$MIRROR_ROOT" "$VERSION" "$TMP/mirror-build"

mutated_zip() {
  local name="$1"
  local dir="$TMP/$name"
  mkdir -p "$dir/unpacked"
  unzip -q "$ZIP1" -d "$dir/unpacked"
  printf '%s\n' "$dir"
}

repack() {
  local dir="$1"
  local root_name="${2:-$VAZIR_RELEASE_SLUG}"
  local out="$dir/$VAZIR_RELEASE_SLUG-$VERSION.zip"
  rm -f "$out"
  (
    cd "$dir/unpacked"
    find "$root_name" -type f -print0 | LC_ALL=C sort -z | xargs -0 zip -X -q "$out"
  )
  printf '%s\n' "$out"
}

case_dir="$(mutated_zip missing-font)"
rm "$case_dir/unpacked/$VAZIR_RELEASE_SLUG/assets/fonts/vazirmatn-900.woff2"
case_zip="$(repack "$case_dir")"
expect_fail 'required font missing' bash "$ROOT/scripts/release/validate-release.sh" "$ROOT" "$case_zip" "$VERSION" "$(sha256sum "$case_zip" | awk '{print $1}')"

case_dir="$(mutated_zip missing-license)"
rm "$case_dir/unpacked/$VAZIR_RELEASE_SLUG/assets/fonts/OFL.txt"
case_zip="$(repack "$case_dir")"
expect_fail 'license missing' bash "$ROOT/scripts/release/validate-release.sh" "$ROOT" "$case_zip" "$VERSION" "$(sha256sum "$case_zip" | awk '{print $1}')"

case_dir="$(mutated_zip missing-provenance)"
rm "$case_dir/unpacked/$VAZIR_RELEASE_SLUG/assets/fonts/Vazirmatn-PROVENANCE.md"
case_zip="$(repack "$case_dir")"
expect_fail 'provenance missing' bash "$ROOT/scripts/release/validate-release.sh" "$ROOT" "$case_zip" "$VERSION" "$(sha256sum "$case_zip" | awk '{print $1}')"

case_dir="$(mutated_zip forbidden-dev)"
mkdir -p "$case_dir/unpacked/$VAZIR_RELEASE_SLUG/tests"
printf 'private dev file\n' > "$case_dir/unpacked/$VAZIR_RELEASE_SLUG/tests/private.txt"
case_zip="$(repack "$case_dir")"
expect_fail 'forbidden development file shipped' bash "$ROOT/scripts/release/validate-release.sh" "$ROOT" "$case_zip" "$VERSION" "$(sha256sum "$case_zip" | awk '{print $1}')"

case_dir="$(mutated_zip licensed-package)"
printf 'licensed bytes placeholder\n' > "$case_dir/unpacked/$VAZIR_RELEASE_SLUG/gravityforms.zip"
case_zip="$(repack "$case_dir")"
expect_fail 'licensed Gravity package shipped' bash "$ROOT/scripts/release/validate-release.sh" "$ROOT" "$case_zip" "$VERSION" "$(sha256sum "$case_zip" | awk '{print $1}')"

case_dir="$(mutated_zip wrong-entrypoint)"
mv "$case_dir/unpacked/$VAZIR_RELEASE_SLUG/vazir-font-wp.php" "$case_dir/unpacked/$VAZIR_RELEASE_SLUG/not-the-plugin.php"
case_zip="$(repack "$case_dir")"
expect_fail 'wrong plugin entrypoint' bash "$ROOT/scripts/release/validate-release.sh" "$ROOT" "$case_zip" "$VERSION" "$(sha256sum "$case_zip" | awk '{print $1}')"

case_dir="$(mutated_zip wrong-root)"
mv "$case_dir/unpacked/$VAZIR_RELEASE_SLUG" "$case_dir/unpacked/wrong-root"
case_zip="$(repack "$case_dir" wrong-root)"
expect_fail 'wrong archive root' bash "$ROOT/scripts/release/validate-release.sh" "$ROOT" "$case_zip" "$VERSION" "$(sha256sum "$case_zip" | awk '{print $1}')"

MANIFEST="$TMP/release-manifest.json"
php "$ROOT/scripts/release/write-manifest.php" \
  --output="$MANIFEST" --mode=dry-run --source-sha="$(printf 'a%.0s' {1..40})" \
  --version="$VERSION" --zip="$ZIP1" --sha256="$SHA1" \
  --validation=PASS --smoke=PASS --settings=PASS --qualification=PASS \
  --publication=NOT_ATTEMPTED_DRY_RUN --run-id='contract-test' >/dev/null
php -r '$m=json_decode(file_get_contents($argv[1]),true); if(($m["publication"]??null)!=="NOT_ATTEMPTED_DRY_RUN") exit(1); if(($m["vazirmatn_release"]??null)!=="v33.003") exit(1);' "$MANIFEST"

printf 'VAZIR_RELEASE_CONTRACT_TESTS_PASS version=%s sha256=%s\n' "$VERSION" "$SHA1"
