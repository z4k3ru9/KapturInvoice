<?php

namespace App\Enums;

/**
 * Phase 07 (docs/rebuild/specs/07-migration-and-cutover/Specs.md):
 * "Support restart from last completed entity checkpoint." A batch stays
 * `Running` while its import command executes; a thrown exception marks
 * it `Failed` with `last_completed_step` pointing at the last entity type
 * that finished, so `--resume` knows where to pick up.
 */
enum MigrationBatchStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Running => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }
}
