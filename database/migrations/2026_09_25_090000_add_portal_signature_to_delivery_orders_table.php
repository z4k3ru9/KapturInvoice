<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Public drawn-signature acceptance for a Delivery Order, same
     * capability as the invoice portal e-sign flow
     * (App\Livewire\Portal\ViewInvoice / App\Models\Invitation) — see
     * App\Livewire\Portal\SignDeliveryOrder's docblock for why this is a
     * lightweight per-model credential rather than a generic polymorphic
     * "signable" table: `App\Models\Invitation` is invoice-specific
     * (`invoice_id`, not polymorphic), and there are only three of these
     * extensions to build.
     *
     * `portal_key` is the unguessable credential — mirrors
     * `invitations.key` exactly (a UUID, unique, never mass-fillable —
     * minted in App\Models\DeliveryOrder::booted()). `signature` is a
     * `longtext` from the start (not `text`, which caps at 64KB on
     * MySQL/MariaDB — see the invitations-table widening migration this
     * mirrors) since a drawn-signature PNG data URI is typically 10-20KB
     * but isn't worth capping. `signed_by_name` records the typed name
     * alongside the drawn signature — unlike an invoice's `Invitation`,
     * a Delivery Order has no per-contact portal record to already know
     * who's signing.
     */
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->string('portal_key')->nullable()->unique()->after('id');
            $table->string('signed_by_name')->nullable()->after('notes');
            $table->longText('signature')->nullable()->after('signed_by_name');
            $table->timestamp('signed_at')->nullable()->after('signature');
        });

        // Backfill existing rows so every Delivery Order already recorded
        // gets a working portal link immediately, not only ones created
        // after this migration.
        DB::table('delivery_orders')->whereNull('portal_key')->orderBy('id')->each(function ($row) {
            DB::table('delivery_orders')->where('id', $row->id)->update([
                'portal_key' => (string) Str::orderedUuid(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn(['portal_key', 'signed_by_name', 'signature', 'signed_at']);
        });
    }
};
