<?php

namespace Tests\Feature\TallStack;

use App\Livewire\TallStackExpenses;
use App\Models\Company;
use App\Models\Document;
use App\Models\Expense;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Same guard coverage as TallStackInvoiceDocumentsTest, against
 * App\Livewire\TallStackExpenses — the identical App\Livewire\Concerns\
 * ManagesDocuments trait wired onto an Expense's edit modal instead of a
 * full detail page. Upload/delete are only reachable once
 * `editingExpenseId` is set (via openEditModal()), matching the "a
 * Document needs a real documentable row to attach to" restriction the
 * Invoice/Quotation forms also apply.
 */
class TallStackExpenseDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Expense $expense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);

        $this->expense = Expense::create([
            'company_id' => $this->company->id,
        ])->fresh();

        $this->actingAs($this->user);
        app(Tenancy::class)->set($this->company);
    }

    public function test_uploading_a_document_records_the_acting_user_as_uploader(): void
    {
        Storage::fake('local');

        Livewire::test(TallStackExpenses::class, ['company' => $this->company])
            ->call('openEditModal', $this->expense->id)
            ->set('newDocument', UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
            ->call('uploadDocument')
            ->assertHasNoErrors();

        $document = Document::where('company_id', $this->company->id)->first();

        $this->assertNotNull($document);
        $this->assertSame($this->user->id, $document->uploaded_by_user_id);
        $this->assertSame($this->expense->id, $document->documentable_id);
        $this->assertSame(Expense::class, $document->documentable_type);
        $this->assertSame('doc.pdf', $document->filename);
    }

    public function test_a_disallowed_file_type_is_rejected(): void
    {
        Storage::fake('local');

        Livewire::test(TallStackExpenses::class, ['company' => $this->company])
            ->call('openEditModal', $this->expense->id)
            ->set('newDocument', UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'))
            ->call('uploadDocument')
            ->assertHasErrors(['newDocument']);

        $this->assertSame(0, Document::where('company_id', $this->company->id)->count());
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        Storage::fake('local');

        Livewire::test(TallStackExpenses::class, ['company' => $this->company])
            ->call('openEditModal', $this->expense->id)
            ->set('newDocument', UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf'))
            ->call('uploadDocument')
            ->assertHasErrors(['newDocument']);

        $this->assertSame(0, Document::where('company_id', $this->company->id)->count());
    }
}
