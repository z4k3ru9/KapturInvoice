<?php

return [

    /*
    |--------------------------------------------------------------------------
    | One-shot database bootstrap route
    |--------------------------------------------------------------------------
    |
    | Backs App\Http\Controllers\DeployBootstrapController, routed at
    | GET /deploy/bootstrap. Exists for cPanel hosts with no Terminal/SSH
    | access — cron can run scheduled artisan commands, but there's no way
    | to run a one-off `php artisan migrate --force` by hand, so this route
    | does it over HTTP instead. Token-gated: leave `migrate_token` unset
    | (the default) and the route always refuses with 404.
    |
    | Set DEPLOY_MIGRATE_TOKEN in .env to a long random string, visit
    | /deploy/bootstrap?token=<that string> once, then remove
    | DEPLOY_MIGRATE_TOKEN from .env again (and re-run `php artisan
    | config:cache`) so the route goes back to refusing everything. Do not
    | leave this token set indefinitely in production.
    |
    */

    'migrate_token' => env('DEPLOY_MIGRATE_TOKEN'),

    // Required for production bootstrap: seed and provision only this tenant.
    // Leave unset for local development, where CompanySeeder seeds both fixtures.
    'company_slug' => env('DEPLOY_COMPANY_SLUG'),

    /*
    |--------------------------------------------------------------------------
    | First admin user
    |--------------------------------------------------------------------------
    |
    | Optional. A brand-new production database has a schema and the two
    | seeded companies but zero users, so there is no way to log in at all
    | — /login only checks existing credentials, it doesn't create an
    | account. Set these to have the bootstrap route create (or, on a
    | repeat visit, update the password of) one real Owner user attached
    | to every company, instead of the well-known dev seed login
    | (test@example.com / password) ever touching production.
    |
    */

    'admin_name' => env('DEPLOY_ADMIN_NAME', 'Owner'),
    'admin_email' => env('DEPLOY_ADMIN_EMAIL'),
    'admin_password' => env('DEPLOY_ADMIN_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Legacy data import (over HTTP, for the same no-Terminal reason above)
    |--------------------------------------------------------------------------
    |
    | Backs App\Http\Controllers\DeployImportController, routed at
    | GET /deploy/import/{company}, same DEPLOY_MIGRATE_TOKEN gate as the
    | bootstrap route above. `{company}` must be a key here — never an
    | arbitrary artisan command/connection name taken from the request —
    | so this can only ever run one of the two pre-approved imports below,
    | each against its own already-configured `config('database.legacy_*')`
    | connection (see config/database.php). As of 2026-09-17 both
    | companies' own InvoiceNinja installs are v5 (Company A was
    | previously v4 — see memory.md's Binding product decisions).
    |
    | Each import command is itself idempotent/resumable (`--resume`, see
    | App\Console\Commands\Concerns\ImportsLegacyInvoiceNinja and
    | docs/data-import.md) — safe to re-run this route if a prior run
    | failed partway through.
    |
    */

    'imports' => [
        'company-a' => [
            'command' => 'import:invoiceninja-v5',
            'connection' => 'legacy_v5_company_a',
        ],
        'company-b' => [
            'command' => 'import:invoiceninja-v5',
            'connection' => 'legacy_v5',
        ],
    ],

];
