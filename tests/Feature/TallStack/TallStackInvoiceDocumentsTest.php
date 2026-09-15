<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackInvoiceForm;
use App\Models\Client;
use App\Models\Company;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ports the deleted pre-TallStackUI Filament admin's Documents relation
 * manager coverage (removed along with app/Filament — see git history,
 * "Phase B of Filament removal") onto its TALL-stack replacement,
 * App\Livewire\Concerns\ManagesDocuments as wired into
 * App\Livewire\TallStackInvoiceForm. Same three guards: the acting user is
 * recorded as uploader, a disallowed file type is rejected, and an
 * oversized file is rejected — in every case with no Document row created.
 */
class TallStackInvoiceDocumentsTest extends TestCase
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
        $this->invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'discount' => 0,
            'discount_is_percentage' => false,
            'is_recurring' => false,
        ])->fresh();

        $this->actingAs($this->user);
        app(Tenancy::class)->set($this->company);
    }

    public function test_uploading_a_document_records_the_acting_user_as_uploader(): void
    {
        Storage::fake('local');

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $this->invoice])
            ->set('newDocument', UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
            ->call('uploadDocument')
            ->assertHasNoErrors();

        $document = Document::where('company_id', $this->company->id)->first();

        $this->assertNotNull($document);
        $this->assertSame($this->user->id, $document->uploaded_by_user_id);
        $this->assertSame($this->invoice->id, $document->documentable_id);
        $this->assertSame(Invoice::class, $document->documentable_type);
        $this->assertSame('doc.pdf', $document->filename);
    }

    public function test_a_disallowed_file_type_is_rejected(): void
    {
        Storage::fake('local');

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $this->invoice])
            ->set('newDocument', UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'))
            ->call('uploadDocument')
            ->assertHasErrors(['newDocument']);

        $this->assertSame(0, Document::where('company_id', $this->company->id)->count());
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        Storage::fake('local');

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $this->invoice])
            ->set('newDocument', UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf'))
            ->call('uploadDocument')
            ->assertHasErrors(['newDocument']);

        $this->assertSame(0, Document::where('company_id', $this->company->id)->count());
    }

    public function test_deleting_a_document_removes_it(): void
    {
        Storage::fake('local');

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $this->invoice])
            ->set('newDocument', UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
            ->call('uploadDocument');

        $document = Document::where('company_id', $this->company->id)->firstOrFail();

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $this->invoice])
            ->call('deleteDocument', $document->id);

        $this->assertSame(0, Document::where('company_id', $this->company->id)->count());
    }
}
