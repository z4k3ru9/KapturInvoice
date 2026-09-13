<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Goods-only jobs may close operationally after delivery. Installation/
     * service jobs require handover before operational closure." — a
     * goods-only job sets this false at job creation (see
     * App\Actions\Sales\CreateSalesOrderFromQuotation); everything else
     * defaults to requiring a Handover Report, the safer default.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->boolean('requires_handover')->default(true)->after('source_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('requires_handover');
        });
    }
};
