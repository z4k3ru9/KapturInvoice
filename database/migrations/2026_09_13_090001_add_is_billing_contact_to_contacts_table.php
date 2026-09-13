<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 02 (docs/rebuild/specs/02-parties-and-catalog/Specs.md): "may
     * be designated for portal access" — per
     * docs/rebuild/specs/FINALIZED-DECISIONS.md §5, only a client's
     * *designated billing contact(s)* see full billing history in the
     * portal; an ordinary contact sees only explicitly shared documents.
     * This flag is that designation. It does not itself build the
     * portal-link expiry/revocation mechanism (`contacts.portal_token`
     * already carries the credential) — that mechanism's rework is
     * Phase 06 (documents/portal/reporting) scope.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('is_billing_contact')->default(false)->after('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('is_billing_contact');
        });
    }
};
