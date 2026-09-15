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
     * same shape as `invoices.document_language`
     * (2026_09_13_010001_add_document_language_to_invoices_table). See
     * docs/rebuild/specs/06b-ux-browser-soa/Specs.md "Required launch
     * document coverage" and `VendorBill::resolveDocumentLanguage()`.
     */
    public function up(): void
    {
        Schema::table('vendor_bills', function (Blueprint $table) {
            $table->string('document_language')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_bills', function (Blueprint $table) {
            $table->dropColumn('document_language');
        });
    }
};
