<?php

namespace Tests\Feature\Documents;

use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\HandoverReport;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use App\Models\VendorPayment;
use App\Models\VendorPaymentReceipt;
use App\Models\VendorPurchaseOrder;
use App\Models\VendorPurchaseOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PDF export for the five vendor/delivery-side launch document types
 * required by docs/rebuild/specs/06b-ux-browser-soa/Specs.md "Required
 * launch document coverage": Vendor PO, Vendor Bill, Vendor Payment
 * Receipt, Delivery Order, Handover Report. Mirrors
 * tests/Feature/PdfExportTest.php's fixture-building style — each
 * document renders in Bahasa Indonesia by default and supports an
 * explicit English `document_language` override.
 */
class VendorDocumentPdfTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(Company $company): User
    {
        $user = User::factory()->create();
        $company->users()->attach($user, ['role' => 'owner']);

        return $user;
    }

    private function makeCompanyAndVendor(): array
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $vendor = Vendor::create(['company_id' => $company->id, 'name' => 'Vendor Co']);

        return [$company, $vendor];
    }

    public function test_vendor_purchase_order_pdf_downloads_in_bahasa_by_default(): void
    {
        [$company, $vendor] = $this->makeCompanyAndVendor();
        $user = $this->actingUser($company);

        $po = VendorPurchaseOrder::create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'number' => 'ACM-VPO-0001',
            'status' => 'draft',
            'po_date' => '2026-09-01',
            'total' => 500,
        ]);
        VendorPurchaseOrderItem::create([
            'vendor_purchase_order_id' => $po->id,
            'title' => 'Cabling', 'quantity' => 1, 'unit_cost' => 500, 'line_total' => 500,
        ]);

        $this->actingAs($user)
            ->get(route('vendor-purchase-orders.pdf', $po))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_vendor_purchase_order_pdf_downloads_in_english_when_overridden(): void
    {
        [$company, $vendor] = $this->makeCompanyAndVendor();
        $user = $this->actingUser($company);

        $po = VendorPurchaseOrder::create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'number' => 'ACM-VPO-0002',
            'status' => 'draft',
            'po_date' => '2026-09-01',
            'total' => 500,
            'document_language' => 'en',
        ]);
        VendorPurchaseOrderItem::create([
            'vendor_purchase_order_id' => $po->id,
            'title' => 'Cabling', 'quantity' => 1, 'unit_cost' => 500, 'line_total' => 500,
        ]);

        $this->actingAs($user)
            ->get(route('vendor-purchase-orders.pdf', $po))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function makeBill(Company $company, Vendor $vendor, string $number, ?string $documentLanguage = null): VendorBill
    {
        $po = VendorPurchaseOrder::create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'number' => "{$number}-PO",
            'status' => 'approved',
            'total' => 500,
        ]);

        $bill = VendorBill::create([
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'vendor_purchase_order_id' => $po->id,
            'number' => $number,
            'status' => 'approved',
            'bill_date' => '2026-09-01',
            'total' => 500,
            'document_language' => $documentLanguage,
        ]);
        $bill->forceFill(['amount_paid' => 0, 'balance' => 500])->save();

        VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'title' => 'Cabling', 'quantity' => 1, 'unit_cost' => 500,
            'net_amount' => 500, 'tax_amount' => 0, 'line_total' => 500,
        ]);

        return $bill->fresh();
    }

    public function test_vendor_bill_pdf_downloads_in_bahasa_by_default(): void
    {
        [$company, $vendor] = $this->makeCompanyAndVendor();
        $user = $this->actingUser($company);
        $bill = $this->makeBill($company, $vendor, 'ACM-VBL-0001');

        $this->actingAs($user)
            ->get(route('vendor-bills.pdf', $bill))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_vendor_bill_pdf_downloads_in_english_when_overridden(): void
    {
        [$company, $vendor] = $this->makeCompanyAndVendor();
        $user = $this->actingUser($company);
        $bill = $this->makeBill($company, $vendor, 'ACM-VBL-0002', 'en');

        $this->actingAs($user)
            ->get(route('vendor-bills.pdf', $bill))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function makeVerifiedPaymentReceipt(Company $company, Vendor $vendor, string $billNumber, ?string $documentLanguage = null): VendorPaymentReceipt
    {
        $bill = $this->makeBill($company, $vendor, $billNumber);

        $payment = VendorPayment::create([
            'company_id' => $company->id,
            'vendor_bill_id' => $bill->id,
            'status' => 'verified',
            'amount' => 500,
            'payment_date' => '2026-09-05',
            'method' => 'bank_transfer',
            'reference' => 'REF-001',
            'proof_path' => 'vendor-payment-proofs/proof.pdf',
            'verified_at' => now(),
        ]);

        return VendorPaymentReceipt::create([
            'company_id' => $company->id,
            'vendor_payment_id' => $payment->id,
            'number' => "{$billNumber}-VPR",
            'issued_at' => now(),
            'document_language' => $documentLanguage,
        ])->fresh();
    }

    public function test_vendor_payment_receipt_pdf_downloads_in_bahasa_by_default(): void
    {
        [$company, $vendor] = $this->makeCompanyAndVendor();
        $user = $this->actingUser($company);
        $receipt = $this->makeVerifiedPaymentReceipt($company, $vendor, 'ACM-VBL-0003');

        $this->actingAs($user)
            ->get(route('vendor-payment-receipts.pdf', $receipt))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_vendor_payment_receipt_pdf_downloads_in_english_when_overridden(): void
    {
        [$company, $vendor] = $this->makeCompanyAndVendor();
        $user = $this->actingUser($company);
        $receipt = $this->makeVerifiedPaymentReceipt($company, $vendor, 'ACM-VBL-0004', 'en');

        $this->actingAs($user)
            ->get(route('vendor-payment-receipts.pdf', $receipt))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function makeJob(Company $company): SalesOrder
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        $quotation = Quotation::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'ACM-QUO-'.random_int(100000, 999999),
        ]);

        $salesOrder = SalesOrder::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
            'number' => 'ACM-SO-'.random_int(100000, 999999),
            'approved_value' => 1000,
            'requires_handover' => false,
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'title' => 'Widget',
            'quantity' => 2,
            'unit_cost' => 500,
            'line_total' => 1000,
        ]);

        return $salesOrder->fresh(['items']);
    }

    public function test_delivery_order_pdf_downloads_in_bahasa_by_default(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $user = $this->actingUser($company);
        $job = $this->makeJob($company);

        $deliveryOrder = DeliveryOrder::create([
            'company_id' => $company->id,
            'sales_order_id' => $job->id,
            'number' => 'ACM-DO-0001',
            'delivery_date' => '2026-09-10',
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'sales_order_item_id' => $job->items->first()->id,
            'description' => 'Widget',
            'quantity_delivered' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('delivery-orders.pdf', $deliveryOrder))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_delivery_order_pdf_downloads_in_english_when_overridden(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $user = $this->actingUser($company);
        $job = $this->makeJob($company);

        $deliveryOrder = DeliveryOrder::create([
            'company_id' => $company->id,
            'sales_order_id' => $job->id,
            'number' => 'ACM-DO-0002',
            'delivery_date' => '2026-09-10',
            'document_language' => 'en',
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'sales_order_item_id' => $job->items->first()->id,
            'description' => 'Widget',
            'quantity_delivered' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('delivery-orders.pdf', $deliveryOrder))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_handover_report_pdf_downloads_in_bahasa_by_default(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $user = $this->actingUser($company);
        $job = $this->makeJob($company);

        $handoverReport = HandoverReport::create([
            'company_id' => $company->id,
            'sales_order_id' => $job->id,
            'number' => 'ACM-HR-0001',
            'handover_date' => '2026-09-12',
        ]);

        $this->actingAs($user)
            ->get(route('handover-reports.pdf', $handoverReport))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_handover_report_pdf_downloads_in_english_when_overridden(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $user = $this->actingUser($company);
        $job = $this->makeJob($company);

        $handoverReport = HandoverReport::create([
            'company_id' => $company->id,
            'sales_order_id' => $job->id,
            'number' => 'ACM-HR-0002',
            'handover_date' => '2026-09-12',
            'document_language' => 'en',
        ]);

        $this->actingAs($user)
            ->get(route('handover-reports.pdf', $handoverReport))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
