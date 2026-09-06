<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Reusable HTML fragments droppable into a Proposal's body — see
     * docs/invoiceninja-v4-schema-reference.md §2.7 and
     * docs/filament-admin-layout-design.md §2.7.
     */
    public function up(): void
    {
        Schema::create('proposal_snippets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('legacy_proposal_snippet_id')->nullable();

            $table->string('name');
            $table->longText('html')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposal_snippets');
    }
};
