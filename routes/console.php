<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// See App\Console\Commands\SendInvoiceReminders — dispatches the
// reminder1-4 schedule configured on EditEmailSettings
// (docs/filament-admin-layout-design.md §3.3).
Schedule::command('invoices:send-reminders')->dailyAt('08:00');

// See App\Console\Commands\ExpireQuotations — closes the "no automatic
// quotation expiry" gap noted during Phase 03 (Sales and Job).
Schedule::command('quotations:expire')->dailyAt('00:05');

// See App\Console\Commands\MarkInvoicesOverdue — closes the equivalent gap
// for InvoiceStatus::Overdue, which had zero writers anywhere in this
// codebase before the status-transition automation review.
Schedule::command('invoices:mark-overdue')->dailyAt('00:10');

// See App\Console\Commands\GenerateDueRecurringInvoices — the `auto_bill`
// flag was previously decorative; this is what actually acts on it.
Schedule::command('recurring-invoices:generate-due')->dailyAt('06:00');
