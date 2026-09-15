<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\ProposalSnippet;
use App\Models\ProposalTemplate;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the Proposal Snippets library — the smaller sibling to
 * App\Livewire\TallStackProposalTemplates (see that class's docblock for
 * the shared reasoning: mockup vs. real schema, generic Gate::before
 * authorization, reached via Proposals' own tab links). Per prompt 23's
 * own scoping ("sketch this as a smaller secondary panel/tab rather than
 * a second full screen, since it's structurally identical to Templates")
 * this page keeps a lighter single-field editor (Name + HTML only — no
 * CSS split pane) and surfaces `App\Models\ProposalSnippet::product_id`
 * as a read-only "Linked to product" badge rather than an editable field,
 * since that link is written only by `App\Services\ProposalSnippetSync`
 * (a product's own "Create proposal snippet" action) — a snippet built
 * here directly is never product-linked.
 */
#[Layout('components.tallstack.app')]
class TallStackProposalSnippets extends Component
{
    use Interactions, WithPagination;

    public Company $company;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?string $name = null;

    public ?string $html = null;

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

    public function create(): void
    {
        Gate::authorize('create', ProposalSnippet::class);

        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $snippet = $this->findScoped($id);

        if (! $snippet) {
            return;
        }

        $this->editingId = $snippet->id;
        $this->name = $snippet->name;
        $this->html = $snippet->html;
        $this->showModal = true;
    }

    /** Never touches product_id — see class docblock. */
    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'html' => ['nullable', 'string'],
        ]);

        if ($this->editingId) {
            $snippet = $this->findScoped($this->editingId);

            if (! $snippet) {
                return;
            }

            Gate::authorize('update', $snippet);

            $snippet->update($data);
            $this->toast()->success('Snippet saved.')->send();
        } else {
            Gate::authorize('create', ProposalSnippet::class);

            $data['company_id'] = $this->company->id;
            ProposalSnippet::create($data);
            $this->toast()->success('Snippet created.')->send();
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $snippet = $this->findScoped($id);

        if (! $snippet) {
            return;
        }

        Gate::authorize('delete', $snippet);

        $snippet->delete();

        $this->toast()->success('Snippet deleted.')->send();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = null;
        $this->html = null;
    }

    /** Never trust a bare `ProposalSnippet::find()` — always re-check company ownership explicitly, same as every other TALL-stack page. */
    private function findScoped(?int $id): ?ProposalSnippet
    {
        if (! $id) {
            return null;
        }

        $snippet = ProposalSnippet::find($id);

        if (! $snippet || $snippet->company_id !== $this->company->id) {
            return null;
        }

        return $snippet;
    }

    public function render(): View
    {
        $snippets = ProposalSnippet::query()
            ->where('company_id', $this->company->id)
            ->with('product')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->through(fn (ProposalSnippet $snippet) => [
                'id' => $snippet->id,
                'name' => $snippet->name,
                'html' => $snippet->html,
                'product_name' => $snippet->product?->name,
                'thumbnail' => $snippet->product?->getImageDataUri(),
            ]);

        return view('livewire.tallstack-proposal-snippets', [
            'snippets' => $snippets,
            'templatesCount' => ProposalTemplate::query()->where('company_id', $this->company->id)->count(),
        ])->layoutData([
            'company' => $this->company,
            'active' => 'proposals',
            'title' => 'Proposal Snippets',
        ]);
    }
}
