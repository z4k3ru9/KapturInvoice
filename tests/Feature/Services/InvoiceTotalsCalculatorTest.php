<?php

namespace Tests\Feature\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\TaxRate;
use App\Services\InvoiceTotalsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ported from tests/Feature/Filament/AdminPanelResourcesTest.php during the
 * Filament-removal Phase B — these two tests exercise
 * App\Services\InvoiceTotalsCalculator directly (no Filament UI involved)
 * and had no other coverage, so they're kept here rather than dropped
 * along with the rest of that file's genuinely Filament-UI-only tests.
 */
class InvoiceTotalsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
    }

    public function test_invoice_totals_recalculate_when_items_and_taxes_change(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
        $product = Product::create(['company_id' => $this->company->id, 'name' => 'Widget', 'unit_cost' => 100]);
        $taxRate = TaxRate::create(['company_id' => $this->company->id, 'name' => 'VAT', 'rate' => 10]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
        ]);

        $item = $invoice->items()->create([
            'product_id' => $product->id,
            'title' => $product->name,
            'quantity' => 2,
            'unit_cost' => 100,
        ]);

        app(InvoiceTotalsCalculator::class)->syncItemTaxes($item, [$taxRate->id]);
        app(InvoiceTotalsCalculator::class)->recalculate($invoice->fresh());

        $invoice->refresh();

        $this->assertSame('200.00', (string) $invoice->subtotal);
        $this->assertSame('20.00', (string) $invoice->tax_total);
        $this->assertSame('220.00', (string) $invoice->total);
        $this->assertSame('220.00', (string) $invoice->balance);
    }

    /**
     * Regression test for the discount-order bug flagged in
     * docs/REFACTOR_PLAN.md §1.3: a document/invoice-level discount must
     * reduce the taxable base before tax is applied, not just the post-tax
     * total. 100 units @ 100 = 10,000 subtotal, 10% document discount =
     * 9,000 taxable, 10% VAT on 9,000 = 900 tax, total 9,900 — not
     * 10,000 - 1,000 + 1,000 (tax on the undiscounted subtotal) = 10,000.
     */
    public function test_document_level_discount_reduces_the_taxable_base_before_tax(): void
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
        $product = Product::create(['company_id' => $this->company->id, 'name' => 'Widget', 'unit_cost' => 100]);
        $taxRate = TaxRate::create(['company_id' => $this->company->id, 'name' => 'VAT', 'rate' => 10]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'discount' => 10,
            'discount_is_percentage' => true,
        ]);

        $item = $invoice->items()->create([
            'product_id' => $product->id,
            'title' => $product->name,
            'quantity' => 100,
            'unit_cost' => 100,
        ]);

        $calculator = app(InvoiceTotalsCalculator::class);
        $calculator->syncItemTaxes($item, [$taxRate->id]);
        $calculator->recalculate($invoice->fresh());

        $invoice->refresh();

        $this->assertSame('10000.00', (string) $invoice->subtotal);
        $this->assertSame('900.00', (string) $invoice->tax_total);
        $this->assertSame('9900.00', (string) $invoice->total);

        // Recalculating again must be idempotent — it must not re-apply the
        // document discount on top of the already-adjusted tax amount.
        $calculator->recalculate($invoice->fresh());
        $invoice->refresh();

        $this->assertSame('900.00', (string) $invoice->tax_total);
        $this->assertSame('9900.00', (string) $invoice->total);
    }
}
