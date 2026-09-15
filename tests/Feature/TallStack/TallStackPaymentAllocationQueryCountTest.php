<?php

namespace Tests\Feature\TallStack;

use App\Actions\Receivables\AllocateCustomerPayment;
use App\Livewire\TallStackPaymentAllocation;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for a real N+1 found in this component: its
 * allocations table rendered $a->invoice?->number per row without
 * eager-loading the invoice relation, so the query count scaled with the
 * allocation count. mount() now eager-loads allocations.invoice.
 */
class TallStackPaymentAllocationQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);
        $this->actingAs($this->user);
    }

    private function paymentWithAllocations(int $invoiceCount): Payment
    {
        $payment = Payment::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'amount' => $invoiceCount * 100,
            'status' => 'verified',
        ]);

        $allocations = [];

        for ($i = 0; $i < $invoiceCount; $i++) {
            $invoice = Invoice::create([
                'company_id' => $this->company->id,
                'client_id' => $this->client->id,
                'type' => 'invoice',
                'status' => 'sent',
                'number' => 'INV-'.uniqid(),
            ]);
            $allocations[$invoice->id] = 100;
        }

        app(AllocateCustomerPayment::class)->allocate($payment, $allocations);

        return $payment->fresh();
    }

    private function queryCountForMount(Payment $payment): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        Livewire::test(TallStackPaymentAllocation::class, [
            'company' => $this->company,
            'payment' => $payment,
        ]);

        DB::flushQueryLog();

        return $count;
    }

    public function test_query_count_stays_flat_as_allocation_count_grows(): void
    {
        $small = $this->queryCountForMount($this->paymentWithAllocations(2));
        $large = $this->queryCountForMount($this->paymentWithAllocations(20));

        $this->assertSame($small, $large, 'Allocation-heavy payments must not issue more queries than light ones.');
    }
}
