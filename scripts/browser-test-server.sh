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

# Pre-compile every Blade view (including vendor/Filament ones) before the
# concurrent Playwright suite starts hitting this single-threaded dev
# server. CI's "Browser tests" job failed twice, deterministically and
# identically (56 failures, mostly "Internal Server Error: Undefined
# variable $getColumnManagerApplyAction" — a Filament vendor view — on
# essentially any page rendering a Filament table), on PR #4 commit
# 422fe56. Extensive local reproduction (matching PHP 8.3.33 exactly, a
# from-scratch `composer install`, fresh DB/Blade cache, CI's own
# workers:2/retries:1) never reproduced it even once — only CI's own
# 2-core runner did, both times. The leading theory: the very first
# concurrent requests across several browser projects can race to
# compile-and-cache the same never-before-rendered view (e.g. any
# Filament table's index.blade.php) under real load on a more
# resource-constrained runner than this sandbox's, corrupting that one
# compiled-view cache entry for the rest of the run. Not independently
# proven (the corruption itself was never directly observed), but
# `view:cache` compiling every discoverable view once, sequentially, up
# front is a standard, harmless Laravel practice regardless — it removes
# the theorized race window entirely by construction. If CI is still red
# after this, the theory above is wrong and needs revisiting.
php artisan view:cache

exec php artisan serve --port="$PORT"
