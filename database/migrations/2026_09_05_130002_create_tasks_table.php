<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * See docs/invoiceninja-v4-schema-reference.md §2.6. Simplified vs.
     * the legacy `time_log` serialized-intervals text column: this rebuild
     * tracks a single active/last timer per task via started_at/stopped_at
     * rather than a full multi-segment child table — see
     * docs/engineering.md §2.5 for the fuller
     * `task_time_entries` design this can grow into if multiple segments
     * per task turn out to be needed.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_status_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('legacy_task_id')->nullable();

            $table->string('description')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->boolean('is_running')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
