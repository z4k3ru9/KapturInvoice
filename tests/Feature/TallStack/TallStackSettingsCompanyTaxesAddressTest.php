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
 * `address_line_1`/`address_line_2`/`city`/`state`/`postal_code`/
 * `country_code`/`timezone` were already real #[Fillable] Company columns
 * (printed on the public homepage and every PDF's company header) with no
 * Settings field of their own — a settings-UI gap, not a missing-column
 * one. Same field set/validation as TallStackVendors's own address block.
 */
class TallStackSettingsCompanyTaxesAddressTest extends TestCase
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

    public function test_saving_address_fields_updates_the_company(): void
    {
        Livewire::test(TallStackSettingsCompanyTaxes::class, ['company' => $this->company])
            ->set('address_line_1', 'Jl. Contoh No. 1')
            ->set('address_line_2', 'Suite 2')
            ->set('city', 'Surabaya')
            ->set('state', 'East Java')
            ->set('postal_code', '60100')
            ->set('country_code', 'ID')
            ->call('save')
            ->assertHasNoErrors();

        $fresh = $this->company->fresh();
        $this->assertSame('Jl. Contoh No. 1', $fresh->address_line_1);
        $this->assertSame('Suite 2', $fresh->address_line_2);
        $this->assertSame('Surabaya', $fresh->city);
        $this->assertSame('East Java', $fresh->state);
        $this->assertSame('60100', $fresh->postal_code);
        $this->assertSame('ID', $fresh->country_code);
    }

    public function test_saving_a_real_timezone_updates_the_company(): void
    {
        Livewire::test(TallStackSettingsCompanyTaxes::class, ['company' => $this->company])
            ->set('timezone', 'Asia/Jakarta')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Asia/Jakarta', $this->company->fresh()->timezone);
    }

    public function test_saving_a_timezone_that_is_not_a_real_identifier_fails_validation(): void
    {
        Livewire::test(TallStackSettingsCompanyTaxes::class, ['company' => $this->company])
            ->set('timezone', 'Not/A_Real_Timezone')
            ->call('save')
            ->assertHasErrors(['timezone']);

        $this->assertSame('UTC', $this->company->fresh()->timezone);
    }
}
