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
# as development-only, not hardened for real concurrent load) has failed
# against this CI runner in two different ways, each taking the whole
# suite down with it for the rest of the run: (1) outright crashes —
# once silently right after a dompdf PDF-render request, once with an
# explicit "Segmentation fault (core dumped)" mid a completely unrelated
# relation-manager render, neither reproducing locally, the latter
# persisting even with memory_limit raised to unlimited — and (2), even
# after adding a restart-on-exit loop for (1) below, a genuine HANG: the
# process stays alive and keeps accepting new TCP connections, but never
# responds to any of them — confirmed by a `.fill()` action waiting the
# entire 90s test timeout for a locator that a normal page load resolves
# in well under a second, with zero server log activity for that whole
# window on both the original attempt and its retry. A hang produces no
# exit event, so the restart-on-exit loop alone never fires for it.
#
# `php artisan serve` has no way to pass a `-d` flag through to the
# process it shells out to (ServeCommand::serverCommand() hardcodes the
# command), so this bypasses it and invokes the built-in server directly
# — browser-test-router.php duplicates the same trivial
# static-file-or-index.php routing `artisan serve` itself uses. Two
# layers cover both failure modes: a restart-on-exit loop (covers any
# crash, whatever its cause) plus a background health-check watchdog
# that force-kills the server if it stops answering `/up` for ~15s
# (covers a hang, which looks alive to the exit-loop but isn't actually
# serving anything) — the kill itself then feeds the exit loop, which
# brings up a fresh instance. A generous but finite memory_limit stays
# as defense-in-depth against the original PDF-render crash cause. One
# incident now costs only the in-flight request(s) — Playwright's own
# retry recovers that one test — instead of every remaining test in the
# run.
main_loop() {
    # `set -e` would otherwise abort this whole script the moment `php`
    # exits non-zero (a crash), which is exactly the one thing this loop
    # exists to survive — `|| true` keeps it running instead.
    while true; do
        php -d memory_limit=512M -S "127.0.0.1:${PORT}" -t public scripts/browser-test-router.php &
        child_pid=$!
        echo "$child_pid" > "$PID_FILE"
        wait "$child_pid" || true
        echo "browser-test-server.sh: dev server exited (code $?) — restarting" >&2
        sleep 0.5
    done
}

watchdog() {
    local consecutive_failures=0
    while true; do
        sleep 5
        if curl -fsS --max-time 3 "http://127.0.0.1:${PORT}/up" > /dev/null 2>&1; then
            consecutive_failures=0
            continue
        fi
        consecutive_failures=$((consecutive_failures + 1))
        if [ "$consecutive_failures" -ge 3 ]; then
            echo "browser-test-server.sh: health check failed ${consecutive_failures}x — server appears hung, killing it" >&2
            kill -KILL "$(cat "$PID_FILE" 2>/dev/null)" 2>/dev/null || true
            consecutive_failures=0
        fi
    done
}

PID_FILE="$(mktemp)"
trap 'kill -TERM "${main_loop_pid:-}" "${watchdog_pid:-}" 2>/dev/null; rm -f "$PID_FILE"; exit 0' TERM INT

main_loop &
main_loop_pid=$!
watchdog &
watchdog_pid=$!

wait "$main_loop_pid"
