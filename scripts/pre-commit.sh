#!/usr/bin/env bash
# Pre-commit hook — fast checks on staged PHP files only.
#
# Install once per clone:  composer hooks:install  (or cp scripts/pre-commit.sh .git/hooks/pre-commit)
#
# Gates:
#   - php -l on staged PHP files ............ BLOCKS the commit (syntax errors)
#   - pint --test on staged PHP files ....... warns only, non-blocking

set -e

FILES=$(git diff --cached --name-only --diff-filter=ACMR -- '*.php')
if [ -z "$FILES" ]; then
    exit 0
fi

echo "pre-commit: php -l on staged files"
for FILE in $FILES; do
    php -l "$FILE" > /dev/null
done

echo "pre-commit: pint --test on staged files (non-blocking)"
if [ -f "./vendor/bin/pint" ]; then
    ./vendor/bin/pint --test $FILES || echo "warning: Pint style issues found (not blocking the commit)"
fi
