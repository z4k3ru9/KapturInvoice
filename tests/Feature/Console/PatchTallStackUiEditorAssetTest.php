<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * App\Console\Commands\PatchTallStackUiEditorAsset — null-guards
 * TallStackUI's bundled editor JS against the real
 * `Cannot read properties of undefined (reading 'contains')` crash in
 * `syncFormats()`, confirmed reproducing on the Invoice edit page and
 * the Payments page. Uses a fixture directory (via the command's
 * test-only `--path` option) rather than mutating the real vendor
 * directory.
 */
class PatchTallStackUiEditorAssetTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDir = storage_path('framework/testing/tallstackui-editor-fixture');

        File::deleteDirectory($this->fixtureDir);
        File::makeDirectory($this->fixtureDir, recursive: true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixtureDir);

        parent::tearDown();
    }

    private function vulnerableAssetContents(): string
    {
        // A minimal excerpt of the real minified shape, enough to exercise
        // the string replacement without embedding the whole vendor file.
        return 'scheduleFormats(){clearTimeout(this.formatsTimeout),this.formatsTimeout=setTimeout(()=>this.syncFormats(),50)},syncFormats(){let e=window.getSelection();!e?.anchorNode||!this.$refs.editable.contains(e.anchorNode)||(this.activeFormats={bold:document.queryCommandState(`bold`)})}';
    }

    public function test_it_null_guards_the_vulnerable_contains_call(): void
    {
        $asset = $this->fixtureDir.'/tallstackui-editor-C6p6X4we.js';
        File::put($asset, $this->vulnerableAssetContents());

        Artisan::call('tallstackui:patch-editor-asset', ['--path' => $this->fixtureDir]);

        $patched = File::get($asset);

        $this->assertStringContainsString('this.$refs.editable?.contains(e.anchorNode)', $patched);
        $this->assertStringNotContainsString('this.$refs.editable.contains(e.anchorNode)', $patched);
    }

    public function test_it_is_idempotent_on_an_already_patched_asset(): void
    {
        $asset = $this->fixtureDir.'/tallstackui-editor-C6p6X4we.js';
        $alreadyPatched = str_replace(
            'this.$refs.editable.contains(e.anchorNode)',
            'this.$refs.editable?.contains(e.anchorNode)',
            $this->vulnerableAssetContents(),
        );
        File::put($asset, $alreadyPatched);

        Artisan::call('tallstackui:patch-editor-asset', ['--path' => $this->fixtureDir]);

        $this->assertSame($alreadyPatched, File::get($asset));
    }

    public function test_it_does_nothing_when_no_editor_asset_is_present(): void
    {
        // The dist directory exists (fixture setUp) but is empty.
        $exitCode = Artisan::call('tallstackui:patch-editor-asset', ['--path' => $this->fixtureDir]);

        $this->assertSame(0, $exitCode);
    }

    public function test_it_does_not_fail_when_the_dist_directory_is_missing(): void
    {
        $missingDir = $this->fixtureDir.'/does-not-exist';

        $exitCode = Artisan::call('tallstackui:patch-editor-asset', ['--path' => $missingDir]);

        $this->assertSame(0, $exitCode);
    }

    public function test_it_patches_the_real_installed_vendor_asset(): void
    {
        // No --path override: exercises the actual default vendor location
        // this command runs against via composer.json's post-autoload-dump
        // hook, confirming the real asset is (or becomes) patched.
        Artisan::call('tallstackui:patch-editor-asset');

        $files = File::glob(base_path('vendor/tallstackui/tallstackui/dist/tallstackui-editor-*.js'));

        $this->assertNotEmpty($files, 'Expected the TallStackUI editor asset to be installed.');

        foreach ($files as $file) {
            $this->assertStringContainsString('this.$refs.editable?.contains(', File::get($file));
        }
    }
}
