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
     * see docs/rebuild/specs/06-documents-portal-reporting/Specs.md. Null
     * means "use the company default"; see `Invoice::resolveDocumentLanguage()`.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('document_language')->nullable()->after('pricing_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('document_language');
        });
    }
};
