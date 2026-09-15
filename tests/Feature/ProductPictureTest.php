<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Proposal;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Services\ProposalSnippetSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Optional product picture: shown as a thumbnail on Quotation item tables
 * and PDFs, and reusable in Proposal snippets — see the request this
 * closes: "product SKUs have picture uploaded as optional which later can
 * be shown on each column on quotation and proposals."
 */
class ProductPictureTest extends TestCase
{
    use RefreshDatabase;

    private function fakeProductImage(Company $company): Product
    {
        Storage::fake(config('filesystems.default'));
        Storage::disk(config('filesystems.default'))->put('products/widget.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        return Product::create([
            'company_id' => $company->id,
            'name' => 'Widget',
            'sku' => 'WID-1',
            'image_path' => 'products/widget.png',
            'unit_cost' => 250,
        ]);
    }

    public function test_product_without_a_picture_has_no_image_data_uri(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $product = Product::create(['company_id' => $company->id, 'name' => 'No Picture', 'unit_cost' => 10]);

        $this->assertNull($product->getImageDataUri());
    }

    public function test_product_picture_resolves_to_a_base64_data_uri(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $product = $this->fakeProductImage($company);

        $this->assertStringStartsWith('data:image/png;base64,', $product->getImageDataUri());
    }

    public function test_quotation_pdf_embeds_the_line_items_product_picture(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $product = $this->fakeProductImage($company);

        $quotation = Quotation::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'KA-QUO-0001',
            'status' => 'draft',
            'subtotal' => 250,
            'total' => 250,
        ]);
        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'title' => 'Widget',
            'quantity' => 1,
            'unit_cost' => 250,
            'line_total' => 250,
        ]);
        $quotation->loadMissing('client', 'company', 'items.product');

        $html = view('pdf.quotation', ['quotation' => $quotation])->render();

        $this->assertStringContainsString('data:image/png;base64,', $html);
    }

    public function test_quotation_pdf_downloads_for_a_user_who_belongs_to_the_owning_company(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $company->users()->attach($user, ['role' => 'owner']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $quotation = Quotation::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'KA-QUO-0001',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->get(route('quotations.pdf', $quotation))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_quotation_pdf_is_forbidden_for_a_user_outside_the_owning_company(): void
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $company->users()->attach($owner, ['role' => 'owner']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        $quotation = Quotation::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'KA-QUO-0001',
            'status' => 'draft',
        ]);

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('quotations.pdf', $quotation))
            ->assertForbidden();
    }

    public function test_proposal_pdf_downloads_for_a_user_who_belongs_to_the_owning_company(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $company->users()->attach($user, ['role' => 'owner']);
        $proposal = Proposal::create(['company_id' => $company->id, 'title' => 'Website redesign', 'amount' => 500, 'html' => '<p>Scope</p>']);

        $this->actingAs($user)
            ->get(route('proposals.pdf', $proposal))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_proposal_pdf_is_forbidden_for_a_user_outside_the_owning_company(): void
    {
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $company->users()->attach($owner, ['role' => 'owner']);
        $proposal = Proposal::create(['company_id' => $company->id, 'title' => 'Website redesign', 'amount' => 500]);

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('proposals.pdf', $proposal))
            ->assertForbidden();
    }

    public function test_creating_a_proposal_snippet_from_a_product_embeds_its_picture(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $product = $this->fakeProductImage($company);

        $snippet = app(ProposalSnippetSync::class)->createOrUpdateFromProduct($product);

        $this->assertSame($company->id, $snippet->company_id);
        $this->assertSame($product->id, $snippet->product_id);
        $this->assertStringContainsString('data:image/png;base64,', $snippet->html);
        $this->assertStringContainsString('WID-1', $snippet->html);
        $this->assertStringContainsString('250.00', $snippet->html);
    }

    public function test_re_running_the_proposal_snippet_sync_refreshes_the_same_snippet(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $product = $this->fakeProductImage($company);

        $first = app(ProposalSnippetSync::class)->createOrUpdateFromProduct($product);

        $product->forceFill(['unit_cost' => 999])->save();
        $second = app(ProposalSnippetSync::class)->createOrUpdateFromProduct($product);

        $this->assertSame($first->id, $second->id);
        $this->assertStringContainsString('999.00', $second->html);
    }

    // A Filament-specific table-column/filter-config assertion
    // ("Stitch gap analysis 06-products-settings-reports.md §1 item 10")
    // used to live here — dropped, not ported, during the Filament-removal
    // Phase B: it asserted internals of Filament's ImageColumn/
    // TernaryFilter config (no rendered column label, a `stock_flag`
    // filter registered on the table), which has no equivalent shape on
    // TallStackProducts (a plain Blade table with its own different
    // layout — see resources/views/livewire/tallstack-products.blade.php,
    // whose "Normally stocked" column and toggle are unrelated markup,
    // not a drop-in substitute for the same assertion). The underlying
    // picture/stock_flag *functionality* stays covered by this file's
    // other tests.
}
