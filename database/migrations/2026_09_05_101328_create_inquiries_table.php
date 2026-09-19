<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Contact-form submissions from a company's public homepage
     * (App\Livewire\HomePage). Deliberately not scoped by
     * App\Models\Concerns\BelongsToCompany — that trait auto-fills/scopes
     * via the legacy admin tenant, but inquiries are created from the public
     * site (no legacy admin tenant in context), keyed instead by whichever
     * Company App\Http\Middleware\ResolveCompanyFromDomain resolved.
     */
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
