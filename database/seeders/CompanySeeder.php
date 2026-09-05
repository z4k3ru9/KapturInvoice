<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the two example entities KapturInvoice runs for local development.
 * Rename/replace with the real two businesses before going live — domains
 * here are placeholders (see §4 of docs/invoiceninja-v4-schema-reference.md
 * for how these map onto Filament's tenancy).
 */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                'name' => 'Entity One',
                'slug' => 'entity-one',
                'domain' => 'entity-one.test',
                'currency_code' => 'USD',
                'invoice_prefix' => 'E1-',
                'quote_prefix' => 'Q1-',
                'credit_prefix' => 'C1-',
            ],
            [
                'name' => 'Entity Two',
                'slug' => 'entity-two',
                'domain' => 'entity-two.test',
                'currency_code' => 'USD',
                'invoice_prefix' => 'E2-',
                'quote_prefix' => 'Q2-',
                'credit_prefix' => 'C2-',
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
