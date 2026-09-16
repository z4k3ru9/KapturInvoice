@props(['status' => 'idle', 'error' => null, 'conflictFields' => null])

{{--
    Inline draft-autosave status — the TALL-stack equivalent of the old
    Filament resources/views/filament/components/autosave-status.blade.php,
    per docs/rebuild/DESIGN.md §6: "Show inline Saving, Saved, or Save
    failed. Keep retry available... Do not toast normal autosave." / "If
    another tab/user changed the draft, show server and local versions,
    changed fields, and explicit merge/review or discard choices. Never
    silently overwrite."

    Included wherever App\Livewire\Concerns\AutosavesDraft is wired onto a
    page — `$status`/`$error`/`$conflictFields` come straight from that
    trait's own public properties on the enclosing Livewire component, so
    the `wire:click` calls below reach it directly.
--}}
<div>
    @if ($status === 'saving')
        <span class="text-sm text-gray-500 dark:text-gray-400!">Saving…</span>
    @elseif ($status === 'saved')
        <span class="text-sm text-green-600 dark:text-green-400!">Saved</span>
    @elseif ($status === 'failed')
        <span class="flex items-center gap-x-2 text-sm text-red-600 dark:text-red-400!">
            <span>Save failed @if ($error)— {{ $error }}@endif</span>
            <button type="button" wire:click="retryAutosaveDraft" class="font-semibold underline">Retry</button>
        </span>
    @elseif ($status === 'conflict')
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-700! dark:bg-amber-950!">
            <p class="font-semibold text-amber-800 dark:text-amber-200!">
                This draft was changed elsewhere while you were editing.
            </p>
            @if (! empty($conflictFields))
                <ul class="mt-2 list-disc space-y-1 pl-5 text-amber-800 dark:text-amber-200!">
                    @foreach ($conflictFields as $field => $values)
                        <li>
                            <strong>{{ $field }}</strong>:
                            yours "{{ $values['local'] }}" — server "{{ $values['server'] }}"
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="mt-3 flex gap-x-3">
                <button type="button" wire:click="discardAutosaveConflict" class="font-semibold underline text-amber-800 dark:text-amber-200!">Discard my changes</button>
                <button type="button" wire:click="overwriteAutosaveConflict" class="font-semibold underline text-red-600 dark:text-red-400!">Keep my changes anyway</button>
            </div>
        </div>
    @endif
</div>
