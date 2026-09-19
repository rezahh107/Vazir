#!/usr/bin/env bash
set -euo pipefail

repo_root="${1:-$(pwd)}"
workflow="$repo_root/.github/workflows/product-evidence-lab.yml"

[[ -f "$workflow" ]] || { echo "Product Evidence Lab workflow not found: $workflow" >&2; exit 1; }

mapfile -t invocations < <(grep -nE '(^|[[:space:]])wp eval-file ' "$workflow" || true)
[[ "${#invocations[@]}" -gt 0 ]] || { echo "No Product Evidence Lab wp eval-file invocations found." >&2; exit 1; }
[[ "${#invocations[@]}" -eq 11 ]] || {
  printf 'Expected 11 current Product Evidence Lab wp eval-file invocations, found %s.\n' "${#invocations[@]}" >&2
  printf '%s\n' "${invocations[@]}" >&2
  exit 1
}

for invocation in "${invocations[@]}"; do
  [[ "$invocation" == *'--use-include'* ]] || {
    echo "Repository-owned PHP harness invocation is missing --use-include: $invocation" >&2
    exit 1
  }
done

declare -A expected_counts=(
  ["tests/product-evidence-lab/core/capture-runtime.php"]=1
  ["tests/gravityforms-evidence-lab/setup-fixtures.php"]=2
  ["tests/product-evidence-lab/profiles/gravityflow/setup-fixtures.php"]=2
  ["tests/product-evidence-lab/profiles/gravityview/setup-fixtures.php"]=2
  ["tests/gravityforms-evidence-lab/runtime-contract.php"]=1
  ["tests/product-evidence-lab/profiles/gravityflow/runtime-contract.php"]=1
  ["tests/product-evidence-lab/profiles/gravityview/runtime-contract.php"]=1
  ["tests/product-evidence-lab/profiles/gravity-stack/runtime-contract.php"]=1
)

for harness in "${!expected_counts[@]}"; do
  harness_path="$repo_root/$harness"
  [[ -f "$harness_path" ]] || { echo "Expected PHP harness is missing: $harness" >&2; exit 1; }
  grep -Eq '^declare\(strict_types=1\);[[:space:]]*$' "$harness_path" || {
    echo "Expected strict_types declaration is missing from harness: $harness" >&2
    exit 1
  }

  count="$(printf '%s\n' "${invocations[@]}" | grep -Fc "$harness" || true)"
  [[ "$count" -eq "${expected_counts[$harness]}" ]] || {
    printf 'Harness %s expected %s workflow invocations, found %s.\n' "$harness" "${expected_counts[$harness]}" "$count" >&2
    exit 1
  }
done

printf 'Product Evidence Lab eval-file include-mode contract verified: %s invocations, all strict harnesses preserved.\n' "${#invocations[@]}"
