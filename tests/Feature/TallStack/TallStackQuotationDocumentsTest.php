<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackQuotationForm;
use App\Models\Client;
use App\Models\Company;
use App\Models\Document;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Same guard coverage as TallStackInvoiceDocumentsTest, against
 * App\Livewire\TallStackQuotationForm — the identical
 * App\Livewire\Concerns\ManagesDocuments trait wired onto a Quotation
 * instead of an Invoice, proving the guards apply per-documentable-type,
 * not just to Invoice.
 */
class TallStackQuotationDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Quotation $quotation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);

        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
        $this->quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ])->fresh();

        $this->actingAs($this->user);
        app(Tenancy::class)->set($this->company);
    }

    public function test_uploading_a_document_records_the_acting_user_as_uploader(): void
    {
        Storage::fake('local');

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $this->quotation])
            ->set('newDocument', UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
            ->call('uploadDocument')
            ->assertHasNoErrors();

        $document = Document::where('company_id', $this->company->id)->first();

        $this->assertNotNull($document);
        $this->assertSame($this->user->id, $document->uploaded_by_user_id);
        $this->assertSame($this->quotation->id, $document->documentable_id);
        $this->assertSame(Quotation::class, $document->documentable_type);
        $this->assertSame('doc.pdf', $document->filename);
    }

    public function test_a_disallowed_file_type_is_rejected(): void
    {
        Storage::fake('local');

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $this->quotation])
            ->set('newDocument', UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'))
            ->call('uploadDocument')
            ->assertHasErrors(['newDocument']);

        $this->assertSame(0, Document::where('company_id', $this->company->id)->count());
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        Storage::fake('local');

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $this->quotation])
            ->set('newDocument', UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf'))
            ->call('uploadDocument')
            ->assertHasErrors(['newDocument']);

        $this->assertSame(0, Document::where('company_id', $this->company->id)->count());
    }
}
