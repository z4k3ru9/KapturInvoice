<?php

namespace Tests\Feature\Authorization;

use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\User;
use App\Services\PeriodLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * docs/rebuild/specs/01-company-foundation/Specs.md: "Add period soft
 * locks; Owner/Accountant reopening requires reason and audit event."
 */
class PeriodLockTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
    }

    public function test_closing_a_period_locks_dates_on_or_before_it(): void
    {
        $service = app(PeriodLockService::class);
        $service->close($this->company, now()->subDay());

        $this->assertTrue($service->isLocked($this->company->fresh(), now()->subDay()));
        $this->assertFalse($service->isLocked($this->company->fresh(), now()));
    }

    public function test_owner_or_accountant_can_reopen_a_locked_period_with_a_reason(): void
    {
        $service = app(PeriodLockService::class);
        $service->close($this->company, now()->subDay());

        $owner = User::factory()->create();
        $this->company->users()->attach($owner, ['role' => 'owner']);

        $service->reopen($this->company->fresh(), $owner, 'Late invoice from last month');

        $this->assertFalse($service->isLocked($this->company->fresh(), now()->subDay()));

        $event = AuditEvent::where('action', 'period.reopened')->first();
        $this->assertNotNull($event);
        $this->assertSame('Late invoice from last month', $event->reason);
    }

    public function test_a_non_owner_accountant_role_cannot_reopen_a_locked_period(): void
    {
        $service = app(PeriodLockService::class);
        $service->close($this->company, now()->subDay());

        $sales = User::factory()->create();
        $this->company->users()->attach($sales, ['role' => 'sales']);

        $this->expectException(RuntimeException::class);
        $service->reopen($this->company->fresh(), $sales, 'Trying anyway');
    }
}
