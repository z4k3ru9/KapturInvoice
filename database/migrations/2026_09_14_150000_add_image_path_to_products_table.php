<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional product picture, shown as a thumbnail on Quotation item
     * tables/PDFs and reusable in Proposal snippets — see
     * App\Models\Product::getImageDataUri(), same pattern as
     * Company::getLogoDataUri().
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('sku');
        });

        Schema::table('proposal_snippets', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proposal_snippets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
