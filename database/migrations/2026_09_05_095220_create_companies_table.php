<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `companies` is the Filament tenant model: one row per billed entity.
     * KapturInvoice runs (at least) two of these — each with its own public
     * homepage domain, branding, and invoice numbering sequence — sharing a
     * single Filament admin panel.
     *
     * See docs/invoiceninja-v4-schema-reference.md §4 for why this replaces
     * the legacy `accounts` table's ~140 flat settings columns with a
     * leaner identity table (numbering/branding stay inline here for now;
     * split into dedicated settings tables later if this grows unwieldy).
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_account_id')->nullable()->unique();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country_code', 2)->nullable();

            $table->string('currency_code', 3)->default('USD');
            $table->string('timezone')->default('UTC');

            $table->string('logo_path')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();

            // Per-entity document numbering (mirrors legacy accounts.*_prefix/
            // *_counter/*_pattern, kept here since each company/tenant needs
            // its own independent sequence).
            $table->string('invoice_prefix')->nullable();
            $table->unsignedInteger('invoice_next_number')->default(1);
            $table->string('quote_prefix')->nullable();
            $table->unsignedInteger('quote_next_number')->default(1);
            $table->string('credit_prefix')->nullable();
            $table->unsignedInteger('credit_next_number')->default(1);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
