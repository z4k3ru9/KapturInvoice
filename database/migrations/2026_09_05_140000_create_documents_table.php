<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Polymorphic `documentable` (Invoice *or* Expense, never both) — see
     * docs/invoiceninja-v4-schema-reference.md §2.8, which flags the legacy
     * `documents` table as "polymorphic by convention" (two nullable FKs)
     * rather than a real Eloquent morph relation.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('legacy_document_id')->nullable();

            $table->morphs('documentable');

            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
