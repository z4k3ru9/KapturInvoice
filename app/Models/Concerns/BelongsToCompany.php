<?php

namespace App\Models\Concerns;

use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies to every directly tenant-owned model (one with a `company_id`
 * column: Client, Product, TaxRate, Invoice, Credit, Payment).
 *
 * Neither the TALL-stack pages (nor, previously, the Filament admin panel
 * they replaced) auto-scope an arbitrary relationship picker (e.g.
 * choosing a client on the Credit form) or a manual query to the active
 * tenant on their own — without this, those pickers would leak rows
 * across companies. This
 * trait adds a global scope (active only once `App\Support\Tenancy\
 * Tenancy` has a tenant set for the current request) plus auto-fills
 * `company_id` on create, so every model using it is scoped/assigned
 * consistently without repeating the logic on each resource/page.
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            if ($tenant = app(Tenancy::class)->get()) {
                $query->where($query->getModel()->getTable().'.company_id', $tenant->getKey());
            }
        });

        static::creating(function ($model) {
            if (blank($model->company_id) && ($tenant = app(Tenancy::class)->get())) {
                $model->company_id = $tenant->getKey();
            }
        });
    }
}
