<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Company-wide default language for printed documents (invoices/quotes/
     * credits) — docs/rebuild/specs/06-documents-portal-reporting/Specs.md:
     * "Printed documents default to Bahasa Indonesia with per-document
     * English override." `Invoice`/`Credit::resolveDocumentLanguage()` fall
     * back to this when a document has no `document_language` of its own.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('default_document_language')->default('id')->after('period_locked_through');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('default_document_language');
        });
    }
};
