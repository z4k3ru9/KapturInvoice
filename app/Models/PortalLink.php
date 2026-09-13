<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The broader, contact-scoped "portal link" from
 * docs/rebuild/specs/06-documents-portal-reporting/Specs.md — gives a
 * designated billing contact read-only access to their client's FULL
 * invoice/receipt/payment history, resolved at
 * `/portal/link/{portalLink:key}` by `App\Livewire\Portal\ClientPortalHome`.
 *
 * Deliberately separate from `App\Models\Invitation` (the existing
 * per-invoice single-document share): an ordinary, non-billing contact's
 * link still resolves its "explicitly shared documents" view through that
 * existing model rather than a new sharing table (FINALIZED-DECISIONS.md
 * §5).
 *
 * `key` is the unguessable credential (same UUID-in-booted() pattern as
 * `Invitation::booted()`) — expiring/revocable/replaceable per the spec:
 * `isActive()` is the single source of truth `ClientPortalHome::mount()`
 * checks before rendering anything, so a revoked or expired link 404s
 * exactly like a cross-company one (see
 * `docs/rebuild/specs/FINALIZED-DECISIONS.md`'s "Portal cannot expose
 * disabled actions through stale links" test).
 */
#[Fillable(['company_id', 'client_id', 'contact_id', 'expires_at', 'revoked_at', 'last_viewed_at'])]
class PortalLink extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $link) {
            $link->key ??= (string) Str::uuid();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
