<?php

namespace Tests\Feature\Console;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * App\Console\Commands\MarkInvoicesOverdue — closes the "InvoiceStatus::
 * Overdue has zero writers" gap found during the status-transition
 * automation review. Mirrors ExpireQuotationsTest's structure for the
 * equivalent, already-shipped Quotation gap.
 */
class MarkInvoicesOverdueTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(Company $company, Client $client, InvoiceStatus $status, ?string $dueDate, float $balance): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => InvoiceType::Invoice,
            'status' => $status,
            'number' => 'ACM-INV-'.uniqid(),
            'due_date' => $dueDate,
        ]);

        $invoice->forceFill(['total' => $balance, 'balance' => $balance, 'amount_paid' => 0])->save();

        return $invoice->fresh();
    }

    public function test_command_marks_an_issued_invoice_past_due_date_with_balance_as_overdue(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);

        $invoice = $this->makeInvoice($company, $client, InvoiceStatus::Issued, now()->subDay()->toDateString(), 1000);

        Artisan::call('invoices:mark-overdue');

        $this->assertTrue($invoice->fresh()->status === InvoiceStatus::Overdue);
    }

    public function test_command_marks_a_partially_paid_invoice_past_due_date_as_overdue(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);

        $invoice = $this->makeInvoice($company, $client, InvoiceStatus::Partial, now()->subDay()->toDateString(), 500);

        Artisan::call('invoices:mark-overdue');

        $this->assertTrue($invoice->fresh()->status === InvoiceStatus::Overdue);
    }

    public function test_command_skips_an_issued_invoice_not_yet_due(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);

        $invoice = $this->makeInvoice($company, $client, InvoiceStatus::Issued, now()->addDay()->toDateString(), 1000);

        Artisan::call('invoices:mark-overdue');

        $this->assertTrue($invoice->fresh()->status === InvoiceStatus::Issued);
    }

    public function test_command_skips_a_fully_paid_invoice_past_due_date(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);

        $invoice = $this->makeInvoice($company, $client, InvoiceStatus::Paid, now()->subDay()->toDateString(), 0);

        Artisan::call('invoices:mark-overdue');

        $this->assertTrue($invoice->fresh()->status === InvoiceStatus::Paid);
    }

    public function test_command_skips_a_draft_invoice_past_due_date(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);

        $invoice = $this->makeInvoice($company, $client, InvoiceStatus::Draft, now()->subDay()->toDateString(), 1000);

        Artisan::call('invoices:mark-overdue');

        $this->assertTrue($invoice->fresh()->status === InvoiceStatus::Draft);
    }

    public function test_command_skips_an_invoice_with_no_due_date(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);

        $invoice = $this->makeInvoice($company, $client, InvoiceStatus::Issued, null, 1000);

        Artisan::call('invoices:mark-overdue');

        $this->assertTrue($invoice->fresh()->status === InvoiceStatus::Issued);
    }

    public function test_command_skips_a_held_invoice_that_would_otherwise_be_marked_overdue(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);

        $invoice = $this->makeInvoice($company, $client, InvoiceStatus::Issued, now()->subDay()->toDateString(), 1000);
        $invoice->forceFill(['held_at' => now(), 'held_reason' => 'Under dispute'])->save();

        Artisan::call('invoices:mark-overdue');

        $this->assertTrue($invoice->fresh()->status === InvoiceStatus::Issued);
    }
}
