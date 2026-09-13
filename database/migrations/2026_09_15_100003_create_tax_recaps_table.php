<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A separate reporting record for each issued taxable invoice or
     * amendment, per FINALIZED-DECISIONS.md §3: "not for payments... retains
     * the invoice transaction period even when adjusted later, and
     * separately records adjustment date." Prefilled at issuance
     * (App\Actions\Billing\IssueInvoice), manually adjustable afterward
     * with a reason and audit event — never mutates the invoice's own
     * tax snapshot.
     */
    public function up(): void
    {
        Schema::create('tax_recaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('reporting_period'); // transaction period, e.g. "2026-09" — frozen at issuance
            $table->string('external_reference')->nullable();
            $table->string('manual_entry_status')->default('pending'); // pending|filed
            $table->date('filing_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_reference')->nullable();

            $table->timestamp('adjusted_at')->nullable();
            $table->foreignId('adjusted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('adjustment_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_recaps');
    }
};
