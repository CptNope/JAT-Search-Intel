#!/usr/bin/env bash
# Syntax-check every shipped PHP file with the current PHP version.
set -euo pipefail
cd "$(dirname "$0")/.."
log=$(mktemp)
if ! git ls-files -z '*.php' ':!:tests/**' ':!:stubs/**' ':!:phpstan-constants.php' \
	| xargs -0 -n1 -P4 php -l -d display_errors=1 > "$log" 2>&1; then
	grep -v '^No syntax errors' "$log"
	exit 1
fi
echo "$(php -r 'echo PHP_VERSION;'): $(wc -l < "$log") files OK"
