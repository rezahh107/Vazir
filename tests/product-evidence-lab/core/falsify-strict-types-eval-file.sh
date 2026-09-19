#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 2 ]]; then
  echo "usage: $0 <repo-root> <wordpress-path>" >&2
  exit 64
fi

repo_root="$1"
wp_path="$2"
probe="$repo_root/tests/product-evidence-lab/core/strict-types-eval-file-probe.php"

[[ -f "$probe" ]] || { echo "strict-types probe not found: $probe" >&2; exit 1; }
[[ -d "$wp_path" ]] || { echo "WordPress path not found: $wp_path" >&2; exit 1; }

set +e
default_output="$(wp eval-file "$probe" --path="$wp_path" 2>&1)"
default_status=$?
set -e

if [[ $default_status -eq 0 ]]; then
  echo "Default wp eval-file unexpectedly accepted a strict_types harness; causal falsification did not reproduce." >&2
  printf '%s\n' "$default_output" >&2
  exit 1
fi

if ! grep -Fq 'strict_types declaration must be the very first statement in the script' <<<"$default_output"; then
  echo "Default wp eval-file failed, but not with the expected strict_types evaluation fatal." >&2
  printf '%s\n' "$default_output" >&2
  exit 1
fi

include_output="$(wp eval-file "$probe" --use-include --path="$wp_path" 2>&1)"
grep -Fq 'VAZIR_LAB_STRICT_TYPES_INCLUDE_OK' <<<"$include_output" || {
  echo "wp eval-file --use-include did not execute the strict_types probe successfully." >&2
  printf '%s\n' "$include_output" >&2
  exit 1
}

printf 'Strict-types eval-file mechanism falsified: default mode rejected; --use-include executed successfully.\n'
