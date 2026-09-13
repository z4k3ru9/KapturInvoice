<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** "Vendor bills support partial vendor payments and evidence." */
    public function up(): void
    {
        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_bill_id')->constrained()->cascadeOnDelete();
            $table->string('number')->nullable();

            $table->decimal('amount', 15, 2);
            $table->date('payment_date')->nullable();
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payments');
    }
};
