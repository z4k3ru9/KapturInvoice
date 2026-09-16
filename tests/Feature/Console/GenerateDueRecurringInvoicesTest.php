<?php

namespace Tests\Feature\Console;

use App\Enums\InvoiceStatus;
use App\Mail\CompanyTemplatedMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * App\Console\Commands\GenerateDueRecurringInvoices — the `auto_bill` flag
 * was previously decorative; this closes the gap by actually generating
 * (and, for that flag, issuing and sending) a template's next invoice once
 * it's due, reusing the exact same App\Services\InvoiceDuplicator/
 * App\Actions\Billing\IssueInvoice/App\Services\BillingMailer code paths a
 * human would use manually.
 */
class GenerateDueRecurringInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);
        Contact::create([
            'client_id' => $this->client->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'is_primary' => true,
        ]);
    }

    private function attachOwner(): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => 'owner']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeTemplate(array $overrides = []): Invoice
    {
        $template = Invoice::create(array_merge([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'draft',
            'number' => 'ACM-INV-TPL-'.uniqid(),
            'is_recurring' => true,
            'auto_bill' => true,
            'pricing_mode' => 'exclusive',
            'recurring_frequency' => 'monthly',
            'recurring_start_date' => now()->subMonth()->toDateString(),
        ], $overrides));

        InvoiceItem::create([
            'invoice_id' => $template->id,
            'title' => 'Monthly service',
            'quantity' => 1,
            'unit_cost' => 500,
            'line_total' => 500,
        ]);

        return $template->fresh();
    }

    public function test_a_due_auto_bill_template_generates_issues_and_sends_one_invoice(): void
    {
        Mail::fake();
        $this->attachOwner();
        $template = $this->makeTemplate();

        Artisan::call('recurring-invoices:generate-due');

        $generated = Invoice::query()->where('recurring_template_id', $template->id)->first();

        $this->assertNotNull($generated);
        $this->assertSame(InvoiceStatus::Issued, $generated->status);
        $this->assertNotNull($generated->number);
        $this->assertSame('500.00', $generated->total);
        $this->assertNotNull($template->fresh()->recurring_last_sent_at);

        Mail::assertSent(CompanyTemplatedMail::class);
    }

    public function test_a_non_auto_bill_template_generates_nothing(): void
    {
        Mail::fake();
        $this->attachOwner();
        $template = $this->makeTemplate(['auto_bill' => false]);

        Artisan::call('recurring-invoices:generate-due');

        $this->assertSame(0, Invoice::query()->where('recurring_template_id', $template->id)->count());
        Mail::assertNothingSent();
    }

    public function test_a_not_yet_due_template_generates_nothing(): void
    {
        Mail::fake();
        $this->attachOwner();
        $template = $this->makeTemplate(['recurring_start_date' => now()->toDateString()]);

        Artisan::call('recurring-invoices:generate-due');

        $this->assertSame(0, Invoice::query()->where('recurring_template_id', $template->id)->count());
    }

    public function test_a_template_past_its_end_date_generates_nothing(): void
    {
        Mail::fake();
        $this->attachOwner();
        $template = $this->makeTemplate([
            // The next monthly occurrence from this start date lands ~1
            // month ago — well after this end date, so it's genuinely
            // past the template's own cutoff, not just coincidentally
            // equal to it.
            'recurring_start_date' => now()->subMonths(2)->toDateString(),
            'recurring_end_date' => now()->subMonths(2)->addDays(3)->toDateString(),
        ]);

        Artisan::call('recurring-invoices:generate-due');

        $this->assertSame(0, Invoice::query()->where('recurring_template_id', $template->id)->count());
    }

    public function test_a_held_template_is_skipped_even_when_due(): void
    {
        Mail::fake();
        $this->attachOwner();
        $template = $this->makeTemplate();
        $template->forceFill(['held_at' => now(), 'held_reason' => 'Needs revision before next billing cycle'])->save();

        Artisan::call('recurring-invoices:generate-due');

        $this->assertSame(0, Invoice::query()->where('recurring_template_id', $template->id)->count());
    }

    public function test_dry_run_generates_nothing(): void
    {
        Mail::fake();
        $this->attachOwner();
        $template = $this->makeTemplate();

        Artisan::call('recurring-invoices:generate-due', ['--dry-run' => true]);

        $this->assertSame(0, Invoice::query()->where('recurring_template_id', $template->id)->count());
        Mail::assertNothingSent();
    }

    public function test_a_due_template_with_no_owner_user_generates_but_leaves_the_invoice_in_draft(): void
    {
        Mail::fake();
        // No attachOwner() call — company has no users at all.
        $template = $this->makeTemplate();

        Artisan::call('recurring-invoices:generate-due');

        $generated = Invoice::query()->where('recurring_template_id', $template->id)->first();

        $this->assertNotNull($generated);
        $this->assertSame(InvoiceStatus::Draft, $generated->status);
        Mail::assertNothingSent();
    }
}
