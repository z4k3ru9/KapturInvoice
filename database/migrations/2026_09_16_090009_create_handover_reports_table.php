<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Handover Report is required only for installation/service jobs and
     * may require completed delivery... Admin/Owner overrides require a
     * reason." `is_override`/`override_reason` record when
     * App\Actions\Delivery\CompleteHandover bypassed the "required
     * delivery items complete" check.
     */
    public function up(): void
    {
        Schema::create('handover_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->string('number')->nullable();

            $table->date('handover_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_override')->default(false);
            $table->text('override_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handover_reports');
    }
};
