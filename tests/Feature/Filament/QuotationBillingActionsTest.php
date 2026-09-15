<?php

namespace Tests\Feature\Filament;

use App\Actions\Sales\TransitionQuotationStatus;
use App\Enums\QuotationStatus;
use App\Filament\Resources\Quotations\Pages\ListQuotations;
use App\Mail\CompanyTemplatedMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Proves the Quotations table's "Send" row action
 * (App\Filament\Resources\Quotations\Tables\QuotationsTable) both
 * transitions the quotation (App\Actions\Sales\TransitionQuotationStatus
 * — already covered on its own in tests/Feature/Sales/
 * QuotationWorkflowTest.php) and actually dispatches
 * App\Services\Sales\QuotationMailer, with the quotation PDF attached —
 * not just one or the other.
 */
class QuotationBillingActionsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);
        Contact::create([
            'client_id' => $this->client->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'is_primary' => true,
        ]);

        $this->actingAs($user);
        Filament::setTenant($this->company);
        app(Tenancy::class)->set($this->company);
    }

    private function approvedQuotation(): Quotation
    {
        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'KA-QUO-2026090001',
            'status' => QuotationStatus::Draft,
        ]);
        $quotation->forceFill(['subtotal' => 1000, 'total' => 1000])->save();

        app(TransitionQuotationStatus::class)->transition($quotation, QuotationStatus::Approved);

        return $quotation->fresh();
    }

    public function test_the_send_table_action_transitions_and_emails_the_quotation_pdf(): void
    {
        Mail::fake();

        $quotation = $this->approvedQuotation();

        Livewire::test(ListQuotations::class)
            ->callTableAction('send', $quotation);

        $this->assertSame(QuotationStatus::Sent, $quotation->fresh()->status);
        $this->assertNotNull($quotation->fresh()->sent_at);

        Mail::assertSent(CompanyTemplatedMail::class, function (CompanyTemplatedMail $mail) use ($quotation) {
            return $mail->hasTo('jane@example.com')
                && count($mail->pdfAttachments) === 1
                && $mail->pdfAttachments[0]->as === "{$quotation->number}.pdf";
        });
    }

    public function test_sending_still_transitions_the_quotation_even_when_the_email_cannot_be_sent(): void
    {
        Mail::fake();

        $quotation = $this->approvedQuotation();
        $quotation->client->contacts()->delete();

        Livewire::test(ListQuotations::class)
            ->callTableAction('send', $quotation);

        $this->assertSame(QuotationStatus::Sent, $quotation->fresh()->status);
        Mail::assertNothingSent();
    }
}
