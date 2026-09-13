<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Scaffolded now (Phase 01) per
     * docs/rebuild/specs/IMPLEMENTATION-STRUCTURE.md §6, but not yet
     * populated by the existing `import:invoiceninja-v4`/`-v5` commands —
     * those keep using their own `legacy_*_id` columns until the Migration
     * phase (docs/rebuild/specs/07-migration-and-cutover) reworks them to
     * target the split canonical schema and write here instead. The
     * uniqueness identity matches docs/rebuild/Specs.md §14: source system
     * + source version + company + entity type + source ID.
     */
    public function up(): void
    {
        Schema::create('source_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source_system'); // e.g. invoiceninja
            $table->string('source_version'); // e.g. v4, v5
            $table->string('entity_type');
            $table->string('source_id');
            $table->string('canonical_type');
            $table->unsignedBigInteger('canonical_id');
            $table->string('batch')->nullable();
            $table->string('status')->default('imported');
            $table->timestamps();

            $table->unique(
                ['company_id', 'source_system', 'source_version', 'entity_type', 'source_id'],
                'source_records_identity_unique'
            );
            $table->index(['canonical_type', 'canonical_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_records');
    }
};
