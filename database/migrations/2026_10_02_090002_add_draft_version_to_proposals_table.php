<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extends App\Livewire\Concerns\AutosavesDraft (already wired onto
     * Invoice/Quotation/RecurringInvoice/VendorBill/VendorPurchaseOrder
     * forms — Proposal was the one remaining document edit form without
     * it) to App\Livewire\TallStackProposalForm. Same shape/reasoning as
     * every other draft_version migration: deliberately not a
     * `#[Fillable]` column, written only by the trait's own
     * forceFill()/conditional UPDATE, never by a normal save().
     */
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->unsignedInteger('draft_version')->default(0)->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn('draft_version');
        });
    }
};
