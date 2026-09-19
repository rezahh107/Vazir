#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 4 ]]; then
  echo "usage: $0 <profile> <package-dir> <wordpress-plugin-dir> <evidence-dir>" >&2
  exit 64
fi
profile="$1"; package_dir="$2"; plugin_dir="$3"; evidence_dir="$4"
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$script_dir/package-identities.sh"
mkdir -p "$package_dir" "$plugin_dir" "$evidence_dir"
case "$profile" in
  gravityforms) packages=(gf) ;;
  gravityflow) packages=(gf flow) ;;
  gravityview) packages=(gf view) ;;
  gravity-stack) packages=(gf flow view) ;;
  *) echo "Unsupported licensed evidence profile: $profile" >&2; exit 64 ;;
esac
identity_file="$evidence_dir/package-identities.jsonl"; : > "$identity_file"
for package in "${packages[@]}"; do
  case "$package" in
    gf) label="$VAZIR_LAB_GF_LABEL"; drive_id="$VAZIR_LAB_GF_DRIVE_ID"; filename="$VAZIR_LAB_GF_FILENAME"; size="$VAZIR_LAB_GF_SIZE"; sha="$VAZIR_LAB_GF_SHA256"; entrypoint="$VAZIR_LAB_GF_ENTRYPOINT"; plugin_name="$VAZIR_LAB_GF_PLUGIN_NAME"; version="$VAZIR_LAB_GF_VERSION" ;;
    flow) label="$VAZIR_LAB_FLOW_LABEL"; drive_id="$VAZIR_LAB_FLOW_DRIVE_ID"; filename="$VAZIR_LAB_FLOW_FILENAME"; size="$VAZIR_LAB_FLOW_SIZE"; sha="$VAZIR_LAB_FLOW_SHA256"; entrypoint="$VAZIR_LAB_FLOW_ENTRYPOINT"; plugin_name="$VAZIR_LAB_FLOW_PLUGIN_NAME"; version="$VAZIR_LAB_FLOW_VERSION" ;;
    view) label="$VAZIR_LAB_VIEW_LABEL"; drive_id="$VAZIR_LAB_VIEW_DRIVE_ID"; filename="$VAZIR_LAB_VIEW_FILENAME"; size="$VAZIR_LAB_VIEW_SIZE"; sha="$VAZIR_LAB_VIEW_SHA256"; entrypoint="$VAZIR_LAB_VIEW_ENTRYPOINT"; plugin_name="$VAZIR_LAB_VIEW_PLUGIN_NAME"; version="$VAZIR_LAB_VIEW_VERSION" ;;
  esac
  zip_path="$package_dir/$filename"
  curl -L --fail --retry 4 --retry-all-errors -o "$zip_path" "https://drive.usercontent.google.com/download?id=${drive_id}&export=download&confirm=t"
  bash "$script_dir/verify-package.sh" "$label" "$zip_path" "$size" "$sha" "$entrypoint" "$plugin_name" "$version"
  unzip -q "$zip_path" -d "$plugin_dir"
  printf '{"label":"%s","drive_id":"%s","filename":"%s","size":%s,"sha256":"%s","entrypoint":"%s","plugin_name":"%s","version":"%s"}\n' "$label" "$drive_id" "$filename" "$size" "$sha" "$entrypoint" "$plugin_name" "$version" >> "$identity_file"
done
