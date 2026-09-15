<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\ReminderSuppression;
use App\Services\BillingMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Scheduled daily (see routes/console.php) to close the "reminder
 * schedule" half of docs/filament-admin-layout-design.md §3.3 — the
 * `reminder1-4` config edited on `EditEmailSettings` is otherwise just
 * stored, never acted on.
 *
 * ⚠️ Matches on an exact date (today vs. due/invoice date shifted by the
 * configured days), so it's safe to run once per day but would re-send a
 * reminder if run twice on the same day — no `reminder{n}_sent_at`
 * tracking column exists yet to dedupe within a day. Fine for a daily
 * cron; flagged here rather than silently assumed.
 *
 * An unconsumed App\Models\ReminderSuppression covers the *next* matching
 * send for its exact invoice+tier, however far ahead that date is — it is
 * marked `consumed_at` the first time it actually causes a skip, so a
 * suppression recorded well before its target date still holds when that
 * date arrives (a fixed "within the last day" window would silently
 * expire before then). See App\Actions\Billing\SuppressReminder.
 */
class SendInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-reminders';

    protected $description = "Send reminder emails for invoices matching each company's configured reminder schedule.";

    public function handle(BillingMailer $mailer): int
    {
        $today = Carbon::today();
        $sent = 0;

        Company::query()->with('settings')->each(function (Company $company) use ($mailer, $today, &$sent) {
            $settings = $company->settings;

            if (! $settings) {
                return;
            }

            foreach (range(1, 4) as $tier) {
                if (! $settings->{"reminder{$tier}_enabled"}) {
                    continue;
                }

                $days = $settings->{"reminder{$tier}_days"};

                if ($days === null) {
                    continue;
                }

                $direction = $settings->{"reminder{$tier}_direction"}; // before|after
                $field = $settings->{"reminder{$tier}_field"}; // due_date|invoice_date

                // "3 days before due_date" means: today is a match when
                // due_date is 3 days in the future; "3 days after" means
                // due_date was 3 days in the past.
                $targetDate = $direction === 'before'
                    ? $today->copy()->addDays($days)
                    : $today->copy()->subDays($days);

                $invoices = $company->invoices()
                    ->where('type', InvoiceType::Invoice)
                    ->whereIn('status', [
                        InvoiceStatus::Issued, InvoiceStatus::Sent, InvoiceStatus::Viewed,
                        InvoiceStatus::Partial, InvoiceStatus::Overdue,
                    ])
                    ->where('balance', '>', 0)
                    ->whereDate($field, $targetDate)
                    ->get();

                foreach ($invoices as $invoice) {
                    // A suppression covers this invoice/tier's *next
                    // occurrence*, however far in the future that is (see
                    // App\Actions\Billing\SuppressReminder) — not merely
                    // "within the last day", which would silently expire a
                    // suppression recorded more than 24h before its target
                    // send date. `consumed_at` marks it used the first time
                    // this command actually skips a send because of it, so
                    // the row's own age never matters, only whether it has
                    // already done its one job.
                    $suppression = ReminderSuppression::query()
                        ->where('invoice_id', $invoice->id)
                        ->where('tier', $tier)
                        ->whereNull('consumed_at')
                        ->first();

                    if ($suppression) {
                        $suppression->forceFill(['consumed_at' => now()])->save();

                        continue;
                    }

                    try {
                        $mailer->sendReminder($invoice, $tier);
                        $sent++;
                    } catch (Throwable $e) {
                        $this->warn("Skipped reminder for invoice {$invoice->number}: {$e->getMessage()}");
                    }
                }
            }
        });

        $this->info("Sent {$sent} reminder email(s).");

        return self::SUCCESS;
    }
}
