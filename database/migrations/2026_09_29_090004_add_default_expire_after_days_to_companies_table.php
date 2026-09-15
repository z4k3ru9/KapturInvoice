<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Repair plan Phase 11 / decision gate G5: the only genuinely missing
     * settings default. `default_payment_terms` (this same table, see
     * 2026_09_05_110000_add_settings_defaults_to_companies_table.php) and
     * `payment_instructions` (2026_08_31 branding migration) already exist
     * as unwired company-level defaults and are reused rather than
     * duplicated — see App\Livewire\TallStackSettingsNumbering's docblock.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_expire_after_days')->nullable()->after('default_tax_rate_2_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('default_expire_after_days');
        });
    }
};
