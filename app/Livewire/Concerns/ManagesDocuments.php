<?php

namespace App\Livewire\Concerns;

use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Reusable "attach a document" upload + list + delete behavior for any
 * TALL-stack Livewire component managing a documentable record (Invoice/
 * Quotation/Expense — anything with a `documents(): MorphMany` relation to
 * App\Models\Document). Ports the behavior of the deleted Filament
 * DocumentsRelationManager (see git history — the commit removing
 * app/Filament) rather than reimplementing it from scratch: same accepted
 * file types, the same 10MB cap, the same disk/directory, and the same
 * `uploaded_by_user_id` = acting user.
 *
 * One deliberate improvement over the original Filament behavior: the
 * original recorded `filename` as `basename($storedPath)` — Laravel's
 * `store()` generates a random hashed filename, so that would have shown
 * a meaningless string in the UI instead of what the user actually
 * uploaded. This trait instead captures `getClientOriginalName()` before
 * storing, so the list (and the existing `documents.download` route,
 * which already serves `Storage::download($path, $document->filename)`)
 * show/download the real original filename.
 *
 * The host component must itself `use Livewire\WithFileUploads` (Livewire
 * requires that trait be composed directly onto the component class) and
 * expose its own `TemporaryUploadedFile|null` property for the file
 * input's `wire:model` — passed explicitly into these methods rather than
 * this trait declaring one itself, since a component may need more than
 * one upload-capable property name.
 */
trait ManagesDocuments
{
    /**
     * Validation rules for a document upload, keyed by the host
     * component's own upload property name (e.g. `newDocument`).
     *
     * @return array<string, array<int, string>>
     */
    protected function documentUploadRules(string $property): array
    {
        return [
            $property => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * Stores the uploaded file and records the Document row against the
     * given documentable model. Company scoping is explicit here rather
     * than left to BelongsToCompany's create-time auto-fill, since a
     * documentable model's own `company_id` (already scoped/verified by
     * the host page's mount()) is the source of truth for which company
     * the attachment belongs to.
     */
    protected function storeUploadedDocument(Model $documentable, TemporaryUploadedFile $file): Document
    {
        $path = $file->store('documents', 'local');

        return $documentable->documents()->create([
            'company_id' => $documentable->company_id,
            'disk' => 'local',
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by_user_id' => auth()->id(),
        ]);
    }

    /**
     * Row shape for the documents list table — filename, size (KB, 1
     * decimal), uploader name (dash if null), uploaded-at — matching the
     * deleted DocumentsRelationManager's table columns.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function documentRows(Model $documentable): Collection
    {
        return $documentable->documents()
            ->with('uploadedBy')
            ->latest()
            ->get()
            ->map(fn (Document $document) => [
                'id' => $document->id,
                'filename' => $document->filename,
                'size' => number_format($document->size / 1024, 1).' KB',
                'uploaded_by' => $document->uploadedBy?->name ?? '—',
                'uploaded_at' => $document->created_at?->format('d M Y H:i') ?? '—',
            ]);
    }

    /** Never trust a bare `Document::find()` — only ever delete a document actually attached to this documentable, same guard every TALL-stack page uses for company scoping. */
    protected function deleteScopedDocument(Model $documentable, int $id): ?Document
    {
        $document = $documentable->documents()->whereKey($id)->first();

        $document?->delete();

        return $document;
    }
}
