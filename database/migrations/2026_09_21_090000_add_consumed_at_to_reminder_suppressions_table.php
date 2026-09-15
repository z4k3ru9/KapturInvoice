<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Codex review finding on PR #4: `SendInvoiceReminders` matched an
 * unconsumed suppression by a fixed "within the last day" window, so a
 * suppression recorded more than 24h before its target send date silently
 * expired before that date arrived — see App\Console\Commands\
 * SendInvoiceReminders and App\Models\ReminderSuppression.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminder_suppressions', function (Blueprint $table) {
            $table->timestamp('consumed_at')->nullable()->after('suppressed_at');
        });
    }

    public function down(): void
    {
        Schema::table('reminder_suppressions', function (Blueprint $table) {
            $table->dropColumn('consumed_at');
        });
    }
};
