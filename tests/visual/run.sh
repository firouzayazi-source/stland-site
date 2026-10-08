#!/usr/bin/env bash
# آزمونِ دیداریِ صفحه‌ی اصلی: یک وردپرسِ کوچک (SQLite + ووکامرسِ ساختگی) می‌سازد،
# درختِ آزمون را می‌کارد، صفحه را رندر و در مرورگر می‌سنجد.
#
#   bash tests/visual/run.sh                 # وردپرس را دانلود می‌کند (CI)
#   WP_SRC=/path/to/wordpress bash tests/visual/run.sh   # از نسخه‌ی محلی
#
# خروجی: tests/visual/out/home-{mobile,desktop}.png
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
root="$(cd "$here/../.." && pwd)"
work="${WORK:-$(mktemp -d)}"
export WP_DIR="$work/wp"
out="$here/out"; mkdir -p "$out"

if [ -n "${WP_SRC:-}" ]; then
  mkdir -p "$WP_DIR"
  (cd "$WP_SRC" && tar cf - --exclude=./wp-content/database --exclude=./wp-content/mu-plugins --exclude=./wp-content/plugins/stland-home --exclude=./wp-config.php --exclude=./wp-content/db.php . ) | (cd "$WP_DIR" && tar xf -)
else
  curl -sSfL https://wordpress.org/latest.tar.gz | tar xz -C "$work"
  mv "$work/wordpress" "$WP_DIR"
  curl -sSfL -o "$work/sqlite.zip" https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip
  unzip -q "$work/sqlite.zip" -d "$WP_DIR/wp-content/plugins"
fi

sqlite="$WP_DIR/wp-content/plugins/sqlite-database-integration"
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$sqlite#" -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" "$sqlite/db.copy" > "$WP_DIR/wp-content/db.php"
sed -e "s/put your unique phrase here/visual-test/g" "$WP_DIR/wp-config-sample.php" > "$WP_DIR/wp-config.php"

mkdir -p "$WP_DIR/wp-content/mu-plugins"
cp "$here/woo-stub.php" "$WP_DIR/wp-content/mu-plugins/stlh-woo-stub.php"
rm -rf "$WP_DIR/wp-content/plugins/stland-home"
cp -r "$root/wp-content/plugins/stland-home" "$WP_DIR/wp-content/plugins/"

php -r '$_SERVER["HTTP_HOST"]="localhost"; define("WP_INSTALLING", true); require getenv("WP_DIR")."/wp-load.php"; require ABSPATH."wp-admin/includes/upgrade.php"; if (!is_blog_installed()) wp_install("Test", "admin", "a@b.test", true, "", "visual-test");' >/dev/null
php "$here/seed.php"
php "$here/theme-files.php"
php "$here/offsite-backup.php"
OUT="$out/home.html" php "$here/render.php"
node "$here/check.mjs" "$out/home.html" "$out"
