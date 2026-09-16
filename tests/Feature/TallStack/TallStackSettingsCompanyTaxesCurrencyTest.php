<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackSettingsCompanyTaxes;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The "Default currency" field on the Company & Taxes settings page — was a
 * free-text `<x-input maxlength="3">` that accepted any 3-character string,
 * unlike the equivalent per-Client currency override on TallStackClients
 * (a real `<x-select.styled>` picker backed by the `currencies` reference
 * table). Brought up to the same standard: a real picker, validated against
 * `App\Models\Currency` via `exists:currencies,code`.
 */
class TallStackSettingsCompanyTaxesCurrencyTest extends TestCase
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

    public function test_the_currency_picker_is_populated_from_the_currencies_table(): void
    {
        // A real HTTP request, not Livewire::test() — the shared
        // <x-tallstack.settings-tabs> wrapper only renders its slot into
        // the DOM when the current request URL matches that tab's own
        // route (see that component's own docblock), so a component-only
        // test never sees this page's card content rendered at all.
        $html = $this->get(route('tallstack.settings.company-and-taxes', $this->company))
            ->assertOk()
            ->getContent();

        // <x-select.styled>'s option list isn't printed as plain label
        // text — TallStackUI base64-encodes it into a hidden
        // `JSON.parse(atob('...'))` blob (rendered with HTML-entity-encoded
        // quotes, `atob(&#039;...&#039;)`) for Alpine to read client-side
        // (vendor/tallstackui/tallstackui/src/resources/views/components/form/select/styled.blade.php).
        // Decode every such blob on the page and confirm one of them is
        // the currency list built from the real `currencies` table.
        preg_match_all('/atob\(&#0*39;([^&]+)&#0*39;\)/', $html, $matches);
        $decodedOptionLists = collect($matches[1])->map(fn (string $b64) => base64_decode($b64));

        $this->assertTrue(
            $decodedOptionLists->contains(fn (string $json) => str_contains($json, '"USD"') && str_contains($json, '"IDR"')),
            'Expected one of the rendered <x-select.styled> option lists to contain both USD and IDR.'
        );
    }

    public function test_saving_a_real_currency_code_updates_the_company(): void
    {
        Livewire::test(TallStackSettingsCompanyTaxes::class, ['company' => $this->company])
            ->set('currency_code', 'IDR')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('IDR', $this->company->fresh()->currency_code);
    }

    public function test_saving_a_currency_code_that_is_not_a_real_currency_fails_validation(): void
    {
        Livewire::test(TallStackSettingsCompanyTaxes::class, ['company' => $this->company])
            ->set('currency_code', 'XXX')
            ->call('save')
            ->assertHasErrors(['currency_code']);

        $this->assertSame('USD', $this->company->fresh()->currency_code);
    }
}
