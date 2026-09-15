<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 07 (docs/rebuild/specs/07-migration-and-cutover/Specs.md):
     * "Support restart from last completed entity checkpoint." Each run of
     * `import:invoiceninja-v4`/`import:invoiceninja-v5` records one row
     * here — `last_completed_step` is the name of the last entity-import
     * method (`importClientsAndContacts`, `importInvoices`, ...) that
     * finished cleanly, so a re-run with `--resume` can skip everything
     * before it instead of restarting the whole import from scratch.
     */
    public function up(): void
    {
        Schema::create('migration_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source_system');
            $table->string('connection');
            $table->string('status')->default('running');
            $table->string('last_completed_step')->nullable();
            $table->text('error_message')->nullable();
            $table->json('stats')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_batches');
    }
};
