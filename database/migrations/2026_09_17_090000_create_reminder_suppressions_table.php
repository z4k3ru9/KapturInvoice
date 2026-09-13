<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Accountant/Admin may suppress an individual reminder with reason
     * and audit event." — docs/rebuild/specs/FINALIZED-DECISIONS.md §5,
     * docs/rebuild/specs/06-documents-portal-reporting/Specs.md. One row
     * = "don't send tier N's reminder for this invoice on its next
     * matching date" — scoped to a single occurrence, not a permanent
     * suppression of the whole tier, per that Specs.md line. Written only
     * by App\Actions\Billing\SuppressReminder; append-only, mirrors
     * `vendor_po_variances`/`job_variations`.
     */
    public function up(): void
    {
        Schema::create('reminder_suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('tier'); // 1-4, mirrors company_settings.reminder{n}_*
            $table->foreignId('suppressed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->timestamp('suppressed_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_suppressions');
    }
};
