#!/usr/bin/env bash
set -euo pipefail

ROOT="${1:-$(pwd)}"
ROOT="$(cd "$ROOT" && pwd -P)"
RELEASE_WORKFLOW="$ROOT/.github/workflows/release.yml"
EVIDENCE_WORKFLOW="$ROOT/.github/workflows/product-evidence-lab.yml"
VALIDATOR="$ROOT/scripts/release/validate-release-inputs.sh"

for file in "$RELEASE_WORKFLOW" "$EVIDENCE_WORKFLOW" "$VALIDATOR"; do
  [[ -f "$file" ]] || { echo "Missing release-boundary contract subject: $file" >&2; exit 1; }
done

expect_fail() {
  local label="$1"; shift
  if "$@" >/tmp/vazir-release-boundary-negative.out 2>&1; then
    echo "Negative release-boundary case unexpectedly passed: $label" >&2
    cat /tmp/vazir-release-boundary-negative.out >&2 || true
    exit 1
  fi
}

# No workflow_dispatch string input may be embedded into Bash source. Declarative
# Action inputs/if/env expressions are intentionally outside this check.
unsafe_run_interpolation="$({
  awk '
    function leading_spaces(s, t) { t=s; sub(/[^ ].*/, "", t); return length(t) }
    /^[ ]+run:[ ]*/ {
      if ($0 ~ /\$\{\{[[:space:]]*inputs\./) print NR ":" $0
      run_indent=leading_spaces($0)
      in_multiline=($0 ~ /run:[ ]*[|>][+-]?[ ]*$/)
      next
    }
    in_multiline {
      if ($0 !~ /^[ ]*$/ && leading_spaces($0) <= run_indent) in_multiline=0
      if (in_multiline && $0 ~ /\$\{\{[[:space:]]*inputs\./) print NR ":" $0
    }
  ' "$RELEASE_WORKFLOW"
} || true)"
if [[ -n "$unsafe_run_interpolation" ]]; then
  echo 'Direct workflow-dispatch input interpolation remains inside run shell source:' >&2
  printf '%s\n' "$unsafe_run_interpolation" >&2
  exit 1
fi

# Manual input flow must be explicit and data-safe.
grep -Fq 'INPUT_VERSION: ${{ inputs.version }}' "$RELEASE_WORKFLOW"
grep -Fq 'INPUT_APPROVED_SHA: ${{ inputs.approved_sha }}' "$RELEASE_WORKFLOW"
grep -Fq 'INPUT_MODE: ${{ inputs.mode }}' "$RELEASE_WORKFLOW"
grep -Fq 'validate-release-inputs.sh "$INPUT_MODE" "$INPUT_VERSION" "$INPUT_APPROVED_SHA"' "$RELEASE_WORKFLOW"

valid_sha='8207fccea7377d6837063d00f2eec3af7d4f933a'
other_sha='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
bash "$VALIDATOR" publish 1.3.0 "$valid_sha" "$valid_sha" >/dev/null
bash "$VALIDATOR" dry-run '' '' "$valid_sha" >/dev/null
bash "$VALIDATOR" dry-run 1.3.0 '' "$valid_sha" >/dev/null
expect_fail 'publish approved SHA mismatch' bash "$VALIDATOR" publish 1.3.0 "$other_sha" "$valid_sha"
expect_fail 'invalid production version' bash "$VALIDATOR" publish '1.3' "$valid_sha" "$valid_sha"
expect_fail 'invalid approved SHA' bash "$VALIDATOR" publish 1.3.0 'main' "$valid_sha"
expect_fail 'invalid mode' bash "$VALIDATOR" 'publish;echo nope' 1.3.0 "$valid_sha" "$valid_sha"

sentinel="$(mktemp -u /tmp/vazir-release-input-injection.XXXXXX)"
rm -f "$sentinel"
malicious_values=(
  "1.3.0'; touch $sentinel; #"
  "1.3.0; touch $sentinel"
  "\$(touch $sentinel)"
  "\`touch $sentinel\`"
  $'1.3.0\ntouch '"$sentinel"
  '1.3.0 && false'
)
for payload in "${malicious_values[@]}"; do
  expect_fail 'shell-significant version input' bash "$VALIDATOR" publish "$payload" "$valid_sha" "$valid_sha"
  [[ ! -e "$sentinel" ]] || { echo 'Version input executed shell content.' >&2; exit 1; }
  expect_fail 'shell-significant approved SHA input' bash "$VALIDATOR" publish 1.3.0 "$payload" "$valid_sha"
  [[ ! -e "$sentinel" ]] || { echo 'approved_sha input executed shell content.' >&2; exit 1; }
done
rm -f "$sentinel"

# Every external Action used by the PR-owned release surfaces must be immutable.
check_full_sha_uses() {
  local workflow="$1"
  local bad
  bad="$(grep -E '^[[:space:]]*uses:[[:space:]]*[^.][^@]*@' "$workflow" | grep -Ev '@[0-9a-f]{40}([[:space:]]+#.*)?$' || true)"
  if [[ -n "$bad" ]]; then
    echo "Mutable external Action reference remains in $workflow:" >&2
    printf '%s\n' "$bad" >&2
    exit 1
  fi
}
check_full_sha_uses "$RELEASE_WORKFLOW"
check_full_sha_uses "$EVIDENCE_WORKFLOW"

if grep -Eq 'actions/(download-artifact@v5|setup-node@v6)' "$RELEASE_WORKFLOW" "$EVIDENCE_WORKFLOW"; then
  echo 'Mutable release-path Action major tag remains.' >&2
  exit 1
fi
grep -Fq 'actions/download-artifact@634f93cb2916e3fdff6788551b99b062d0335ce0 # v5' "$RELEASE_WORKFLOW"
grep -Fq 'actions/setup-node@249970729cb0ef3589644e2896645e5dc5ba9c38 # v6' "$RELEASE_WORKFLOW"
grep -Fq 'actions/download-artifact@634f93cb2916e3fdff6788551b99b062d0335ce0 # v5' "$EVIDENCE_WORKFLOW"

# Permission and exact-artifact verification locks.
[[ "$(grep -c '^[[:space:]]*contents: write[[:space:]]*$' "$RELEASE_WORKFLOW")" -eq 1 ]]
! grep -Eq '^[[:space:]]*(actions|packages|deployments|id-token): write[[:space:]]*$' "$RELEASE_WORKFLOW"
grep -Fq 'sha256sum "$ZIP"' "$EVIDENCE_WORKFLOW"
grep -Fq 'plugin_zip_sha256' "$EVIDENCE_WORKFLOW"
grep -Fq "hash_file( 'sha256', \$options['zip'] )" "$ROOT/scripts/release/write-manifest.php"

printf '%s\n' 'VAZIR_RELEASE_BOUNDARY_CONTRACT_TESTS_PASS'
