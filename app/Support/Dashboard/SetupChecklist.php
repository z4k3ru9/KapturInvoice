<?php

namespace App\Support\Dashboard;

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Quotation;

/**
 * The first-run/zero-state setup checklist — see
 * docs/rebuild/outputs/18-stitch-ui-gap-analysis/01-shell-dashboard.md O1.
 * Four steps, each linking to the existing TALL-stack page that completes
 * it: company profile & numbering, at least one catalog item, at least
 * one client, and the first quotation.
 */
class SetupChecklist
{
    /**
     * @return array{steps: list<array{key: string, label: string, done: bool, url: string}>, done: int, total: int}
     */
    public static function for(Company $company): array
    {
        $steps = [
            [
                'key' => 'company_profile',
                'label' => 'Set up your company profile and numbering',
                'done' => filled($company->code) && filled($company->address_line_1),
                'url' => route('tallstack.settings.company-and-taxes', $company),
            ],
            [
                'key' => 'numbering',
                'label' => 'Confirm your invoice numbering prefix',
                'done' => filled($company->invoice_prefix),
                'url' => route('tallstack.settings.numbering', $company),
            ],
            [
                'key' => 'catalog',
                'label' => 'Add your first product or service',
                'done' => Product::query()->exists(),
                'url' => route('tallstack.products', $company),
            ],
            [
                'key' => 'client',
                'label' => 'Add your first client',
                'done' => Client::query()->exists(),
                'url' => route('tallstack.clients', $company),
            ],
            [
                'key' => 'quotation',
                'label' => 'Create your first quotation',
                'done' => Quotation::query()->exists(),
                'url' => route('tallstack.quotations', $company),
            ],
        ];

        $done = count(array_filter($steps, fn (array $step) => $step['done']));

        return ['steps' => $steps, 'done' => $done, 'total' => count($steps)];
    }

    public static function isComplete(Company $company): bool
    {
        $checklist = self::for($company);

        return $checklist['done'] === $checklist['total'];
    }
}
