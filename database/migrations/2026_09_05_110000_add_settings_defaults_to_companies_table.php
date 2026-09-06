<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Invoice defaults belong alongside the numbering columns already on
     * `companies` (see the docs/filament-admin-layout-design.md §3.2
     * "Numbering & Invoice Defaults" settings page) — account-level
     * fallback terms/taxes, referenced by FK now rather than the legacy
     * inline tax_name/rate columns.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('default_payment_terms')->nullable()->after('credit_next_number');
            $table->foreignId('default_tax_rate_1_id')->nullable()
                ->after('default_payment_terms')->constrained('tax_rates')->nullOnDelete();
            $table->foreignId('default_tax_rate_2_id')->nullable()
                ->after('default_tax_rate_1_id')->constrained('tax_rates')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_tax_rate_1_id');
            $table->dropConstrainedForeignId('default_tax_rate_2_id');
            $table->dropColumn('default_payment_terms');
        });
    }
};
