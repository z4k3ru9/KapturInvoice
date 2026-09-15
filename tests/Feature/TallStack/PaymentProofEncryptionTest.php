<?php

namespace Tests\Feature\TallStack;

use App\Actions\Procurement\ApproveVendorBill;
use App\Actions\Procurement\ApproveVendorPurchaseOrder;
use App\Actions\Procurement\SubmitVendorBill;
use App\Livewire\TallStackPayments;
use App\Livewire\TallStackVendorBillForm;
use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use App\Models\VendorPayment;
use App\Models\VendorPurchaseOrder;
use App\Models\VendorPurchaseOrderItem;
use App\Support\Files\EncryptedFileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 15 file-encryption slice (repair plan G4 — see memory.md and
 * dreamy-fluttering-willow.md's decision gate). Covers:
 *
 * - Both upload sites (TallStackPayments::recordPayment(),
 *   TallStackVendorBillForm::recordPayment()) now store the proof file's
 *   content through App\Support\Files\EncryptedFileStorage rather than
 *   plain UploadedFile::store() — the bytes on disk must differ from the
 *   original upload, and the original bytes must come back byte-for-byte
 *   through the new authenticated download route.
 * - App\Http\Controllers\PaymentProofController /
 *   VendorPaymentProofController enforce the same "outside the panel,
 *   explicit canAccessTenant() check" tenant boundary every other
 *   download controller in this app already uses (InvoicePdfController,
 *   DocumentDownloadController) — a user outside the owning company gets
 *   403, a payment with no proof gets 404.
 * - EncryptedFileStorage::get() falls back to the raw stored bytes for a
 *   file that predates the encryption rollout (a real DecryptException),
 *   so an already-uploaded proof from before this feature shipped isn't
 *   silently broken.
 */
class PaymentProofEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Client $client;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $this->company->users()->attach($this->user, ['role' => 'owner']);
        $this->client = Client::create(['company_id' => $this->company->id, 'name' => 'Client Co']);
        $this->vendor = Vendor::create(['company_id' => $this->company->id, 'name' => 'Vendor Co']);

        $this->actingAs($this->user);
    }

    private function makeApprovedBill(float $total): VendorBill
    {
        $po = VendorPurchaseOrder::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'number' => 'ACM-VPO-'.random_int(100000, 999999),
        ]);
        VendorPurchaseOrderItem::create([
            'vendor_purchase_order_id' => $po->id,
            'title' => 'Cabling', 'quantity' => 1, 'unit_cost' => $total, 'line_total' => $total,
        ]);
        $po->forceFill(['total' => $total])->save();
        app(ApproveVendorPurchaseOrder::class)->approve($po->fresh());

        $bill = VendorBill::create([
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'vendor_purchase_order_id' => $po->id,
            'number' => 'ACM-VBL-'.random_int(100000, 999999),
        ]);
        VendorBillItem::create([
            'vendor_bill_id' => $bill->id,
            'title' => 'Cabling', 'quantity' => 1, 'unit_cost' => $total,
            'net_amount' => $total, 'tax_amount' => 0, 'line_total' => $total,
        ]);
        $bill->forceFill(['total' => $total])->save();
        app(SubmitVendorBill::class)->submit($bill->fresh());
        app(ApproveVendorBill::class)->approve($bill->fresh(), $this->user);

        return $bill->fresh();
    }

    // --- Customer payment (TallStackPayments) -------------------------------

    public function test_recording_a_customer_payment_stores_the_proof_encrypted_and_it_round_trips(): void
    {
        Storage::fake('local');
        $original = "%PDF-1.4 fake customer proof bytes\n".bin2hex(random_bytes(64));

        Livewire::test(TallStackPayments::class, ['company' => $this->company])
            ->set('record_client_id', (string) $this->client->id)
            ->set('record_method', 'bank_transfer')
            ->set('record_amount', 250)
            ->set('record_payment_date', now()->toDateString())
            ->set('record_proof', UploadedFile::fake()->createWithContent('proof.pdf', $original))
            ->call('recordPayment')
            ->assertHasNoErrors();

        $payment = Payment::where('company_id', $this->company->id)->firstOrFail();

        $this->assertNotNull($payment->proof_path);
        $this->assertStringEndsWith('.enc', $payment->proof_path);

        // The bytes on disk must NOT be the plaintext upload — that's the
        // actual "encrypted at rest" guarantee this slice exists for.
        $onDisk = Storage::disk('local')->get($payment->proof_path);
        $this->assertNotSame($original, $onDisk);
        $this->assertStringNotContainsString('fake customer proof bytes', $onDisk);

        // But it must decrypt back to the exact original bytes.
        $this->assertSame($original, app(EncryptedFileStorage::class)->get($payment->proof_path));

        $response = $this->actingAs($this->user)->get(route('payments.proof', $payment));
        $response->assertOk();
        $this->assertSame($original, $response->getContent());
    }

    public function test_payment_proof_route_is_forbidden_for_a_user_outside_the_owning_company(): void
    {
        Storage::fake('local');

        $payment = Payment::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'amount' => 100,
            'status' => 'pending',
            'proof_path' => app(EncryptedFileStorage::class)->store(
                UploadedFile::fake()->createWithContent('proof.pdf', 'secret bytes'),
                'payment-proofs'
            ),
        ]);

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('payments.proof', $payment))
            ->assertForbidden();
    }

    public function test_payment_proof_route_404s_when_the_payment_has_no_proof(): void
    {
        $payment = Payment::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'amount' => 100,
            'status' => 'pending',
        ]);

        $this->actingAs($this->user)
            ->get(route('payments.proof', $payment))
            ->assertNotFound();
    }

    // --- Vendor payment (TallStackVendorBillForm) ----------------------------

    public function test_recording_a_vendor_payment_stores_the_proof_encrypted_and_it_round_trips(): void
    {
        Storage::fake('local');
        $bill = $this->makeApprovedBill(1000);
        $original = "%PDF-1.4 fake vendor proof bytes\n".bin2hex(random_bytes(64));

        Livewire::test(TallStackVendorBillForm::class, ['company' => $this->company, 'vendorBill' => $bill])
            ->set('payment_amount', 1000)
            ->set('payment_date', now()->toDateString())
            ->set('payment_proof', UploadedFile::fake()->createWithContent('proof.pdf', $original))
            ->call('recordPayment')
            ->assertHasNoErrors();

        $vendorPayment = VendorPayment::where('vendor_bill_id', $bill->id)->firstOrFail();

        $this->assertNotNull($vendorPayment->proof_path);
        $this->assertStringEndsWith('.enc', $vendorPayment->proof_path);

        $onDisk = Storage::disk('local')->get($vendorPayment->proof_path);
        $this->assertNotSame($original, $onDisk);
        $this->assertStringNotContainsString('fake vendor proof bytes', $onDisk);

        $this->assertSame($original, app(EncryptedFileStorage::class)->get($vendorPayment->proof_path));

        $response = $this->actingAs($this->user)->get(route('vendor-payments.proof', $vendorPayment));
        $response->assertOk();
        $this->assertSame($original, $response->getContent());
    }

    public function test_vendor_payment_proof_route_is_forbidden_for_a_user_outside_the_owning_company(): void
    {
        Storage::fake('local');
        $bill = $this->makeApprovedBill(1000);

        $vendorPayment = VendorPayment::create([
            'company_id' => $this->company->id,
            'vendor_bill_id' => $bill->id,
            'status' => 'pending',
            'amount' => 500,
            'payment_date' => now(),
            'proof_path' => app(EncryptedFileStorage::class)->store(
                UploadedFile::fake()->createWithContent('proof.pdf', 'secret vendor bytes'),
                'vendor-payment-proofs'
            ),
        ]);

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('vendor-payments.proof', $vendorPayment))
            ->assertForbidden();
    }

    public function test_vendor_payment_proof_route_404s_when_there_is_no_proof(): void
    {
        $bill = $this->makeApprovedBill(1000);

        $vendorPayment = VendorPayment::create([
            'company_id' => $this->company->id,
            'vendor_bill_id' => $bill->id,
            'status' => 'pending',
            'amount' => 500,
            'payment_date' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('vendor-payments.proof', $vendorPayment))
            ->assertNotFound();
    }

    // --- Legacy/pre-encryption fallback --------------------------------------

    public function test_a_pre_encryption_legacy_file_is_served_as_plain_bytes_instead_of_erroring(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payment-proofs/legacy.pdf', 'plain legacy bytes, never encrypted');

        $this->assertSame(
            'plain legacy bytes, never encrypted',
            app(EncryptedFileStorage::class)->get('payment-proofs/legacy.pdf')
        );
    }
}
