#!/bin/sh
# Copies the plugin (without tests and tools) into the test site. Usage: sync-plugin.sh /work
W="${1:-$SF_WP_WORK}"
HERE=$(cd "$(dirname "$0")" && pwd)
D="$W/site/wp-content/plugins/sixandfour-live"
rm -rf "$D" && mkdir -p "$D" && cd "$HERE/../.." && tar --exclude=tests --exclude=tools -cf - . | tar -xf - -C "$D"
