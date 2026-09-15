<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackDocuments;
use App\Models\Client;
use App\Models\Company;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the TALL-stack Documents register (App\Livewire\TallStackDocuments)
 * — same shape as tests/Feature/Filament/DocumentResourceTest.php (if any),
 * proving the page is company-scoped, search/type-filter work, and delete
 * reuses App\Models\Document's own delete() the same way
 * App\Filament\Resources\Documents\DocumentResource's DeleteAction does.
 */
class TallStackDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);

        $this->actingAs($this->user);
    }

    private function makeDocument(Company $company, array $overrides = []): Document
    {
        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Test Client',
        ]);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'number' => 'INV-'.random_int(1000, 999999),
        ]);

        // `documentable_type`/`documentable_id` aren't in Document's own
        // #[Fillable] list (only set via the parent's own
        // DocumentsRelationManager relation in real app code), so mass
        // assignment silently drops them — associate the morph explicitly.
        $document = Document::make(array_merge([
            'company_id' => $company->id,
            'disk' => 'local',
            'path' => 'documents/test.pdf',
            'filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
            'uploaded_by_user_id' => $this->user->id,
        ], $overrides));
        $document->documentable()->associate($invoice);
        $document->save();

        return $document;
    }

    public function test_the_page_loads_for_a_user_with_access(): void
    {
        Livewire::test(TallStackDocuments::class, ['company' => $this->company])
            ->assertStatus(200)
            ->assertSee('Documents');
    }

    public function test_mount_aborts_for_a_user_without_access_to_the_company(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);

        Livewire::test(TallStackDocuments::class, ['company' => $otherCompany])
            ->assertStatus(403);
    }

    public function test_the_register_only_lists_the_active_companys_rows(): void
    {
        $this->makeDocument($this->company, ['filename' => 'mine.pdf']);

        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);
        $this->makeDocument($otherCompany, ['filename' => 'not-yours.pdf']);

        Livewire::test(TallStackDocuments::class, ['company' => $this->company])
            ->assertSee('mine.pdf')
            ->assertDontSee('not-yours.pdf');
    }

    public function test_search_filters_by_filename(): void
    {
        $this->makeDocument($this->company, ['filename' => 'invoice-scan.pdf']);
        $this->makeDocument($this->company, ['filename' => 'logo.png', 'mime_type' => 'image/png']);

        Livewire::test(TallStackDocuments::class, ['company' => $this->company])
            ->set('search', 'invoice')
            ->assertSee('invoice-scan.pdf')
            ->assertDontSee('logo.png');
    }

    public function test_type_filter_narrows_to_pdfs_or_images(): void
    {
        $this->makeDocument($this->company, ['filename' => 'invoice-scan.pdf', 'mime_type' => 'application/pdf']);
        $this->makeDocument($this->company, ['filename' => 'logo.png', 'mime_type' => 'image/png']);
        $this->makeDocument($this->company, ['filename' => 'archive.zip', 'mime_type' => 'application/zip']);

        Livewire::test(TallStackDocuments::class, ['company' => $this->company])
            ->call('filterType', 'pdf')
            ->assertSee('invoice-scan.pdf')
            ->assertDontSee('logo.png')
            ->assertDontSee('archive.zip')
            ->call('filterType', 'image')
            ->assertSee('logo.png')
            ->assertDontSee('invoice-scan.pdf')
            ->call('filterType', 'other')
            ->assertSee('archive.zip')
            ->assertDontSee('invoice-scan.pdf')
            ->assertDontSee('logo.png');
    }

    public function test_delete_removes_the_document_row(): void
    {
        $document = $this->makeDocument($this->company, ['filename' => 'to-delete.pdf']);

        Livewire::test(TallStackDocuments::class, ['company' => $this->company])
            ->call('delete', $document->id)
            ->assertDontSee('to-delete.pdf');

        $this->assertNull(Document::find($document->id));
    }

    public function test_a_document_from_another_company_is_never_deletable(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other', 'currency_code' => 'USD']);
        $foreignDocument = $this->makeDocument($otherCompany, ['filename' => 'not-yours.pdf']);

        Livewire::test(TallStackDocuments::class, ['company' => $this->company])
            ->call('delete', $foreignDocument->id);

        // Bypass Document's own BelongsToCompany global scope here — it's
        // active for the rest of this test process too (Tenancy is still
        // set from the component's own mount()), which would otherwise
        // make this assertion pass for the wrong reason.
        $this->assertNotNull(Document::withoutGlobalScopes()->find($foreignDocument->id));
    }
}
