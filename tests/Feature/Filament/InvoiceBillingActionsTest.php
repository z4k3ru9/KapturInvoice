<?php

namespace Tests\Feature\Filament;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Exercises the Phase 04 (docs/rebuild/specs/04-billing-and-receivables)
 * Issue/Amend/Void & reissue table actions wired onto
 * App\Filament\Resources\Invoices\Tables\InvoicesTable — the underlying
 * action classes (App\Actions\Billing\*) already have their own thorough
 * unit-adjacent coverage in tests/Feature/Billing/InvoiceIssuanceTest.php;
 * this file proves the Livewire wiring itself actually calls them.
 */
class InvoiceBillingActionsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    private function draftInvoice(): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => InvoiceStatus::Draft,
        ]);

        $invoice->items()->create(['title' => 'Widget', 'quantity' => 1, 'unit_cost' => 100]);

        return $invoice;
    }

    public function test_the_issue_table_action_issues_a_draft_invoice(): void
    {
        $invoice = $this->draftInvoice();

        Livewire::test(ListInvoices::class)
            ->callTableAction('issue', $invoice);

        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->number);
        $this->assertNotNull($invoice->fresh()->taxSnapshot);
    }

    public function test_the_amend_table_action_creates_a_linked_correction_invoice(): void
    {
        $invoice = $this->draftInvoice();
        $invoice->forceFill(['status' => InvoiceStatus::Issued, 'number' => 'LEGACY-0002', 'issued_at' => now(), 'total' => 100])->save();

        Livewire::test(ListInvoices::class)
            ->callTableAction('amend', $invoice, data: [
                'reason' => 'Wrong quantity billed',
                'items' => [
                    ['title' => 'Widget', 'quantity' => 2, 'unit_cost' => 100, 'discount' => 0, 'discount_is_percentage' => false],
                ],
            ]);

        $original = $invoice->fresh();
        $this->assertSame(InvoiceStatus::Amended, $original->status);
        $this->assertNotNull($original->correction);
        $this->assertSame('Wrong quantity billed', $original->correction->correction_reason);
    }

    public function test_the_void_and_reissue_table_action_creates_a_fresh_numbered_invoice(): void
    {
        $invoice = $this->draftInvoice();
        // A number outside the new "COMPANY-INV-YEARMONTHSEQ" format so it
        // can never collide with the fresh number VoidAndReissueInvoice
        // allocates from the same 'invoice' sequence — mirrors a legacy
        // already-imported invoice's number shape.
        $invoice->forceFill(['status' => InvoiceStatus::Issued, 'number' => 'LEGACY-0001', 'issued_at' => now(), 'total' => 100])->save();

        Livewire::test(ListInvoices::class)
            ->callTableAction('voidAndReissue', $invoice, data: [
                'reason' => 'Wrong client billed',
                'items' => [
                    ['title' => 'Widget', 'quantity' => 1, 'unit_cost' => 100, 'discount' => 0, 'discount_is_percentage' => false],
                ],
            ]);

        $original = $invoice->fresh();
        $this->assertSame(InvoiceStatus::Void, $original->status);
        $this->assertSame('Wrong client billed', $original->void_reason);
        $this->assertNotNull($original->correction);
        $this->assertNotSame($original->number, $original->correction->number);
    }
}
