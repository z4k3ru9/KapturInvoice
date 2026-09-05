<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'legacy_expense_category_id', 'name'])]
class ExpenseCategory extends Model
{
    use BelongsToCompany;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
