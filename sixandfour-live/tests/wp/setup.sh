#!/bin/sh
# Builds a throwaway WordPress (database: SQLite) next to the plugin, for testing.
# Usage: tests/wp/setup.sh /path/to/work-folder
# Needs: git, PHP 7.4+ with sqlite3 and gd, Node 18+ with the playwright package.
set -e
W="${1:?Give a work folder, for example /tmp/sf-wp}"
HERE=$(cd "$(dirname "$0")" && pwd)
mkdir -p "$W" && cd "$W" && W=$(pwd)
[ -d core ] || git clone --depth 1 https://github.com/WordPress/WordPress.git core
[ -d sqlite ] || git clone --depth 1 https://github.com/WordPress/sqlite-database-integration.git sqlite
rm -rf site && cp -a core site && rm -rf site/.git
mkdir -p site/wp-content/plugins site/wp-content/mu-plugins site/wp-content/database
cp -rL sqlite/packages/plugin-sqlite-database-integration site/wp-content/plugins/sqlite-database-integration
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$W/site/wp-content/plugins/sqlite-database-integration#" \
    -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
    site/wp-content/plugins/sqlite-database-integration/db.copy > site/wp-content/db.php
cat > site/wp-config.php <<PHP
<?php
define( 'DB_NAME', 'wp' ); define( 'DB_USER', '' ); define( 'DB_PASSWORD', '' ); define( 'DB_HOST', '' );
define( 'DB_CHARSET', 'utf8mb4' ); define( 'DB_COLLATE', '' );
define( 'DB_DIR', '$W/site/wp-content/database/' ); define( 'DB_FILE', 'wp.sqlite' );
define( 'AUTH_KEY', 'k1-test-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ); define( 'SECURE_AUTH_KEY', 'k2-test-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ); define( 'LOGGED_IN_KEY', 'k3-test-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ); define( 'NONCE_KEY', 'k4-test-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' );
define( 'AUTH_SALT', 's1-test-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ); define( 'SECURE_AUTH_SALT', 's2-test-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ); define( 'LOGGED_IN_SALT', 's3-test-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ); define( 'NONCE_SALT', 's4-test-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' );
\$table_prefix = 'wp_';
define( 'WP_DEBUG', true ); define( 'WP_DEBUG_LOG', true ); define( 'WP_DEBUG_DISPLAY', false );
define( 'DISABLE_WP_CRON', true ); define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_HOME', 'http://localhost:8099' ); define( 'WP_SITEURL', WP_HOME );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
PHP
cp "$HERE/mu-test-hooks.php" site/wp-content/mu-plugins/
export SF_WP_WORK="$W"
php "$HERE/install.php"
"$HERE/sync-plugin.sh" "$W"
php "$HERE/run.php" "$HERE/activate.php"
echo "Ready. Next:"
echo "  node $HERE/mock-servers.cjs &"
echo "  (cd $W && php -S localhost:8099 -t site) &"
echo "  SF_WP_WORK=$W node $HERE/http-test.cjs"
echo "  SF_WP_WORK=$W php $HERE/run.php $HERE/in-process.php"
