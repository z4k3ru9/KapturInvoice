<?php

namespace App\Enums;

/**
 * "Support direct full payment or custom milestones" — Specs.md. A
 * FullPayment milestone is exactly one milestone covering the whole
 * `approved_value`; Custom is any user-defined amount/percentage/
 * description/due-date combination (there may be several per job).
 */
enum MilestoneType: string
{
    case FullPayment = 'full_payment';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::FullPayment => 'Direct full payment',
            self::Custom => 'Custom milestone',
        };
    }
}
