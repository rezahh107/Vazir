#!/usr/bin/env bash
set -euo pipefail

VAZIR_RELEASE_SLUG='vazir-font-wp'
VAZIR_RELEASE_ENTRYPOINT='vazir-font-wp.php'
VAZIR_VAZIRMATN_RELEASE='v33.003'

release_fail() {
  echo "VAZIR_RELEASE_FAIL: $*" >&2
  return 1
}

release_require_command() {
  command -v "$1" >/dev/null 2>&1 || release_fail "Required command is unavailable: $1"
}

release_is_production_version() {
  [[ "${1:-}" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]] && [[ "$1" != '0.0.0' ]]
}

release_plugin_version() {
  local root="${1:-.}"
  sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "$root/$VAZIR_RELEASE_ENTRYPOINT" | head -n1 | tr -d '\r'
}

release_constant_version() {
  local root="${1:-.}"
  sed -n "s/^[[:space:]]*const[[:space:]]\+VAZIR_FONT_VERSION[[:space:]]*=[[:space:]]*'\([^']*\)';.*/\1/p" "$root/$VAZIR_RELEASE_ENTRYPOINT" | head -n1 | tr -d '\r'
}

release_assert_version_mirrors() {
  local root="${1:-.}"
  local header constant
  header="$(release_plugin_version "$root")"
  constant="$(release_constant_version "$root")"
  [[ -n "$header" ]] || release_fail 'Plugin header Version is missing.'
  [[ -n "$constant" ]] || release_fail 'VAZIR_FONT_VERSION is missing.'
  [[ "$header" == "$constant" ]] || release_fail "Version mirror mismatch: header=$header constant=$constant"
  release_is_production_version "$header" || release_fail "Invalid plugin production version: $header"
}

release_runtime_files() {
  local root="${1:-.}"
  (
    cd "$root"
    printf '%s\n' "$VAZIR_RELEASE_ENTRYPOINT" 'README.md'
    find includes -type f -name '*.php' -print
    find assets/css -type f -name '*.css' -print
    find assets/js -type f -name '*.js' -print
    find assets/fonts -maxdepth 1 -type f \( -name '*.woff2' -o -name 'OFL.txt' -o -name 'AUTHORS.txt' -o -name 'Vazirmatn-PROVENANCE.md' \) -print
    if [[ -d languages ]]; then
      find languages -type f \( -name '*.pot' -o -name '*.po' -o -name '*.mo' \) -print
    fi
  ) | LC_ALL=C sort -u
}

release_required_runtime_files() {
  cat <<'FILES'
README.md
vazir-font-wp.php
includes/class-vazirfont-admin-settings.php
includes/class-vazirfont-gravityforms-integration.php
includes/class-vazirfont-loader.php
assets/css/admin.css
assets/css/vazir-fonts.css
assets/js/admin.js
assets/fonts/AUTHORS.txt
assets/fonts/OFL.txt
assets/fonts/Vazirmatn-PROVENANCE.md
assets/fonts/vazirmatn-300.woff2
assets/fonts/vazirmatn-400.woff2
assets/fonts/vazirmatn-500.woff2
assets/fonts/vazirmatn-700.woff2
assets/fonts/vazirmatn-900.woff2
languages/vazir-font-wp.pot
FILES
}

release_vazirmatn_hashes() {
  cat <<'HASHES'
a3aa104f9a256734ca6769e017b4a2697c3036221e13758e0995a0cbeea969c4  assets/fonts/vazirmatn-300.woff2
e382101336c6eb32cfb31381c027d02d2e0354bad08f6a395d4088beb3db3d91  assets/fonts/vazirmatn-400.woff2
3333e31188a2b628db8780ca22fd5aad85bc083ccee9beb8d4d52db18cb98d48  assets/fonts/vazirmatn-500.woff2
836fae7d42d83faa249bc00e0099592be98a1fa260d22d82f269b6091e585627  assets/fonts/vazirmatn-700.woff2
e65a05523e6c0a434265913805746ebe6ed48af843e6126a936d06f69d7d47ad  assets/fonts/vazirmatn-900.woff2
17e355067c8284f47743a1ee3b1ef7ff684ff0601eda357f9353b10b3016ab31  assets/fonts/OFL.txt
b57746a5f7002c0974c76c32af74079ff7ef1aaf8f35495e9409cfa1eb11e1ca  assets/fonts/AUTHORS.txt
HASHES
}

release_assert_vazirmatn_identity() {
  local root="${1:-.}"
  local provenance="$root/assets/fonts/Vazirmatn-PROVENANCE.md"
  [[ -f "$provenance" ]] || release_fail 'Vazirmatn provenance file is missing.'
  grep -Fq 'Upstream release/tag: `v33.003`' "$provenance" || release_fail 'Vazirmatn provenance release identity mismatch.'

  local expected path actual
  while read -r expected path; do
    [[ -f "$root/$path" ]] || release_fail "Required pinned font/license file is missing: $path"
    actual="$(sha256sum "$root/$path" | awk '{print $1}')"
    [[ "$actual" == "$expected" ]] || release_fail "Pinned Vazirmatn byte mismatch: $path expected=$expected actual=$actual"
    grep -Fq "\`$expected\`" "$provenance" || release_fail "Provenance does not record pinned digest for $path"
  done < <(release_vazirmatn_hashes)
}
