<?php

namespace App\Console\Commands;

use App\Actions\Billing\IssueInvoice;
use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\BillingMailer;
use App\Services\InvoiceDuplicator;
use App\Services\RecurringInvoiceSchedule;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * `Invoice` already has `recurring_frequency`/`recurring_start_date`/
 * `recurring_end_date`/`recurring_last_sent_at` plus an `auto_bill`
 * boolean with its own UI toggle and dashboard stat count — all
 * previously decorative. `App\Livewire\TallStackRecurringInvoices::
 * generateNow()` was the only trigger anywhere, entirely manual and
 * unconditional (no due-date check). This closes that gap: daily,
 * for every `is_recurring = true, auto_bill = true` template that isn't
 * held (App\Models\Concerns\Holdable) and is actually due per
 * App\Services\RecurringInvoiceSchedule, generates the next instance via
 * the existing App\Services\InvoiceDuplicator::generateRecurringInstance()
 * — the same method "Generate now" already calls, so the generated
 * invoice itself is byte-for-byte identical either way.
 *
 * `auto_bill = true` is the user's own explicit, pre-existing per-template
 * consent — no other invoice-creation path in this app auto-issues or
 * auto-sends. Because that consent exists, this command goes one step
 * further than plain generation for these templates specifically: it also
 * issues and emails the generated invoice, via the same
 * App\Actions\Billing\IssueInvoice and App\Services\BillingMailer a human
 * would use from the Invoice form's own Issue/Send buttons. This is the
 * highest-risk piece of the status-transition automation — it emails a
 * real invoice to a real client with no human review in that run — kept
 * as tightly scoped as possible (opt-in per template, reusing the exact
 * same issuance/send code path and its own role/business-rule checks
 * unchanged).
 *
 * IssueInvoice::issue() requires a User $actor and hard-checks
 * CompanyRole::invoiceIssuanceRoles() — there is no logged-in user in a
 * scheduled command, so the acting user is resolved as the template's own
 * company's Owner (the same role CompanySeeder always attaches). This
 * keeps the existing security/audit-trail code path completely intact
 * rather than adding a bypass. A company with no active Owner user skips
 * that template for this run (logged as a warning) rather than failing
 * the whole job.
 */
class GenerateDueRecurringInvoices extends Command
{
    protected $signature = 'recurring-invoices:generate-due {--dry-run : List what would be generated/issued/sent without doing it.}';

    protected $description = 'Generate, issue, and send the next invoice for every due, auto_bill-enabled recurring template.';

    public function handle(
        RecurringInvoiceSchedule $schedule,
        InvoiceDuplicator $duplicator,
        IssueInvoice $issueInvoice,
        BillingMailer $mailer,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $generated = 0;
        $issuedAndSent = 0;
        $skipped = 0;

        Invoice::query()
            ->where('is_recurring', true)
            ->where('auto_bill', true)
            ->notHeld()
            ->each(function (Invoice $template) use ($schedule, $duplicator, $issueInvoice, $mailer, $dryRun, &$generated, &$issuedAndSent, &$skipped) {
                if (! $schedule->isDue($template)) {
                    return;
                }

                if ($dryRun) {
                    $this->line("[dry-run] Would generate, issue, and send from template #{$template->id} ({$template->number}).");
                    $generated++;

                    return;
                }

                $invoice = $duplicator->generateRecurringInstance($template);
                $generated++;

                $actor = $this->resolveOwner($template->company);

                if (! $actor) {
                    $this->warn("Template #{$template->id}: generated invoice #{$invoice->number}, but company [{$template->company->name}] has no active Owner user to issue/send as — left in Draft.");
                    $skipped++;

                    return;
                }

                try {
                    $issueInvoice->issue($invoice, $actor);
                    $mailer->sendInvoice($invoice->fresh());
                    $issuedAndSent++;
                } catch (RuntimeException $e) {
                    $this->warn("Template #{$template->id}: generated invoice #{$invoice->number}, but could not issue/send it: {$e->getMessage()}");
                    $skipped++;
                }
            });

        $this->info("Generated {$generated} invoice(s), issued and sent {$issuedAndSent}, {$skipped} left in Draft.");

        return self::SUCCESS;
    }

    private function resolveOwner(Company $company): ?User
    {
        return $company->users()
            ->wherePivot('is_active', true)
            ->wherePivot('role', CompanyRole::Owner->value)
            ->first();
    }
}
