<?php

namespace App\Models\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies to every directly tenant-owned model (one with a `company_id`
 * column: Client, Product, TaxRate, Invoice, Credit, Payment).
 *
 * Filament only auto-scopes a Resource's own listing/record-binding query
 * to the active tenant — NOT arbitrary `Select::relationship()` picker
 * options (e.g. choosing a client on the Credit form) or manual queries.
 * Without this, those pickers would leak rows across companies. This
 * trait adds a global scope (active only inside a Filament request with a
 * resolved tenant) plus auto-fills `company_id` on create, so every model
 * using it is scoped/assigned consistently without repeating the logic on
 * each resource.
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            if (Filament::hasTenancy() && ($tenant = Filament::getTenant())) {
                $query->where($query->getModel()->getTable().'.company_id', $tenant->getKey());
            }
        });

        static::creating(function ($model) {
            if (blank($model->company_id) && Filament::hasTenancy() && ($tenant = Filament::getTenant())) {
                $model->company_id = $tenant->getKey();
            }
        });
    }
}
