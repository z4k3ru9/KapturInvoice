<?php

namespace App\Console\Commands;

use App\Enums\CatalogItemType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\ProposalStatus;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\TaxCategory;
use App\Enums\VendorBillStatus;
use App\Enums\VendorPurchaseOrderStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Proposal;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use App\Models\VendorPurchaseOrder;
use App\Models\VendorPurchaseOrderItem;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Floods one company with a large, deliberately edge-case-heavy dataset —
 * NOT for correctness testing (it bypasses the real domain actions
 * entirely: no IssueInvoice, no TaxCalculationService, no
 * DocumentNumberGenerator), purely to stress the ADMIN UI's layout under
 * real volume and pagination: does a long client/product name overflow
 * its table cell, does a document with 30 line items render sanely, does
 * the page-size selector and column sorting still behave with hundreds
 * of rows, does a huge total/negative-looking number misalign a
 * right-aligned column.
 *
 * Volume target is calibrated against this app's own documented REAL
 * legacy-import numbers (docs/data-import.md: Karunia Abadi's real v4
 * dump was 487 invoices/quotes, 134 clients, 1,972 items, 369 payments)
 * — deliberately seeded past that ceiling, not just up to it, since the
 * goal here is finding an overflow, not reproducing a typical company.
 *
 * Destructive-safe: only ever INSERTs new rows scoped to the given
 * company (via App\Models\Concerns\BelongsToCompany's auto-fill), never
 * touches an existing row. Re-running adds more on top rather than
 * erroring — intentional, since "flood with more" is the whole point.
 */
class StressSeedCompany extends Command
{
    protected $signature = 'stress:seed {company : Company slug}
        {--clients=250} {--vendors=80} {--products=150}
        {--invoices=700} {--quotations=200} {--jobs=100}
        {--proposals=80} {--vendor-purchase-orders=60} {--vendor-bills=60}
        {--payments=400}';

    protected $description = 'Flood one company with large, edge-case-heavy data for UI overflow/pagination stress testing';

    private const LONG_NAME_RATE = 0.12;

    private const VERY_LONG_NAME = 'PT. Perusahaan Dagang, Jasa Konstruksi, Elektrikal, Mekanikal, dan Instalasi Sistem Keamanan Multiguna Sejahtera Abadi Nusantara Internasional Tbk';

    /** Distinguishes this run's document numbers from any earlier run's, so re-running to "flood with more" never collides. */
    private string $runToken;

    private array $longWords = [
        'Konstruksi', 'Elektrikal', 'Mekanikal', 'Instalasi', 'Keamanan',
        'Teknologi', 'Informatika', 'Multiguna', 'Sejahtera', 'Nusantara',
        'Internasional', 'Perkasa', 'Mandiri', 'Persada', 'Utama',
    ];

    public function handle(): int
    {
        $company = Company::where('slug', $this->argument('company'))->first();

        if (! $company) {
            $this->error("No company with slug '{$this->argument('company')}'.");

            return self::FAILURE;
        }

        app(Tenancy::class)->set($company);

        $this->runToken = Str::upper(Str::random(4));
        $this->info("Flooding {$company->name} ({$company->slug})… run token {$this->runToken}");

        $clients = $this->seedClients($company, (int) $this->option('clients'));
        $this->info('Clients: '.$clients->count());

        $vendors = $this->seedVendors($company, (int) $this->option('vendors'));
        $this->info('Vendors: '.$vendors->count());

        $products = $this->seedProducts($company, (int) $this->option('products'));
        $this->info('Products: '.$products->count());

        $invoices = $this->seedInvoices($company, $clients, $products, (int) $this->option('invoices'));
        $this->info('Invoices: '.$invoices->count());

        $quotations = $this->seedQuotations($company, $clients, $products, (int) $this->option('quotations'));
        $this->info('Quotations: '.$quotations->count());

        $jobs = $this->seedJobs($company, $quotations, (int) $this->option('jobs'));
        $this->info('Jobs: '.$jobs->count());

        $proposals = $this->seedProposals($company, $clients, (int) $this->option('proposals'));
        $this->info('Proposals: '.$proposals->count());

        $pos = $this->seedVendorPurchaseOrders($company, $vendors, (int) $this->option('vendor-purchase-orders'));
        $this->info('Vendor POs: '.$pos->count());

        $bills = $this->seedVendorBills($company, $pos, (int) $this->option('vendor-bills'));
        $this->info('Vendor bills: '.$bills->count());

        $payments = $this->seedPayments($company, $invoices, (int) $this->option('payments'));
        $this->info('Payments: '.$payments->count());

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function name(bool $long = false): string
    {
        if ($long || $this->chance(self::LONG_NAME_RATE)) {
            return self::VERY_LONG_NAME;
        }

        return fake()->company().' '.fake()->companySuffix();
    }

    private function chance(float $probability): bool
    {
        return mt_rand() / mt_getrandmax() < $probability;
    }

    private function longDescription(): string
    {
        // A deliberately long, wrapped-paragraph description — stresses
        // any fixed-height table cell/PDF line-item row that assumes a
        // short one-liner.
        return implode(' ', fake()->sentences(8)).' '.implode(' ', $this->longWords);
    }

    private function seedClients(Company $company, int $count)
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'company_id' => $company->id,
                'name' => $this->name(),
                'email' => $this->chance(0.1)
                    ? 'very.long.edge.case.email.address.for.overflow.testing.purposes.only'.$i.'@a-genuinely-long-subdomain-name-for-testing.example-company-domain.co.id'
                    : fake()->companyEmail(),
                'phone' => fake()->phoneNumber(),
                'address_line_1' => $this->chance(0.1) ? fake()->address().', '.fake()->address() : fake()->streetAddress(),
                'city' => fake()->city(),
                'currency_code' => $company->currency_code,
                'default_discount' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Client::insert($rows);

        return Client::where('company_id', $company->id)->latest('id')->take($count)->get();
    }

    private function seedVendors(Company $company, int $count)
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'company_id' => $company->id,
                'name' => $this->name(),
                'email' => fake()->companyEmail(),
                'phone' => fake()->phoneNumber(),
                'address_line_1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Vendor::insert($rows);

        return Vendor::where('company_id', $company->id)->latest('id')->take($count)->get();
    }

    private function seedProducts(Company $company, int $count)
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'company_id' => $company->id,
                'sku' => 'SKU-'.Str::upper(Str::random(8)),
                'name' => $this->chance(self::LONG_NAME_RATE)
                    ? 'Hikvision DS-2CD2387G2-LU(C) 8MP ColorVu Fixed Bullet Network Camera with Built-in Microphone and Smart Hybrid Light IR/White Light 40m Range'
                    : fake()->words(3, true),
                'description' => $this->longDescription(),
                'unit_cost' => fake()->randomFloat(2, 10000, 25000000),
                'type' => fake()->randomElement(CatalogItemType::cases())->value,
                'tax_category' => fake()->randomElement(TaxCategory::cases())->value,
                'unit' => fake()->randomElement(['pcs', 'unit', 'set', 'meter', 'hour']),
                'stock_flag' => $this->chance(0.5),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Product::insert($rows);

        return Product::where('company_id', $company->id)->latest('id')->take($count)->get();
    }

    private function seedInvoices(Company $company, $clients, $products, int $count)
    {
        $statuses = InvoiceStatus::cases();
        $created = collect();

        for ($i = 0; $i < $count; $i++) {
            $client = $clients->random();
            $status = fake()->randomElement($statuses);
            $itemCount = $this->chance(0.05) ? mt_rand(20, 35) : mt_rand(1, 6);

            $items = [];
            $subtotal = 0;
            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products->random();
                $qty = mt_rand(1, 20);
                $unitCost = (float) $product->unit_cost;
                $lineTotal = round($qty * $unitCost, 2);
                $subtotal += $lineTotal;
                $items[] = [
                    'product_id' => $product->id,
                    'title' => $product->name,
                    'description' => $this->chance(0.15) ? $this->longDescription() : $product->description,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'discount' => 0,
                    'discount_is_percentage' => false,
                    'line_total' => $lineTotal,
                    'sort_order' => $j,
                ];
            }

            $balance = in_array($status, [InvoiceStatus::Paid, InvoiceStatus::Draft, InvoiceStatus::Cancelled, InvoiceStatus::Void], true)
                ? 0.0
                : round($subtotal * fake()->randomElement([0, 0.3, 0.5, 1]), 2);

            // subtotal/tax_total/total/balance are deliberately NOT in
            // Invoice's #[Fillable] list (they're normally only ever
            // written by TaxCalculationService/RecalculateInvoiceReceivables)
            // — forceFill them past that guard since this command exists
            // to flood raw rows for a UI layout stress test, not to
            // exercise the real billing pipeline.
            $invoice = Invoice::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'type' => InvoiceType::Invoice,
                'status' => $status,
                'number' => 'STRESS-'.$this->runToken.'-INV-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'invoice_date' => fake()->dateTimeBetween('-2 years', 'now'),
                'due_date' => fake()->dateTimeBetween('now', '+60 days'),
                'currency_code' => $company->currency_code,
                'discount' => 0,
                'discount_is_percentage' => false,
                'public_notes' => $this->chance(0.1) ? $this->longDescription() : null,
                'terms' => $this->chance(0.1) ? $this->longDescription() : null,
            ]);
            $invoice->forceFill(['subtotal' => $subtotal, 'tax_total' => 0, 'total' => $subtotal, 'balance' => $balance])->save();

            foreach ($items as $item) {
                InvoiceItem::create([...$item, 'invoice_id' => $invoice->id]);
            }

            $created->push($invoice);
        }

        return $created;
    }

    private function seedQuotations(Company $company, $clients, $products, int $count)
    {
        $statuses = QuotationStatus::cases();
        $created = collect();

        for ($i = 0; $i < $count; $i++) {
            $client = $clients->random();
            $itemCount = mt_rand(1, 10);
            $items = [];
            $subtotal = 0;
            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products->random();
                $qty = mt_rand(1, 15);
                $unitCost = (float) $product->unit_cost;
                $lineTotal = round($qty * $unitCost, 2);
                $subtotal += $lineTotal;
                $items[] = [
                    'product_id' => $product->id,
                    'title' => $product->name,
                    'description' => $product->description,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'discount' => 0,
                    'discount_is_percentage' => false,
                    'line_total' => $lineTotal,
                    'sort_order' => $j,
                ];
            }

            $quotation = Quotation::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'status' => fake()->randomElement($statuses),
                'number' => 'STRESS-'.$this->runToken.'-QUO-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'quotation_date' => fake()->dateTimeBetween('-1 year', 'now'),
                'valid_until' => fake()->dateTimeBetween('now', '+30 days'),
                'discount' => 0,
                'discount_is_percentage' => false,
            ]);
            $quotation->forceFill(['subtotal' => $subtotal, 'total' => $subtotal, 'portal_key' => (string) Str::uuid()])->save();

            foreach ($items as $item) {
                QuotationItem::create([...$item, 'quotation_id' => $quotation->id]);
            }

            $created->push($quotation);
        }

        return $created;
    }

    private function seedJobs(Company $company, $quotations, int $count)
    {
        $statuses = SalesOrderStatus::cases();
        $created = collect();
        $pool = $quotations->shuffle()->take(min($count, $quotations->count()));

        $i = 0;
        foreach ($pool as $quotation) {
            $i++;
            $job = SalesOrder::create([
                'company_id' => $company->id,
                'client_id' => $quotation->client_id,
                'quotation_id' => $quotation->id,
                'number' => 'STRESS-'.$this->runToken.'-JOB-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'status' => fake()->randomElement($statuses),
                'approved_value' => $quotation->total,
                'job_type' => fake()->randomElement(['goods', 'installation', 'service']),
            ]);
            $created->push($job);
        }

        return $created;
    }

    private function seedProposals(Company $company, $clients, int $count)
    {
        $statuses = ProposalStatus::cases();
        $created = collect();

        for ($i = 0; $i < $count; $i++) {
            $created->push(Proposal::create([
                'company_id' => $company->id,
                'client_id' => $clients->random()->id,
                'title' => $this->chance(self::LONG_NAME_RATE)
                    ? 'Comprehensive Integrated Security, Networking, and Electrical Infrastructure Modernization Proposal for Multi-Site Enterprise Deployment — Phase '.($i + 1)
                    : fake()->catchPhrase(),
                'html' => '<p>'.$this->longDescription().'</p>',
                'status' => fake()->randomElement($statuses),
                'amount' => fake()->randomFloat(2, 500000, 500000000),
                'valid_until' => fake()->dateTimeBetween('now', '+45 days'),
            ]));
        }

        return $created;
    }

    private function seedVendorPurchaseOrders(Company $company, $vendors, int $count)
    {
        $statuses = VendorPurchaseOrderStatus::cases();
        $created = collect();

        for ($i = 0; $i < $count; $i++) {
            $vendor = $vendors->random();
            $itemCount = mt_rand(1, 8);
            $items = [];
            $total = 0;
            for ($j = 0; $j < $itemCount; $j++) {
                $qty = mt_rand(1, 50);
                $unitCost = fake()->randomFloat(2, 5000, 2000000);
                $lineTotal = round($qty * $unitCost, 2);
                $total += $lineTotal;
                $items[] = [
                    'title' => fake()->words(4, true),
                    'description' => fake()->sentence(),
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'discount' => 0,
                    'discount_is_percentage' => false,
                    'line_total' => $lineTotal,
                    'sort_order' => $j,
                ];
            }

            $po = VendorPurchaseOrder::create([
                'company_id' => $company->id,
                'vendor_id' => $vendor->id,
                'number' => 'STRESS-'.$this->runToken.'-PO-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'status' => fake()->randomElement($statuses),
                'po_date' => fake()->dateTimeBetween('-1 year', 'now'),
                'total' => $total,
            ]);

            foreach ($items as $item) {
                VendorPurchaseOrderItem::create([...$item, 'vendor_purchase_order_id' => $po->id]);
            }

            $created->push($po);
        }

        return $created;
    }

    private function seedVendorBills(Company $company, $pos, int $count)
    {
        $statuses = VendorBillStatus::cases();
        $created = collect();
        $pool = $pos->shuffle()->take(min($count, $pos->count()));

        $i = 0;
        foreach ($pool as $po) {
            $i++;
            $itemCount = mt_rand(1, 6);
            $items = [];
            $total = 0;
            for ($j = 0; $j < $itemCount; $j++) {
                $qty = mt_rand(1, 30);
                $unitCost = fake()->randomFloat(2, 5000, 2000000);
                $lineTotal = round($qty * $unitCost, 2);
                $total += $lineTotal;
                $items[] = [
                    'title' => fake()->words(4, true),
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'discount' => 0,
                    'discount_is_percentage' => false,
                    'net_amount' => $lineTotal,
                    'tax_amount' => 0,
                    'line_total' => $lineTotal,
                    'sort_order' => $j,
                ];
            }

            $bill = VendorBill::create([
                'company_id' => $company->id,
                'vendor_id' => $po->vendor_id,
                'vendor_purchase_order_id' => $po->id,
                'number' => 'STRESS-'.$this->runToken.'-VB-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'status' => fake()->randomElement($statuses),
                'bill_date' => fake()->dateTimeBetween('-1 year', 'now'),
                'total' => $total,
            ]);
            // amount_paid/balance are normally RecalculateVendorBillPayments-owned, not fillable.
            $bill->forceFill(['amount_paid' => 0, 'balance' => $total])->save();

            foreach ($items as $item) {
                VendorBillItem::create([...$item, 'vendor_bill_id' => $bill->id]);
            }

            $created->push($bill);
        }

        return $created;
    }

    private function seedPayments(Company $company, $invoices, int $count)
    {
        $created = collect();
        $pool = $invoices->shuffle()->take(min($count, $invoices->count()));

        foreach ($pool as $invoice) {
            $created->push(Payment::create([
                'company_id' => $company->id,
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'amount' => fake()->randomFloat(2, 10000, (float) $invoice->total ?: 1000000),
                'currency_code' => $company->currency_code,
                'method' => fake()->randomElement(['bank_transfer', 'cash', 'credit_card', 'cheque']),
                'status' => fake()->randomElement(PaymentStatus::cases()),
                'payment_date' => fake()->dateTimeBetween('-1 year', 'now'),
                'notes' => $this->chance(0.1) ? $this->longDescription() : null,
            ]));
        }

        return $created;
    }
}
