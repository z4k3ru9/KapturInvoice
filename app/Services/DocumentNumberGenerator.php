<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Assigns the next document number for a company and atomically advances
 * its counter — closes the gap flagged in
 * docs/filament-admin-layout-design.md §2.1/§3.2: `EditNumberingSettings`
 * edited `invoice_prefix`/`invoice_next_number` etc., but nothing read
 * them when a document was actually created.
 *
 * Wrapped in a DB transaction with `lockForUpdate()` so two concurrent
 * creates can't race the same counter value (on SQLite, used in dev/test,
 * `lockForUpdate()` is a no-op — SQLite serializes writers at the file
 * level regardless; MySQL/Postgres get a real row lock).
 */
class DocumentNumberGenerator
{
    private const SEQUENCES = [
        'invoice' => ['invoice_prefix', 'invoice_next_number'],
        'quote' => ['quote_prefix', 'quote_next_number'],
        'credit' => ['credit_prefix', 'credit_next_number'],
    ];

    public function next(Company $company, string $sequence): string
    {
        if (! array_key_exists($sequence, self::SEQUENCES)) {
            throw new InvalidArgumentException("Unknown numbering sequence [{$sequence}].");
        }

        [$prefixColumn, $counterColumn] = self::SEQUENCES[$sequence];

        return DB::transaction(function () use ($company, $prefixColumn, $counterColumn) {
            /** @var Company $locked */
            $locked = Company::query()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();

            $number = ((string) $locked->{$prefixColumn}).str_pad(
                (string) $locked->{$counterColumn},
                4,
                '0',
                STR_PAD_LEFT
            );

            $locked->increment($counterColumn);

            return $number;
        });
    }
}
