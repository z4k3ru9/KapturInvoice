<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 13 repair plan item 2 — extends App\Livewire\Concerns\
     * AutosavesDraft (previously wired only on App\Livewire\
     * TallStackInvoiceForm, which reused `invoices.draft_version` from
     * 2026_09_20_090000_add_draft_version_to_invoices_table.php) to
     * App\Livewire\TallStackQuotationForm. Same shape/reasoning as that
     * migration: deliberately not a `#[Fillable]` column, written only by
     * the trait's own forceFill()/conditional UPDATE.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedInteger('draft_version')->default(0)->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('draft_version');
        });
    }
};
