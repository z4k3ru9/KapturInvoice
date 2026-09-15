<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `invitations.signature` was already a `text` column (not `varchar(255)`),
     * but a `text` column tops out at 64KB on MySQL/MariaDB — comfortably
     * enough for a typical drawn-signature PNG data URI, but not a safety
     * margin worth trusting for every browser/canvas-size combination once
     * the portal e-sign flow (`App\Livewire\Portal\ViewInvoice`) switched
     * from a typed name to a real drawn signature (TallStackUI
     * `<x-signature>`, `App\Models\Invitation::signature`). Widening to
     * `longtext` (4GB) removes that ceiling entirely; a no-op on SQLite,
     * where `text` never had a practical size limit.
     */
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->longText('signature')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->text('signature')->nullable()->change();
        });
    }
};
