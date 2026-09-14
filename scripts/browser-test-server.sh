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

# `php artisan serve`'s built-in PHP dev server (explicitly documented
# as development-only, not hardened for real concurrent load) crashed
# outright twice against this CI runner, taking the whole suite down
# with it for the rest of the run since nothing restarts it: once with
# no diagnostic at all right after a dompdf PDF-render request
# (ERR_EMPTY_RESPONSE, then ERR_CONNECTION_REFUSED for everything after
# — plausibly that request's memory use exceeding the runner's default
# finite memory_limit), and once with an explicit "Segmentation fault
# (core dumped)" mid a completely unrelated relation-manager render —
# a genuine PHP process crash unrelated to memory_limit, confirmed by
# still happening even after raising memory_limit to unlimited. Rather
# than keep chasing individual crash causes in a server that isn't
# meant to be this durable, wrap it in a restart loop: `php artisan
# serve` has no way to pass a `-d` flag through to the process it shells
# out to (ServeCommand::serverCommand() hardcodes the command), so this
# bypasses it and invokes the built-in server directly —
# browser-test-router.php duplicates the same trivial
# static-file-or-index.php routing `artisan serve` itself uses. A
# generous but finite memory_limit (defense-in-depth against the first
# crash cause) plus the restart loop (covers the second, and any other,
# cause) means one crash costs only the in-flight request(s) — Playwright's
# own retry then recovers that one test — instead of every remaining
# test in the run.
# `set -e` would otherwise abort this whole script the moment `php`
# exits non-zero (a crash), which is exactly the one thing this loop
# exists to survive — `|| true` keeps the loop running instead.
# Forward a real shutdown signal (Playwright tearing the webServer down
# between runs) to the child and stop looping, rather than restarting
# forever against a closed test run.
trap 'kill -TERM "${child_pid:-}" 2>/dev/null; exit 0' TERM INT

while true; do
    php -d memory_limit=512M -S "127.0.0.1:${PORT}" -t public scripts/browser-test-router.php &
    child_pid=$!
    wait "$child_pid" || true
    echo "browser-test-server.sh: dev server exited (code $?) — restarting" >&2
    sleep 0.5
done
