<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * TallStackUI v4.1.0's `<x-stats>` Blade view
 * (`vendor/tallstackui/tallstackui/src/resources/views/components/stats/
 * main.blade.php`) wraps the title/number column in a plain
 * `<div class="grow">` inside a `flex` row alongside the icon square —
 * `grow` only sets `flex-grow: 1`, it never sets `min-width: 0`, so this
 * flex item keeps the browser default `min-width: auto`, which floors its
 * width at its content's own intrinsic (unbreakable) size. A page passing
 * a real, wide value through the DEFAULT slot (every `<x-stats>` call in
 * this app that renders its own custom markup instead of using the
 * `:number` prop — see e.g. tallstack-dashboard.blade.php's "Outstanding
 * balance" card) can never actually shrink below that width, so the
 * icon+text row silently grows past the card's own visible width and
 * `wrapper.first`'s `overflow-hidden` (AppServiceProvider's own stats
 * customization) clips the tail mid-digit — confirmed live with a
 * genuinely large seeded Rupiah value ("Rp 101.431.740.047"). This app is
 * an accounting/invoicing product: a currency figure must never be
 * shortened or hidden anywhere in the UI (no ellipsis truncation, no
 * "1,2 Jt"-style abbreviation — see memory.md and docs/rebuild/DESIGN.md's
 * "numeric values are never truncated or abbreviated" rule), so the fix
 * here is to let the value WRAP onto a second line instead of being
 * clipped or shortened — each page's own value span pairs this patch with
 * a `break-words` class (not `truncate`) for exactly that reason.
 *
 * No upstream release beyond v4.1.0 (the version this app requires,
 * `^4.1`) fixes this, so — following the exact precedent of
 * `PatchTallStackUiTabAsset`/`PatchTallStackUiEditorAsset` (different
 * vendor bugs, same package, same "no upstream fix yet" situation) — this
 * patches the vulnerable Blade source directly: `class="grow"` becomes
 * `class="grow min-w-0"`. Once this flex item can actually shrink, a
 * `break-words` value span can wrap within its own card instead of being
 * held at its full single-line content width and clipped by the card's
 * own `overflow-hidden` boundary.
 *
 * Because the vendor directory is reinstalled by Composer (a plain
 * `composer install`/`update` re-fetches the package's own source files,
 * overwriting any hand edit), this command re-applies the patch
 * idempotently and is wired into composer.json's existing
 * `post-autoload-dump` hook, so the fix survives a fresh `composer
 * install` the same way `composer setup` and CI both perform one, not
 * just a one-off edit to the checked-out vendor copy.
 */
class PatchTallStackUiStatsAsset extends Command
{
    protected $signature = 'tallstackui:patch-stats-asset {--path= : Override the target Blade file to patch (for tests only; defaults to the real vendor path).}';

    protected $description = "Let TallStackUI's <x-stats> title/number column actually shrink below its content width (vendor bug, no upstream fix yet), so a page's own break-words value span can wrap onto a second line instead of being clipped.";

    /** The exact source this vendor version ships in stats/main.blade.php. */
    private const VULNERABLE = '<div class="grow">';

    private const PATCHED = '<div class="grow min-w-0">';

    public function handle(): int
    {
        $file = $this->option('path') ?? base_path('vendor/tallstackui/tallstackui/src/resources/views/components/stats/main.blade.php');

        if (! File::exists($file)) {
            // The package may not be installed yet (e.g. mid-`composer
            // install` before the source files land) or may have moved
            // layout in a future version — never fail the wider Composer
            // run over this.
            $this->comment("TallStackUI stats asset not found, skipping patch: {$file}");

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
            $this->warn("Unrecognized TallStackUI stats asset shape, leaving untouched: {$file}");

            return self::SUCCESS;
        }

        File::put($file, str_replace(self::VULNERABLE, self::PATCHED, $contents));

        $this->info("Patched TallStackUI stats asset: {$file}");

        return self::SUCCESS;
    }
}
