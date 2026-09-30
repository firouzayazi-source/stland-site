#!/usr/bin/env bash
# Build dist/<plugin>.zip for every plugin plus dist/manifest.json.
# stland-updater (on the site) reads the manifest from release "latest" and
# offers each zip as a normal WordPress plugin update. The same zips are the
# manual fallback: Plugins > Add New > Upload > "Replace current".
set -euo pipefail
cd "$(dirname "$0")/.."
rm -rf dist && mkdir -p dist

entries=()
for dir in wp-content/plugins/*/; do
  slug=$(basename "$dir")
  main="$dir$slug.php"
  [ -f "$main" ] || { echo "missing $main" >&2; exit 1; }
  version=$(grep -oP '^\s*\*\s*Version:\s*\K[0-9.]+' "$main")
  # Content hash: lets CI catch a changed plugin whose version was not bumped.
  hash=$(cd wp-content/plugins && find "$slug" -type f ! -name '.DS_Store' -print0 | sort -z | xargs -0 sha256sum | sha256sum | cut -c1-64)
  (cd wp-content/plugins && zip -qrX "../../dist/$slug.zip" "$slug" -x '*/.DS_Store')
  entries+=("$slug" "$version" "$hash")
  echo "dist/$slug.zip ($version)"
done

php -r '
  $a = array_slice($argv, 1); $plugins = [];
  for ($i = 0; $i < count($a); $i += 3) {
    $plugins[$a[$i]] = ["version" => $a[$i+1], "zip" => $a[$i] . ".zip", "hash" => $a[$i+2]];
  }
  echo json_encode(["sha" => getenv("GITHUB_SHA") ?: "local", "builtAt" => gmdate("c"), "plugins" => $plugins],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
' "${entries[@]}" > dist/manifest.json
echo "dist/manifest.json"
