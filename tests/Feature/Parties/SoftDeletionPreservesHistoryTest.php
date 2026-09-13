<?php

namespace Tests\Feature\Parties;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 02 required test (docs/rebuild/specs/02-parties-and-catalog/Specs.md):
 * "Soft deletion of unused master data does not remove historical
 * snapshots." Client/Product are already SoftDeletes-only (no cascade
 * delete of dependent invoices/items — invoice_items.product_id is
 * nullOnDelete, and a soft delete never touches other tables' rows at
 * all), and invoice_items already snapshot their own title/unit_cost at
 * creation time rather than live-joining the product. This test proves
 * that structural guarantee explicitly rather than leaving it implicit.
 */
class SoftDeletionPreservesHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deleting_a_client_does_not_remove_its_invoices(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'number' => 'INV-0001',
        ]);

        $client->delete();

        $this->assertTrue($client->fresh()->trashed());
        $this->assertNotNull(Invoice::query()->find($invoice->id));
        $this->assertSame($client->id, $invoice->fresh()->client_id);
        // The invoice's client relation is still resolvable (with trashed
        // parents included), not silently nulled or orphaned.
        $this->assertTrue($invoice->fresh()->client()->withTrashed()->first()->is($client));
    }

    public function test_soft_deleting_a_catalog_item_preserves_the_invoice_items_snapshot(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Widget',
            'unit_cost' => 100,
        ]);
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
        ]);
        $item = $invoice->items()->create([
            'product_id' => $product->id,
            'title' => $product->name,
            'quantity' => 2,
            'unit_cost' => $product->unit_cost,
        ]);

        $product->delete();

        $this->assertTrue($product->fresh()->trashed());

        // The line item keeps its own snapshotted title/unit_cost — it
        // never re-reads a live product row, so a later price change or
        // deletion cannot retroactively alter an issued invoice's history.
        $item->refresh();
        $this->assertSame('Widget', $item->title);
        $this->assertSame('100.0000', $item->unit_cost);
        $this->assertSame($product->id, $item->product_id);
    }
}
