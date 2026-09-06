<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * A starter set of currencies — global reference data, not tenant-scoped.
 * Extend this list (or import the fuller legacy `currencies` table via the
 * import scripts) as needed; see docs/invoiceninja-v4-schema-reference.md §2.9.
 */
class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'precision' => 2],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'precision' => 2],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'precision' => 2],
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'precision' => 0],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'precision' => 2],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'precision' => 2],
        ];

        foreach ($currencies as $currency) {
            Currency::query()->updateOrCreate(['code' => $currency['code']], $currency);
        }
    }
}
