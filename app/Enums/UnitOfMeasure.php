<?php

namespace App\Enums;

/**
 * A defined, fixed set of units of measure for a catalog item, and for
 * every document line item that carries one (Invoice, Quotation, Job/
 * Sales Order, Vendor Bill, Vendor Purchase Order — see each model's own
 * `unit` column). Deliberately a closed list, not free text: ratified
 * per an explicit request to make "unit" a defined field rather than
 * whatever string happened to get typed. Seeded from this app's own
 * real pre-existing product data (`pcs`, `hour`, `unit`, `meter`, `set`
 * were already in live use) plus a few more common to an IT/security-
 * infrastructure integrator's catalog (hardware pieces, cabling,
 * installation points, labor, licensing, recurring services).
 */
enum UnitOfMeasure: string
{
    case Piece = 'pcs';
    case Unit = 'unit';
    case Set = 'set';
    case Package = 'package';
    case Box = 'box';
    case Roll = 'roll';
    case Meter = 'meter';
    case Point = 'point';
    case License = 'license';
    case Hour = 'hour';
    case Day = 'day';
    case Month = 'month';
    case Year = 'year';

    public function getLabel(): string
    {
        return match ($this) {
            self::Piece => 'Piece (pcs)',
            self::Unit => 'Unit',
            self::Set => 'Set',
            self::Package => 'Package',
            self::Box => 'Box',
            self::Roll => 'Roll',
            self::Meter => 'Meter',
            self::Point => 'Point',
            self::License => 'License',
            self::Hour => 'Hour',
            self::Day => 'Day',
            self::Month => 'Month',
            self::Year => 'Year',
        };
    }

    /** Short form for a document line/PDF — "20 pcs", "8 hour", never the full label. */
    public function getAbbreviation(): string
    {
        return match ($this) {
            self::Piece => 'pcs',
            self::Unit => 'unit',
            self::Set => 'set',
            self::Package => 'pkg',
            self::Box => 'box',
            self::Roll => 'roll',
            self::Meter => 'm',
            self::Point => 'pt',
            self::License => 'lic',
            self::Hour => 'hour',
            self::Day => 'day',
            self::Month => 'mo',
            self::Year => 'yr',
        };
    }
}
