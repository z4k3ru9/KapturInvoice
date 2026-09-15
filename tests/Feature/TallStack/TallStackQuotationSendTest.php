<?php

namespace Tests\Feature\TallStack;

use App\Actions\Sales\TransitionQuotationStatus;
use App\Enums\QuotationStatus;
use App\Livewire\TallStackQuotationForm;
use App\Livewire\TallStackQuotations;
use App\Mail\CompanyTemplatedMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Proves both TallStackUI "Send" entry points
 * (App\Livewire\TallStackQuotations::send() on the list page,
 * App\Livewire\TallStackQuotationForm::send() on the edit page) apply the
 * same App\Actions\Sales\TransitionQuotationStatus transition the
 * pre-TallStackUI Filament admin's table "Send" action used, and now also
 * dispatch App\Services\Sales\QuotationMailer alongside it.
 */
class TallStackQuotationSendTest extends TestCase
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

    public function test_the_list_pages_send_method_transitions_and_emails_the_quotation(): void
    {
        Mail::fake();

        $quotation = $this->approvedQuotation();

        Livewire::test(TallStackQuotations::class, ['company' => $this->company])
            ->call('send', $quotation->id);

        $this->assertSame(QuotationStatus::Sent, $quotation->fresh()->status);

        Mail::assertSent(CompanyTemplatedMail::class, fn (CompanyTemplatedMail $mail) => $mail->hasTo('jane@example.com')
            && count($mail->pdfAttachments) === 1);
    }

    public function test_the_edit_pages_send_method_transitions_and_emails_the_quotation(): void
    {
        Mail::fake();

        $quotation = $this->approvedQuotation();

        Livewire::test(TallStackQuotationForm::class, ['company' => $this->company, 'quotation' => $quotation])
            ->call('send');

        $this->assertSame(QuotationStatus::Sent, $quotation->fresh()->status);

        Mail::assertSent(CompanyTemplatedMail::class, fn (CompanyTemplatedMail $mail) => $mail->hasTo('jane@example.com')
            && count($mail->pdfAttachments) === 1);
    }
}
