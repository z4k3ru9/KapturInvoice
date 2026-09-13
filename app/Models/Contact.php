<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['client_id', 'legacy_contact_id', 'first_name', 'last_name', 'email', 'phone', 'is_primary', 'is_billing_contact'])]
#[Hidden(['portal_token'])]
class Contact extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            // Per docs/rebuild/specs/FINALIZED-DECISIONS.md §5: only a
            // designated billing contact sees a client's full billing
            // history in the portal; an ordinary contact sees only
            // explicitly shared documents. Scoped through this contact's
            // own client/company — never checked in isolation from that
            // relationship (see ContactBillingEligibilityTest).
            'is_billing_contact' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $contact) {
            $contact->portal_token ??= (string) Str::uuid();
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
