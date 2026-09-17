<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackSettingsFormatting;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The "Formatting" settings screen — consolidates `currency_code`/
 * `timezone` (moved off Company & Taxes' own Identity card) and
 * `default_document_language` (had no Settings field anywhere at all,
 * despite being read by every one of the 12 launch document types' own
 * `resolveDocumentLanguage()` fallback) into one place. 2026-09-17
 * Settings reorganization — see memory.md.
 */
class TallStackSettingsFormattingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user, ['role' => 'owner']);

        $this->actingAs($this->user);
        app(Tenancy::class)->set($this->company);
    }

    /** Ported from the pre-reorg TallStackSettingsCompanyTaxesCurrencyTest — see that test's own history for why the currency picker is a real <x-select.styled>, not free text. */
    public function test_the_currency_picker_is_populated_from_the_currencies_table(): void
    {
        $html = $this->get(route('tallstack.settings.formatting', $this->company))
            ->assertOk()
            ->getContent();

        // <x-select.styled>'s option list isn't printed as plain label
        // text — TallStackUI base64-encodes it into a hidden
        // `JSON.parse(atob('...'))` blob (see TallStackSettingsCompanyTaxesCurrencyTest's original docblock for the full mechanism).
        preg_match_all('/atob\(&#0*39;([^&]+)&#0*39;\)/', $html, $matches);
        $decodedOptionLists = collect($matches[1])->map(fn (string $b64) => base64_decode($b64));

        $this->assertTrue(
            $decodedOptionLists->contains(fn (string $json) => str_contains($json, '"USD"') && str_contains($json, '"IDR"')),
            'Expected one of the rendered <x-select.styled> option lists to contain both USD and IDR.'
        );
    }

    public function test_saving_a_real_currency_code_updates_the_company(): void
    {
        Livewire::test(TallStackSettingsFormatting::class, ['company' => $this->company])
            ->set('currency_code', 'IDR')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('IDR', $this->company->fresh()->currency_code);
    }

    public function test_saving_a_currency_code_that_is_not_a_real_currency_fails_validation(): void
    {
        Livewire::test(TallStackSettingsFormatting::class, ['company' => $this->company])
            ->set('currency_code', 'XXX')
            ->call('save')
            ->assertHasErrors(['currency_code']);

        $this->assertSame('USD', $this->company->fresh()->currency_code);
    }

    public function test_saving_a_real_timezone_updates_the_company(): void
    {
        Livewire::test(TallStackSettingsFormatting::class, ['company' => $this->company])
            ->set('timezone', 'Asia/Jakarta')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Asia/Jakarta', $this->company->fresh()->timezone);
    }

    public function test_saving_a_timezone_that_is_not_a_real_identifier_fails_validation(): void
    {
        Livewire::test(TallStackSettingsFormatting::class, ['company' => $this->company])
            ->set('timezone', 'Not/A_Real_Timezone')
            ->call('save')
            ->assertHasErrors(['timezone']);

        $this->assertSame('UTC', $this->company->fresh()->timezone);
    }

    public function test_saving_a_document_language_updates_the_company_settings(): void
    {
        Livewire::test(TallStackSettingsFormatting::class, ['company' => $this->company])
            ->set('default_document_language', 'en')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('en', CompanySetting::query()->where('company_id', $this->company->id)->value('default_document_language'));
    }

    public function test_saving_an_unsupported_document_language_fails_validation(): void
    {
        Livewire::test(TallStackSettingsFormatting::class, ['company' => $this->company])
            ->set('default_document_language', 'fr')
            ->call('save')
            ->assertHasErrors(['default_document_language']);
    }
}
