<?php

namespace Tests\Feature\TallStack;

use App\Enums\CompanyRole;
use App\Livewire\TallStackSettingsCompanyTaxes;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `companies.is_active` gates login/portal access entirely
 * (App\Models\User::canAccessTenant() checks it unconditionally, even
 * for a super-admin) but had no Settings field anywhere — Owner-only to
 * change here, since flipping it off locks EVERYONE, including whoever
 * just clicked it, out of the company immediately. 2026-09-17 Settings
 * reorganization — see memory.md.
 */
class TallStackSettingsCompanyTaxesActiveToggleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
    }

    private function actingAsRole(CompanyRole $role): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role->value]);
        $this->actingAs($user);
        app(Tenancy::class)->set($this->company);

        return $user;
    }

    public function test_an_owner_can_deactivate_the_company(): void
    {
        $this->actingAsRole(CompanyRole::Owner);

        Livewire::test(TallStackSettingsCompanyTaxes::class, ['company' => $this->company])
            ->set('is_active', false)
            ->set('name', $this->company->name)
            ->set('slug', $this->company->slug)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse((bool) $this->company->fresh()->is_active);
    }

    /** Admin has settingsRoles() access to this whole page, but is_active is deliberately narrower — Owner only. */
    public function test_an_admin_cannot_deactivate_the_company(): void
    {
        $this->actingAsRole(CompanyRole::Admin);

        Livewire::test(TallStackSettingsCompanyTaxes::class, ['company' => $this->company])
            ->set('is_active', false)
            ->set('name', $this->company->name)
            ->set('slug', $this->company->slug)
            ->call('save')
            ->assertForbidden();

        $this->assertTrue((bool) $this->company->fresh()->is_active);
    }

    /** Saving the form without actually touching is_active must never re-trigger the Owner-only gate for an Admin. */
    public function test_saving_other_fields_without_changing_is_active_does_not_require_owner(): void
    {
        $this->actingAsRole(CompanyRole::Admin);

        Livewire::test(TallStackSettingsCompanyTaxes::class, ['company' => $this->company])
            ->set('name', 'Acme Renamed')
            ->set('slug', $this->company->slug)
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Acme Renamed', $this->company->fresh()->name);
    }
}
