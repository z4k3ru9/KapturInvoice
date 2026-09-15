<?php

namespace Tests\Feature\Tenancy;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * docs/rebuild/specs/01-company-foundation/Specs.md "Required tests":
 * same client identity in two companies cannot cross-read/mutate, a
 * disabled company/membership blocks access immediately without deleting
 * history. Cross-company PDF/portal download denial is already covered by
 * tests/Feature/PdfExportTest.php.
 */
class CompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_with_the_same_name_and_email_in_two_companies_remain_separate_records(): void
    {
        $companyA = Company::create(['name' => 'A Co', 'slug' => 'a-co', 'currency_code' => 'USD']);
        $companyB = Company::create(['name' => 'B Co', 'slug' => 'b-co', 'currency_code' => 'USD']);

        $clientA = Client::create(['company_id' => $companyA->id, 'name' => 'Shared Name', 'email' => 'shared@example.com']);
        $clientB = Client::create(['company_id' => $companyB->id, 'name' => 'Shared Name', 'email' => 'shared@example.com']);

        $this->assertNotSame($clientA->id, $clientB->id);

        $user = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($user);

        app(Tenancy::class)->set($companyA);
        $this->assertTrue(Client::query()->whereKey($clientA->id)->exists());
        $this->assertFalse(Client::query()->whereKey($clientB->id)->exists(), 'company A tenant scope must not see company B\'s client');

        app(Tenancy::class)->set($companyB);
        $this->assertTrue(Client::query()->whereKey($clientB->id)->exists());
        $this->assertFalse(Client::query()->whereKey($clientA->id)->exists(), 'company B tenant scope must not see company A\'s client');
    }

    public function test_disabled_company_is_excluded_from_a_users_tenant_list(): void
    {
        $user = User::factory()->create();
        $active = Company::create(['name' => 'Active Co', 'slug' => 'active-co', 'currency_code' => 'USD']);
        $disabled = Company::create(['name' => 'Disabled Co', 'slug' => 'disabled-co', 'currency_code' => 'USD', 'is_active' => false]);

        $active->users()->attach($user, ['role' => 'owner']);
        $disabled->users()->attach($user, ['role' => 'owner']);

        $tenants = $this->tenantsFor($user);

        $this->assertTrue($tenants->contains('id', $active->id));
        $this->assertFalse($tenants->contains('id', $disabled->id));
    }

    public function test_a_user_cannot_access_a_disabled_company_as_tenant(): void
    {
        $user = User::factory()->create();
        $disabled = Company::create(['name' => 'Disabled Co', 'slug' => 'disabled-co', 'currency_code' => 'USD', 'is_active' => false]);
        $disabled->users()->attach($user, ['role' => 'owner']);

        $this->assertFalse($user->canAccessTenant($disabled));
    }

    public function test_a_super_admin_cannot_access_a_disabled_company_either(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);
        $disabled = Company::create(['name' => 'Disabled Co', 'slug' => 'disabled-co', 'currency_code' => 'USD', 'is_active' => false]);

        $this->assertFalse($user->canAccessTenant($disabled));
        $this->assertFalse($this->tenantsFor($user)->contains('id', $disabled->id));
    }

    public function test_disabled_membership_blocks_tenant_access_even_though_the_company_itself_is_active(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $company->users()->attach($user, ['role' => 'owner', 'is_active' => false]);

        $this->assertFalse($user->canAccessTenant($company));
        $this->assertFalse($this->tenantsFor($user)->contains('id', $company->id));
    }

    /**
     * The list of companies a user may act as tenant for — mirrors
     * App\Livewire\Login's own "first active company" resolution (a super
     * admin sees every active company, an ordinary user only their own
     * active memberships in an active company). Filament's HasTenants
     * contract required a User::getTenants() method before the
     * Filament-removal Phase B; nothing in the app calls the full list
     * today (only the "first one" a login redirects to), so this stays a
     * local test helper rather than a real app method built for no
     * current caller.
     */
    private function tenantsFor(User $user): Collection
    {
        return $user->is_super_admin
            ? Company::query()->active()->get()
            : $user->companies()->active()->wherePivot('is_active', true)->get();
    }
}
