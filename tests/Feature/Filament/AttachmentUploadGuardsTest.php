<?php

namespace Tests\Feature\Filament;

use App\Filament\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Client;
use App\Models\Company;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Drift-audit fix (docs/rebuild/specs/06-documents-portal-reporting/Specs.md
 * / FINALIZED-DECISIONS.md — "financial attachments are private, company-
 * scoped PDF/JPG/JPEG/PNG files capped at 10 MB, with uploader/timestamp
 * evidence"): DocumentsRelationManager previously accepted any file of any
 * size and never recorded who uploaded it.
 */
class AttachmentUploadGuardsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);

        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
        $this->invoice = Invoice::create(['company_id' => $this->company->id, 'client_id' => $client->id, 'type' => 'invoice', 'status' => 'draft']);

        $this->actingAs($this->user);
        Filament::setTenant($this->company);
        app(Tenancy::class)->set($this->company);
    }

    public function test_uploading_a_document_records_the_acting_user_as_uploader(): void
    {
        Storage::fake('local');

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $this->invoice, 'pageClass' => ViewInvoice::class])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])
            ->assertHasNoTableActionErrors();

        $document = Document::where('company_id', $this->company->id)->first();

        $this->assertNotNull($document);
        $this->assertSame($this->user->id, $document->uploaded_by_user_id);
    }

    public function test_a_disallowed_file_type_is_rejected(): void
    {
        Storage::fake('local');

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $this->invoice, 'pageClass' => ViewInvoice::class])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
            ])
            ->assertHasTableActionErrors(['path']);

        $this->assertSame(0, Document::where('company_id', $this->company->id)->count());
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        Storage::fake('local');

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $this->invoice, 'pageClass' => ViewInvoice::class])
            ->callTableAction('create', data: [
                'path' => UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf'),
            ])
            ->assertHasTableActionErrors(['path']);

        $this->assertSame(0, Document::where('company_id', $this->company->id)->count());
    }
}
