<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Reusable starting points for Proposals — see
     * docs/invoiceninja-v4-schema-reference.md §2.7 and
     * docs/engineering.md §2.7. Raw html/css blobs, same
     * as the legacy `proposal_templates` table.
     */
    public function up(): void
    {
        Schema::create('proposal_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('legacy_proposal_template_id')->nullable();

            $table->string('name');
            $table->longText('html')->nullable();
            $table->longText('css')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposal_templates');
    }
};
