<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * App\Console\Commands\PatchTallStackUiTabAsset — guards
 * `<x-tab.items>`'s x-init against pushing a duplicate entry into the
 * parent `<x-tab>`'s shared Alpine `tabs` array, which corrupts
 * select()/change() when a panel's x-init fires more than once (e.g.
 * nesting a second `<x-tab>` inside another `<x-tab>` panel). Uses a
 * fixture file (via the command's test-only `--path` option) rather
 * than mutating the real vendor file.
 */
class PatchTallStackUiTabAssetTest extends TestCase
{
    private string $fixtureFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureFile = storage_path('framework/testing/tallstackui-tab-items-fixture.blade.php');

        File::ensureDirectoryExists(dirname($this->fixtureFile));
    }

    protected function tearDown(): void
    {
        File::delete($this->fixtureFile);

        parent::tearDown();
    }

    private function vulnerableAssetContents(): string
    {
        return '<div x-show="selected === @js($tab)" role="tabpanel" x-init="tabs.push({ tab: @js($tab), title: @js($title), right: @js($content[\'right\']), left: @js($content[\'left\']), href: @js($href), navigate: @js((bool) $navigate), navigateHover: @js((bool) $navigateHover) }); @if($shouldRender && $href) selected = @js($tab); @endif" aria-labelledby="{{ $tab }}">';
    }

    public function test_it_guards_the_tabs_push_call_against_duplicates(): void
    {
        File::put($this->fixtureFile, $this->vulnerableAssetContents());

        Artisan::call('tallstackui:patch-tab-asset', ['--path' => $this->fixtureFile]);

        $patched = File::get($this->fixtureFile);

        $this->assertStringContainsString('if (!tabs.some((t) => t.tab === @js($tab))) { tabs.push(', $patched);
        $this->assertStringNotContainsString('x-init="tabs.push(', $patched);
    }

    public function test_it_is_idempotent_on_an_already_patched_asset(): void
    {
        Artisan::call('tallstackui:patch-tab-asset', ['--path' => $this->fixtureFile]);
        File::put($this->fixtureFile, $this->vulnerableAssetContents());
        Artisan::call('tallstackui:patch-tab-asset', ['--path' => $this->fixtureFile]);
        $oncePatched = File::get($this->fixtureFile);

        Artisan::call('tallstackui:patch-tab-asset', ['--path' => $this->fixtureFile]);

        $this->assertSame($oncePatched, File::get($this->fixtureFile));
    }

    public function test_it_does_not_fail_when_the_target_file_is_missing(): void
    {
        $missingFile = storage_path('framework/testing/does-not-exist.blade.php');

        $exitCode = Artisan::call('tallstackui:patch-tab-asset', ['--path' => $missingFile]);

        $this->assertSame(0, $exitCode);
    }

    public function test_it_leaves_an_unrecognized_asset_untouched(): void
    {
        File::put($this->fixtureFile, '<div>some other markup entirely</div>');

        Artisan::call('tallstackui:patch-tab-asset', ['--path' => $this->fixtureFile]);

        $this->assertSame('<div>some other markup entirely</div>', File::get($this->fixtureFile));
    }

    public function test_it_patches_the_real_installed_vendor_asset(): void
    {
        // No --path override: exercises the actual default vendor location
        // this command runs against via composer.json's post-autoload-dump
        // hook, confirming the real asset is (or becomes) patched.
        Artisan::call('tallstackui:patch-tab-asset');

        $file = base_path('vendor/tallstackui/tallstackui/src/resources/views/components/tab/items.blade.php');

        $this->assertFileExists($file);
        $this->assertStringContainsString('if (!tabs.some((t) => t.tab === @js($tab))) { tabs.push(', File::get($file));
    }
}
