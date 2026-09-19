#!/usr/bin/env bash
set -euo pipefail

artifact_dir="${1:-${VAZIR_LAB_ARTIFACT_DIR:-}}"
if [[ -z "$artifact_dir" ]]; then
  echo "artifact directory is required" >&2
  exit 64
fi
if [[ ! -d "$artifact_dir" ]]; then
  echo "artifact directory is unavailable: $artifact_dir" >&2
  exit 1
fi

unsafe="$(find "$artifact_dir" \( -type f -o -type l \) -iname '*.zip' -print -quit)"
if [[ -n "$unsafe" ]]; then
  echo "Licensed ZIP bytes must never enter the evidence artifact directory: $unsafe" >&2
  exit 1
fi

if [[ -n "${GITHUB_OUTPUT:-}" ]]; then
  echo 'safe=true' >> "$GITHUB_OUTPUT"
fi
printf 'Artifact directory verified safe for upload: %s\n' "$artifact_dir"
