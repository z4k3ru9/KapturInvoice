<?php

namespace Tests\Feature\Documents;

use App\Filament\Resources\Credits\Pages\ListCredits;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Quotes\Pages\CreateQuote;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\NumberingSequence;
use App\Models\User;
use App\Services\DocumentNumberGenerator;
use App\Services\InvoiceDuplicator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * App\Services\DocumentNumberGenerator assigns
 * `COMPANY-DOCUMENTTYPE-YEARMONTHSEQ` numbers (e.g. `ACME-INV-2026090001`)
 * per docs/rebuild/specs/FINALIZED-DECISIONS.md §2, backed by
 * `numbering_sequences` rather than the legacy `companies.invoice_next_number`
 * counters (those stay untouched, backing only already-issued historical
 * numbers). See docs/rebuild/specs/01-company-foundation/Specs.md "Required
 * tests".
 */
class DocumentNumberingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-15 12:00:00');

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_generator_assigns_and_increments_each_sequence_independently(): void
    {
        $generator = app(DocumentNumberGenerator::class);

        $this->assertSame('ACM-INV-2026090001', $generator->next($this->company, 'invoice'));
        $this->assertSame('ACM-INV-2026090002', $generator->next($this->company, 'invoice'));
        $this->assertSame('ACM-QUO-2026090001', $generator->next($this->company, 'quote'));
        $this->assertSame('ACM-CR-2026090001', $generator->next($this->company, 'credit'));

        $this->assertSame(3, NumberingSequence::where('company_id', $this->company->id)->where('document_type', 'INV')->value('next_sequence'));
        $this->assertSame(2, NumberingSequence::where('company_id', $this->company->id)->where('document_type', 'QUO')->value('next_sequence'));
        $this->assertSame(2, NumberingSequence::where('company_id', $this->company->id)->where('document_type', 'CR')->value('next_sequence'));
    }

    public function test_sequence_does_not_reset_monthly_but_resets_each_year(): void
    {
        $generator = app(DocumentNumberGenerator::class);

        Carbon::setTestNow('2026-01-15');
        $this->assertSame('ACM-INV-2026010001', $generator->next($this->company, 'invoice'));

        Carbon::setTestNow('2026-12-20');
        $this->assertSame('ACM-INV-2026120002', $generator->next($this->company, 'invoice'), 'same year, later month: sequence keeps incrementing, does not reset');

        Carbon::setTestNow('2027-01-05');
        $this->assertSame('ACM-INV-2027010001', $generator->next($this->company, 'invoice'), 'new year: sequence resets to 1');
    }

    public function test_sequence_is_independent_per_company_and_document_type(): void
    {
        $other = Company::create(['name' => 'Other', 'slug' => 'other', 'code' => 'OTH', 'currency_code' => 'USD']);
        $generator = app(DocumentNumberGenerator::class);

        $this->assertSame('ACM-INV-2026090001', $generator->next($this->company, 'invoice'));
        $this->assertSame('OTH-INV-2026090001', $generator->next($other, 'invoice'), 'a second company starts its own sequence at 1, unaffected by the first');
        $this->assertSame('ACM-INV-2026090002', $generator->next($this->company, 'invoice'));
    }

    public function test_repeated_allocation_never_produces_duplicate_numbers(): void
    {
        $generator = app(DocumentNumberGenerator::class);

        $numbers = [];
        for ($i = 0; $i < 25; $i++) {
            $numbers[] = $generator->next($this->company, 'invoice');
        }

        $this->assertCount(25, array_unique($numbers), 'every allocated number must be unique');
    }

    public function test_first_issuance_locks_the_company_code(): void
    {
        $this->assertNull($this->company->codes_locked_at);

        app(DocumentNumberGenerator::class)->next($this->company, 'invoice');

        $this->assertNotNull($this->company->fresh()->codes_locked_at);
    }

    public function test_generator_requires_a_configured_company_code(): void
    {
        // Bypass Company's creating() hook (which derives a code from the
        // slug when none is given) to exercise the "code required" guard.
        DB::table('companies')->where('id', $this->company->id)->update(['code' => null]);

        $this->expectException(InvalidArgumentException::class);

        app(DocumentNumberGenerator::class)->next($this->company->fresh(), 'invoice');
    }

    public function test_creating_an_invoice_with_a_blank_number_auto_assigns_one(): void
    {
        Livewire::test(CreateInvoice::class, ['tenant' => $this->company])
            ->fillForm(['client_id' => $this->client->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('invoices', ['company_id' => $this->company->id, 'number' => 'ACM-INV-2026090001']);
    }

    public function test_creating_an_invoice_with_an_explicit_number_does_not_consume_the_sequence(): void
    {
        Livewire::test(CreateInvoice::class, ['tenant' => $this->company])
            ->fillForm(['client_id' => $this->client->id, 'number' => 'MANUAL-1'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('invoices', ['company_id' => $this->company->id, 'number' => 'MANUAL-1']);
        $this->assertNull(NumberingSequence::where('company_id', $this->company->id)->where('document_type', 'INV')->first());
    }

    public function test_creating_a_quote_assigns_from_the_quote_sequence(): void
    {
        Livewire::test(CreateQuote::class, ['tenant' => $this->company])
            ->fillForm(['client_id' => $this->client->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('invoices', ['company_id' => $this->company->id, 'type' => 'quote', 'number' => 'ACM-QUO-2026090001']);
        $this->assertNull(
            NumberingSequence::where('company_id', $this->company->id)->where('document_type', 'INV')->first(),
            'quote creation must not touch the invoice sequence'
        );
    }

    public function test_creating_a_credit_assigns_from_the_credit_sequence(): void
    {
        Livewire::test(ListCredits::class)
            ->callAction('create', data: ['client_id' => $this->client->id, 'amount' => 50])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('credits', ['company_id' => $this->company->id, 'number' => 'ACM-CR-2026090001']);
    }

    public function test_converting_a_quote_assigns_a_real_invoice_number(): void
    {
        $quote = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'quote', 'status' => 'draft']);

        $invoice = app(InvoiceDuplicator::class)->convertQuoteToInvoice($quote->fresh(['items']));

        $this->assertSame('ACM-INV-2026090001', $invoice->number);
    }

    public function test_generating_a_recurring_instance_assigns_a_real_invoice_number(): void
    {
        $template = Invoice::create(['company_id' => $this->company->id, 'client_id' => $this->client->id, 'type' => 'invoice', 'status' => 'draft', 'is_recurring' => true]);

        $invoice = app(InvoiceDuplicator::class)->generateRecurringInstance($template->fresh(['items']));

        $this->assertSame('ACM-INV-2026090001', $invoice->number);
    }
}
