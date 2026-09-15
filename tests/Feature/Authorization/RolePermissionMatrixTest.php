<?php

namespace Tests\Feature\Authorization;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
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
        app(Tenancy::class)->set($this->company);

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

        $this->get(route('tallstack.clients', $this->company))
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

    /**
     * Every TallStack settings route delegates to the same
     * App\Policies\CompanyPolicy::viewSettings gate (see e.g.
     * App\Livewire\TallStackSettingsBranding::mount()); the pre-TallStackUI
     * Filament admin's settings Pages this test used to enumerate
     * (its EditBrandingSettings::canAccess() etc.) are gone as of the
     * Filament-removal Phase B, so this now hits each TallStack route
     * directly instead.
     *
     * @return array<string, array{string}>
     */
    public static function settingsPagesProvider(): array
    {
        return [
            'branding' => ['tallstack.settings.branding'],
            'client portal' => ['tallstack.settings.client-portal'],
            'email' => ['tallstack.settings.email'],
            'numbering' => ['tallstack.settings.numbering'],
            'company profile' => ['tallstack.settings.company-and-taxes'],
            // Phase 12 (F29) settings consolidation added the shared
            // tab-wrapper component every one of these routes now renders
            // through — this route previously had no coverage here at all.
            'lookups' => ['tallstack.settings.lookups'],
        ];
    }

    #[DataProvider('settingsPagesProvider')]
    public function test_owner_and_admin_can_access_every_settings_page(string $route): void
    {
        foreach (['owner', 'admin'] as $role) {
            $this->userWithRole($role);
            $this->get(route($route, $this->company))->assertOk();
        }
    }

    #[DataProvider('settingsPagesProvider')]
    public function test_auditor_and_other_non_settings_roles_cannot_access_settings_pages(string $route): void
    {
        foreach (['accountant', 'sales', 'staff', 'auditor'] as $role) {
            $this->userWithRole($role);
            $this->get(route($route, $this->company))->assertForbidden();
        }
    }

    public function test_a_super_admin_bypasses_role_checks_entirely(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($user);
        app(Tenancy::class)->set($this->company);

        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->assertTrue($user->can('create', Client::class));
        $this->assertTrue($user->can('forceDelete', $client));
        $this->get(route('tallstack.settings.branding', $this->company))->assertOk();
    }

    /**
     * Phase 12 (F29): the six settings pages were consolidated behind one
     * shared tab-header component (resources/views/components/tallstack/
     * settings-tabs.blade.php). TallStackUI's tab labels are only ever
     * materialized client-side by Alpine (`x-text` inside a `<template
     * x-for>`), seeded from a JSON literal server-rendered via `@js($title)`
     * inside each `<x-tab.items>`'s own `x-init` — so a real "Company &
     * Taxes" substring never appears in the raw server HTML even when
     * correct; what this test can assert against is that JSON literal.
     * `@js()` JSON-encodes with JSON_HEX_AMP, so a correctly single-escaped
     * label's `&` becomes the JSON unicode escape for it (backslash, u,
     * 0, 0, 2, 6) — three of the six labels contain one ("Company &
     * Taxes", "Tax Rates & Lookups", "Email & Reminders"), asserted
     * against literally below. The bug this session found and fixed
     * (`title="{{ $string }}"` on `<x-tab.items>` re-escapes an
     * already-plain string via Blade's own `{{ }}` before that string
     * ever reaches `@js()`) would instead produce that same escape
     * followed by the letters "amp;" — and once Alpine renders that JSON
     * value into the DOM via `x-text` (which does not decode HTML
     * entities), the tab would visibly read "Company &amp; Taxes" in the
     * browser.
     */
    public function test_settings_tab_strip_seeds_every_tab_title_with_correct_ampersands(): void
    {
        $this->userWithRole('owner');

        $html = $this->get(route('tallstack.settings.company-and-taxes', $this->company))
            ->assertOk()
            ->getContent();

        $expectedTitles = [
            'company-and-taxes' => 'Company \u0026 Taxes',
            'branding' => 'Branding',
            'lookups' => 'Tax Rates \u0026 Lookups',
            'numbering' => 'Numbering',
            'email' => 'Email \u0026 Reminders',
            'client-portal' => 'Client Portal',
        ];

        foreach ($expectedTitles as $tab => $title) {
            $needle = "tab: '{$tab}', title: '{$title}'";
            $this->assertSame(1, substr_count($html, $needle), "expected exactly one tab-strip entry [{$needle}]");
        }
    }
}
