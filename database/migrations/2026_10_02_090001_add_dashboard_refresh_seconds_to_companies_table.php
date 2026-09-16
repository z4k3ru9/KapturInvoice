<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How often the Dashboard's own stat cards/trend chart re-pull data on
     * their own, client-side, via a plain `setInterval` calling Livewire's
     * `loadDashboardData` — replacing the hand-clicked refresh button (see
     * App\Livewire\TallStackDashboard). `null`/`0` means auto-refresh is
     * off (the previous, manual-only behavior); any other value is a
     * whole number of seconds, set from the Company & Taxes settings page.
     * Company-wide, not per-user — every admin viewing this company's
     * dashboard shares the same cadence, matching how every other
     * dashboard-shaping setting in this app (currency, branding, document
     * numbering) is already a company-level default rather than a
     * personal one.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('dashboard_refresh_seconds')->nullable()->after('default_expire_after_days');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('dashboard_refresh_seconds');
        });
    }
};
