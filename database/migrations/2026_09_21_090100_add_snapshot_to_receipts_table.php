<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Codex review finding on PR #4: downloading an already-issued Receipt
 * re-rendered the payment's *current* live allocation history
 * (App\Actions\Receivables\AmendPaymentAllocation supersedes and inserts
 * new allocation rows after the fact), so the receipt's printed content
 * silently changed after issuance and could show allocation rows that
 * did not exist when it was issued — violating "one verified event
 * produces one receipt" (CLAUDE.md) and the immutable-document guardrail.
 * `snapshot` is captured once, at issuance, by
 * App\Actions\Receivables\IssuePaymentReceipt and never touched again —
 * the PDF view renders from it exclusively.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->json('snapshot')->nullable()->after('issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('snapshot');
        });
    }
};
