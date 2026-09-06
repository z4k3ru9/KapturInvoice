<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A client-level default discount (amount or %) to prefill new
     * invoices for that client — the invoice/item-level discount fields
     * already existed (InvoiceForm/ItemsRelationManager), but nothing on
     * the client side offered a default to start from.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('default_discount', 15, 2)->default(0)->after('id_number');
            $table->boolean('default_discount_is_percentage')->default(false)->after('default_discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['default_discount', 'default_discount_is_percentage']);
        });
    }
};
