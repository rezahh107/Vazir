#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 4 ]]; then
  echo "usage: $0 <gravityforms.zip> <expected-size> <expected-sha256> <expected-version>" >&2
  exit 64
fi

zip_path="$1"
expected_size="$2"
expected_sha="$3"
expected_version="$4"

[[ -f "$zip_path" ]] || { echo "Gravity Forms package not found: $zip_path" >&2; exit 1; }

actual_size="$(stat -c '%s' "$zip_path")"
[[ "$actual_size" = "$expected_size" ]] || {
  echo "Gravity Forms package size mismatch: expected $expected_size, got $actual_size" >&2
  exit 1
}

actual_sha="$(sha256sum "$zip_path" | awk '{print $1}')"
[[ "$actual_sha" = "$expected_sha" ]] || {
  echo "Gravity Forms package SHA-256 mismatch: expected $expected_sha, got $actual_sha" >&2
  exit 1
}

listing="$(mktemp)"
trap 'rm -f "$listing"' EXIT
unzip -Z1 "$zip_path" > "$listing"

if grep -Eq '(^/|(^|/)\.\.(/|$))' "$listing"; then
  echo "Gravity Forms package contains an unsafe archive path" >&2
  exit 1
fi

entrypoint='gravityforms/gravityforms.php'
grep -Fxq "$entrypoint" "$listing" || {
  echo "Gravity Forms package entrypoint is missing: $entrypoint" >&2
  exit 1
}

actual_version="$(unzip -p "$zip_path" "$entrypoint" | sed -n 's/^Version:[[:space:]]*//p' | head -n 1 | tr -d '\r')"
[[ "$actual_version" = "$expected_version" ]] || {
  echo "Gravity Forms package version mismatch: expected $expected_version, got ${actual_version:-<missing>}" >&2
  exit 1
}

printf 'Gravity Forms package verified: size=%s sha256=%s version=%s\n' "$actual_size" "$actual_sha" "$actual_version"
