<?php

namespace Tests\Feature\Console;

use App\Mail\CompanyTemplatedMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Contact;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * App\Console\Commands\SendInvoiceReminders — the reminder-schedule half of
 * docs/filament-admin-layout-design.md §3.3.
 */
class SendInvoiceRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_a_reminder_for_an_invoice_matching_the_configured_schedule(): void
    {
        Mail::fake();

        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        CompanySetting::create([
            'company_id' => $company->id,
            'reminder1_enabled' => true,
            'reminder1_days' => 3,
            'reminder1_direction' => 'after',
            'reminder1_field' => 'due_date',
        ]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'email' => 'jane@example.com', 'is_primary' => true]);

        $due = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'number' => 'INV-0001',
            'due_date' => now()->subDays(3)->toDateString(),
        ]);
        $due->forceFill(['balance' => 100])->save();

        Artisan::call('invoices:send-reminders');

        Mail::assertSent(CompanyTemplatedMail::class, 1);
    }

    public function test_command_skips_invoices_whose_due_date_does_not_match_the_schedule(): void
    {
        Mail::fake();

        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        CompanySetting::create([
            'company_id' => $company->id,
            'reminder1_enabled' => true,
            'reminder1_days' => 3,
            'reminder1_direction' => 'after',
            'reminder1_field' => 'due_date',
        ]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'email' => 'jane@example.com', 'is_primary' => true]);

        $notYetDue = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'number' => 'INV-0002',
            'due_date' => now()->subDay()->toDateString(),
        ]);
        $notYetDue->forceFill(['balance' => 100])->save();

        Artisan::call('invoices:send-reminders');

        Mail::assertNothingSent();
    }

    public function test_command_skips_invoices_with_a_zero_balance(): void
    {
        Mail::fake();

        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        CompanySetting::create([
            'company_id' => $company->id,
            'reminder1_enabled' => true,
            'reminder1_days' => 3,
            'reminder1_direction' => 'after',
            'reminder1_field' => 'due_date',
        ]);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client Co']);
        Contact::create(['client_id' => $client->id, 'first_name' => 'Jane', 'email' => 'jane@example.com', 'is_primary' => true]);

        Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'paid',
            'number' => 'INV-0003',
            'due_date' => now()->subDays(3)->toDateString(),
        ]);

        Artisan::call('invoices:send-reminders');

        Mail::assertNothingSent();
    }
}
