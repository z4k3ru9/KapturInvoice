<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Vendor bills flow through Draft, Submitted, Approved, Partially
     * Paid, and Paid; Accountant and higher may self-approve with audit
     * evidence." A bill always ties back to the Vendor PO whose approved
     * total defines the payment ceiling — a required FK, not nullable
     * (a vendor bill with no PO is legacy `Expense` scope, not this
     * table's).
     */
    public function up(): void
    {
        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('number')->nullable();
            $table->string('status')->default('draft'); // draft|submitted|approved|partially_paid|paid|cancelled

            $table->date('bill_date')->nullable();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();

            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);

            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bills');
    }
};
