<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A company's own bank account, printed as a "Payment Method" entry on
 * the Invoice PDF (`resources/views/pdf/invoice.blade.php`). A company
 * may have more than one (e.g. two different banks) — ordered by
 * `sort_order`, all shown.
 */
#[Fillable(['company_id', 'bank_name', 'account_name', 'account_number', 'branch', 'swift_code', 'sort_order'])]
class CompanyBankAccount extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
