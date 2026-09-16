<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * TallStackUI v4.1.0's `<x-tab.items>` Blade view
 * (`vendor/tallstackui/tallstackui/src/resources/views/components/tab/
 * items.blade.php`) unconditionally pushes itself into the parent
 * `<x-tab>`'s shared Alpine `tabs` array on every `x-init`, with no
 * de-duplication or teardown:
 *
 *     x-init="tabs.push({ tab: @js($tab), ... })"
 *
 * If that panel's `x-init` ever fires more than once for the same tab
 * id — confirmed live by nesting a second `<x-tab>` inside a page that
 * is itself already inside another `<x-tab>` panel (Settings > Lookups
 * inside the consolidated settings-tabs.blade.php wrapper) — the entry
 * is pushed a second time, duplicating the tab header and corrupting
 * `select()`/`change()`'s `tabs.find()` lookups (blanked panel content,
 * a doubled header). Reproduces on pristine, un-modified vendor code;
 * not something this app's own Blade markup causes on its own.
 *
 * No upstream release beyond v4.1.0 (the version this app already
 * requires, `^4.1`) fixes this, so — following the exact precedent of
 * `PatchTallStackUiEditorAsset` (a different vendor bug, same package,
 * same "no upstream fix yet" situation) — this null-guards the
 * vulnerable Blade source directly: `tabs.push(...)` becomes
 * conditional on the tab id not already being present in the array.
 * Existing single (non-nested) `<x-tab>` usage is unaffected — the
 * guard is a no-op there since each tab id is only ever pushed once.
 *
 * Because the vendor directory is reinstalled by Composer (a plain
 * `composer install`/`update` re-fetches the package's own source
 * files, overwriting any hand edit), this command re-applies the patch
 * idempotently and is wired into composer.json's existing
 * `post-autoload-dump` hook — which already runs after every Composer
 * install/update — so the fix survives a fresh `composer install` the
 * same way `composer setup` and CI both perform one, not just a one-off
 * edit to the checked-out vendor copy.
 */
class PatchTallStackUiTabAsset extends Command
{
    protected $signature = 'tallstackui:patch-tab-asset {--path= : Override the target Blade file to patch (for tests only; defaults to the real vendor path).}';

    protected $description = "Guard TallStackUI's <x-tab.items> against duplicate Alpine tabs.push() entries when nested inside another <x-tab> (vendor bug, no upstream fix yet).";

    /** The exact source this vendor version ships in items.blade.php. */
    private const VULNERABLE = 'x-init="tabs.push({ tab: @js($tab), title: @js($title), right: @js($content[\'right\']), left: @js($content[\'left\']), href: @js($href), navigate: @js((bool) $navigate), navigateHover: @js((bool) $navigateHover) });';

    private const PATCHED = 'x-init="if (!tabs.some((t) => t.tab === @js($tab))) { tabs.push({ tab: @js($tab), title: @js($title), right: @js($content[\'right\']), left: @js($content[\'left\']), href: @js($href), navigate: @js((bool) $navigate), navigateHover: @js((bool) $navigateHover) }) };';

    public function handle(): int
    {
        $file = $this->option('path') ?? base_path('vendor/tallstackui/tallstackui/src/resources/views/components/tab/items.blade.php');

        if (! File::exists($file)) {
            // The package may not be installed yet (e.g. mid-`composer
            // install` before the source files land) or may have moved
            // layout in a future version — never fail the wider Composer
            // run over this.
            $this->comment("TallStackUI tab asset not found, skipping patch: {$file}");

            return self::SUCCESS;
        }

        $contents = File::get($file);

        if (str_contains($contents, self::PATCHED)) {
            $this->comment("Already patched: {$file}");

            return self::SUCCESS;
        }

        if (! str_contains($contents, self::VULNERABLE)) {
            // A future TallStackUI release may reshape this differently
            // (or fix it upstream) — don't corrupt an asset we don't
            // recognize.
            $this->warn("Unrecognized TallStackUI tab asset shape, leaving untouched: {$file}");

            return self::SUCCESS;
        }

        File::put($file, str_replace(self::VULNERABLE, self::PATCHED, $contents));

        $this->info("Patched TallStackUI tab asset: {$file}");

        return self::SUCCESS;
    }
}
