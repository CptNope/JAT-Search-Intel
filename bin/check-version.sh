#!/usr/bin/env bash
# Fail unless every place that states the plugin version agrees:
# the plugin header, the JAT_SEARCH_INTEL_VERSION constant, readme.txt's
# Stable tag, readme.txt's newest changelog entry and package.json.
# With an argument (a tag such as v3.5.0) the version must also match it.
set -euo pipefail
cd "$(dirname "$0")/.."

header=$(sed -n 's/^ \* Version:[[:space:]]*//p' jat-search-intel.php | head -1)
constant=$(sed -n "s/.*define( 'JAT_SEARCH_INTEL_VERSION', '\([^']*\)'.*/\1/p" jat-search-intel.php | head -1)
stable=$(sed -n 's/^Stable tag:[[:space:]]*//p' readme.txt | head -1)
changelog=$(sed -n '/^== Changelog ==/,$ s/^= \([0-9][^ ]*\) =$/\1/p' readme.txt | head -1)
package=$(sed -n 's/^  "version": "\([^"]*\)".*/\1/p' package.json | head -1)

printf '%-26s %s\n' "Plugin header:" "$header" "JAT_SEARCH_INTEL_VERSION:" "$constant" \
	"readme.txt Stable tag:" "$stable" "readme.txt changelog:" "$changelog" "package.json:" "$package"

status=0
for value in "$constant" "$stable" "$changelog" "$package"; do
	if [ "$value" != "$header" ]; then
		status=1
	fi
done
if [ -n "${1:-}" ] && [ "${1#v}" != "$header" ]; then
	echo "Tag ${1} does not match plugin version ${header}"
	status=1
fi
if [ "$status" -ne 0 ]; then
	echo "::error::Version numbers disagree (see above)"
	exit 1
fi
echo "All version numbers are $header"
