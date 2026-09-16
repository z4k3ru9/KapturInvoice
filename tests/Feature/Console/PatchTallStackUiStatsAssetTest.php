<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * App\Console\Commands\PatchTallStackUiStatsAsset — lets `<x-stats>`'s
 * title/number column (a plain `<div class="grow">` flex item with no
 * `min-w-0`) actually shrink below its content's full single-line width,
 * so a page's own `break-words` value span can wrap instead of being held
 * at full width and clipped by the card's `overflow-hidden` boundary.
 * Uses a fixture file (via the command's test-only `--path` option) rather
 * than mutating the real vendor file.
 */
class PatchTallStackUiStatsAssetTest extends TestCase
{
    private string $fixtureFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureFile = storage_path('framework/testing/tallstackui-stats-main-fixture.blade.php');

        File::ensureDirectoryExists(dirname($this->fixtureFile));
    }

    protected function tearDown(): void
    {
        File::delete($this->fixtureFile);

        parent::tearDown();
    }

    private function vulnerableAssetContents(): string
    {
        return <<<'BLADE'
        <div class="{{ $customization['wrapper.third'] }}"></div>
        <div class="grow">
            @if ($title)
                <h2>{{ $title }}</h2>
            @endif
        </div>
        BLADE;
    }

    public function test_it_adds_min_w_0_to_the_grow_wrapper(): void
    {
        File::put($this->fixtureFile, $this->vulnerableAssetContents());

        Artisan::call('tallstackui:patch-stats-asset', ['--path' => $this->fixtureFile]);

        $patched = File::get($this->fixtureFile);

        $this->assertStringContainsString('<div class="grow min-w-0">', $patched);
        $this->assertStringNotContainsString('<div class="grow">', $patched);
    }

    public function test_it_is_idempotent_on_an_already_patched_asset(): void
    {
        Artisan::call('tallstackui:patch-stats-asset', ['--path' => $this->fixtureFile]);
        File::put($this->fixtureFile, $this->vulnerableAssetContents());
        Artisan::call('tallstackui:patch-stats-asset', ['--path' => $this->fixtureFile]);
        $oncePatched = File::get($this->fixtureFile);

        Artisan::call('tallstackui:patch-stats-asset', ['--path' => $this->fixtureFile]);

        $this->assertSame($oncePatched, File::get($this->fixtureFile));
    }

    public function test_it_does_not_fail_when_the_target_file_is_missing(): void
    {
        $missingFile = storage_path('framework/testing/does-not-exist-stats.blade.php');

        $exitCode = Artisan::call('tallstackui:patch-stats-asset', ['--path' => $missingFile]);

        $this->assertSame(0, $exitCode);
    }

    public function test_it_leaves_an_unrecognized_asset_untouched(): void
    {
        File::put($this->fixtureFile, '<div>some other markup entirely</div>');

        Artisan::call('tallstackui:patch-stats-asset', ['--path' => $this->fixtureFile]);

        $this->assertSame('<div>some other markup entirely</div>', File::get($this->fixtureFile));
    }

    public function test_it_patches_the_real_installed_vendor_asset(): void
    {
        // No --path override: exercises the actual default vendor location
        // this command runs against via composer.json's post-autoload-dump
        // hook, confirming the real asset is (or becomes) patched.
        Artisan::call('tallstackui:patch-stats-asset');

        $file = base_path('vendor/tallstackui/tallstackui/src/resources/views/components/stats/main.blade.php');

        $this->assertFileExists($file);
        $this->assertStringContainsString('<div class="grow min-w-0">', File::get($file));
    }
}
