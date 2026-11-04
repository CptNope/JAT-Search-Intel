#!/usr/bin/env bash
# Build the WordPress.org distributable from the committed tree:
#   dist/jat-search-intel.zip
# Exclusions come from .gitattributes (export-ignore), mirrored in .distignore.
# Run `npm ci && npm run build` and commit build/ first if src/ changed.
set -euo pipefail
cd "$(dirname "$0")/.."
SLUG=jat-search-intel
REF="${1:-HEAD}"
rm -rf dist && mkdir -p dist
git archive --format=zip --prefix="$SLUG/" -o "dist/$SLUG.zip" "$REF"
echo "Built dist/$SLUG.zip from $REF"
