<?php

namespace Tests\Feature\Authorization;

use App\Filament\Pages\Settings\EditBrandingSettings;
use App\Filament\Pages\Settings\EditClientPortalSettings;
use App\Filament\Pages\Settings\EditEmailSettings;
use App\Filament\Pages\Settings\EditNumberingSettings;
use App\Filament\Pages\Tenancy\EditCompanyProfile;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * docs/rebuild/Specs.md §10 "Protected actions" and
 * docs/rebuild/specs/01-company-foundation/Specs.md "Required tests":
 * every role may perform only its approved actions; Auditor is read-only
 * everywhere and never sees settings; physical deletion is Owner-only.
 * Enforced by the Gate::before hook in App\Providers\AppServiceProvider
 * (create/update/delete/restore/forceDelete on any
 * App\Models\Concerns\BelongsToCompany model) and
 * App\Policies\CompanyPolicy::viewSettings (Settings pages).
 */
class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role]);
        $this->actingAs($user);
        Filament::setTenant($this->company);

        return $user;
    }

    /** @return array<string, array{string}> */
    public static function mutatingRolesProvider(): array
    {
        return [
            'owner' => ['owner'],
            'admin' => ['admin'],
            'accountant' => ['accountant'],
            'sales' => ['sales'],
            'staff' => ['staff'],
        ];
    }

    #[DataProvider('mutatingRolesProvider')]
    public function test_every_non_auditor_role_can_create_update_and_delete_company_scoped_records(string $role): void
    {
        $user = $this->userWithRole($role);
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->assertTrue($user->can('create', Client::class));
        $this->assertTrue($user->can('update', $client));
        $this->assertTrue($user->can('delete', $client));
    }

    public function test_auditor_cannot_create_update_or_delete_company_scoped_records(): void
    {
        $user = $this->userWithRole('auditor');
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->assertFalse($user->can('create', Client::class));
        $this->assertFalse($user->can('update', $client));
        $this->assertFalse($user->can('delete', $client));
    }

    public function test_auditor_can_still_view_records_read_only(): void
    {
        $user = $this->userWithRole('auditor');
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->get(ClientResource::getUrl('index', tenant: $this->company))
            ->assertOk()
            ->assertSee('Test Client');
    }

    public function test_only_owner_can_force_delete_a_company_scoped_record(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $owner = $this->userWithRole('owner');
        $this->assertTrue($owner->can('forceDelete', $client));

        foreach (['admin', 'accountant', 'sales', 'staff', 'auditor'] as $role) {
            $user = $this->userWithRole($role);
            $this->assertFalse($user->can('forceDelete', $client), "role [{$role}] must not be able to force-delete");
        }
    }

    /** @return array<string, array{class-string}> */
    public static function settingsPagesProvider(): array
    {
        return [
            'branding' => [EditBrandingSettings::class],
            'client portal' => [EditClientPortalSettings::class],
            'email' => [EditEmailSettings::class],
            'numbering' => [EditNumberingSettings::class],
            'company profile' => [EditCompanyProfile::class],
        ];
    }

    #[DataProvider('settingsPagesProvider')]
    public function test_owner_and_admin_can_access_every_settings_page(string $page): void
    {
        foreach (['owner', 'admin'] as $role) {
            $this->userWithRole($role);
            $this->assertTrue($page::canAccess(), "role [{$role}] should be able to access {$page}");
        }
    }

    #[DataProvider('settingsPagesProvider')]
    public function test_auditor_and_other_non_settings_roles_cannot_access_settings_pages(string $page): void
    {
        foreach (['accountant', 'sales', 'staff', 'auditor'] as $role) {
            $this->userWithRole($role);
            $this->assertFalse($page::canAccess(), "role [{$role}] must not be able to access {$page}");
        }
    }

    public function test_a_super_admin_bypasses_role_checks_entirely(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($user);
        Filament::setTenant($this->company);

        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->assertTrue($user->can('create', Client::class));
        $this->assertTrue($user->can('forceDelete', $client));
        $this->assertTrue(EditBrandingSettings::canAccess());
    }
}
