import { readFileSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import type { Page, TestInfo } from '@playwright/test';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * A single, decisive capture for any 5xx response during a test, instead of
 * another incremental guess-and-wait CI round trip: the exact new
 * storage/logs/laravel.log content the request wrote — exception class,
 * message, file:line, and full stack trace, which Laravel's exception
 * handler logs unconditionally via report(), independent of APP_DEBUG — and
 * the full rendered error page body. Both are printed straight to the CI
 * job's own console output (visible without downloading the HTML report
 * artifact) and the body is also attached to the Playwright report.
 *
 * scripts/browser-test-server.sh runs a fresh `migrate:fresh` (which
 * truncates storage/logs/laravel.log's target file implicitly via a clean
 * checkout, not literally — the log simply accumulates for the life of one
 * CI job's dev-server process) so the log-size-at-start offset here is
 * enough to isolate exactly what this one navigation wrote, without
 * picking up an unrelated earlier test's entries in the same job.
 */
const LARAVEL_LOG_PATH = path.resolve(__dirname, '../../../storage/logs/laravel.log');

function laravelLogSize(): number {
    try {
        return statSync(LARAVEL_LOG_PATH).size;
    } catch {
        return 0;
    }
}

function laravelLogSince(startSize: number, maxChars = 8_000): string {
    try {
        const buffer = readFileSync(LARAVEL_LOG_PATH);
        const appended = buffer.subarray(Math.min(startSize, buffer.length)).toString('utf-8');

        if (!appended) {
            return '(no new laravel.log content since navigation — the failure never reached Laravel\'s own exception handler)';
        }

        return appended.length > maxChars ? `…(truncated)…\n${appended.slice(-maxChars)}` : appended;
    } catch (error) {
        return `[could not read laravel.log: ${String(error)}]`;
    }
}

export function attachServerErrorDiagnostics(page: Page, testInfo: TestInfo): void {
    const logSizeAtStart = laravelLogSize();

    page.on('response', (response) => {
        if (response.status() < 500) {
            return;
        }

        const status = response.status();
        const url = response.url();

        void (async () => {
            const logTail = laravelLogSince(logSizeAtStart);
            console.log(
                `[DIAGNOSTIC] ${status} ${url}\n--- storage/logs/laravel.log since navigation ---\n${logTail}\n--- end laravel.log ---`,
            );

            try {
                const body = await response.text();
                console.log(`[DIAGNOSTIC] response body (first 4000 chars):\n${body.slice(0, 4_000)}`);
                await testInfo.attach(`server-error-${status}-${Date.now()}.html`, { body, contentType: 'text/html' });
            } catch (error) {
                console.log(`[DIAGNOSTIC] could not read response body: ${String(error)}`);
            }
        })();
    });

    page.on('pageerror', (error) => {
        console.log(`[DIAGNOSTIC pageerror] ${error.message}\n${error.stack}`);
    });

    page.on('console', (msg) => {
        if (msg.type() === 'error' || msg.type() === 'warning') {
            console.log(`[DIAGNOSTIC console.${msg.type()}] ${msg.text()}`);
        }
    });
}
