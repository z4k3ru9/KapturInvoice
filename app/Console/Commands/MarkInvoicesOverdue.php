<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Scheduled daily (see routes/console.php) to close a real gap:
 * InvoiceStatus::Overdue is a valid transition target and is read
 * everywhere (dashboards, App\Console\Commands\SendInvoiceReminders,
 * Statement of Account) but had zero writers anywhere in this codebase —
 * confirmed by a full grep during the status-transition automation review.
 * Mirrors App\Console\Commands\ExpireQuotations' exact shape for the
 * equivalent, already-shipped Quotation gap.
 *
 * Only Issued/Partial invoices past their due_date with a positive balance
 * are eligible — InvoiceStatus::allowedNextStates() already only permits
 * Issued/Partial -> Overdue, so this can never mark a Draft/Approved
 * invoice. A held invoice (App\Models\Concerns\Holdable) is skipped —
 * placing a hold is the intended override lever for pausing this specific
 * automation on one record, not a status dropdown.
 */
class MarkInvoicesOverdue extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark Issued/Partial invoices past their due_date with a positive balance as Overdue.';

    public function handle(AuditLogger $auditLogger): int
    {
        $marked = 0;

        Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Partial])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today())
            ->where('balance', '>', 0)
            ->notHeld()
            ->each(function (Invoice $invoice) use ($auditLogger, &$marked) {
                $before = $invoice->status;

                $invoice->forceFill(['status' => InvoiceStatus::Overdue])->save();

                $auditLogger->record(
                    $invoice->company,
                    'invoice.marked_overdue',
                    $invoice,
                    ['status' => $before->value],
                    ['status' => InvoiceStatus::Overdue->value],
                );

                $marked++;
            });

        $this->info("Marked {$marked} invoice(s) as Overdue.");

        return self::SUCCESS;
    }
}
