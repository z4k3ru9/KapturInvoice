<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 06B Slice 1 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md) —
     * a read-only, client/company-scoped Statement of Account for a
     * reporting period. "A generated or sent SOA preserves an immutable
     * PDF snapshot" — `snapshot` freezes the full computed line data
     * (App\Services\Reports\BuildStatementOfAccount's output) at
     * generation time, so the PDF is re-rendered from this exact frozen
     * array and never recomputed live afterwards. Only
     * App\Actions\Reports\GenerateStatementOfAccount writes a row here —
     * a preview calls the service directly without persisting.
     */
    public function up(): void
    {
        Schema::create('statement_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('closing_balance', 15, 2)->default(0);

            $table->string('number')->nullable();
            $table->string('document_language')->nullable();

            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();

            $table->json('snapshot');

            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_of_accounts');
    }
};
