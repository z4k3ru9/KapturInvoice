<?php

namespace App\Actions\Billing;

use App\Enums\CompanyRole;
use App\Models\Invoice;
use App\Models\ReminderSuppression;
use App\Models\User;
use App\Services\AuditLogger;
use RuntimeException;

/**
 * "Accountant/Admin may suppress an individual reminder with reason and
 * audit event." — docs/rebuild/specs/FINALIZED-DECISIONS.md §5. Reuses
 * CompanyRole::paymentVerificationRoles() (Owner/Admin/Accountant) rather
 * than inventing a new role helper — "who may suppress a reminder" maps
 * to the same billing-adjacent privileged-action tier as payment
 * verification. Scoped to "skip the next occurrence for this
 * invoice+tier" — see App\Models\ReminderSuppression's docblock and
 * App\Console\Commands\SendInvoiceReminders::handle() for how the
 * suppression is consulted.
 */
class SuppressReminder
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function suppress(Invoice $invoice, int $tier, string $reason, User $actor): ReminderSuppression
    {
        if (! $actor->hasCompanyRole($invoice->company, ...CompanyRole::paymentVerificationRoles())) {
            throw new RuntimeException('Only Accountant, Admin, or Owner may suppress a reminder.');
        }

        if (blank($reason)) {
            throw new RuntimeException('A reason is required to suppress a reminder.');
        }

        $suppression = ReminderSuppression::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'tier' => $tier,
            'suppressed_by_user_id' => $actor->id,
            'reason' => $reason,
            'suppressed_at' => now(),
        ]);

        $this->auditLogger->record(
            $invoice->company,
            'invoice.reminder_suppressed',
            $invoice,
            [],
            ['tier' => $tier],
            $reason,
        );

        return $suppression;
    }
}
