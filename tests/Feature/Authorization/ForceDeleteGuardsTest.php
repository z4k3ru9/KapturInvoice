<?php

namespace Tests\Feature\Authorization;

use App\Actions\Billing\ForceDeleteCredit;
use App\Actions\Billing\ForceDeleteInvoice;
use App\Actions\Billing\ForceDeleteQuote;
use App\Actions\Procurement\ForceDeleteVendorBill;
use App\Actions\Procurement\ForceDeleteVendorPurchaseOrder;
use App\Actions\Receivables\ForceDeletePayment;
use App\Actions\Sales\ForceDeleteQuotation;
use App\Actions\Sales\ForceDeleteSalesOrder;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\VendorBillStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorPurchaseOrder;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Ported from the deleted `Tests\Feature\Filament\ForceDeleteGuardsTest`
 * (removed, unreplaced, when Filament was) — same 14 guard predicate
 * scenarios, now proven against the App\Actions\* Action-layer classes
 * that replaced each Filament Table's static `isSafeToForceDelete()`
 * guard, plus an authorization test proving
 * `App\Providers\AppServiceProvider::registerCompanyRoleGate()`'s
 * "physical deletion is Owner-only" rule holds through the new Action
 * (not just at the bare Gate level).
 *
 * docs/REFACTOR_PLAN.md drift audit this originally caught: Filament's
 * stock ForceDeleteBulkAction only hid itself while the Trashed filter
 * wasn't set — once switched to "With Trashed"/"Only Trashed", any
 * selected row (regardless of status) got `forceDelete()`'d directly,
 * contradicting FINALIZED-DECISIONS.md §2's "never physically deleted,
 * including by an Owner." Each guard predicate below proves the rule
 * itself, independent of any UI.
 */
class ForceDeleteGuardsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->owner = User::factory()->create();
        $this->company->users()->attach($this->owner, ['role' => 'owner']);
        $this->actingAs($this->owner);
        app(Tenancy::class)->set($this->company);
    }

    public function test_a_draft_invoice_with_no_history_is_safe_to_force_delete(): void
    {
        $invoice = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => 'draft']);

        $this->assertTrue(ForceDeleteInvoice::isSafeToForceDelete($invoice));

        app(ForceDeleteInvoice::class)->forceDelete($invoice, $this->owner);

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    public function test_an_issued_invoice_is_never_safe_to_force_delete(): void
    {
        $invoice = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => InvoiceStatus::Issued]);

        $this->assertFalse(ForceDeleteInvoice::isSafeToForceDelete($invoice));

        $this->expectException(RuntimeException::class);
        app(ForceDeleteInvoice::class)->forceDelete($invoice, $this->owner);
    }

    public function test_a_draft_invoice_with_a_recorded_payment_is_not_safe_to_force_delete(): void
    {
        $invoice = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => 'draft']);
        Payment::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'invoice_id' => $invoice->id, 'amount' => 10, 'status' => PaymentStatus::Pending]);

        $this->assertFalse(ForceDeleteInvoice::isSafeToForceDelete($invoice->fresh()));

        $this->expectException(RuntimeException::class);
        app(ForceDeleteInvoice::class)->forceDelete($invoice->fresh(), $this->owner);
    }

    public function test_a_pending_payment_with_no_history_is_safe_to_force_delete(): void
    {
        $payment = Payment::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'amount' => 10, 'status' => PaymentStatus::Pending]);

        $this->assertTrue(ForceDeletePayment::isSafeToForceDelete($payment));

        app(ForceDeletePayment::class)->forceDelete($payment, $this->owner);

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    }

    public function test_a_verified_payment_is_never_safe_to_force_delete(): void
    {
        $payment = Payment::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'amount' => 10, 'status' => PaymentStatus::Verified]);

        $this->assertFalse(ForceDeletePayment::isSafeToForceDelete($payment));

        $this->expectException(RuntimeException::class);
        app(ForceDeletePayment::class)->forceDelete($payment, $this->owner);
    }

    public function test_a_draft_quotation_with_no_job_is_safe_to_force_delete(): void
    {
        $quotation = Quotation::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'number' => 'ACM-QUO-1', 'status' => QuotationStatus::Draft]);

        $this->assertTrue(ForceDeleteQuotation::isSafeToForceDelete($quotation));

        app(ForceDeleteQuotation::class)->forceDelete($quotation, $this->owner);

        $this->assertDatabaseMissing('quotations', ['id' => $quotation->id]);
    }

    public function test_a_quotation_with_a_job_created_from_it_is_never_safe_to_force_delete(): void
    {
        $quotation = Quotation::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'number' => 'ACM-QUO-2', 'status' => QuotationStatus::Draft]);
        SalesOrder::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'quotation_id' => $quotation->id, 'number' => 'ACM-SO-1', 'approved_value' => 0]);

        $this->assertFalse(ForceDeleteQuotation::isSafeToForceDelete($quotation->fresh()));

        $this->expectException(RuntimeException::class);
        app(ForceDeleteQuotation::class)->forceDelete($quotation->fresh(), $this->owner);
    }

    public function test_a_draft_quote_with_no_converted_invoice_is_safe_to_force_delete(): void
    {
        $quote = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'quote', 'status' => 'draft']);

        $this->assertTrue(ForceDeleteQuote::isSafeToForceDelete($quote));

        app(ForceDeleteQuote::class)->forceDelete($quote, $this->owner);

        $this->assertDatabaseMissing('invoices', ['id' => $quote->id]);
    }

    public function test_a_quote_already_converted_to_an_invoice_is_never_safe_to_force_delete(): void
    {
        $quote = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'quote', 'status' => 'draft']);
        Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => 'draft', 'converted_from_quote_id' => $quote->id]);

        $this->assertFalse(ForceDeleteQuote::isSafeToForceDelete($quote->fresh()));

        $this->expectException(RuntimeException::class);
        app(ForceDeleteQuote::class)->forceDelete($quote->fresh(), $this->owner);
    }

    public function test_a_draft_job_with_no_activity_is_safe_to_force_delete(): void
    {
        $quotation = Quotation::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'number' => 'ACM-QUO-3']);
        $job = SalesOrder::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'quotation_id' => $quotation->id, 'number' => 'ACM-SO-2', 'approved_value' => 0, 'status' => SalesOrderStatus::Draft]);

        $this->assertTrue(ForceDeleteSalesOrder::isSafeToForceDelete($job));

        app(ForceDeleteSalesOrder::class)->forceDelete($job, $this->owner);

        $this->assertDatabaseMissing('sales_orders', ['id' => $job->id]);
    }

    public function test_a_job_with_an_invoice_is_never_safe_to_force_delete(): void
    {
        $quotation = Quotation::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'number' => 'ACM-QUO-4']);
        $job = SalesOrder::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'quotation_id' => $quotation->id, 'number' => 'ACM-SO-3', 'approved_value' => 0, 'status' => SalesOrderStatus::Draft]);
        Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => 'draft', 'sales_order_id' => $job->id]);

        $this->assertFalse(ForceDeleteSalesOrder::isSafeToForceDelete($job->fresh()));

        $this->expectException(RuntimeException::class);
        app(ForceDeleteSalesOrder::class)->forceDelete($job->fresh(), $this->owner);
    }

    public function test_a_job_that_is_not_draft_is_never_safe_to_force_delete(): void
    {
        $quotation = Quotation::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'number' => 'ACM-QUO-5']);
        $job = SalesOrder::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'quotation_id' => $quotation->id, 'number' => 'ACM-SO-4', 'approved_value' => 0, 'status' => SalesOrderStatus::Approved]);

        $this->assertFalse(ForceDeleteSalesOrder::isSafeToForceDelete($job));

        $this->expectException(RuntimeException::class);
        app(ForceDeleteSalesOrder::class)->forceDelete($job, $this->owner);
    }

    public function test_an_unapplied_credit_is_safe_to_force_delete(): void
    {
        $credit = Credit::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'amount' => 100]);

        $this->assertTrue(ForceDeleteCredit::isSafeToForceDelete($credit));

        app(ForceDeleteCredit::class)->forceDelete($credit, $this->owner);

        $this->assertDatabaseMissing('credits', ['id' => $credit->id]);
    }

    public function test_a_partially_applied_credit_is_never_safe_to_force_delete(): void
    {
        $credit = Credit::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'amount' => 100]);
        // 'balance' is deliberately not mass-assignable (Credit::booted()
        // is the only normal writer) — forceFill simulates a real
        // application of the credit for this guard test.
        $credit->forceFill(['balance' => 40])->save();

        $this->assertFalse(ForceDeleteCredit::isSafeToForceDelete($credit->fresh()));

        $this->expectException(RuntimeException::class);
        app(ForceDeleteCredit::class)->forceDelete($credit->fresh(), $this->owner);
    }

    // --- Ported from the deleted Filament-era force-delete-bulk-action
    // tests in Tests\Feature\Procurement\VendorDocumentLockAfterWorkflowTest ---

    public function test_force_delete_skips_an_approved_vendor_purchase_order_with_a_bill(): void
    {
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Acme Supplies']);
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'number' => 'ACM-VPO-0005']);
        $po->forceFill(['total' => 1000, 'status' => 'approved'])->save();
        VendorBill::create(['company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'vendor_purchase_order_id' => $po->id, 'number' => 'ACM-VBL-0003']);

        $this->assertFalse(ForceDeleteVendorPurchaseOrder::isSafeToForceDelete($po->fresh()));

        $this->expectException(RuntimeException::class);
        app(ForceDeleteVendorPurchaseOrder::class)->forceDelete($po->fresh(), $this->owner);
    }

    public function test_force_delete_removes_an_unreferenced_draft_vendor_purchase_order(): void
    {
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Acme Supplies']);
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'number' => 'ACM-VPO-0006'])->fresh();

        $this->assertTrue(ForceDeleteVendorPurchaseOrder::isSafeToForceDelete($po));

        app(ForceDeleteVendorPurchaseOrder::class)->forceDelete($po, $this->owner);

        $this->assertDatabaseMissing('vendor_purchase_orders', ['id' => $po->id]);
    }

    public function test_force_delete_skips_a_submitted_vendor_bill(): void
    {
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Acme Supplies']);
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'number' => 'ACM-VPO-0007']);
        $bill = VendorBill::create(['company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'vendor_purchase_order_id' => $po->id, 'number' => 'ACM-VBL-0004']);
        $bill->forceFill(['total' => 1000, 'status' => VendorBillStatus::Submitted])->save();

        $this->assertFalse(ForceDeleteVendorBill::isSafeToForceDelete($bill->fresh()));

        $this->expectException(RuntimeException::class);
        app(ForceDeleteVendorBill::class)->forceDelete($bill->fresh(), $this->owner);
    }

    public function test_force_delete_removes_an_unreferenced_draft_vendor_bill(): void
    {
        $vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Acme Supplies']);
        $po = VendorPurchaseOrder::create(['company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'number' => 'ACM-VPO-0008']);
        $bill = VendorBill::create(['company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'vendor_purchase_order_id' => $po->id, 'number' => 'ACM-VBL-0005'])->fresh();

        $this->assertTrue(ForceDeleteVendorBill::isSafeToForceDelete($bill));

        app(ForceDeleteVendorBill::class)->forceDelete($bill, $this->owner);

        $this->assertDatabaseMissing('vendor_bills', ['id' => $bill->id]);
    }

    // --- Authorization: Gate::before's "physical deletion is Owner-only"
    // rule must hold through the new Action, not just at the bare Gate. ---

    /** @return array<string, array{string}> */
    public static function nonOwnerRolesProvider(): array
    {
        return [
            'admin' => ['admin'],
            'accountant' => ['accountant'],
            'sales' => ['sales'],
            'staff' => ['staff'],
            'auditor' => ['auditor'],
        ];
    }

    #[DataProvider('nonOwnerRolesProvider')]
    public function test_a_non_owner_role_is_blocked_from_force_deleting_an_otherwise_eligible_invoice(string $role): void
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role]);
        $this->actingAs($user);
        app(Tenancy::class)->set($this->company);

        $invoice = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => 'draft']);

        $this->assertTrue(ForceDeleteInvoice::isSafeToForceDelete($invoice));
        $this->assertFalse($user->can('forceDelete', $invoice));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only the company Owner may permanently delete an invoice.');
        app(ForceDeleteInvoice::class)->forceDelete($invoice, $user);
    }
}
