<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Document;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the Documents register — see App\Livewire\TallStackVendors's docblock
 * for the established pattern this follows. Reuses App\Models\Document
 * unmodified; a presentation-layer swap over the equivalent pre-TallStackUI
 * Filament documents resource.
 *
 * Per docs/rebuild/outputs/ui-rebuild/27-filament-parity-gap-prompts.md prompt 22,
 * this is a flat, company-scoped "every file we have" register — a
 * Document is always attached to an owning record (an invoice, expense,
 * etc.) elsewhere in the app via its `documentable` morph, uploaded from
 * that parent's own DocumentsRelationManager. This page deliberately has
 * no "New document" upload button; it's register/list-only, matching the
 * Stitch mockup ("Documents & File Library — Company Scoped Reference
 * Register").
 *
 * Download reuses the existing, already-authorized
 * App\Http\Controllers\DocumentDownloadController route
 * (`documents.download`) rather than building a new download path.
 * Delete reuses Document's own `delete()` — the same physical-row
 * deletion DocumentResource's DeleteAction and
 * DocumentsRelationManager's DeleteAction already perform (a Document is
 * an uploaded attachment, not one of the immutable issued-document types
 * CLAUDE.md's "never physically deleted" guard covers).
 */
#[Layout('components.tallstack.app')]
class TallStackDocuments extends Component
{
    use Interactions, WithPagination;

    public Company $company;

    public string $search = '';

    /** @var 'all'|'pdf'|'image'|'other' */
    public string $typeFilter = 'all';

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function filterType(string $type): void
    {
        $this->typeFilter = $type;
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $document = $this->findScoped($id);

        if (! $document) {
            return;
        }

        $document->delete();
        $this->toast()->success('Document deleted.')->send();
    }

    /** Never trust a bare `Document::find()` — always re-check company ownership, same as every other TALL-stack page. */
    private function findScoped(?int $id): ?Document
    {
        if (! $id) {
            return null;
        }

        $document = Document::find($id);

        if (! $document || $document->company_id !== $this->company->id) {
            return null;
        }

        return $document;
    }

    /** @return Builder<Document> */
    private function baseQuery(): Builder
    {
        return Document::query()->where('company_id', $this->company->id);
    }

    private function applyTypeFilter(Builder $query, string $type): Builder
    {
        return match ($type) {
            'pdf' => $query->where('mime_type', 'application/pdf'),
            'image' => $query->where('mime_type', 'like', 'image/%'),
            'other' => $query->where(function ($q) {
                $q->whereNull('mime_type')
                    ->orWhere(function ($q) {
                        $q->where('mime_type', '!=', 'application/pdf')
                            ->where('mime_type', 'not like', 'image/%');
                    });
            }),
            default => $query,
        };
    }

    public function render(): View
    {
        $documents = $this->applyTypeFilter((clone $this->baseQuery()), $this->typeFilter)
            ->when($this->search, fn ($q) => $q->where('filename', 'like', "%{$this->search}%"))
            ->with('uploadedBy')
            ->latest()
            ->paginate(15)
            ->through(fn (Document $document) => [
                'id' => $document->id,
                'filename' => $document->filename,
                'file_icon' => $this->fileIconFor($document->mime_type),
                'size' => $this->formatSize($document->size),
                'uploaded_by' => $document->uploadedBy?->name ?? '—',
                'uploaded_relative' => $document->created_at?->diffForHumans() ?? '—',
            ]);

        return view('livewire.tallstack-documents', [
            'documents' => $documents,
            'counts' => [
                'all' => (clone $this->baseQuery())->count(),
                'pdf' => $this->applyTypeFilter((clone $this->baseQuery()), 'pdf')->count(),
                'image' => $this->applyTypeFilter((clone $this->baseQuery()), 'image')->count(),
                'other' => $this->applyTypeFilter((clone $this->baseQuery()), 'other')->count(),
            ],
            'hasAnyDocuments' => (clone $this->baseQuery())->exists(),
        ])->layoutData([
            'company' => $this->company,
            'active' => 'documents',
            'title' => 'Documents',
        ]);
    }

    private function fileIconFor(?string $mimeType): string
    {
        if ($mimeType === 'application/pdf') {
            return 'document-text';
        }

        if ($mimeType && str_starts_with($mimeType, 'image/')) {
            return 'photo';
        }

        return 'paper-clip';
    }

    private function formatSize(?int $bytes): string
    {
        if (! $bytes) {
            return '—';
        }

        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1).' MB';
        }

        return number_format($bytes / 1024, 1).' KB';
    }
}
