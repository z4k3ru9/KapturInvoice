<?php

namespace App\Actions\Procurement;

use App\Models\JobCostAllocation;
use App\Models\SalesOrder;
use App\Models\VendorBillItem;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * "One vendor purchase may serve multiple jobs. Allocate shared cost by
 * explicit quantity or amount. Prevent allocation above source line
 * amount. Show unallocated remainder and exclude it from confidently
 * allocated margin." —
 * docs/rebuild/specs/05-procurement-and-delivery/Specs.md,
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §3 ("Use gross vendor cost for
 * job margin while preserving net/tax/gross components"). Calling this
 * more than once against the SAME VendorBillItem for DIFFERENT SalesOrders
 * is the normal "shared purchase split across two jobs" case, not an
 * error — only the running total across all of an item's allocations is
 * bounded by its `line_total`. No role gate: Specs.md doesn't restrict
 * who may allocate cost.
 */
class AllocateJobCost
{
    public function allocate(
        VendorBillItem $item,
        SalesOrder $salesOrder,
        float $amount,
        ?float $quantity = null,
        string $method = 'amount',
    ): JobCostAllocation {
        if ($amount <= 0) {
            throw new RuntimeException('Job cost allocation amount must be greater than zero.');
        }

        $unallocated = $item->unallocatedAmount();

        if ($amount > $unallocated + 0.01) {
            throw new RuntimeException(
                "Allocation of {$amount} exceeds the source line's remaining unallocated amount of {$unallocated}."
            );
        }

        return JobCostAllocation::create([
            'vendor_bill_item_id' => $item->id,
            'sales_order_id' => $salesOrder->id,
            'method' => $method,
            'quantity' => $quantity,
            'amount' => $amount,
            'created_by_user_id' => Auth::id(),
        ]);
    }
}
