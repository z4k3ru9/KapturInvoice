#!/usr/bin/env bash
# Starts the app for the Playwright browser suite (Phase 06B Slice 4)
# against a DEDICATED sqlite database — never database/database.sqlite,
# the developer's own dev DB — with a fresh migrate+seed on every run, per
# docs/rebuild/specs/06b-ux-browser-soa/Specs.md: "Run against a built
# application with a fresh database and seeded companies."
set -euo pipefail

cd "$(dirname "$0")/.."

export APP_ENV=testing
export DB_CONNECTION=sqlite
export DB_DATABASE="$(pwd)/database/testing-browser.sqlite"
export PORT="${PLAYWRIGHT_APP_PORT:-8123}"

touch "$DB_DATABASE"
php artisan migrate:fresh --seed --force
php artisan db:seed --class="Database\\Seeders\\PlaywrightFixturesSeeder" --force

# `php artisan serve` has no way to pass a `-d` flag through to the
# built-in server it shells out to (Illuminate\Foundation\Console\
# ServeCommand::serverCommand() hardcodes the command). This matters
# here specifically: a CI run against PR #4 crashed the dev server
# entirely partway through — one dompdf PDF-render request
# (/statement-of-accounts/{id}/pdf) got no response at all
# (ERR_EMPTY_RESPONSE), and every request after that got
# ERR_CONNECTION_REFUSED for the rest of the run, because this suite's
# single PHP process has no supervisor to restart it after a crash.
# `php -i` on this CI runner's PHP showed a finite CLI memory_limit
# (unlike this sandbox's own unconfigured `-1`/unlimited) — dompdf is a
# known memory hog, and a PDF-render request appears to have exceeded
# it and taken the whole server down with it. Bypass `artisan serve` and
# invoke the built-in server directly so `-d memory_limit=-1` can be
# passed through; browser-test-router.php duplicates the same trivial
# static-file-or-index.php routing `artisan serve` itself uses.
exec php -d memory_limit=-1 -S "127.0.0.1:${PORT}" -t public scripts/browser-test-router.php
