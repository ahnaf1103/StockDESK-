#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/../stockdesk-release-$(date +%Y%m%d).zip}"
cd "$ROOT"
rm -f "$OUT"
zip -qr "$OUT" . -x 'config.php' 'uploads/*' '*.DS_Store'
printf 'Created %s\n' "$OUT"
