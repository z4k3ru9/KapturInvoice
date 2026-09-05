<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * A starter set of countries — global reference data, not tenant-scoped.
 * The legacy `countries` table has ~250 rows with locale-formatting quirks
 * (swap_postal_code, etc.); import the fuller list from the dump later if
 * needed, see docs/invoiceninja-v4-schema-reference.md §2.9.
 */
class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['code' => 'US', 'iso_3166_3' => 'USA', 'name' => 'United States'],
            ['code' => 'GB', 'iso_3166_3' => 'GBR', 'name' => 'United Kingdom'],
            ['code' => 'ID', 'iso_3166_3' => 'IDN', 'name' => 'Indonesia'],
            ['code' => 'SG', 'iso_3166_3' => 'SGP', 'name' => 'Singapore'],
            ['code' => 'AU', 'iso_3166_3' => 'AUS', 'name' => 'Australia'],
            ['code' => 'DE', 'iso_3166_3' => 'DEU', 'name' => 'Germany'],
        ];

        foreach ($countries as $country) {
            Country::query()->updateOrCreate(['code' => $country['code']], $country);
        }
    }
}
