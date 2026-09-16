<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A company's own bank accounts, printed as a "Payment Method" section
     * on the Invoice PDF (a real gap: this app had no bank/payment-detail
     * storage anywhere before this). Deliberately a dedicated one-to-many
     * table, not a JSON column on `company_settings` — "possible to have
     * multiple method of bank transfer so 2 different numbers can be
     * displayed" is a genuine one-to-many relationship (add/edit/delete/
     * reorder individual accounts), matching this app's existing pattern
     * for a company-scoped lookup list (see `tax_rates`'s own migration)
     * rather than the JSON-blob shape.
     */
    public function up(): void
    {
        Schema::create('company_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number');
            $table->string('branch')->nullable();
            $table->string('swift_code')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_bank_accounts');
    }
};
