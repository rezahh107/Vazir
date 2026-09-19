#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 7 ]]; then
  echo "usage: $0 <label> <package.zip> <expected-size> <expected-sha256> <expected-entrypoint> <expected-plugin-name> <expected-version>" >&2
  exit 64
fi

label="$1"
zip_path="$2"
expected_size="$3"
expected_sha="$4"
entrypoint="$5"
expected_plugin_name="$6"
expected_version="$7"

[[ -n "$label" ]] || { echo "Package label is required." >&2; exit 64; }
[[ -f "$zip_path" ]] || { echo "$label package not found: $zip_path" >&2; exit 1; }
[[ "$expected_size" =~ ^[0-9]+$ ]] || { echo "$label expected size is invalid." >&2; exit 64; }
[[ "$expected_sha" =~ ^[0-9a-f]{64}$ ]] || { echo "$label expected SHA-256 is invalid." >&2; exit 64; }
[[ "$entrypoint" != /* && "$entrypoint" != *'..'* ]] || { echo "$label expected entrypoint is unsafe." >&2; exit 64; }

actual_size="$(stat -c '%s' "$zip_path")"
[[ "$actual_size" = "$expected_size" ]] || { echo "$label package size mismatch: expected $expected_size, got $actual_size" >&2; exit 1; }
actual_sha="$(sha256sum "$zip_path" | awk '{print $1}')"
[[ "$actual_sha" = "$expected_sha" ]] || { echo "$label package SHA-256 mismatch: expected $expected_sha, got $actual_sha" >&2; exit 1; }
unzip -tqq "$zip_path" >/dev/null

python3 - "$zip_path" "$entrypoint" <<'PY'
import pathlib
import stat
import sys
import zipfile
archive, expected_entrypoint = sys.argv[1:]
with zipfile.ZipFile(archive) as zf:
    names = []
    for info in zf.infolist():
        name = info.filename.replace('\\', '/')
        path = pathlib.PurePosixPath(name)
        if path.is_absolute() or '..' in path.parts or name.startswith('/'):
            raise SystemExit(f'unsafe archive path: {name}')
        mode = (info.external_attr >> 16) & 0xFFFF
        if mode and stat.S_ISLNK(mode):
            raise SystemExit(f'archive symlink is not allowed: {name}')
        names.append(name)
    if names.count(expected_entrypoint) != 1:
        raise SystemExit(f'expected exactly one entrypoint {expected_entrypoint}; found {names.count(expected_entrypoint)}')
PY

header="$(unzip -p "$zip_path" "$entrypoint")"
actual_plugin_name="$(printf '%s\n' "$header" | sed -n 's/^[[:space:]*#\/]*Plugin Name:[[:space:]]*//p' | head -n 1 | tr -d '\r')"
actual_version="$(printf '%s\n' "$header" | sed -n 's/^[[:space:]*#\/]*Version:[[:space:]]*//p' | head -n 1 | tr -d '\r')"
[[ "$actual_plugin_name" = "$expected_plugin_name" ]] || { echo "$label plugin identity mismatch: expected '$expected_plugin_name', got '${actual_plugin_name:-<missing>}'" >&2; exit 1; }
[[ "$actual_version" = "$expected_version" ]] || { echo "$label package version mismatch: expected $expected_version, got ${actual_version:-<missing>}" >&2; exit 1; }
printf '%s package verified: size=%s sha256=%s entrypoint=%s version=%s\n' "$label" "$actual_size" "$actual_sha" "$entrypoint" "$actual_version"
