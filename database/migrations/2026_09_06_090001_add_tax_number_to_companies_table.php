<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The company's own tax id (e.g. NPWP for an Indonesian entity) —
     * printed on invoice/credit PDFs alongside the client's `tax_number`.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('tax_number')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('tax_number');
        });
    }
};
