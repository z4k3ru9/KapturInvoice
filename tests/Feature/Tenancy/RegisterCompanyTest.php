<?php

namespace Tests\Feature\Tenancy;

use App\Livewire\TallStackRegisterCompany;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * App\Livewire\TallStackRegisterCompany — the TallStackUI-native
 * replacement for the pre-TallStackUI legacy admin admin's RegisterCompany
 * tenancy page (legacy admin's RegisterTenant page), the only path today that
 * creates a new Company row
 * and attaches the creating user as owner. See that component's docblock
 * for why this stays a parallel, directly-reachable page rather than being
 * wired into legacy admin's own tenant-registration redirect this phase.
 */
class RegisterCompanyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // currency_code must resolve to a real row in the currencies
        // reference table (see the `exists:currencies,code` validation
        // rule on TallStackRegisterCompany::register()) — this table isn't
        // auto-seeded by RefreshDatabase, so every test that submits a
        // currency code needs it present.
        $this->seed(CurrencySeeder::class);
    }

    public function test_a_company_less_user_sees_the_registration_form(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TallStackRegisterCompany::class)
            ->assertOk()
            ->assertSee('Register company');
    }

    public function test_a_user_who_already_has_a_company_is_redirected_to_its_dashboard(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Existing Co', 'slug' => 'existing-co', 'currency_code' => 'USD']);
        $company->users()->attach($user, ['role' => 'owner']);

        Livewire::actingAs($user)
            ->test(TallStackRegisterCompany::class)
            ->assertRedirect(route('tallstack.dashboard', $company));
    }

    public function test_a_super_admin_with_no_membership_row_is_still_redirected_to_an_active_company(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);
        $company = Company::create(['name' => 'Someone Elses Co', 'slug' => 'someone-elses-co', 'currency_code' => 'USD']);

        Livewire::actingAs($user)
            ->test(TallStackRegisterCompany::class)
            ->assertRedirect(route('tallstack.dashboard', $company));
    }

    public function test_registering_creates_the_company_and_attaches_the_user_as_owner(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TallStackRegisterCompany::class)
            ->set('name', 'Brand New Co')
            ->set('slug', 'brand-new-co')
            ->set('currency_code', 'USD')
            ->call('register')
            ->assertRedirect(route('tallstack.dashboard', Company::where('slug', 'brand-new-co')->firstOrFail()));

        $company = Company::where('slug', 'brand-new-co')->firstOrFail();

        $this->assertSame('Brand New Co', $company->name);
        $this->assertSame('USD', $company->currency_code);

        $pivot = $company->users()->whereKey($user->id)->first()?->pivot;
        $this->assertNotNull($pivot);
        $this->assertSame('owner', $pivot->role);
        $this->assertTrue($user->canAccessTenant($company));
    }

    public function test_updating_the_name_autofills_the_slug(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TallStackRegisterCompany::class)
            ->set('name', 'Some Cool Company')
            ->assertSet('slug', 'some-cool-company');
    }

    public function test_registering_with_a_slug_already_taken_by_another_company_fails_validation(): void
    {
        $user = User::factory()->create();
        Company::create(['name' => 'Taken Co', 'slug' => 'taken-slug', 'currency_code' => 'USD']);

        Livewire::actingAs($user)
            ->test(TallStackRegisterCompany::class)
            ->set('name', 'Whatever')
            ->set('slug', 'taken-slug')
            ->set('currency_code', 'USD')
            ->call('register')
            ->assertHasErrors(['slug']);

        $this->assertSame(0, $user->companies()->count());
    }

    public function test_registering_with_a_domain_already_taken_by_another_company_fails_validation(): void
    {
        $user = User::factory()->create();
        Company::create(['name' => 'Taken Co', 'slug' => 'taken-co', 'domain' => 'taken.test', 'currency_code' => 'USD']);

        Livewire::actingAs($user)
            ->test(TallStackRegisterCompany::class)
            ->set('name', 'Whatever')
            ->set('slug', 'whatever')
            ->set('domain', 'taken.test')
            ->set('currency_code', 'USD')
            ->call('register')
            ->assertHasErrors(['domain']);

        $this->assertSame(0, $user->companies()->count());
    }

    public function test_registering_with_a_currency_code_that_is_not_a_real_currency_fails_validation(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TallStackRegisterCompany::class)
            ->set('name', 'Whatever')
            ->set('slug', 'whatever')
            ->set('currency_code', 'XXX')
            ->call('register')
            ->assertHasErrors(['currency_code']);

        $this->assertSame(0, $user->companies()->count());
    }
}
