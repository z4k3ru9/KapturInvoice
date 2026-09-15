<?php

namespace Tests\Feature\Filament;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Codex review finding on PR #4: the Invoice status Select exposed every
 * InvoiceStatus option, so saving the edit form with `status => issued`
 * (or void/amended) wrote that status directly — bypassing
 * App\Actions\Billing\IssueInvoice/AmendIssuedInvoice/VoidAndReissueInvoice
 * entirely, so the invoice would end up "Issued" with no number, no tax
 * snapshot, no audit event. Fixed via App\Filament\Resources\Invoices\
 * Schemas\InvoiceForm's `->disableOptionWhen()` on the three action-owned
 * states.
 */
class InvoiceStatusFieldLockTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
        $this->company->users()->attach($user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
        app(Tenancy::class)->set($this->company);
    }

    private function draftInvoice(string $number): Invoice
    {
        return Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => InvoiceStatus::Draft,
            'number' => $number,
            'currency_code' => 'USD',
        ]);
    }

    /** @return array<string, array{0: string}> */
    public static function actionOwnedStatuses(): array
    {
        return [
            'issued' => ['issued'],
            'void' => ['void'],
            'amended' => ['amended'],
        ];
    }

    #[DataProvider('actionOwnedStatuses')]
    public function test_the_edit_form_cannot_directly_set_an_action_owned_status(string $status): void
    {
        $invoice = $this->draftInvoice('ACM-INV-'.strtoupper($status));

        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->fillForm(['status' => $status])
            ->call('save');

        $this->assertSame(InvoiceStatus::Draft, $invoice->fresh()->status);
    }

    public function test_the_edit_form_can_still_set_an_ordinary_status(): void
    {
        $invoice = $this->draftInvoice('ACM-INV-SENT');

        Livewire::test(EditInvoice::class, ['record' => $invoice->id])
            ->fillForm(['status' => 'sent'])
            ->call('save');

        $this->assertSame(InvoiceStatus::Sent, $invoice->fresh()->status);
    }
}
