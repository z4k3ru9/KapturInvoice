<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackSalesOrders;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the Hold/Release-hold UI wired onto the Jobs (SalesOrder) list —
 * App\Actions\Shared\{PlaceHold,ReleaseHold}, the override lever for the
 * auto-close-operationally automation (App\Actions\Delivery\
 * CompleteDelivery/CompleteHandover). Mirrors
 * TallStackInvoicesHoldTest — the actions' own role/reason enforcement is
 * covered directly; this just tests the Livewire wiring.
 */
class TallStackSalesOrdersHoldTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private SalesOrder $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => 'ACM-QUO-0001',
            'status' => 'draft',
        ]);
        $this->job = SalesOrder::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
            'number' => 'ACM-SO-0001',
        ]);
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => 'owner']);

        return $user;
    }

    public function test_an_owner_can_place_and_release_a_hold_from_the_list(): void
    {
        $this->actingAs($this->owner());

        Livewire::test(TallStackSalesOrders::class, ['company' => $this->company])
            ->call('openHoldModal', $this->job->id)
            ->set('holdReason', 'Under dispute')
            ->call('placeHold')
            ->assertSet('showHoldModal', false);

        $this->assertNotNull($this->job->fresh()->held_at);
        $this->assertSame('Under dispute', $this->job->fresh()->held_reason);

        Livewire::test(TallStackSalesOrders::class, ['company' => $this->company])
            ->call('releaseHold', $this->job->id);

        $this->assertNull($this->job->fresh()->held_at);
    }

    public function test_a_staff_member_cannot_place_a_hold(): void
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => 'staff']);
        $this->actingAs($user);

        Livewire::test(TallStackSalesOrders::class, ['company' => $this->company])
            ->call('openHoldModal', $this->job->id)
            ->set('holdReason', 'Trying anyway')
            ->call('placeHold');

        $this->assertNull($this->job->fresh()->held_at);
    }

    public function test_placing_a_hold_without_a_reason_is_refused(): void
    {
        $this->actingAs($this->owner());

        Livewire::test(TallStackSalesOrders::class, ['company' => $this->company])
            ->call('openHoldModal', $this->job->id)
            ->set('holdReason', '')
            ->call('placeHold');

        $this->assertNull($this->job->fresh()->held_at);
    }

    public function test_a_job_cannot_be_held_from_a_different_company(): void
    {
        $other = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);
        $owner = User::factory()->create();
        $other->users()->attach($owner, ['role' => 'owner']);
        $this->actingAs($owner);

        Livewire::test(TallStackSalesOrders::class, ['company' => $other])
            ->call('openHoldModal', $this->job->id)
            ->set('holdReason', 'Cross-company attempt')
            ->call('placeHold');

        $this->assertNull($this->job->fresh()->held_at);
    }
}
