<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Per-document override of the company's `default_document_language` —
     * same shape as `invoices.document_language`, since `Credit` is its own
     * table/model (not a filtered view over `invoices`). See
     * docs/rebuild/specs/06-documents-portal-reporting/Specs.md and
     * `Credit::resolveDocumentLanguage()`.
     */
    public function up(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->string('document_language')->nullable()->after('credit_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropColumn('document_language');
        });
    }
};
