<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The status-transition automation added around this same time
     * (Invoice auto-Overdue, recurring auto-generate/issue/send, SalesOrder
     * auto-close-operationally) needs an explicit override lever for the
     * real case this comes up — moderation or a revision that needs
     * approval before anything is amended automatically — rather than a
     * free-form status dropdown that would just get silently recomputed
     * back on the next automated run. `held_at`/`held_reason`/`held_by`
     * are deliberately NOT `#[Fillable]`, same precedent as
     * `draft_version` above: written only by the dedicated Hold/Release
     * actions (App\Models\Concerns\Holdable), never by mass assignment
     * from a form.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('held_at')->nullable()->after('status');
            $table->text('held_reason')->nullable()->after('held_at');
            $table->foreignId('held_by')->nullable()->after('held_reason')->constrained('users')->nullOnDelete();
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->timestamp('held_at')->nullable()->after('status');
            $table->text('held_reason')->nullable()->after('held_at');
            $table->foreignId('held_by')->nullable()->after('held_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('held_by');
            $table->dropColumn(['held_at', 'held_reason']);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('held_by');
            $table->dropColumn(['held_at', 'held_reason']);
        });
    }
};
