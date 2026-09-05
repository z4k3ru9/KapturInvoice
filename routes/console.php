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
