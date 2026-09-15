<?php

namespace App\Support\TallStack;

/**
 * Every status enum's getColor() returns a Filament semantic color name
 * (gray/info/success/warning/danger) — correct for Filament's own Badge
 * column, but TallStackUI's <x-badge>/<x-stats> color prop expects a
 * literal Tailwind palette name (gray/blue/green/amber/red/...) and
 * silently falls back to an unstyled black-outline badge for any name it
 * doesn't recognize. This maps one to the other so every TALL-stack page
 * can pass an enum's own getColor() straight through instead of
 * duplicating a color decision per status per page.
 */
class StatusColor
{
    private const MAP = [
        'gray' => 'gray',
        'info' => 'blue',
        'success' => 'green',
        'warning' => 'amber',
        'danger' => 'red',
        'primary' => 'primary',
        'secondary' => 'secondary',
    ];

    public static function map(string $filamentColor): string
    {
        return self::MAP[$filamentColor] ?? 'gray';
    }
}
