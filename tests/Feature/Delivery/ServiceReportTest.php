<?php

namespace Tests\Feature\Delivery;

use App\Actions\Delivery\ApproveServiceReport;
use App\Actions\Delivery\CancelServiceReport;
use App\Actions\Delivery\CompleteHandover;
use App\Actions\Delivery\RecordServiceReport;
use App\Actions\Delivery\SubmitServiceReport;
use App\Enums\JobType;
use App\Enums\ServiceReportResult;
use App\Enums\ServiceReportStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Service Report (`SVR`, change request ratified 2026-09-14,
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §10) — required tests:
 * "Draft -> Submitted -> Approved lifecycle, immutable once approved,
 * service-job handover gated on an approved Resolved report with no open
 * Follow-up-required report."
 */
class ServiceReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Acme', 'slug' => 'acme', 'code' => 'ACM', 'currency_code' => 'USD']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $this->company->users()->attach($user, ['role' => $role]);

        return $user;
    }

    private function makeJob(JobType $jobType): SalesOrder
    {
        $client = Client::create(['company_id' => $this->company->id, 'name' => 'Test Client']);

        $quotation = Quotation::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => 'ACM-QUO-'.random_int(100000, 999999),
            'job_type' => $jobType,
        ]);

        $salesOrder = SalesOrder::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'quotation_id' => $quotation->id,
            'number' => 'ACM-SO-'.random_int(100000, 999999),
            'approved_value' => 1000,
            'job_type' => $jobType,
            'requires_handover' => $jobType->requiresHandover(),
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'title' => 'Widget',
            'quantity' => 1,
            'unit_cost' => 1000,
            'line_total' => 1000,
        ]);

        return $salesOrder->fresh(['items']);
    }

    public function test_a_staff_actor_can_record_a_draft_service_report(): void
    {
        $job = $this->makeJob(JobType::Service);
        $staff = $this->userWithRole('staff');

        $report = app(RecordServiceReport::class)->record($job, $staff, [
            'problem_reported' => 'No power',
            'diagnosis' => 'Faulty fuse',
            'action_taken' => 'Replaced fuse',
            'result' => ServiceReportResult::Resolved->value,
        ]);

        $this->assertNotNull($report->number);
        $this->assertSame(ServiceReportStatus::Draft, $report->status);
        $this->assertSame($staff->id, $report->created_by_user_id);
    }

    public function test_an_auditor_cannot_record_a_service_report(): void
    {
        $job = $this->makeJob(JobType::Service);
        $auditor = $this->userWithRole('auditor');

        $this->expectException(RuntimeException::class);

        app(RecordServiceReport::class)->record($job, $auditor, ['result' => ServiceReportResult::Resolved->value]);
    }

    public function test_the_full_draft_to_approved_lifecycle_freezes_a_snapshot(): void
    {
        $job = $this->makeJob(JobType::Service);
        $staff = $this->userWithRole('staff');

        $report = app(RecordServiceReport::class)->record($job, $staff, [
            'problem_reported' => 'No power',
            'result' => ServiceReportResult::Resolved->value,
        ]);

        app(SubmitServiceReport::class)->submit($report, $staff);
        $approved = app(ApproveServiceReport::class)->approve($report->fresh(), $staff);

        $this->assertSame(ServiceReportStatus::Approved, $approved->status);
        $this->assertNotNull($approved->snapshot);
        $this->assertSame('No power', $approved->snapshot['problem_reported']);
        $this->assertSame($staff->id, $approved->approved_by_user_id);
    }

    public function test_a_report_cannot_be_approved_directly_from_draft(): void
    {
        $job = $this->makeJob(JobType::Service);
        $staff = $this->userWithRole('staff');

        $report = app(RecordServiceReport::class)->record($job, $staff, ['result' => ServiceReportResult::Resolved->value]);

        $this->expectException(RuntimeException::class);

        app(ApproveServiceReport::class)->approve($report, $staff);
    }

    public function test_a_draft_report_can_be_cancelled(): void
    {
        $job = $this->makeJob(JobType::Service);
        $staff = $this->userWithRole('staff');

        $report = app(RecordServiceReport::class)->record($job, $staff, ['result' => ServiceReportResult::Unresolved->value]);
        $cancelled = app(CancelServiceReport::class)->cancel($report, $staff, 'Duplicate entry');

        $this->assertSame(ServiceReportStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_an_approved_report_cannot_be_cancelled(): void
    {
        $job = $this->makeJob(JobType::Service);
        $staff = $this->userWithRole('staff');

        $report = app(RecordServiceReport::class)->record($job, $staff, ['result' => ServiceReportResult::Resolved->value]);
        app(SubmitServiceReport::class)->submit($report, $staff);
        $approved = app(ApproveServiceReport::class)->approve($report->fresh(), $staff);

        $this->expectException(RuntimeException::class);

        app(CancelServiceReport::class)->cancel($approved, $staff);
    }

    public function test_a_service_job_handover_is_blocked_without_any_approved_resolved_report(): void
    {
        $job = $this->makeJob(JobType::Service);
        $staff = $this->userWithRole('staff');

        // Recorded but never submitted/approved.
        app(RecordServiceReport::class)->record($job, $staff, ['result' => ServiceReportResult::Resolved->value]);

        $this->expectException(RuntimeException::class);

        app(CompleteHandover::class)->complete($job->fresh(), $staff);
    }

    public function test_a_service_job_handover_is_blocked_while_an_approved_follow_up_required_report_is_open(): void
    {
        $job = $this->makeJob(JobType::Service);
        $staff = $this->userWithRole('staff');

        $resolved = app(RecordServiceReport::class)->record($job, $staff, ['result' => ServiceReportResult::Resolved->value]);
        app(SubmitServiceReport::class)->submit($resolved, $staff);
        app(ApproveServiceReport::class)->approve($resolved->fresh(), $staff);

        $followUp = app(RecordServiceReport::class)->record($job->fresh(), $staff, ['result' => ServiceReportResult::FollowUpRequired->value]);
        app(SubmitServiceReport::class)->submit($followUp, $staff);
        app(ApproveServiceReport::class)->approve($followUp->fresh(), $staff);

        $this->expectException(RuntimeException::class);

        app(CompleteHandover::class)->complete($job->fresh(), $staff);
    }

    public function test_a_service_job_handover_succeeds_once_a_resolved_report_is_approved(): void
    {
        $job = $this->makeJob(JobType::Service);
        $staff = $this->userWithRole('staff');

        $report = app(RecordServiceReport::class)->record($job, $staff, ['result' => ServiceReportResult::Resolved->value]);
        app(SubmitServiceReport::class)->submit($report, $staff);
        app(ApproveServiceReport::class)->approve($report->fresh(), $staff);

        $handover = app(CompleteHandover::class)->complete($job->fresh(), $staff);

        $this->assertNotNull($handover->number);
        $this->assertFalse($handover->is_override);
    }

    public function test_an_installation_job_is_unaffected_by_service_report_state(): void
    {
        $job = $this->makeJob(JobType::Installation);
        $staff = $this->userWithRole('staff');

        // No service reports recorded at all — installation still gates on
        // delivery, not service reports, so this must fail on delivery,
        // not silently pass or fail on the wrong reason.
        $this->expectException(RuntimeException::class);

        app(CompleteHandover::class)->complete($job->fresh(), $staff);
    }
}
