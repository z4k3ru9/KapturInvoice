<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * TallStackUI v4.1.0's bundled `<x-editor>` JS (compiled from the
 * package's own `src/Components/Editor/alpine.js`, served straight from
 * `vendor/tallstackui/tallstackui/dist/` at runtime by
 * TallStackUi\Http\Controllers\TallStackUiAssetsController — it is never
 * built by this app's own Vite pipeline, so `npm run build` never touches
 * it) throws `Uncaught TypeError: Cannot read properties of undefined
 * (reading 'contains')` in `syncFormats()`.
 *
 * Root cause: `syncFormats()` is debounced via a 50ms `scheduleFormats()`
 * timeout, and when it fires it calls
 * `this.$refs.editable.contains(selection.anchorNode)` without checking
 * `$refs.editable` is defined. If the Alpine component tears down/
 * re-renders (Livewire morph, navigation) inside that 50ms window after a
 * selection-change event, `$refs.editable` is `undefined` and it throws —
 * reproduces on any page with an `<x-editor>` field (confirmed on both
 * the Invoice edit page and the Payments page), not something specific to
 * this app's own code. No upstream release beyond v4.1.0 (the version
 * this app already requires, `^4.1`) exists yet to pull a real fix from,
 * so this null-guards the compiled asset directly.
 *
 * Because the vendor directory is reinstalled by Composer (a plain
 * `composer install`/`update` re-fetches the package's own dist file,
 * overwriting any hand edit), this command re-applies the patch
 * idempotently and is wired into composer.json's existing
 * `post-autoload-dump` hook — which already runs after every Composer
 * install/update — so the fix survives a fresh `composer install` the
 * same way `composer setup` and CI both perform one, not just a one-off
 * edit to the checked-out vendor copy.
 */
class PatchTallStackUiEditorAsset extends Command
{
    protected $signature = 'tallstackui:patch-editor-asset {--path= : Override the dist directory to scan (for tests only; defaults to the real vendor path).}';

    protected $description = "Null-guard TallStackUI's bundled editor JS against a syncFormats() crash on a torn-down \$refs.editable (vendor bug, no upstream fix yet).";

    /** The exact minified call this vendor build emits, present in every affected v4.1.0 dist file. */
    private const VULNERABLE = 'this.$refs.editable.contains(e.anchorNode)';

    private const PATCHED = 'this.$refs.editable?.contains(e.anchorNode)';

    public function handle(): int
    {
        $distDirectory = $this->option('path') ?? base_path('vendor/tallstackui/tallstackui/dist');

        if (! File::isDirectory($distDirectory)) {
            // The package may not be installed yet (e.g. mid-`composer install`
            // before the dist files land) or may have moved layout in a future
            // version — never fail the wider Composer run over this.
            $this->comment('TallStackUI dist directory not found, skipping editor asset patch.');

            return self::SUCCESS;
        }

        $files = File::glob($distDirectory.'/tallstackui-editor-*.js');

        if ($files === [] || $files === false) {
            $this->comment('No TallStackUI editor asset found, skipping patch.');

            return self::SUCCESS;
        }

        foreach ($files as $file) {
            $contents = File::get($file);

            if (str_contains($contents, self::PATCHED)) {
                $this->comment("Already patched: {$file}");

                continue;
            }

            if (! str_contains($contents, self::VULNERABLE)) {
                // A future TallStackUI release may reshape/minify this
                // differently (or fix it upstream) — don't corrupt an
                // asset we don't recognize.
                $this->warn("Unrecognized TallStackUI editor asset shape, leaving untouched: {$file}");

                continue;
            }

            File::put($file, str_replace(self::VULNERABLE, self::PATCHED, $contents));

            $this->info("Patched TallStackUI editor asset: {$file}");
        }

        return self::SUCCESS;
    }
}
