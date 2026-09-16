<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Applies to any model with `held_at`/`held_reason`/`held_by` columns
 * (Invoice — covers both a plain invoice and a recurring template row, and
 * SalesOrder). The override lever for the status-transition automation
 * added alongside this trait: pausing automation on one specific record for
 * a real reason (moderation, a revision that needs approval) rather than a
 * free-form status dropdown that the next automated run would just
 * recompute back. Written only through App\Actions\Shared\{PlaceHold,
 * ReleaseHold} — see this app's own `draft_version` precedent for why
 * these columns are deliberately NOT `#[Fillable]`.
 */
trait Holdable
{
    public function scopeNotHeld(Builder $query): Builder
    {
        return $query->whereNull($query->getModel()->getTable().'.held_at');
    }

    public function isHeld(): bool
    {
        return $this->held_at !== null;
    }
}
