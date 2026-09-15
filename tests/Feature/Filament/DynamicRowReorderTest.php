<?php

namespace Tests\Feature\Filament;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Invoices\RelationManagers\ItemsRelationManager;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 06B Slice 3 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md):
 * "Provide drag handles and keyboard reorder controls; preserve
 * deliberate row order in PDFs." / "Implement dynamic row behavior with
 * batched requests and deterministic sort order in persisted documents."
 * Filament's built-in `reorderable()` table behavior persists every
 * dragged row's new position in one batched UPDATE (never per
 * keystroke); App\Models\Invoice::items() already orders by this same
 * `sort_order` column, so a real drag reorder changes both the admin
 * table's and the printed PDF's row order identically.
 */
class DynamicRowReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reordering_invoice_items_persists_sort_order_in_one_batched_write(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $company->users()->attach($user, ['role' => 'owner']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        $this->actingAs($user);
        Filament::setTenant($company);
        app(Tenancy::class)->set($company);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => InvoiceStatus::Draft,
            'number' => 'ACM-INV-REORDER',
            'currency_code' => 'USD',
        ]);

        $first = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'First typed', 'quantity' => 1, 'unit_cost' => 100, 'sort_order' => 0]);
        $second = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Second typed', 'quantity' => 1, 'unit_cost' => 200, 'sort_order' => 1]);
        $third = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Third typed', 'quantity' => 1, 'unit_cost' => 300, 'sort_order' => 2]);

        // Confirms items() really is ordered by sort_order before any
        // reorder happens (the baseline this feature depends on).
        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $invoice->fresh()->items->pluck('id')->all(),
        );

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $invoice,
            'pageClass' => ViewInvoice::class,
        ])->call('reorderTable', [$third->id, $first->id, $second->id]);

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            $invoice->fresh()->items->pluck('id')->all(),
        );
    }

    public function test_reordering_an_issued_invoices_items_is_rejected(): void
    {
        // Codex review finding on PR #4: reordering an issued invoice's
        // items silently mutates an already-printed document and breaks
        // the positional correspondence with its frozen tax snapshot's
        // line-by-line breakdown.
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $company->users()->attach($user, ['role' => 'owner']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        $this->actingAs($user);
        Filament::setTenant($company);
        app(Tenancy::class)->set($company);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => InvoiceStatus::Issued,
            'number' => 'ACM-INV-ISSUED-REORDER',
            'currency_code' => 'USD',
        ]);

        $first = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'First typed', 'quantity' => 1, 'unit_cost' => 100, 'sort_order' => 0]);
        $second = InvoiceItem::create(['invoice_id' => $invoice->id, 'title' => 'Second typed', 'quantity' => 1, 'unit_cost' => 200, 'sort_order' => 1]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $invoice,
            'pageClass' => ViewInvoice::class,
        ])->call('reorderTable', [$second->id, $first->id]);

        $this->assertSame(
            [$first->id, $second->id],
            $invoice->fresh()->items->pluck('id')->all(),
            'reorder must be a no-op once the invoice is Issued',
        );
    }
}
