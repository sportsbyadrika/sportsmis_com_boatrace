#!/usr/bin/env bash
#
# Regenerate the committed vendor/ tree.
#
# vendor/ is committed so a deploy needs no composer on the server (see
# .gitignore for why). That makes it a build artefact living in git, which is
# only safe if it is regenerated reproducibly — hence this script rather than
# a hand-run composer command.
#
#   ./tools/vendor-refresh.sh && git add -A vendor composer.lock
#
set -euo pipefail
cd "$(dirname "$0")/.."

echo "==> Installing production dependencies"
rm -rf vendor
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Composer falls back to cloning from source when it cannot use dist archives,
# which leaves a .git directory inside every package. Those are the bulk of the
# tree, and git would treat each one as a submodule.
echo "==> Stripping package source-control metadata and test suites"
find vendor -type d -name '.git'     -prune -exec rm -rf {} + 2>/dev/null || true
find vendor -type d -name '.github'  -prune -exec rm -rf {} + 2>/dev/null || true
find vendor -type d \( -iname 'tests' -o -iname 'test' \) -prune -exec rm -rf {} + 2>/dev/null || true
find vendor -type d \( -iname 'docs' -o -iname 'Build' -o -iname 'generator' \
                       -o -iname 'performance' \) -prune -exec rm -rf {} + 2>/dev/null || true
find vendor \( -iname 'phpunit.xml*' -o -iname '*.dist' -o -iname 'CHANGELOG*' \
               -o -iname 'phpstan*' -o -iname '.php_cs*' -o -iname '.gitignore' \
               -o -iname '.gitattributes' \) -delete 2>/dev/null || true

echo "==> Verifying the PDF pipeline still renders"
php tools/selftest.php >/dev/null

printf '==> vendor/ is %s across %s files\n' \
    "$(du -sb vendor | cut -f1 | numfmt --to=iec)" \
    "$(find vendor -type f | wc -l | tr -d ' ')"
echo "==> Done. Commit vendor/ and composer.lock together."
