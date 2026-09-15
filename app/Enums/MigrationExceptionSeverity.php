<?php

namespace App\Enums;

/**
 * "Quarantine uncertain mappings and missing financial data" —
 * docs/rebuild/specs/07-migration-and-cutover/Specs.md. `High` blocks
 * business reconciliation sign-off; `Medium`/`Low` are recorded for
 * Owner review but don't by themselves block a trial import.
 */
enum MigrationExceptionSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'warning',
            self::High => 'danger',
        };
    }
}
