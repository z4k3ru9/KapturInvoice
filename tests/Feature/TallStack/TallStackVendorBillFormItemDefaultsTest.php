<?php

namespace Tests\Feature\TallStack;

use App\Enums\PaymentMethod;
use App\Enums\VendorBillStatus;
use App\Livewire\TallStackVendorBillForm;
use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use App\Models\VendorPurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Repair plan Phase 6 / decision gate G1: the Vendor Bill line-item modal's
 * Net amount field defaulted to 0 with no derivation from quantity x unit
 * cost, so an unedited line silently totaled Rp 0. Net amount now smart-
 * defaults from `quantity * unit_cost` (the same "lineGross" convention
 * App\Services\Procurement\VendorPurchaseOrderTotalsCalculator/
 * InvoiceTotalsCalculator/QuotationTotalsCalculator already use) as a
 * reactive default — recomputed whenever quantity/unit cost/product change
 * — but only until the user edits Net amount directly, since the vendor's
 * actual bill is the source of truth and may legitimately differ from the
 * naive product. Also covers the sibling fix: the payment Method field is a
 * real App\Enums\PaymentMethod select now, not a free-text input echoing
 * "bank_transfer, cheque, or manual." as a hint.
 */
class TallStackVendorBillFormItemDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Vendor $vendor;

    private VendorBill $bill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);
        $this->vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Vendor Co']);
        $this->actingAs($this->user);

        $po = VendorPurchaseOrder::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'number' => 'PO-'.uniqid(),
        ]);
        // A blank PO has a Rp 0 payment ceiling — give it real headroom so
        // the payment-recording tests below aren't rejected by
        // App\Actions\Procurement\RecordVendorPayment's own ceiling guard,
        // which is unrelated to what this test file covers.
        $po->forceFill(['total' => 5000000])->save();

        $this->bill = VendorBill::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'vendor_purchase_order_id' => $po->id,
            'number' => 'VBL-'.uniqid(),
        ])->fresh();
    }

    public function test_net_amount_defaults_from_quantity_times_unit_cost_reactively(): void
    {
        Livewire::test(TallStackVendorBillForm::class, [
            'company' => $this->company,
            'vendorBill' => $this->bill,
        ])
            ->call('addItem')
            ->set('item_quantity', 1)
            ->set('item_unit_cost', 1200000)
            ->assertSet('item_net_amount', 1200000.0)
            ->set('item_quantity', 2)
            ->assertSet('item_net_amount', 2400000.0);
    }

    public function test_net_amount_stays_editable_and_is_not_overwritten_once_touched(): void
    {
        Livewire::test(TallStackVendorBillForm::class, [
            'company' => $this->company,
            'vendorBill' => $this->bill,
        ])
            ->call('addItem')
            ->set('item_quantity', 1)
            ->set('item_unit_cost', 1200000)
            ->assertSet('item_net_amount', 1200000.0)
            // The vendor's actual bill differs from the naive product —
            // once the user types their own figure, it must stick.
            ->set('item_net_amount', 999999)
            ->set('item_quantity', 5)
            ->assertSet('item_net_amount', 999999.0);
    }

    public function test_saving_an_item_with_only_quantity_and_unit_cost_filled_does_not_total_zero(): void
    {
        Livewire::test(TallStackVendorBillForm::class, [
            'company' => $this->company,
            'vendorBill' => $this->bill,
        ])
            ->call('addItem')
            ->set('item_title', 'Router')
            ->set('item_quantity', 1)
            ->set('item_unit_cost', 1200000)
            ->call('saveItem');

        $item = $this->bill->items()->sole();

        $this->assertSame(1200000.0, (float) $item->net_amount);
        $this->assertSame(1200000.0, (float) $item->line_total);
    }

    public function test_editing_an_existing_item_does_not_silently_overwrite_its_stored_net_amount(): void
    {
        $item = VendorBillItem::create([
            'vendor_bill_id' => $this->bill->id,
            'title' => 'Custom install',
            'quantity' => 1,
            'unit_cost' => 1000000,
            // The vendor billed a different net amount than qty x unit cost
            // (e.g. a bundled discount already baked into their invoice).
            'net_amount' => 850000,
            'tax_amount' => 0,
            'line_total' => 850000,
        ]);

        Livewire::test(TallStackVendorBillForm::class, [
            'company' => $this->company,
            'vendorBill' => $this->bill,
        ])
            ->call('editItem', $item->id)
            ->assertSet('item_net_amount', 850000.0)
            ->set('item_quantity', 2)
            ->assertSet('item_net_amount', 850000.0);
    }

    public function test_payment_method_field_only_accepts_a_real_payment_method_enum_value(): void
    {
        Livewire::test(TallStackVendorBillForm::class, [
            'company' => $this->company,
            'vendorBill' => $this->bill->fresh(['items']),
        ])
            ->call('openPaymentModal')
            ->set('payment_amount', 10)
            ->set('payment_method', 'not-a-real-method')
            ->call('recordPayment')
            ->assertHasErrors('payment_method');
    }

    public function test_recorded_payment_method_is_humanized_in_the_payments_table(): void
    {
        $this->bill->update(['status' => VendorBillStatus::Approved]);

        Livewire::test(TallStackVendorBillForm::class, [
            'company' => $this->company,
            'vendorBill' => $this->bill->fresh(['items']),
        ])
            ->call('openPaymentModal')
            ->set('payment_amount', 10)
            ->set('payment_method', PaymentMethod::BankTransfer->value)
            ->call('recordPayment')
            ->assertHasNoErrors();

        $payment = $this->bill->fresh(['payments'])->payments->sole();

        $this->assertSame(PaymentMethod::BankTransfer->value, $payment->method);

        $component = Livewire::test(TallStackVendorBillForm::class, [
            'company' => $this->company,
            'vendorBill' => $this->bill->fresh(['items', 'payments']),
        ]);

        $row = collect($component->viewData('payments'))->firstWhere('id', $payment->id);

        $this->assertSame('Bank transfer', $row['method']);
    }
}
