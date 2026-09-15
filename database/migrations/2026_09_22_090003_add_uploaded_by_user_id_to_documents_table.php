<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drift audit against docs/rebuild/specs/06-documents-portal-reporting/
     * Specs.md and FINALIZED-DECISIONS.md: financial attachments require
     * "uploader/timestamp evidence" — `documents` had no uploader column at
     * all. Nullable so legacy-imported rows (no acting user at import time)
     * stay valid.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('uploaded_by_user_id')->nullable()->after('size')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by_user_id');
        });
    }
};
