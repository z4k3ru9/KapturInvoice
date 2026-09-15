{{--
    Phase 06B Slice 3 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md) —
    inline draft-autosave status, per docs/rebuild/DESIGN.md §6: "Show
    inline Saving, Saved, or Save failed. Keep retry available... Do not
    toast normal autosave." / "If another tab/user changed the draft,
    show server and local versions, changed fields, and explicit
    merge/review or discard choices. Never silently overwrite."
    Rendered by App\Filament\Concerns\AutosavesDraft via a
    Filament\Schemas\Components\Html closure, so `$livewire` is the
    EditRecord page itself (the public autosave* properties live there).
--}}
<div>
    @if ($livewire->autosaveStatus === 'saving')
        <div class="fi-in-text text-sm text-gray-500 dark:text-gray-400">
            {{ __('Saving…') }}
        </div>
    @elseif ($livewire->autosaveStatus === 'saved')
        <div class="fi-in-text text-sm text-success-600 dark:text-success-400">
            {{ __('Saved') }}
        </div>
    @elseif ($livewire->autosaveStatus === 'failed')
        <div class="flex items-center gap-x-2 text-sm text-danger-600 dark:text-danger-400">
            <span>{{ __('Save failed') }}@if ($livewire->autosaveError) — {{ $livewire->autosaveError }} @endif</span>
            <button
                type="button"
                wire:click="retryAutosaveDraft"
                class="fi-link fi-size-sm font-semibold underline"
            >{{ __('Retry') }}</button>
        </div>
    @elseif ($livewire->autosaveStatus === 'conflict')
        <div class="rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm dark:border-warning-600 dark:bg-warning-950">
            <p class="font-semibold text-warning-800 dark:text-warning-200">
                {{ __('This draft was changed elsewhere while you were editing.') }}
            </p>
            @if (! empty($livewire->autosaveConflictFields))
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($livewire->autosaveConflictFields as $field => $values)
                        <li>
                            <strong>{{ $field }}</strong>:
                            {{ __('yours') }} "{{ $values['local'] }}" —
                            {{ __('server') }} "{{ $values['server'] }}"
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="mt-3 flex gap-x-3">
                <button
                    type="button"
                    wire:click="discardAutosaveConflict"
                    class="fi-link fi-size-sm font-semibold underline"
                >{{ __('Discard my changes') }}</button>
                <button
                    type="button"
                    wire:click="overwriteAutosaveConflict"
                    class="fi-link fi-size-sm font-semibold text-danger-600 underline dark:text-danger-400"
                >{{ __('Keep my changes anyway') }}</button>
            </div>
        </div>
    @endif
</div>
