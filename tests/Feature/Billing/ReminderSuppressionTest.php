<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\SuppressReminder;
use App\Mail\CompanyTemplatedMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * "Suppression requires reason and audit." —
 * docs/rebuild/specs/06-documents-portal-reporting/Specs.md,
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §5. Mirrors
 * tests/Feature/Console/SendInvoiceRemindersTest.php's Mail::fake()
 * assertion style for the "does it actually block the send" half.
 */
class ReminderSuppressionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Client $client;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        CompanySetting::create([
            'company_id' => $this->company->id,
            'reminder1_enabled' => true,
            'reminder1_days' => 3,
            'reminder1_direction' => 'after',
            'reminder1_field' => 'due_date',
        ]);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);
        Contact::create(['client_id' => $this->client->id, 'first_name' => 'Jane', 'email' => 'jane@example.com', 'is_primary' => true]);

        $this->invoice = Invoice::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'number' => 'INV-0001',
            'due_date' => now()->subDays(3)->toDateString(),
        ]);
        $this->invoice->forceFill(['balance' => 100])->save();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role]);

        return $user;
    }

    public function test_suppressing_a_reminder_blocks_that_tiers_send_on_the_next_run(): void
    {
        Mail::fake();

        $owner = $this->userWithRole('owner');

        app(SuppressReminder::class)->suppress($this->invoice, 1, 'Client called, already paying by hand', $owner);

        Artisan::call('invoices:send-reminders');

        Mail::assertNothingSent();
    }

    public function test_a_suppression_recorded_well_before_its_target_date_still_holds(): void
    {
        // Codex review finding on PR #4: the command used to match a
        // suppression only if `suppressed_at >= now()->subDay()`, so one
        // recorded more than 24h before its actual target send date would
        // already be "too old" by the time that date arrived and the
        // reminder would be sent anyway.
        Mail::fake();

        $owner = $this->userWithRole('owner');
        $suppression = app(SuppressReminder::class)->suppress($this->invoice, 1, 'Suppressed well in advance', $owner);
        $suppression->forceFill(['suppressed_at' => now()->subDays(5)])->save();

        Artisan::call('invoices:send-reminders');

        Mail::assertNothingSent();
        $this->assertNotNull($suppression->fresh()->consumed_at);
    }

    public function test_a_consumed_suppression_does_not_block_a_later_occurrence(): void
    {
        Mail::fake();

        $owner = $this->userWithRole('owner');
        $suppression = app(SuppressReminder::class)->suppress($this->invoice, 1, 'Suppressed once', $owner);
        $suppression->forceFill(['consumed_at' => now()->subDay()])->save();

        Artisan::call('invoices:send-reminders');

        Mail::assertSent(CompanyTemplatedMail::class, 1);
    }

    public function test_an_unsuppressed_tier_still_sends_normally(): void
    {
        Mail::fake();

        Artisan::call('invoices:send-reminders');

        Mail::assertSent(CompanyTemplatedMail::class, 1);
    }

    public function test_suppression_without_a_reason_is_rejected(): void
    {
        $owner = $this->userWithRole('owner');

        $this->expectException(RuntimeException::class);

        app(SuppressReminder::class)->suppress($this->invoice, 1, '', $owner);
    }

    public function test_suppression_is_denied_for_an_unauthorized_role(): void
    {
        $sales = $this->userWithRole('sales');

        $this->expectException(RuntimeException::class);

        app(SuppressReminder::class)->suppress($this->invoice, 1, 'Client called', $sales);
    }

    public function test_owner_admin_and_accountant_can_suppress(): void
    {
        foreach (['owner', 'admin', 'accountant'] as $role) {
            $user = $this->userWithRole($role);

            $suppression = app(SuppressReminder::class)->suppress($this->invoice, 1, "Suppressed by {$role}", $user);

            $this->assertSame($this->invoice->id, $suppression->invoice_id);
            $this->assertSame(1, $suppression->tier);
        }
    }

    public function test_an_audit_event_is_recorded(): void
    {
        $owner = $this->userWithRole('owner');
        $this->actingAs($owner);

        app(SuppressReminder::class)->suppress($this->invoice, 1, 'Client called, already paying by hand', $owner);

        $this->assertDatabaseHas('audit_events', [
            'company_id' => $this->company->id,
            'action' => 'invoice.reminder_suppressed',
            'entity_type' => $this->invoice->getMorphClass(),
            'entity_id' => $this->invoice->id,
            'reason' => 'Client called, already paying by hand',
            'user_id' => $owner->id,
        ]);
    }
}
