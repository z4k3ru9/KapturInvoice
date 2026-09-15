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
     * same shape as `invoices.document_language`/`credits.document_language`.
     * See docs/rebuild/specs/06b-ux-browser-soa/Specs.md "Required launch
     * document coverage" and `SalesOrder::resolveDocumentLanguage()`.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('document_language')->nullable()->after('source_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('document_language');
        });
    }
};
