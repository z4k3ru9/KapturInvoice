<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per company (tenant): email/reminder templates and client
     * portal toggles, split out of the legacy `accounts`/
     * `account_email_settings` ~140-column grab-bag per
     * docs/invoiceninja-v4-schema-reference.md §2.1/§4 and
     * docs/filament-admin-layout-design.md §3.3/§3.5. Edited via two
     * separate Filament settings pages (Email & Reminders, Client Portal)
     * that both bind to this one table.
     */
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();

            // Email templates (subject/body), one pair per document kind.
            $table->string('invoice_email_subject')->nullable();
            $table->text('invoice_email_body')->nullable();
            $table->string('quote_email_subject')->nullable();
            $table->text('quote_email_body')->nullable();
            $table->string('payment_email_subject')->nullable();
            $table->text('payment_email_body')->nullable();

            // Four reminder schedules, mirroring legacy reminder1-4.
            foreach (range(1, 4) as $n) {
                $table->boolean("reminder{$n}_enabled")->default(false);
                $table->unsignedSmallInteger("reminder{$n}_days")->nullable();
                $table->string("reminder{$n}_direction")->default('after'); // before|after
                $table->string("reminder{$n}_field")->default('due_date'); // due_date|invoice_date
            }

            // Three late-fee tiers, mirroring legacy late_fee1-3.
            foreach (range(1, 3) as $n) {
                $table->decimal("late_fee{$n}_amount", 13, 2)->nullable();
                $table->decimal("late_fee{$n}_percent", 8, 3)->nullable();
            }

            // Client portal.
            $table->boolean('portal_enabled')->default(true);
            $table->boolean('portal_allow_client_payments')->default(true);
            $table->boolean('portal_show_tasks')->default(false);
            $table->boolean('portal_require_signature')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
