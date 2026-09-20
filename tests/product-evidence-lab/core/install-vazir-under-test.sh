#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 3 ]]; then
  echo "usage: $0 <repository-root> <wordpress-path> <evidence-dir>" >&2
  exit 64
fi

repo_root="$1"
wp_path="$2"
evidence_dir="$3"
plugin_dir="$wp_path/wp-content/plugins/vazir-font-wp"
mkdir -p "$evidence_dir"

if [[ -n "${VAZIR_LAB_PLUGIN_ZIP:-}" ]]; then
  [[ -f "$VAZIR_LAB_PLUGIN_ZIP" ]] || { echo "Vazir artifact ZIP not found: $VAZIR_LAB_PLUGIN_ZIP" >&2; exit 1; }
  [[ -n "${VAZIR_LAB_PLUGIN_ZIP_SHA256:-}" ]] || { echo 'VAZIR_LAB_PLUGIN_ZIP_SHA256 is required for artifact mode.' >&2; exit 64; }
  actual_sha="$(sha256sum "$VAZIR_LAB_PLUGIN_ZIP" | awk '{print $1}')"
  [[ "$actual_sha" == "$VAZIR_LAB_PLUGIN_ZIP_SHA256" ]] || {
    echo "Vazir artifact checksum mismatch: expected=$VAZIR_LAB_PLUGIN_ZIP_SHA256 actual=$actual_sha" >&2
    exit 1
  }
  rm -rf "$plugin_dir"
  wp plugin install "$VAZIR_LAB_PLUGIN_ZIP" --force --path="$wp_path" >/dev/null
  [[ -f "$plugin_dir/vazir-font-wp.php" ]] || { echo 'Installed Vazir artifact has wrong root/entrypoint.' >&2; exit 1; }
  printf '{"installation_mode":"production_zip","zip_filename":"%s","sha256":"%s"}\n' \
    "$(basename "$VAZIR_LAB_PLUGIN_ZIP")" "$actual_sha" > "$evidence_dir/vazir-artifact-identity.json"
else
  rm -rf "$plugin_dir"
  ln -s "$repo_root" "$plugin_dir"
  printf '{"installation_mode":"repository_checkout","repository_sha":"%s"}\n' \
    "${VAZIR_LAB_REPOSITORY_SHA:-UNKNOWN}" > "$evidence_dir/vazir-artifact-identity.json"
fi
