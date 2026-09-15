<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'legacy_proposal_snippet_id', 'product_id', 'name', 'html'])]
class ProposalSnippet extends Model
{
    use BelongsToCompany;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Set only for a snippet generated from a product's catalog picture
     * (see the Products list's "Create proposal snippet" action) —
     * re-running that action on the same
     * product refreshes this same snippet instead of duplicating it, same
     * `product_id`-keyed upsert pattern as `products.price_list_item_id`.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
