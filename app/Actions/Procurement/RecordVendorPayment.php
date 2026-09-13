<?php

namespace App\Actions\Procurement;

use App\Enums\VendorBillStatus;
use App\Models\VendorBill;
use App\Models\VendorPayment;
use App\Services\AuditLogger;
use App\Services\DocumentNumberGenerator;
use App\Services\Procurement\RecalculateVendorBillPayments;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "Vendor bills support partial vendor payments and evidence." "A vendor
 * bill can exceed its approved Vendor PO only as a recorded variance.
 * Payment above the PO total is blocked until Admin or Owner approves
 * the variance with a reason; the original PO remains immutable." —
 * docs/rebuild/specs/05-procurement-and-delivery/Specs.md,
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §4. Unlike a customer
 * Payment (Phase 04), a vendor payment carries no verification step —
 * it counts toward the bill's paid amount as soon as it is recorded, via
 * App\Services\Procurement\RecalculateVendorBillPayments. The PO ceiling
 * check considers every payment across every bill raised against the
 * same Vendor PO, not just this bill, since one PO may be billed in
 * parts across multiple VendorBill rows.
 */
class RecordVendorPayment
{
    public function __construct(
        private AuditLogger $auditLogger,
        private RecalculateVendorBillPayments $recalculator,
        private DocumentNumberGenerator $numberGenerator,
    ) {}

    /**
     * @param  array{
     *     amount: float|string,
     *     payment_date: \DateTimeInterface|string,
     *     method?: string|null,
     *     reference?: string|null,
     *     proof_path?: string|null,
     *     notes?: string|null,
     * }  $data
     */
    public function record(VendorBill $bill, array $data): VendorPayment
    {
        if (! in_array($bill->status, [VendorBillStatus::Approved, VendorBillStatus::PartiallyPaid], true)) {
            throw new RuntimeException("A vendor bill in status [{$bill->status->value}] cannot be paid.");
        }

        $po = $bill->vendorPurchaseOrder;
        $ceiling = $po->paymentCeiling();
        $amount = round((float) $data['amount'], 2);

        $existingPayments = round((float) VendorPayment::query()
            ->whereIn('vendor_bill_id', $po->bills()->pluck('id'))
            ->sum('amount'), 2);

        if (($existingPayments + $amount) > ($ceiling + 0.01)) {
            throw new RuntimeException(
                "This payment would exceed the vendor PO's approved payment ceiling of {$ceiling}. ".
                'An Owner or Admin must approve a VendorPoVariance before payment can proceed.'
            );
        }

        $payment = DB::transaction(function () use ($bill, $data, $amount) {
            $payment = VendorPayment::create([
                'company_id' => $bill->company_id,
                'vendor_bill_id' => $bill->id,
                'number' => $this->numberGenerator->next($bill->company, 'vendor_payment'),
                'amount' => $amount,
                'payment_date' => $data['payment_date'],
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'proof_path' => $data['proof_path'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->recalculator->recalculate($bill);

            return $payment;
        });

        $this->auditLogger->record(
            $bill->company,
            'vendor_bill.payment_recorded',
            $bill,
            ['amount_paid' => $bill->amount_paid],
            ['amount_paid' => $bill->fresh()->amount_paid],
        );

        return $payment;
    }
}
