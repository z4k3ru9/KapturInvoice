<?php

namespace Tests\Feature\Sales;

use App\Actions\Sales\AcceptQuotation;
use App\Actions\Sales\ApproveJobVariation;
use App\Actions\Sales\CreateSalesOrderFromQuotation;
use App\Actions\Sales\TransitionQuotationStatus;
use App\Enums\JobVariationType;
use App\Enums\QuotationStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Required tests from docs/rebuild/specs/03-sales-and-job/Specs.md:
 * "Unauthorized overrun approval denied", "Original quotation unchanged
 * after approved variation" — plus FINALIZED-DECISIONS.md §4's
 * "Preserve original approved values and variation history."
 */
class JobVariationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Quotation $quotation;

    private SalesOrder $salesOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $this->quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => 'KA-QUO-2026090001',
            'status' => QuotationStatus::Draft,
        ]);

        QuotationItem::create([
            'quotation_id' => $this->quotation->id,
            'title' => 'Widget',
            'quantity' => 2,
            'unit_cost' => 500,
            'line_total' => 1000,
        ]);
        $this->quotation->forceFill(['subtotal' => 1000, 'total' => 1000])->save();

        app(TransitionQuotationStatus::class)->transition($this->quotation, QuotationStatus::Approved);
        app(TransitionQuotationStatus::class)->transition($this->quotation, QuotationStatus::Sent);
        app(AcceptQuotation::class)->accept($this->quotation->fresh());

        $this->salesOrder = app(CreateSalesOrderFromQuotation::class)->create($this->quotation->fresh());
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role]);

        return $user;
    }

    public function test_owner_can_approve_an_overrun_variation(): void
    {
        $owner = $this->userWithRole('owner');

        $variation = app(ApproveJobVariation::class)->approve(
            $this->salesOrder,
            $owner,
            JobVariationType::Overrun,
            'Extra cabling required on-site',
            250.00,
        );

        $this->assertSame('1250.00', $this->salesOrder->fresh()->approved_value);
        $this->assertSame('1000.00', $variation->value_before);
        $this->assertSame('1250.00', $variation->value_after);
        $this->assertTrue($variation->approvedBy->is($owner));
    }

    public function test_unauthorized_overrun_approval_is_denied_for_staff(): void
    {
        $staff = $this->userWithRole('staff');

        $this->expectException(RuntimeException::class);

        app(ApproveJobVariation::class)->approve(
            $this->salesOrder,
            $staff,
            JobVariationType::Overrun,
            'Extra cabling required on-site',
            250.00,
        );
    }

    public function test_unauthorized_overrun_approval_is_denied_for_sales(): void
    {
        $sales = $this->userWithRole('sales');

        $this->expectException(RuntimeException::class);

        app(ApproveJobVariation::class)->approve(
            $this->salesOrder,
            $sales,
            JobVariationType::Overrun,
            'Extra cabling required on-site',
            250.00,
        );
    }

    public function test_original_quotation_is_unchanged_after_an_approved_variation(): void
    {
        $admin = $this->userWithRole('admin');

        app(ApproveJobVariation::class)->approve(
            $this->salesOrder,
            $admin,
            JobVariationType::Substitution,
            'Swapped for an equivalent in-stock model',
            -75.00,
        );

        $quotation = $this->quotation->fresh();

        // The accepted quotation's own total is frozen — only the job's
        // approved_value (and the append-only JobVariation history) moves.
        $this->assertSame('1000.00', $quotation->total);
        $this->assertSame('925.00', $this->salesOrder->fresh()->approved_value);
    }

    public function test_variation_history_accumulates_rather_than_overwriting(): void
    {
        $owner = $this->userWithRole('owner');

        app(ApproveJobVariation::class)->approve($this->salesOrder, $owner, JobVariationType::Overrun, 'First overrun', 100.00);
        app(ApproveJobVariation::class)->approve($this->salesOrder->fresh(), $owner, JobVariationType::OutOfScope, 'Extra out-of-scope work', 50.00);

        $this->assertSame(2, $this->salesOrder->variations()->count());
        $this->assertSame('1150.00', $this->salesOrder->fresh()->approved_value);
    }
}
