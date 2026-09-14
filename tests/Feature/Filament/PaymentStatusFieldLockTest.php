<?php

namespace Tests\Feature\Filament;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Codex review finding on PR #4: the Payment status Select (used by the
 * modal-based create/edit action — see CLAUDE.md "Modal-based Create/
 * Edit") exposed `Verified`/`Reversed`, so editing a payment with
 * `status => verified` wrote that status directly — bypassing
 * App\Actions\Receivables\VerifyCustomerPayment entirely, so the role
 * check, proof/cheque-cleared requirement, verified_at/
 * verified_by_user_id, verification event, audit record, and receivables
 * recalculation are all skipped. Fixed via App\Filament\Resources\
 * Payments\Schemas\PaymentForm's `->disableOptionWhen()`.
 */
class PaymentStatusFieldLockTest extends TestCase
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
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);

        $this->actingAs($user);
        Filament::setTenant($this->company);
    }

    /** @return array<string, array{0: string}> */
    public static function actionOwnedStatuses(): array
    {
        return [
            'verified' => ['verified'],
            'reversed' => ['reversed'],
        ];
    }

    #[DataProvider('actionOwnedStatuses')]
    public function test_the_edit_modal_cannot_directly_set_an_action_owned_status(string $status): void
    {
        $payment = Payment::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status' => PaymentStatus::Pending,
            'amount' => 100,
        ]);

        Livewire::test(ListPayments::class)
            ->callTableAction('edit', $payment, data: ['status' => $status]);

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }
}
