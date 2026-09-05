<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['vendor_id', 'legacy_vendor_contact_id', 'first_name', 'last_name', 'email', 'phone', 'is_primary'])]
class VendorContact extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
