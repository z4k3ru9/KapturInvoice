<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 06 (docs/rebuild/specs/06-documents-portal-reporting): a
     * broader, contact-scoped "portal link" giving a designated billing
     * contact read-only access to their client's FULL invoice/receipt/
     * payment history — separate from `invitations`, which stays the
     * per-invoice single-document share mechanism an ordinary contact's
     * "explicitly shared documents" view still relies on (see
     * FINALIZED-DECISIONS.md §5).
     *
     * One row per generated link: `key` is the unguessable credential
     * (same UUID-in-booted() pattern as `invitations.key`), `expires_at`/
     * `revoked_at` make it expiring/revocable, and generating a new row
     * for the same contact makes it "replaceable" without needing a
     * combined action.
     */
    public function up(): void
    {
        Schema::create('portal_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();

            $table->string('key')->unique();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_links');
    }
};
