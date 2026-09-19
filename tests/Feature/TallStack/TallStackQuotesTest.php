<?php

namespace Tests\Feature\TallStack;

use App\Actions\Billing\AmendIssuedInvoice;
use App\Actions\Billing\VoidAndReissueInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Livewire\TallStackInvoiceForm;
use App\Livewire\TallStackQuotes;
use App\Mail\CompanyTemplatedMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Covers the gap flagged in docs/engineering.md and this
 * task's own prompt: the legacy Quotes register (`Invoice` rows with
 * `type = InvoiceType::Quote`) had no TallStackUI surface at all after
 * the old legacy admin `QuoteResource` was removed. This is new coverage —
 * tests/Feature/Services/InvoiceDuplicatorTest.php already covers
 * App\Services\InvoiceDuplicator::convertQuoteToInvoice() at the service
 * level (item cloning/subtotal), so this file does not repeat that; it
 * only covers the register/list page rendering, the Send/Convert row
 * actions, and App\Livewire\TallStackInvoiceForm being reused
 * (App\Enums\InvoiceType::Quote must render/save correctly, never expose
 * Issue/Amend/Void) — plus the server-side guard proving a quote can
 * never reach IssueInvoice/AmendIssuedInvoice/VoidAndReissueInvoice even
 * if called directly.
 */
class TallStackQuotesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
        Contact::create([
            'client_id' => $this->client->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'is_primary' => true,
        ]);

        $this->actingAs($this->user);
    }

    private function quote(array $overrides = []): Invoice
    {
        $quote = Invoice::create(array_merge([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => InvoiceType::Quote,
            'status' => InvoiceStatus::Draft,
            'number' => 'ACME-QUO-0001',
        ], $overrides));

        $quote->items()->create(['title' => 'Consulting', 'quantity' => 1, 'unit_cost' => 500]);

        return $quote->fresh(['items']);
    }

    public function test_the_register_lists_only_quote_type_rows(): void
    {
        $quote = $this->quote();
        Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => InvoiceType::Invoice,
            'status' => InvoiceStatus::Draft,
            'number' => 'ACME-INV-0001',
        ]);

        Livewire::test(TallStackQuotes::class, ['company' => $this->company])
            ->assertSee($quote->number)
            ->assertDontSee('ACME-INV-0001');
    }

    public function test_send_emails_the_quote_via_billing_mailer(): void
    {
        Mail::fake();

        $quote = $this->quote();

        Livewire::test(TallStackQuotes::class, ['company' => $this->company])
            ->call('send', $quote->id);

        Mail::assertSent(CompanyTemplatedMail::class, fn (CompanyTemplatedMail $mail) => $mail->hasTo('jane@example.com'));
    }

    public function test_convert_to_invoice_creates_a_linked_draft_invoice(): void
    {
        $quote = $this->quote();

        Livewire::test(TallStackQuotes::class, ['company' => $this->company])
            ->call('convertToInvoice', $quote->id);

        $invoice = Invoice::query()->where('type', InvoiceType::Invoice)->where('converted_from_quote_id', $quote->id)->first();

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
    }

    public function test_force_delete_removes_a_draft_never_converted_quote(): void
    {
        $quote = $this->quote();

        Livewire::test(TallStackQuotes::class, ['company' => $this->company])
            ->call('forceDelete', $quote->id);

        $this->assertNull(Invoice::find($quote->id));
    }

    public function test_the_edit_form_reuses_tallstackinvoiceform_for_a_quote(): void
    {
        $quote = $this->quote();

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $quote])
            ->assertSet('invoice.id', $quote->id)
            ->assertDontSee('Issue this invoice')
            ->assertDontSee('Void & reissue');
    }

    public function test_the_reused_form_sends_a_quote_not_an_invoice_email(): void
    {
        Mail::fake();

        $quote = $this->quote();

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $quote])
            ->call('send');

        Mail::assertSent(CompanyTemplatedMail::class, fn (CompanyTemplatedMail $mail) => $mail->hasTo('jane@example.com')
            && str_contains(strtolower($mail->subjectLine), 'quote'));
    }

    public function test_the_reused_form_never_issues_a_quote_even_if_the_action_is_called_directly(): void
    {
        $quote = $this->quote();

        Livewire::test(TallStackInvoiceForm::class, ['company' => $this->company, 'invoice' => $quote])
            ->call('issue');

        // Never advanced — a quote isn't issued through this path at all,
        // guarded both by App\Livewire\TallStackInvoiceForm::issue()'s own
        // early return and (the real source of truth)
        // App\Actions\Billing\IssueInvoice's own type check.
        $this->assertSame(InvoiceStatus::Draft, $quote->fresh()->status);
    }

    public function test_amend_issued_invoice_action_rejects_a_quote_type_row_server_side(): void
    {
        $quote = $this->quote(['status' => InvoiceStatus::Paid]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only a plain invoice can be amended this way');

        app(AmendIssuedInvoice::class)->amend($quote, 'test', [
            ['title' => 'x', 'quantity' => 1, 'unit_cost' => 1],
        ], $this->user);
    }

    public function test_void_and_reissue_invoice_action_rejects_a_quote_type_row_server_side(): void
    {
        $quote = $this->quote(['status' => InvoiceStatus::Paid]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only a plain invoice can be voided and reissued this way');

        app(VoidAndReissueInvoice::class)->voidAndReissue($quote, 'test', [
            ['title' => 'x', 'quantity' => 1, 'unit_cost' => 1],
        ], $this->user);
    }
}
