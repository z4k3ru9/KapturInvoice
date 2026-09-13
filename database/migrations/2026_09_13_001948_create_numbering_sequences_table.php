<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Backs the new `COMPANY-DOCUMENTTYPE-YEARMONTHSEQ` numbering format
     * (docs/rebuild/specs/FINALIZED-DECISIONS.md §2): one row per
     * company/document-type/year, allocated atomically via `lockForUpdate()`
     * inside App\Services\DocumentNumberGenerator. The sequence increments
     * across months within a year and resets only at the start of the next
     * year — never monthly, never reused. This replaces the legacy
     * `companies.invoice_next_number`/etc. columns as the source of truth
     * for numbers issued from now on; those legacy columns stay in place
     * untouched so already-issued historical numbers remain exactly as
     * issued (docs/rebuild/specs/FINALIZED-DECISIONS.md §6).
     */
    public function up(): void
    {
        Schema::create('numbering_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type'); // e.g. INV, QUO, CR
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next_sequence')->default(1);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'document_type', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('numbering_sequences');
    }
};
