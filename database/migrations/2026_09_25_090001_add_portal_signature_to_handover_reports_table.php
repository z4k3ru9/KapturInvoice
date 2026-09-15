<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Public drawn-signature acceptance for a Handover Report — same
     * shape as the sibling Delivery Order migration; see that file's
     * docblock for the full reasoning (App\Livewire\Portal\
     * SignHandoverReport).
     */
    public function up(): void
    {
        Schema::table('handover_reports', function (Blueprint $table) {
            $table->string('portal_key')->nullable()->unique()->after('id');
            $table->string('signed_by_name')->nullable()->after('override_reason');
            $table->longText('signature')->nullable()->after('signed_by_name');
            $table->timestamp('signed_at')->nullable()->after('signature');
        });

        DB::table('handover_reports')->whereNull('portal_key')->orderBy('id')->each(function ($row) {
            DB::table('handover_reports')->where('id', $row->id)->update([
                'portal_key' => (string) Str::orderedUuid(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('handover_reports', function (Blueprint $table) {
            $table->dropColumn(['portal_key', 'signed_by_name', 'signature', 'signed_at']);
        });
    }
};
