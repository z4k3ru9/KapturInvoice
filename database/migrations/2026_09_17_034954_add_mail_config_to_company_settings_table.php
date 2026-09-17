<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Per-company outbound mail transport (host/port/username/password-or-
     * API-key/encryption/from address/from name), configurable from the
     * Email & Reminders settings page instead of being hardcoded to the
     * single app-wide `.env` mailer every company previously shared. One
     * encrypted JSON column, mirroring `PaymentGateway::config`'s own
     * `encrypted:array` pattern exactly — see App\Models\CompanySetting
     * and App\Services\CompanyMailerResolver. Null means "use the app's
     * own default mailer", so this is opt-in per company, not required.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->text('mail_config')->nullable()->after('default_document_language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('mail_config');
        });
    }
};
