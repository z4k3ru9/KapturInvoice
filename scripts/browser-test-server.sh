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

# Root cause of the long-investigated intermittent "hang" (actually a real
# 500): Laravel lazily compiles .blade.php templates into
# storage/framework/views/*.php on first request, via a non-atomic write
# (Illuminate\Filesystem\Filesystem::put(), no temp file + rename). This
# CI runner's dev server crashes intermittently (segfault, confirmed in
# this script's own restart-loop log) — if a crash lands mid-write of a
# compiled view, the cached file is left truncated on disk. It's keyed by
# a stable content hash, so every later request that resolves to that
# same view hits the same corrupted file and fails identically for the
# rest of the job — confirmed via a CI-only diagnostic
# (tests/browser/support/diagnostics.ts) that caught the real exception:
# "Undefined variable $getFiltersTriggerAction". Precompiling every reachable
# view (app + package/vendor namespaces) here, once, before any test
# traffic starts, collapses that whole request-time compile race into a
# single low-risk startup step instead of exposure on every one of
# hundreds of requests across the run.
php artisan view:cache

# `php artisan serve`'s built-in PHP dev server (explicitly documented
# as development-only) has crashed outright against this CI runner more
# than once — once silently right after a dompdf PDF-render request,
# once with an explicit "Segmentation fault (core dumped)" mid a
# completely unrelated relation-manager render, neither reproducing
# locally. Wrap it in a restart-on-exit loop so one crash costs only the
# in-flight request(s) — Playwright's own retry recovers that one test —
# instead of every remaining test in the run, since nothing else
# restarts a crashed `php artisan serve` process.
#
# A background health-check watchdog (an earlier version of this
# script) was tried too, to also catch a genuine HANG (the process
# alive but never responding) rather than only an outright exit — but
# it was reverted: this app's single-threaded dev server is exactly
# what `playwright.config.ts`'s `workers: 1` exists to keep free of
# concurrent requests, because a concurrent request here can interrupt
# the framework reflection-cache build mid-population and permanently
# corrupt it for that component for the rest of the process's life (see
# that config's own comment for the original, much larger incident this
# caused). The watchdog's own periodic health-check request is exactly
# such a concurrent request — and a run with it enabled reproduced a
# hang with the exact signature of that corruption (a specific form
# field permanently unresponsive, identically on both the original
# attempt and its retry, `/up` itself still answering fine throughout
# since the corruption is component-specific, not a real server crash).
# Chasing a hang this way risks causing worse hangs than it fixes;
# `php artisan serve` has no way to pass a `-d` flag through to the
# process it shells out to (ServeCommand::serverCommand() hardcodes the
# command), so this bypasses it and invokes the built-in server directly
# — browser-test-router.php duplicates the same trivial
# static-file-or-index.php routing `artisan serve` itself uses — purely
# so `-d memory_limit` can be set, with no other request traffic added.
#
# `set -e` would otherwise abort this whole script the moment `php`
# exits non-zero (a crash), which is exactly the one thing this loop
# exists to survive — `|| true` keeps it running instead. Forward a real
# shutdown signal (Playwright tearing the webServer down between runs)
# to the child and stop looping, rather than restarting forever against
# a closed test run.
trap 'kill -TERM "${child_pid:-}" 2>/dev/null; exit 0' TERM INT

while true; do
    php -d memory_limit=512M -S "127.0.0.1:${PORT}" -t public scripts/browser-test-router.php &
    child_pid=$!
    wait "$child_pid" || true
    echo "browser-test-server.sh: dev server exited (code $?) — restarting" >&2
    sleep 0.5
done
