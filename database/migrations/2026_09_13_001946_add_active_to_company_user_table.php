<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Disabling a membership must block a user's access to that company
     * immediately without deleting their history (see
     * docs/rebuild/specs/01-company-foundation/Specs.md) — this flag is
     * checked by App\Models\User::canAccessTenant()/getTenants() and by
     * App\Models\User::hasCompanyRole().
     */
    public function up(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
