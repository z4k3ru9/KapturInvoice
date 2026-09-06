<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the two real entities KapturInvoice replaces the legacy InvoiceNinja
 * installs for — both Surabaya-based IT/security-infrastructure integrators
 * sharing this codebase. Real identity/branding fields below are taken
 * straight from each company's own InvoiceNinja `accounts`/`companies` row
 * (see docs/data-import.md); `invoice_next_number` etc. start at 1 here and
 * get bumped past the real historical high-water mark by the importer
 * commands once historical data is loaded (never rely on these seeded
 * starting values once real invoices have been imported).
 */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                // Source: chronopr_ninj226.sql (InvoiceNinja v4), accounts.id=1.
                'name' => 'Karunia Abadi',
                'slug' => 'karunia-abadi',
                'domain' => 'karuniaabadi.id',
                'email' => 'kja@karuniaabadi.id',
                'phone' => '+628113549500',
                'address_line_1' => 'Sukolilo Sukorejo 26',
                'city' => 'Surabaya',
                'state' => 'East Java',
                'postal_code' => '60118',
                'country_code' => 'ID',
                'currency_code' => 'IDR',
                'primary_color' => '#8c0d1b',
                'invoice_prefix' => 'KJA-INV-',
                'quote_prefix' => 'KJA-QUO-',
                'credit_prefix' => 'KJA-CR-',
            ],
            [
                // Source: axentech_ninj876.sql (InvoiceNinja v5), companies.id=2.
                'name' => 'PT. Axen Technology Indonesia',
                'slug' => 'axen-technology-indonesia',
                'domain' => 'axentechnology.web.id',
                'email' => 'ati@axentechnology.web.id',
                'phone' => '+6281554549509',
                'address_line_1' => 'Sukolilo Sukorejo 26',
                'city' => 'Surabaya',
                'state' => 'East Java',
                'postal_code' => '60112',
                'country_code' => 'ID',
                'currency_code' => 'IDR',
                'tax_number' => '63.611.560.2-619.000',
                'primary_color' => '#298AAB',
                'secondary_color' => '#7081e0',
                'invoice_prefix' => 'ATI-INV-',
                'quote_prefix' => 'ATI-QUO-',
                'credit_prefix' => 'ATI-CR-',
            ],
        ];

        $user = User::query()->first();

        foreach ($companies as $attributes) {
            $company = Company::query()->updateOrCreate(['slug' => $attributes['slug']], $attributes);

            if ($user) {
                $company->users()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
            }
        }
    }
}
