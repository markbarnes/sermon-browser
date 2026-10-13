#!/bin/bash
# Runs the PHPUnit tests in the wp-env test site's CLI container (npm run test:unit).
# WordPress's test bootstrap reinstalls WordPress in the database it is given, so the
# tests get a database of their own, sb_phpunit, and never touch the test site's.
set -e
cd "$(dirname "$0")/../.."
wp db query 'CREATE DATABASE IF NOT EXISTS sb_phpunit' > /dev/null
composer install --quiet --no-interaction
WORDPRESS_DB_NAME=sb_phpunit exec vendor/bin/phpunit "$@"
