<?php

namespace Tests\Feature\Dashboard;

use App\Livewire\TallStackDashboard;
use App\Livewire\TallStackSettingsCompanyTaxes;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers Company::dashboard_refresh_seconds — replaces the Dashboard's
 * old hand-clicked refresh button with a company-wide auto-refresh
 * cadence (App\Livewire\TallStackDashboard's x-init setInterval), set
 * from the Company & Taxes settings page's own new "Dashboard" section.
 */
class TallStackDashboardAutoRefreshTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->owner = User::factory()->create();
        $this->company->users()->attach($this->owner, ['role' => 'owner', 'is_active' => true]);
    }

    public function test_the_dashboard_does_not_render_an_auto_refresh_interval_by_default(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(TallStackDashboard::class, ['company' => $this->company])
            ->assertDontSee('setInterval', false);
    }

    public function test_the_dashboard_renders_the_configured_interval(): void
    {
        $this->company->update(['dashboard_refresh_seconds' => 60]);
        $this->actingAs($this->owner);

        Livewire::test(TallStackDashboard::class, ['company' => $this->company])
            ->assertSee('loadDashboardData() } }, 60000)', false);
    }

    public function test_saving_the_settings_page_updates_the_company(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(TallStackSettingsCompanyTaxes::class, ['company' => $this->company])
            ->set('dashboard_refresh_seconds', 120)
            ->call('save');

        $this->assertSame(120, $this->company->fresh()->dashboard_refresh_seconds);
    }

    public function test_saving_off_stores_null_not_zero(): void
    {
        $this->company->update(['dashboard_refresh_seconds' => 60]);
        $this->actingAs($this->owner);

        Livewire::test(TallStackSettingsCompanyTaxes::class, ['company' => $this->company])
            ->set('dashboard_refresh_seconds', 0)
            ->call('save');

        $this->assertNull($this->company->fresh()->dashboard_refresh_seconds);
    }

    public function test_an_invalid_interval_is_rejected(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(TallStackSettingsCompanyTaxes::class, ['company' => $this->company])
            ->set('dashboard_refresh_seconds', 45)
            ->call('save')
            ->assertHasErrors(['dashboard_refresh_seconds']);
    }
}
