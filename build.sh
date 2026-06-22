#!/usr/bin/env bash
set -euo pipefail
# Produce a clean distributable zip (plugin files only, no dev tooling).
VERSION="$(grep -oE 'Version: *[0-9.]+' iterochat.php | grep -oE '[0-9.]+')"
rm -rf build
mkdir -p build/iterochat
rsync -a --exclude-from=- ./ build/iterochat/ <<'EXCLUDES'
.git/
.github/
build/
vendor/
tests/
node_modules/
composer.json
composer.lock
phpunit.xml.dist
build.sh
.gitignore
.phpunit.result.cache
*.zip
EXCLUDES
( cd build && zip -rq "iterochat-${VERSION}.zip" iterochat )
echo "Built build/iterochat-${VERSION}.zip"
