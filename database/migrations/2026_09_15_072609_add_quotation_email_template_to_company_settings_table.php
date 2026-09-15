<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A distinct subject/body template pair for the new job-centric
     * `App\Models\Quotation` (`App\Services\Sales\QuotationMailer`) —
     * separate from `quote_email_subject`/`quote_email_body`, which is
     * the legacy `Invoice`/`type=quote` template
     * (`App\Services\BillingMailer::sendQuote()`). Same string/text shape
     * as every other email template pair on this table.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('quotation_email_subject')->nullable()->after('quote_email_body');
            $table->text('quotation_email_body')->nullable()->after('quotation_email_subject');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['quotation_email_subject', 'quotation_email_body']);
        });
    }
};
