# KapturInvoice Gate 0 Baseline Report

Date: 2026-09-12
Repository: `/Users/richardpangalila/Downloads/KapturInvoice`  
Branch: `main`  
Remote: `https://github.com/z4k3ru9/KapturInvoice.git`

## Result

Gate 0 is partially complete. The repository is clean, PHP dependencies install successfully, the Laravel application boots, migrations run on a fresh SQLite database, and the existing test suite passes all 102 tests. The test suite is now independent of a locally generated Vite manifest through the shared test base, while the production-like asset build remains an environment prerequisite.

## Environment observed

| Check | Result |
| --- | --- |
| PHP | 8.5.9, compatible with the project requirement `^8.3` |
| Composer | 2.10.3 |
| Laravel | 13.30.1 from lock file |
| Filament | 5.7.8 |
| Livewire | 4.4.3 |
| TallStack UI | 4.1.0 |
| Node | 20.15.1; below the declared floor of the installed Vite/Rolldown toolchain |
| npm | 10.7.0 |
| Database | SQLite local database created and migrated successfully |
| Queue | Database queue configured |
| Working tree | Clean after the test-base fix and documentation update |

## Checks performed

- Installed PHP dependencies from `composer.lock`.
- Initialized a local `.env` and application key for testing.
- Created the local SQLite database and ran all current migrations.
- Ran the existing test suite: 102 passed, 463 assertions.
- Attempted the frontend asset build with the locked JavaScript dependencies.

## Current blocker

`npm run build` still cannot load Rolldown's macOS native optional binding. The installed Node version is also below the package engine requirements. The public rendering tests no longer fail for this reason in the PHP test suite, but a reproducible production-like asset build is still required before Gate 0 can close.

This is an environment prerequisite, not a reason to change application dependencies or begin the renovation.

## Required resolution before Gate 1

1. Use Node `20.19+` or Node `22.12+` for the current Vite toolchain.
2. Reinstall JavaScript dependencies with optional platform bindings enabled.
3. Run `npm run build` and confirm `public/build/manifest.json` exists.
4. Rerun `composer test` after the asset environment is corrected and confirm the result remains 102 passing tests.
5. Preserve the clean branch and do not commit generated local environment files.

## Renovation implication

The test baseline is now green, but implementation should begin only after the asset toolchain is reproducible and Gate 0 is explicitly closed. The next unchecked Claude task is to restore a compatible Node environment, run `npm run build`, verify `public/build/manifest.json`, and update this report. Gate 1 should then start with company isolation, roles, settings, numbering, and audit foundations, using the approved canonical data model rather than extending the legacy payment and mutable-document relationships.
