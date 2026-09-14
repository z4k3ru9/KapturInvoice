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

# Pre-compile every Blade view once, up front — harmless, standard
# Laravel practice. (Does NOT by itself prevent the issue below: Blade
# caching only transforms template syntax to PHP, it doesn't execute
# views with real data, so it can't touch a runtime error.)
php artisan view:cache

# CI's "Browser tests" job failed three times running (56, then 56, then
# 54 failures — "Internal Server Error: Undefined variable" on a Filament
# vendor view, on essentially any page rendering a Filament table), never
# once locally even after exhaustively matching CI's PHP version,
# extensions, a from-scratch `composer install`, fresh DB/Blade cache, and
# CI's own workers:2/retries:1. The specific missing variable differed
# between runs ($getColumnManagerApplyAction, then $getFiltersFormWidth)
# — both are plain public methods on the same `Filament\Tables\Table`
# class, enumerated together by one `ReflectionClass::getMethods()` loop
# in vendor/filament/support/src/Components/ComponentManager.php's
# extractPublicMethods(), cached per-class for the lifetime of the PHP
# process. A different missing variable each time means that loop itself
# is getting cut off at a different point each run — consistent with a
# request being interrupted mid-loop under real concurrent load, which
# `php artisan serve`'s single request-at-a-time built-in server (this
# app's own existing comments already document that limitation elsewhere)
# is exactly the kind of bottleneck that produces: many browser projects'
# concurrent requests queue up behind it, and a queued request that times
# out server-side mid-render can plausibly abort PHP execution partway
# through that same reflection loop, leaving its class-level cache
# permanently, silently incomplete for every later request in the run.
#
# `PHP_CLI_SERVER_WORKERS` (needs `--no-reload`, Laravel's own supported
# combination — see ServeCommand::initialize()) forks that many actual
# worker processes instead of one, so concurrent requests are genuinely
# handled in parallel rather than queuing behind a single process. This
# targets the trigger condition directly, rather than trying to pre-warm
# every place a corrupted cache could show up (tried and insufficient —
# see this branch's commit history).
#
# First attempt used 4 workers and confirmed the theory — CI went from
# widespread "Internal Server Error" failures to only the pre-existing
# documented autosave flake, and the job ran 5-6x faster (real parallelism,
# not queuing). But the very next CI run crashed outright partway through
# (cascading ERR_CONNECTION_REFUSED / ERR_EMPTY_RESPONSE across dozens of
# unrelated tests) — 4 full PHP worker processes is real additional memory
# pressure, and CI's runner has far fewer resources than a typical
# development machine. Matching Playwright's own `workers: 2` in CI
# (playwright.config.ts) keeps just enough real concurrency to avoid the
# single-process request-queuing bottleneck without asking for more
# processes than the suite itself ever has concurrent requests in flight.
#
# A speculative SQLite WAL/busy_timeout hardening was added alongside this
# on the assumption that real multi-process concurrent DB access might
# need it, but the actual observed failures were never a "database is
# locked" error — only a memory-pressure crash (4 workers) and, once at 2
# workers with that SQLite change added, a run that just never finished
# (30+ minutes with no failure or completion, far past every other
# attempt's duration, cancelled rather than diagnosed further). Removed
# it again rather than layering an unproven, unrelated change on top of
# the one actually-evidenced fix — change one variable at a time.
export PHP_CLI_SERVER_WORKERS=2

exec php artisan serve --port="$PORT" --no-reload
