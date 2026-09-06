<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Global reference data — not tenant-scoped. Seeded via CurrencySeeder. */
class Currency extends Model
{
    protected function casts(): array
    {
        return [
            'precision' => 'integer',
        ];
    }
}
