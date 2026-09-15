<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 13 repair plan item 2 — extends App\Livewire\Concerns\
     * AutosavesDraft to App\Livewire\TallStackVendorPurchaseOrderForm. Same
     * shape/reasoning as
     * 2026_09_20_090000_add_draft_version_to_invoices_table.php:
     * deliberately not a `#[Fillable]` column, written only by the trait's
     * own forceFill()/conditional UPDATE.
     */
    public function up(): void
    {
        Schema::table('vendor_purchase_orders', function (Blueprint $table) {
            $table->unsignedInteger('draft_version')->default(0)->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_purchase_orders', function (Blueprint $table) {
            $table->dropColumn('draft_version');
        });
    }
};
