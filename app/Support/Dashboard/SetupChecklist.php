<?php

namespace App\Support\Dashboard;

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Quotation;

/**
 * The first-run/zero-state setup checklist.
 * Five steps, each linking to the existing TALL-stack page that completes
 * it: company profile (Identity), numbering (Documents & Numbering), at
 * least one catalog item, at least one client, and the first quotation.
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
                'label' => 'Set up your company profile',
                'done' => filled($company->address_line_1),
                'url' => route('tallstack.settings.identity', $company),
            ],
            [
                'key' => 'numbering',
                'label' => 'Set your company code and invoice numbering prefix',
                'done' => filled($company->code) && filled($company->invoice_prefix),
                'url' => route('tallstack.settings.documents-numbering', $company),
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
