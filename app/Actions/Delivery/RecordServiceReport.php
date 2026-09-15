<?php

namespace App\Actions\Delivery;

use App\Enums\CompanyRole;
use App\Enums\ServiceReportResult;
use App\Enums\ServiceReportStatus;
use App\Models\SalesOrder;
use App\Models\ServiceReport;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentNumberGenerator;
use RuntimeException;

/**
 * The `Draft` creation step of the Service Report lifecycle
 * (docs/rebuild/specs/FINALIZED-DECISIONS.md §10) — "Staff and higher may
 * submit/approve" implies the same tier records one in the first place
 * (CompanyRole::deliveryAndHandoverRoles(), the same role check
 * App\Actions\Delivery\CompleteDelivery/CompleteHandover already use).
 * Assigns the `SVR` number immediately at creation, mirroring VendorBill's
 * own numbering-at-create convention rather than deferring it to Submit.
 */
class RecordServiceReport
{
    public function __construct(
        private DocumentNumberGenerator $numberGenerator,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{
     *     service_date?: ?string,
     *     technician_user_id?: ?int,
     *     external_technician_name?: ?string,
     *     problem_reported?: ?string,
     *     diagnosis?: ?string,
     *     action_taken?: ?string,
     *     parts_used?: ?string,
     *     result?: ?string,
     *     follow_up_notes?: ?string,
     *     customer_acknowledgement_name?: ?string,
     * }  $data
     */
    public function record(SalesOrder $salesOrder, User $actor, array $data): ServiceReport
    {
        if (! $actor->hasCompanyRole($salesOrder->company, ...CompanyRole::deliveryAndHandoverRoles())) {
            throw new RuntimeException('Only Staff and higher may record a service report.');
        }

        $number = $this->numberGenerator->next($salesOrder->company, 'service_report');

        $serviceReport = ServiceReport::create([
            'company_id' => $salesOrder->company_id,
            'sales_order_id' => $salesOrder->id,
            'number' => $number,
            'status' => ServiceReportStatus::Draft,
            'service_date' => $data['service_date'] ?? now()->toDateString(),
            'technician_user_id' => $data['technician_user_id'] ?? null,
            'external_technician_name' => $data['external_technician_name'] ?? null,
            'problem_reported' => $data['problem_reported'] ?? null,
            'diagnosis' => $data['diagnosis'] ?? null,
            'action_taken' => $data['action_taken'] ?? null,
            'parts_used' => $data['parts_used'] ?? null,
            'result' => $data['result'] ?? ServiceReportResult::Unresolved->value,
            'follow_up_notes' => $data['follow_up_notes'] ?? null,
            'customer_acknowledgement_name' => $data['customer_acknowledgement_name'] ?? null,
            'created_by_user_id' => $actor->id,
        ]);

        $this->auditLogger->record(
            $salesOrder->company,
            'service_report.recorded',
            $serviceReport,
            [],
            ['number' => $number],
        );

        return $serviceReport;
    }
}
