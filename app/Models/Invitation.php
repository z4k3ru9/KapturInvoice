<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['invoice_id', 'contact_id', 'legacy_invitation_id', 'sent_at', 'viewed_at', 'signed_at', 'signature'])]
class Invitation extends Model
{
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invitation) {
            $invitation->key ??= (string) Str::orderedUuid();
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * True when `signature` holds a drawn-signature image (a base64 data
     * URI — either captured by the portal's `<x-signature>` canvas or
     * imported from a legacy InvoiceNinja `signature_base64` column) rather
     * than the older plain typed-name string a handful of already-signed
     * rows may still carry.
     */
    public function hasSignatureImage(): bool
    {
        return is_string($this->signature) && str_starts_with($this->signature, 'data:image/');
    }
}
