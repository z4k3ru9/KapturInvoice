<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Draft-only autosave for a plain Livewire form component — the TALL-stack
 * port of the old App\Filament\Concerns\AutosavesDraft (removed with the
 * rest of app/Filament/** during the Filament-removal Phase B), per
 * docs/rebuild/specs/06b-ux-browser-soa/Specs.md: "Autosave is draft-only,
 * batched, debounced, conflict-aware, and never performs a financial
 * action." / "Add server version or equivalent optimistic concurrency
 * protection." / "Preserve local values after failed save and expose
 * explicit retry." / docs/rebuild/DESIGN.md §6.
 *
 * The Filament original worked against a `$this->data` array and
 * `$this->getRecord()` (an EditRecord page's own Filament plumbing). This
 * app's TALL-stack form components (e.g. App\Livewire\TallStackInvoiceForm)
 * hold each field as a direct public property instead, so this trait's
 * contract is adapted to that shape: {@see autosaveModel()} returns the
 * Eloquent model being edited (or null before it exists / outside edit
 * mode), and {@see collectAutosaveFieldValues()} reads
 * `$this->{$field}` directly rather than indexing into a `$data` array.
 *
 * A component using this trait must implement {@see autosaveModel()},
 * {@see autosaveFields()} (an explicit whitelist — never `status`, never a
 * derived total/balance column, never anything a privileged Action class
 * alone should write) and {@see autosaveGuard()} (true only while the
 * record is safely in a draft state). Wire each whitelisted field with
 * `wire:model.live.debounce.1750ms="fieldName"` plus an `updated{Field}()`
 * hook that calls `$this->autosaveDraft()` — Livewire's own debounced
 * `wire:model` already commits on blur as well as after the debounce
 * window, so one mechanism satisfies both halves of "after 1.5-2 seconds
 * of inactivity and on blur".
 *
 * The record's own `draft_version` column (never in any model's
 * `#[Fillable]` — written only from here) is the optimistic-concurrency
 * guard: a stale save (another tab/user autosaved or issued the document
 * in between) is rejected and surfaced as an explicit conflict rather than
 * silently merged or silently overwritten, exactly as DESIGN.md requires.
 */
trait AutosavesDraft
{
    public string $autosaveStatus = 'idle';

    public ?int $autosaveKnownVersion = null;

    /** @var array<string, array{local: mixed, server: mixed}>|null */
    public ?array $autosaveConflictFields = null;

    public ?string $autosaveError = null;

    /** The Eloquent model being autosaved, or null before it exists / outside edit mode. */
    abstract protected function autosaveModel(): ?Model;

    /** @return list<string> the model attributes this component may autosave — never status/total/balance/number. */
    abstract protected function autosaveFields(): array;

    /** Whether the record is currently safe to autosave (e.g. still Draft). */
    abstract protected function autosaveGuard(): bool;

    protected function initializeAutosaveVersion(): void
    {
        $record = $this->autosaveModel();

        $this->autosaveKnownVersion = $record ? (int) $record->getAttribute('draft_version') : null;
    }

    public function autosaveDraft(): void
    {
        $record = $this->autosaveModel();

        if (! $record || ! $this->autosaveGuard()) {
            $this->autosaveStatus = 'idle';

            return;
        }

        $this->autosaveStatus = 'saving';

        try {
            // Same reasoning as the Filament original (a Codex review
            // finding on PR #4): the version check and the write must be
            // one atomic conditional UPDATE, never a separate read then
            // write — that window let two concurrent autosaves both read
            // the same version, both pass the comparison, and both save,
            // the later write silently clobbering the earlier one while
            // both tabs report "saved". An affected-row count of zero
            // means someone else's write already moved the version out
            // from under us.
            $fields = $this->collectAutosaveFieldValues();
            $knownVersion = $this->autosaveKnownVersion;

            $affected = $record->newQuery()
                ->whereKey($record->getKey())
                ->where('draft_version', $knownVersion)
                ->update([
                    ...$fields,
                    'draft_version' => $knownVersion + 1,
                ]);

            if ($affected === 0) {
                $freshVersion = (int) $record->newQuery()
                    ->whereKey($record->getKey())
                    ->value('draft_version');

                $this->flagAutosaveConflict($record, $freshVersion);

                return;
            }

            $record->forceFill([...$fields, 'draft_version' => $knownVersion + 1]);

            $this->autosaveKnownVersion = $knownVersion + 1;
            $this->autosaveStatus = 'saved';
            $this->autosaveError = null;
        } catch (Throwable $e) {
            // The component's own public properties are untouched by a
            // thrown exception — the typed values stay exactly as the
            // user left them, ready for an explicit retry, per Specs.md
            // "Preserve local values after failed save and expose
            // explicit retry."
            $this->autosaveStatus = 'failed';
            $this->autosaveError = $e->getMessage();
        }
    }

    public function retryAutosaveDraft(): void
    {
        $this->autosaveDraft();
    }

    /** Accept the server's values, discarding the unsaved local edit. */
    public function discardAutosaveConflict(): void
    {
        $record = $this->autosaveModel()?->fresh();

        if (! $record) {
            return;
        }

        foreach ($this->autosaveFields() as $field) {
            $this->{$field} = $record->getAttribute($field);
        }

        $this->autosaveKnownVersion = (int) $record->getAttribute('draft_version');
        $this->autosaveConflictFields = null;
        $this->autosaveStatus = 'idle';
    }

    /** Force the local edit through anyway, deliberately overriding the server's newer values. */
    public function overwriteAutosaveConflict(): void
    {
        $record = $this->autosaveModel();

        if (! $record) {
            return;
        }

        $freshVersion = (int) $record->newQuery()->whereKey($record->getKey())->value('draft_version');

        $record->forceFill([
            ...$this->collectAutosaveFieldValues(),
            'draft_version' => $freshVersion + 1,
        ])->saveQuietly();

        $this->autosaveKnownVersion = $freshVersion + 1;
        $this->autosaveConflictFields = null;
        $this->autosaveStatus = 'saved';
    }

    /** @return array<string, mixed> */
    protected function collectAutosaveFieldValues(): array
    {
        $values = [];

        foreach ($this->autosaveFields() as $field) {
            $values[$field] = $this->{$field} ?? null;
        }

        return $values;
    }

    protected function flagAutosaveConflict(Model $record, int $freshVersion): void
    {
        $server = $record->newQuery()->whereKey($record->getKey())->first();

        $conflicts = [];

        foreach ($this->autosaveFields() as $field) {
            $local = $this->{$field} ?? null;
            $serverValue = $server?->getAttribute($field);

            if ((string) $local !== (string) $serverValue) {
                $conflicts[$field] = ['local' => $local, 'server' => $serverValue];
            }
        }

        // Even with no field-level differences, the version itself moved
        // (e.g. another action bumped it without touching these fields) —
        // still surface it as a conflict rather than silently accepting a
        // new "known" version derived from a save that never happened.
        $this->autosaveConflictFields = $conflicts;
        $this->autosaveStatus = 'conflict';
    }
}
