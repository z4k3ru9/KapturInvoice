<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Draft-only autosave for a Filament EditRecord page — Phase 06B Slice 3
 * (docs/rebuild/specs/06b-ux-browser-soa/Specs.md): "Autosave is
 * draft-only, batched, debounced, conflict-aware, and never performs a
 * financial action." / "Add server version or equivalent optimistic
 * concurrency protection." / "Preserve local values after failed save
 * and expose explicit retry." / docs/rebuild/DESIGN.md §6.
 *
 * A page using this trait must implement {@see autosaveFields()} (an
 * explicit whitelist — never `status`, never a derived total/balance
 * column, never anything a privileged Action class alone should write)
 * and {@see autosaveGuard()} (true only while the record is safely in a
 * draft state). Wire each whitelisted form field with
 * `->live(debounce: '1750ms')->afterStateUpdated(fn ($livewire) =>
 * $livewire->autosaveDraft())` — Livewire's own debounced `wire:model`
 * already commits on blur as well as after the debounce window, so one
 * mechanism satisfies both halves of "after 1.5-2 seconds of inactivity
 * and on blur".
 *
 * Every whitelisted field change is written together in one
 * `autosaveDraft()` call (Livewire coalesces near-simultaneous property
 * updates into one request), never once per keystroke. The record's own
 * `draft_version` column (never in any model's `#[Fillable]` — written
 * only from here) is the optimistic-concurrency guard: a stale save
 * (another tab/user autosaved or issued the document in between) is
 * rejected and surfaced as an explicit conflict rather than silently
 * merged or silently overwritten, exactly as DESIGN.md requires.
 */
trait AutosavesDraft
{
    public string $autosaveStatus = 'idle';

    public ?int $autosaveKnownVersion = null;

    /** @var array<string, array{local: mixed, server: mixed}>|null */
    public ?array $autosaveConflictFields = null;

    public ?string $autosaveError = null;

    /** @return list<string> the model attributes this page may autosave — never status/total/balance/number. */
    abstract protected function autosaveFields(): array;

    /** Whether the record is currently safe to autosave (e.g. still Draft). */
    abstract protected function autosaveGuard(): bool;

    protected function initializeAutosaveVersion(): void
    {
        $this->autosaveKnownVersion = (int) $this->getRecord()->getAttribute('draft_version');
    }

    public function autosaveDraft(): void
    {
        $record = $this->getRecord();

        if (! $this->autosaveGuard()) {
            $this->autosaveStatus = 'idle';

            return;
        }

        $this->autosaveStatus = 'saving';

        try {
            // A Codex review finding on PR #4: reading `draft_version`
            // and then, in a separate step, unconditionally writing
            // `draft_version + 1` left a window in which two concurrent
            // autosaves could both read the same version, both pass the
            // comparison, and then both save — the later write silently
            // clobbering the earlier one while both tabs report
            // "saved". The version check and the write must be one
            // atomic operation: a single conditional UPDATE whose WHERE
            // clause repeats the known version, so the database itself
            // (not two round trips from this process) decides whether
            // the guard still held at write time. An affected-row count
            // of zero means someone else's write already moved the
            // version out from under us — even a MySQL/Postgres row
            // lock from a separate read-then-write couldn't close that
            // window the way conditioning the UPDATE itself does; only
            // genuine concurrent database connections can actually
            // exercise this race, which a single-threaded PHPUnit
            // process cannot reproduce — the guarantee is verified here
            // by exercising the same code path, and end-to-end by
            // tests/browser/documents/autosave.spec.ts's two-tab test.
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
            // Local $this->data is untouched by a thrown exception — the
            // typed values stay exactly as the user left them, ready for
            // an explicit retry, per Specs.md "Preserve local values
            // after failed save and expose explicit retry."
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
        $record = $this->getRecord()->fresh();

        foreach ($this->autosaveFields() as $field) {
            $this->data[$field] = $record->getAttribute($field);
        }

        $this->autosaveKnownVersion = (int) $record->getAttribute('draft_version');
        $this->autosaveConflictFields = null;
        $this->autosaveStatus = 'idle';
    }

    /** Force the local edit through anyway, deliberately overriding the server's newer values. */
    public function overwriteAutosaveConflict(): void
    {
        $record = $this->getRecord();
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
            $values[$field] = $this->data[$field] ?? null;
        }

        return $values;
    }

    protected function flagAutosaveConflict(Model $record, int $freshVersion): void
    {
        $server = $record->newQuery()->whereKey($record->getKey())->first();

        $conflicts = [];

        foreach ($this->autosaveFields() as $field) {
            $local = $this->data[$field] ?? null;
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
