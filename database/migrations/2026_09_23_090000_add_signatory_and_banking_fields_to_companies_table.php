<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('signatory_name')->nullable();
            $table->string('signatory_title')->nullable();
            $table->string('signature_image_path')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->text('payment_instructions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'signatory_name',
                'signatory_title',
                'signature_image_path',
                'bank_name',
                'bank_account_number',
                'bank_account_name',
                'payment_instructions',
            ]);
        });
    }
};
