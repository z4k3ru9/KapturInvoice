<?php

namespace App\Enums;

/**
 * "Result (Resolved, Partially resolved, Follow-up required,
 * Unresolved)." — docs/rebuild/specs/FINALIZED-DECISIONS.md §10. Only
 * `Resolved` (with no open `FollowUpRequired` report) satisfies a service
 * job's handover gate — see App\Models\SalesOrder::
 * isServiceReportsResolvedForHandover().
 */
enum ServiceReportResult: string
{
    case Resolved = 'resolved';
    case PartiallyResolved = 'partially_resolved';
    case FollowUpRequired = 'follow_up_required';
    case Unresolved = 'unresolved';

    public function getLabel(): string
    {
        return match ($this) {
            self::Resolved => 'Resolved',
            self::PartiallyResolved => 'Partially resolved',
            self::FollowUpRequired => 'Follow-up required',
            self::Unresolved => 'Unresolved',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Resolved => 'success',
            self::PartiallyResolved => 'warning',
            self::FollowUpRequired => 'danger',
            self::Unresolved => 'danger',
        };
    }
}
