<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * "Support overrun, out-of-scope work, and item substitutions only
 * through Owner/Admin approval with reason" — Specs.md
 * (docs/rebuild/specs/03-sales-and-job/Specs.md).
 */
enum JobVariationType: string implements HasLabel
{
    case Overrun = 'overrun';
    case OutOfScope = 'out_of_scope';
    case Substitution = 'substitution';

    public function getLabel(): string
    {
        return match ($this) {
            self::Overrun => 'Overrun',
            self::OutOfScope => 'Out-of-scope work',
            self::Substitution => 'Item substitution',
        };
    }
}
