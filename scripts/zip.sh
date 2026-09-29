#!/usr/bin/env bash
# Build dist/stland-home.zip — the fallback when FTP can't reach the host:
# WordPress admin → Plugins → Add New → Upload → "Replace current with uploaded".
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p dist
rm -f dist/stland-home.zip
(cd wp-content/plugins && zip -qr ../../dist/stland-home.zip stland-home -x '*/.DS_Store')
echo "dist/stland-home.zip"
