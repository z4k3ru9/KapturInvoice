<?php

namespace Tests\Feature\TallStack;

use App\Enums\InvoiceType;
use App\Livewire\TallStackInvoices;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the Hold/Release-hold UI wired onto the Invoices list —
 * App\Actions\Shared\{PlaceHold,ReleaseHold}, the override lever for the
 * status-transition automation (App\Console\Commands\MarkInvoicesOverdue,
 * GenerateDueRecurringInvoices). The actions' own role/reason enforcement
 * is covered directly; this just tests the Livewire wiring.
 */
class TallStackInvoicesHoldTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);
        $this->invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => InvoiceType::Invoice,
            'status' => 'issued',
            'number' => 'ACM-INV-0001',
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

        Livewire::test(TallStackInvoices::class, ['company' => $this->company])
            ->call('openHoldModal', $this->invoice->id)
            ->set('holdReason', 'Under dispute')
            ->call('placeHold')
            ->assertSet('showHoldModal', false);

        $this->assertNotNull($this->invoice->fresh()->held_at);
        $this->assertSame('Under dispute', $this->invoice->fresh()->held_reason);

        Livewire::test(TallStackInvoices::class, ['company' => $this->company])
            ->call('releaseHold', $this->invoice->id);

        $this->assertNull($this->invoice->fresh()->held_at);
    }

    public function test_a_staff_member_cannot_place_a_hold(): void
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => 'staff']);
        $this->actingAs($user);

        Livewire::test(TallStackInvoices::class, ['company' => $this->company])
            ->call('openHoldModal', $this->invoice->id)
            ->set('holdReason', 'Trying anyway')
            ->call('placeHold');

        $this->assertNull($this->invoice->fresh()->held_at);
    }

    public function test_placing_a_hold_without_a_reason_is_refused(): void
    {
        $this->actingAs($this->owner());

        Livewire::test(TallStackInvoices::class, ['company' => $this->company])
            ->call('openHoldModal', $this->invoice->id)
            ->set('holdReason', '')
            ->call('placeHold');

        $this->assertNull($this->invoice->fresh()->held_at);
    }
}
