<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProposalSnippet;

/**
 * Turns (or refreshes) a Product's picture/name/sku/price into a
 * ProposalSnippet an author can copy/paste into a Proposal's rich editor
 * — the only way today a product picture reaches a Proposal, since
 * Proposals are free-form HTML/CSS documents with no line-item table of
 * their own (see App\Models\Proposal's docblock: "one line item from its
 * title/amount" only, on conversion to invoice).
 *
 * `proposal_snippets.product_id` links the two, so re-running this after
 * the product's picture/price changes refreshes the same snippet instead
 * of duplicating it — same upsert spirit as ProductSync.
 */
class ProposalSnippetSync
{
    public function createOrUpdateFromProduct(Product $product): ProposalSnippet
    {
        $snippet = ProposalSnippet::query()
            ->where('company_id', $product->company_id)
            ->where('product_id', $product->id)
            ->first() ?? new ProposalSnippet;

        $snippet->fill([
            'company_id' => $product->company_id,
            'product_id' => $product->id,
            'name' => $product->name,
            'html' => $this->renderHtml($product),
        ]);

        $snippet->save();

        return $snippet;
    }

    private function renderHtml(Product $product): string
    {
        // The picture is embedded as a base64 data URI (Product::getImageDataUri())
        // rather than a Storage::url() reference, so the snippet's HTML is
        // self-contained wherever it ends up — pasted into the rich editor
        // on screen, or later inside a Proposal PDF (dompdf can't fetch a
        // Storage::url() for the `local` disk, same reasoning as
        // Company::getLogoDataUri()).
        $image = $product->getImageDataUri();
        $name = e($product->name);
        $sku = e($product->sku ?? '');
        $price = number_format((float) $product->unit_cost, 2);

        $imageTag = $image
            ? "<img src=\"{$image}\" alt=\"{$name}\" style=\"max-width:200px;display:block;margin-bottom:8px;\">"
            : '';

        $skuLine = $sku !== '' ? "SKU: {$sku}<br>" : '';

        return <<<HTML
            <div class="product-snippet">
                {$imageTag}
                <p><strong>{$name}</strong><br>{$skuLine}Price: {$price}</p>
            </div>
            HTML;
    }
}
