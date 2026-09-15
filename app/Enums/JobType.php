<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * "Job types at launch are goods, installation, and service. Goods jobs
 * close operationally after delivery; installation jobs require delivery
 * then handover; service jobs require Service Reports then handover." —
 * docs/rebuild/specs/FINALIZED-DECISIONS.md §10. Selected on the
 * Quotation (App\Filament\Resources\Quotations\Schemas\QuotationForm) and
 * copied onto the Job at creation
 * (App\Actions\Sales\CreateSalesOrderFromQuotation), which also derives
 * `SalesOrder::requires_handover` from it (false only for Goods).
 */
enum JobType: string implements HasLabel
{
    case Goods = 'goods';
    case Installation = 'installation';
    case Service = 'service';

    public function getLabel(): string
    {
        return match ($this) {
            self::Goods => 'Goods',
            self::Installation => 'Installation',
            self::Service => 'Service',
        };
    }

    public function requiresHandover(): bool
    {
        return $this !== self::Goods;
    }
}
