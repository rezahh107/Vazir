#!/usr/bin/env bash
set -euo pipefail

repo_root="${1:-$(pwd)}"
workflow="$repo_root/.github/workflows/product-evidence-lab.yml"
safety="$repo_root/tests/product-evidence-lab/core/artifact-safety.sh"

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
mkdir -p "$tmp/artifacts"
output="$tmp/github-output"

: > "$output"
GITHUB_OUTPUT="$output" bash "$safety" "$tmp/artifacts"
grep -Fxq 'safe=true' "$output"

: > "$output"
touch "$tmp/artifacts/forbidden.zip"
if GITHUB_OUTPUT="$output" bash "$safety" "$tmp/artifacts"; then
  echo 'Unsafe artifact directory unexpectedly passed.' >&2
  exit 1
fi
if grep -Fxq 'safe=true' "$output"; then
  echo 'Unsafe artifact directory unexpectedly emitted safety proof.' >&2
  exit 1
fi
rm -f "$tmp/artifacts/forbidden.zip"

safety_block="$(awk '/- name: Assert licensed package bytes are outside evidence/{flag=1} flag{print} flag && /^      - name:/ && !/Assert licensed package bytes are outside evidence/{exit}' "$workflow")"
grep -Fq 'id: artifact_safety' <<<"$safety_block"
grep -Fq 'if: always()' <<<"$safety_block"
grep -Fq 'bash tests/product-evidence-lab/core/artifact-safety.sh "$VAZIR_LAB_ARTIFACT_DIR"' <<<"$safety_block"

success_block="$(awk '/- name: Upload bounded profile evidence/{flag=1} flag{print} /retention-days: 30/{exit}' "$workflow")"
grep -Fq "if: \${{ success() && steps.artifact_safety.outputs.safe == 'true' }}" <<<"$success_block"
grep -Fq '/tmp/vazir-lab-artifacts/**' <<<"$success_block"
grep -Fq '!/tmp/vazir-lab-artifacts/**/*.zip' <<<"$success_block"

safe_failure_block="$(awk '/- name: Upload bounded profile failure diagnostics/{flag=1} flag{print} /retention-days: 7/{exit}' "$workflow")"
grep -Fq "if: \${{ failure() && steps.artifact_safety.outputs.safe == 'true' }}" <<<"$safe_failure_block"
grep -Fq '/tmp/vazir-lab-artifacts/**' <<<"$safe_failure_block"
grep -Fq '!/tmp/vazir-lab-artifacts/**/*.zip' <<<"$safe_failure_block"
grep -Fq '/tmp/vazir-lab-wp-server.log' <<<"$safe_failure_block"

unsafe_fallback_block="$(awk '/- name: Upload unsafe-directory log-only diagnostics/{flag=1} flag{print} flag && /retention-days: 7/{exit}' "$workflow")"
grep -Fq "if: \${{ failure() && steps.artifact_safety.outputs.safe != 'true' }}" <<<"$unsafe_fallback_block"
grep -Fq '/tmp/vazir-lab-wp-server.log' <<<"$unsafe_fallback_block"
if grep -Fq 'vazir-lab-artifacts' <<<"$unsafe_fallback_block"; then
  echo 'Unsafe-directory fallback must not upload the artifact directory.' >&2
  exit 1
fi

# Eligibility truth table matching the locked workflow expressions.
# Earlier unrelated failure + safe proof keeps safe failure diagnostics eligible.
success_upload_eligible() { [[ "$1" = false && "$2" = true ]]; }
failure_upload_eligible() { [[ "$1" = true && "$2" = true ]]; }
unsafe_log_eligible() { [[ "$1" = true && "$2" != true ]]; }

success_upload_eligible false true
failure_upload_eligible true true
! success_upload_eligible false false
! failure_upload_eligible true false
unsafe_log_eligible true false

printf '%s\n' 'ARTIFACT SAFETY FALSIFICATION PASSED'
