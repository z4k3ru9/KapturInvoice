<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 06B Slice 3 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md):
     * "Add server version or equivalent optimistic concurrency
     * protection" for draft autosave — see App\Filament\Concerns\
     * AutosavesDraft. Deliberately not a `#[Fillable]` column: it is
     * only ever written by that trait's own forceFill(), never through a
     * normal form submission.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('draft_version')->default(0)->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('draft_version');
        });
    }
};
