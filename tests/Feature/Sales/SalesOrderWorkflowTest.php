<?php

namespace Tests\Feature\Sales;

use App\Actions\Sales\AcceptQuotation;
use App\Actions\Sales\ApproveSalesOrder;
use App\Actions\Sales\CreateSalesOrderFromQuotation;
use App\Actions\Sales\TransitionQuotationStatus;
use App\Actions\Sales\TransitionSalesOrderStatus;
use App\Enums\MilestoneType;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Required tests from docs/rebuild/specs/03-sales-and-job/Specs.md:
 * "Direct full-payment job", "Multiple custom milestones", "Milestone
 * approval rejects an over- or under-funded job", "Invalid job state
 * transitions denied".
 */
class SalesOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function acceptedQuotationJob(): SalesOrder
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'currency_code' => 'USD']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Test Client']);

        $quotation = Quotation::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'KA-QUO-2026090001',
            'status' => QuotationStatus::Draft,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'title' => 'Widget',
            'quantity' => 2,
            'unit_cost' => 500,
            'line_total' => 1000,
        ]);
        $quotation->forceFill(['subtotal' => 1000, 'total' => 1000])->save();

        app(TransitionQuotationStatus::class)->transition($quotation, QuotationStatus::Approved);
        app(TransitionQuotationStatus::class)->transition($quotation, QuotationStatus::Sent);
        app(AcceptQuotation::class)->accept($quotation->fresh());

        return app(CreateSalesOrderFromQuotation::class)->create($quotation->fresh());
    }

    public function test_job_created_from_accepted_quotation_preserves_the_source_snapshot(): void
    {
        $salesOrder = $this->acceptedQuotationJob();

        $this->assertSame('1000.00', $salesOrder->approved_value);
        $this->assertSame(1, $salesOrder->items()->count());
        $this->assertNotNull($salesOrder->source_snapshot);
        $this->assertSame('1000.00', $salesOrder->source_snapshot['total']);
    }

    public function test_direct_full_payment_job_is_approved_with_one_milestone_covering_the_full_value(): void
    {
        $salesOrder = $this->acceptedQuotationJob();

        $salesOrder->milestones()->create([
            'type' => MilestoneType::FullPayment,
            'description' => 'Full payment on completion',
            'amount' => $salesOrder->approved_value,
        ]);

        $approved = app(ApproveSalesOrder::class)->approve($salesOrder);

        $this->assertTrue($approved->status === SalesOrderStatus::Approved);
        $this->assertNotNull($approved->approved_at);
    }

    public function test_multiple_custom_milestones_summing_to_the_job_value_are_approved(): void
    {
        $salesOrder = $this->acceptedQuotationJob();

        $salesOrder->milestones()->create(['type' => MilestoneType::Custom, 'description' => 'Down payment', 'amount' => 400]);
        $salesOrder->milestones()->create(['type' => MilestoneType::Custom, 'description' => 'Progress', 'amount' => 300]);
        $salesOrder->milestones()->create(['type' => MilestoneType::Custom, 'description' => 'Final', 'amount' => 300]);

        $approved = app(ApproveSalesOrder::class)->approve($salesOrder);

        $this->assertTrue($approved->status === SalesOrderStatus::Approved);
        $this->assertSame(3, $approved->milestones()->count());
    }

    public function test_milestone_approval_rejects_an_under_funded_job(): void
    {
        $salesOrder = $this->acceptedQuotationJob();

        $salesOrder->milestones()->create(['type' => MilestoneType::Custom, 'description' => 'Down payment', 'amount' => 400]);

        $this->expectException(RuntimeException::class);

        app(ApproveSalesOrder::class)->approve($salesOrder);
    }

    public function test_milestone_approval_rejects_an_over_funded_job(): void
    {
        $salesOrder = $this->acceptedQuotationJob();

        $salesOrder->milestones()->create(['type' => MilestoneType::Custom, 'description' => 'Down payment', 'amount' => 1200]);

        $this->expectException(RuntimeException::class);

        app(ApproveSalesOrder::class)->approve($salesOrder);
    }

    public function test_invalid_job_state_transition_is_denied(): void
    {
        $salesOrder = $this->acceptedQuotationJob();

        $this->expectException(RuntimeException::class);

        // Draft cannot jump straight to Procurement — it must be Approved first.
        app(TransitionSalesOrderStatus::class)->transition($salesOrder, SalesOrderStatus::Procurement);
    }

    public function test_job_can_be_cancelled_from_a_non_terminal_state(): void
    {
        $salesOrder = $this->acceptedQuotationJob();

        $cancelled = app(TransitionSalesOrderStatus::class)->transition($salesOrder, SalesOrderStatus::Cancelled);

        $this->assertTrue($cancelled->status === SalesOrderStatus::Cancelled);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_job_state_transition_is_denied_once_cancelled(): void
    {
        $salesOrder = $this->acceptedQuotationJob();
        app(TransitionSalesOrderStatus::class)->transition($salesOrder, SalesOrderStatus::Cancelled);

        $this->expectException(RuntimeException::class);

        app(TransitionSalesOrderStatus::class)->transition($salesOrder->fresh(), SalesOrderStatus::Procurement);
    }
}
