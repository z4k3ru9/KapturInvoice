<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Invoices\RelationManagers\ItemsRelationManager;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression test for a real Phase 02 finding
 * (docs/rebuild/specs/02-parties-and-catalog/Specs.md — "search does not
 * load unbounded records"): the Items relation manager's product picker
 * used to build its options via an eager
 * `Product::query()->pluck('name', 'id')` — every product row for the
 * company, fetched on every render of the line-item form, regardless of
 * whether the user had typed a search term. Fixed to use Filament's
 * relationship-mode Select (`->relationship('product', 'name')`), which
 * only resolves the currently selected option's label up front and defers
 * the full list to a bounded, server-searched AJAX lookup. This test
 * proves the eager unbounded query is actually gone, not just that the
 * form renders.
 */
class ItemsRelationManagerProductPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_mounting_the_create_item_form_does_not_eagerly_query_every_product(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $company->users()->attach($user, ['role' => 'owner']);

        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);
        $invoice = Invoice::create(['company_id' => $company->id, 'client_id' => $client->id, 'type' => 'invoice', 'status' => 'draft']);

        // Comfortably past Filament's default Select options limit (50) —
        // if the old eager pluck() ever came back, this is enough rows to
        // make a "select every product" query unmistakable in the log.
        for ($i = 1; $i <= 60; $i++) {
            Product::create(['company_id' => $company->id, 'name' => "Product {$i}"]);
        }

        $this->actingAs($user);
        Filament::setTenant($company);
        app(Tenancy::class)->set($company);

        DB::enableQueryLog();

        Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $invoice, 'pageClass' => ViewInvoice::class])
            ->mountTableAction('create');

        $queryLog = DB::getQueryLog();
        DB::disableQueryLog();

        $unboundedProductScan = collect($queryLog)->first(function (array $entry) {
            $sql = strtolower($entry['query']);

            return str_contains($sql, 'select') && str_contains($sql, '"products"') && ! str_contains($sql, 'limit');
        });

        $this->assertNull(
            $unboundedProductScan,
            'Mounting the create-item form ran a products query with no LIMIT: '
                .($unboundedProductScan['query'] ?? '')
        );
    }
}
