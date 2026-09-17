<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackSettingsClientPortal;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `portal_allow_client_payments`/`portal_require_signature` were already
 * real, actively-read CompanySetting columns — gating the Pay section
 * and e-signature capture on the public portal's own invoice page — with
 * no Settings field anywhere until now. Settings-UI gap, not a
 * missing-column one.
 */
class TallStackSettingsClientPortalTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => 'owner']);

        $this->actingAs($user);
        app(Tenancy::class)->set($this->company);
    }

    public function test_saving_all_three_portal_toggles_updates_the_company_settings(): void
    {
        Livewire::test(TallStackSettingsClientPortal::class, ['company' => $this->company])
            ->set('portal_enabled', true)
            ->set('portal_allow_client_payments', true)
            ->set('portal_require_signature', true)
            ->call('save')
            ->assertHasNoErrors();

        $settings = CompanySetting::query()->where('company_id', $this->company->id)->first();

        $this->assertTrue((bool) $settings->portal_enabled);
        $this->assertTrue((bool) $settings->portal_allow_client_payments);
        $this->assertTrue((bool) $settings->portal_require_signature);
    }

    public function test_the_component_mounts_existing_values(): void
    {
        CompanySetting::query()->create([
            'company_id' => $this->company->id,
            'portal_enabled' => true,
            'portal_allow_client_payments' => true,
            'portal_require_signature' => false,
        ]);

        Livewire::test(TallStackSettingsClientPortal::class, ['company' => $this->company])
            ->assertSet('portal_enabled', true)
            ->assertSet('portal_allow_client_payments', true)
            ->assertSet('portal_require_signature', false);
    }
}
