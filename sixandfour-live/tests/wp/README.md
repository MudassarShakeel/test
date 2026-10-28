# Testing the plugin on a real WordPress

`setup.sh` builds a throwaway WordPress with a SQLite database from the public WordPress GitHub repositories, so no MySQL is needed.

```sh
tests/wp/setup.sh /tmp/sf-wp
node tests/wp/mock-servers.cjs &                       # fake cricket API and fake Facebook on port 8100
(cd /tmp/sf-wp && php -S 127.0.0.1:8099 -t site) &     # the site on port 8099
SF_WP_WORK=/tmp/sf-wp php tests/wp/run.php tests/wp/in-process.php      # runs inside WordPress
SF_WP_WORK=/tmp/sf-wp node tests/wp/http-test.cjs                       # real browser test
```

After you change the plugin, run `tests/wp/sync-plugin.sh /tmp/sf-wp` to copy it into the test site.

The test site log-in is `admin` with the password `Admin#Pass12345`. Never use this setup on the internet.

Two lighter tests need only PHP and run without WordPress:

```sh
php tests/engine-test.php   # engine with fake data and a SQLite stand-in for wpdb
php tests/views-test.php    # renders every admin screen
```
