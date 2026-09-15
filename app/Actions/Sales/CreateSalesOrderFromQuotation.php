<?php

namespace App\Actions\Sales;

use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Services\DocumentNumberGenerator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "Create a job only from an accepted quotation. Preserve accepted
 * quotation values as the job source snapshot." —
 * docs/rebuild/specs/03-sales-and-job/Specs.md. The job's line items are
 * copied (not referenced) from the quotation's, and the whole accepted
 * quotation is additionally captured as one JSON snapshot
 * (`sales_orders.source_snapshot`) — neither is ever re-synced from the
 * quotation afterward, so a later edit to the (already-terminal, Accepted)
 * quotation can never retroactively change job history. In practice the
 * quotation is immutable past Accepted anyway (no allowed transition
 * leads back to an editable state), but the snapshot makes that guarantee
 * explicit rather than incidental.
 *
 * Also copies the quotation's `job_type` onto the job and derives
 * `requires_handover` from it (false only for Goods) —
 * FINALIZED-DECISIONS.md §10. This was previously left at the column's
 * blanket default regardless of job type (see docs/REFACTOR_PLAN.md's
 * drift audit); every job now gets the value its own job type implies.
 */
class CreateSalesOrderFromQuotation
{
    public function __construct(private DocumentNumberGenerator $numberGenerator) {}

    public function create(Quotation $quotation): SalesOrder
    {
        if ($quotation->status !== QuotationStatus::Accepted) {
            throw new RuntimeException('A job can only be created from an accepted quotation.');
        }

        if ($quotation->salesOrder()->exists()) {
            throw new RuntimeException('This quotation already has a job.');
        }

        $quotation->loadMissing('items');

        return DB::transaction(function () use ($quotation) {
            $salesOrder = SalesOrder::create([
                'company_id' => $quotation->company_id,
                'client_id' => $quotation->client_id,
                'quotation_id' => $quotation->id,
                'number' => $this->numberGenerator->next($quotation->company, 'sales_order'),
                'status' => SalesOrderStatus::Draft,
                'approved_value' => $quotation->total,
                'job_type' => $quotation->job_type,
                'requires_handover' => $quotation->job_type->requiresHandover(),
                'source_snapshot' => [
                    'quotation_number' => $quotation->number,
                    'pricing_mode' => $quotation->pricing_mode->value,
                    'discount' => (string) $quotation->discount,
                    'discount_is_percentage' => $quotation->discount_is_percentage,
                    'subtotal' => (string) $quotation->subtotal,
                    'total' => (string) $quotation->total,
                    'customer_po_number' => $quotation->customer_po_number,
                    'customer_po_date' => $quotation->customer_po_date?->toDateString(),
                    'customer_po_is_system_generated' => $quotation->customer_po_is_system_generated,
                    'accepted_at' => $quotation->accepted_at?->toIso8601String(),
                    'items' => $quotation->items->map(fn ($item) => [
                        'product_id' => $item->product_id,
                        'title' => $item->title,
                        'description' => $item->description,
                        'quantity' => (string) $item->quantity,
                        'unit_cost' => (string) $item->unit_cost,
                        'line_total' => (string) $item->line_total,
                    ])->all(),
                ],
            ]);

            foreach ($quotation->items as $item) {
                $salesOrder->items()->create([
                    'quotation_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'discount' => $item->discount,
                    'discount_is_percentage' => $item->discount_is_percentage,
                    'line_total' => $item->line_total,
                    'sort_order' => $item->sort_order,
                ]);
            }

            return $salesOrder;
        });
    }
}
