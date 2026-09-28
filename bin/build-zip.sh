#!/bin/sh
# Builds dist/travel-risk-<version>.zip for upload via WordPress > Plugins > Add New > Upload.
# Tests are left out of the package.
set -e
cd "$(dirname "$0")/.."
VERSION=$(sed -n "s/^const VERSION = '\(.*\)';/\1/p" travel-risk/travel-risk.php)
mkdir -p dist
rm -f "dist/travel-risk-$VERSION.zip"
zip -qr "dist/travel-risk-$VERSION.zip" travel-risk -x 'travel-risk/tests/*'
echo "dist/travel-risk-$VERSION.zip"
