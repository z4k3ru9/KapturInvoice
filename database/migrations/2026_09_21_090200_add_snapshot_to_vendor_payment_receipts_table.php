<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same fix as receipts.snapshot (see that migration's docblock), for the
 * vendor-side equivalent — App\Actions\Procurement\AmendVendorPayment
 * mutates `vendor_payments.amount` directly, so an already-issued
 * VendorPaymentReceipt rendering that live column would show a different
 * amount after an amendment than what was true at issuance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_payment_receipts', function (Blueprint $table) {
            $table->json('snapshot')->nullable()->after('issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_payment_receipts', function (Blueprint $table) {
            $table->dropColumn('snapshot');
        });
    }
};
