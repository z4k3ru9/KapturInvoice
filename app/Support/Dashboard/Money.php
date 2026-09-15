<?php

namespace App\Support\Dashboard;

use Illuminate\Support\Number;

/**
 * Dashboard-widget currency formatting — see
 * docs/rebuild/outputs/ui-rebuild/18-stitch-ui-gap-analysis/01-shell-dashboard.md D17.
 * `Number::currency()` (the `intl` extension is present in every
 * environment this ships to) gives locale-aware thousands separators;
 * whole-Rupiah precision matches the approved upward-rounding rule for
 * final totals (docs/rebuild/specs/FINALIZED-DECISIONS.md) rather than
 * showing meaningless sub-Rupiah cents on a summary tile.
 */
class Money
{
    public static function format(float|int $amount, ?string $currencyCode): string
    {
        $currency = $currencyCode ?: 'IDR';

        return Number::currency((float) $amount, in: $currency, locale: 'id', precision: 0);
    }
}
