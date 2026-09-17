<?php

namespace Tests\Feature\TallStack;

use App\Enums\CompanyRole;
use App\Livewire\TallStackSettingsTaxes;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * App\Services\PeriodLockService was fully built (close/reopen,
 * role-gated reopen with a required audited reason) but had no caller
 * anywhere in the app until this settings card wired it up — same shape
 * as the Hold/Release-hold gap Jobs already had. 2026-09-17 Settings
 * reorganization — see memory.md and docs/out-of-scope-findings.md.
 */
class TallStackSettingsTaxesPeriodLockTest extends TestCase
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

    /** "Closing... is not itself destructive, so any settings-capable role may do it" — PeriodLockService's own docblock; Admin is settingsRoles() but not periodReopenRoles(). */
    public function test_an_admin_can_close_a_period(): void
    {
        $this->actingAsRole(CompanyRole::Admin);

        Livewire::test(TallStackSettingsTaxes::class, ['company' => $this->company])
            ->set('closeThroughDate', '2026-01-31')
            ->call('closePeriod')
            ->assertHasNoErrors();

        $this->assertSame('2026-01-31', CompanySetting::query()->where('company_id', $this->company->id)->value('period_locked_through')?->toDateString());
    }

    public function test_closing_without_a_date_fails_validation(): void
    {
        $this->actingAsRole(CompanyRole::Owner);

        Livewire::test(TallStackSettingsTaxes::class, ['company' => $this->company])
            ->set('closeThroughDate', '')
            ->call('closePeriod')
            ->assertHasErrors(['closeThroughDate']);
    }

    public function test_an_owner_can_reopen_a_closed_period_with_a_reason_and_it_is_audited(): void
    {
        CompanySetting::query()->create(['company_id' => $this->company->id, 'period_locked_through' => '2026-01-31']);
        $this->actingAsRole(CompanyRole::Owner);

        Livewire::test(TallStackSettingsTaxes::class, ['company' => $this->company])
            ->set('reopenReason', 'Correcting a backdated invoice')
            ->call('reopenPeriod')
            ->assertHasNoErrors()
            ->assertSet('showReopenModal', false);

        $this->assertNull(CompanySetting::query()->where('company_id', $this->company->id)->value('period_locked_through'));
        $this->assertDatabaseHas('audit_events', [
            'company_id' => $this->company->id,
            'action' => 'period.reopened',
            'reason' => 'Correcting a backdated invoice',
        ]);
    }

    /** periodReopenRoles() is Owner/Accountant only — Admin (settingsRoles(), but not this) must be rejected, not crash. */
    public function test_an_admin_cannot_reopen_a_closed_period(): void
    {
        CompanySetting::query()->create(['company_id' => $this->company->id, 'period_locked_through' => '2026-01-31']);
        $this->actingAsRole(CompanyRole::Admin);

        Livewire::test(TallStackSettingsTaxes::class, ['company' => $this->company])
            ->set('reopenReason', 'Trying anyway')
            ->call('reopenPeriod');

        $this->assertSame('2026-01-31', CompanySetting::query()->where('company_id', $this->company->id)->value('period_locked_through')?->toDateString());
    }

    public function test_reopening_without_a_reason_fails_validation(): void
    {
        CompanySetting::query()->create(['company_id' => $this->company->id, 'period_locked_through' => '2026-01-31']);
        $this->actingAsRole(CompanyRole::Owner);

        Livewire::test(TallStackSettingsTaxes::class, ['company' => $this->company])
            ->set('reopenReason', '')
            ->call('reopenPeriod')
            ->assertHasErrors(['reopenReason']);
    }
}
