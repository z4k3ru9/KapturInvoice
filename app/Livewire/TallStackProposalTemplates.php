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
use TallStackUi\Traits\Interactions;

/**
 * A TALL-stack-native (TallStackUI components, no Filament) rendering of
 * the Proposal Templates library — Proposals' own supporting resource,
 * per docs/rebuild/outputs/ui-rebuild/27-filament-parity-gap-prompts.md prompt 23.
 * Reached via the "Proposal Templates" tab link on
 * App\Livewire\TallStackProposals's header (previously a placeholder
 * link straight to the Filament admin resource — now this page).
 *
 * Reuses App\Models\ProposalTemplate unmodified; matches the equivalent
 * pre-TallStackUI Filament proposal template resource's own field set
 * exactly (name/html/css — the legacy import id is
 * traceability-only metadata, not shown on this screen, same reasoning
 * App\Livewire\TallStackProposalForm already applies to Proposal's own
 * legacy field). No dedicated Policy exists for ProposalTemplate — its
 * create/update/delete are already gated by the generic
 * App\Providers\AppServiceProvider::registerCompanyRoleGate() Gate::before
 * hook every BelongsToCompany model gets (CompanyRole::mutatingRoles()),
 * reused here unmodified via Gate::authorize(), same as
 * App\Livewire\TallStackPaymentGateways.
 *
 * Prompt 23's own mockup ("Proposal Templates & Snippets Library",
 * generated in the Stitch project) sketches a considerably richer
 * document-management screen (merge-tag variables, category scoping,
 * batch actions, per-template usage counts) than this app's actual
 * ProposalTemplate schema supports — those fields don't exist on the
 * model and aren't invented here; this page instead matches the
 * mockup's genuinely reusable shape: a tabbed Templates/Snippets
 * library, a thumbnail-preview table, and an inline editor with a live
 * preview pane.
 */
#[Layout('components.tallstack.app')]
class TallStackProposalTemplates extends Component
{
    use Interactions;

    public Company $company;

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?string $name = null;

    public ?string $html = null;

    public ?string $css = null;

    public function mount(Company $company): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        // Same reasoning as every other TALL-stack page's mount() — kept
        // for parity even though nothing on this page calls
        // Resource::getUrl() today.
        app(Tenancy::class)->set($company);
    }

    public function create(): void
    {
        Gate::authorize('create', ProposalTemplate::class);

        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $template = $this->findScoped($id);

        if (! $template) {
            return;
        }

        $this->editingId = $template->id;
        $this->name = $template->name;
        $this->html = $template->html;
        $this->css = $template->css;
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'html' => ['nullable', 'string'],
            'css' => ['nullable', 'string'],
        ]);

        if ($this->editingId) {
            $template = $this->findScoped($this->editingId);

            if (! $template) {
                return;
            }

            Gate::authorize('update', $template);

            $template->update($data);
            $this->toast()->success('Proposal template saved.')->send();
        } else {
            Gate::authorize('create', ProposalTemplate::class);

            $data['company_id'] = $this->company->id;
            ProposalTemplate::create($data);
            $this->toast()->success('Proposal template created.')->send();
        }

        $this->showModal = false;
        $this->resetForm();
    }

    /**
     * Copies name/html/css only — mirrors
     * App\Livewire\TallStackProposals::duplicate()'s own reasoning (a
     * fresh, independent copy, never sharing any identity with the
     * source).
     */
    public function duplicate(int $id): void
    {
        $template = $this->findScoped($id);

        if (! $template) {
            return;
        }

        Gate::authorize('create', ProposalTemplate::class);

        ProposalTemplate::create([
            'company_id' => $this->company->id,
            'name' => $template->name.' (copy)',
            'html' => $template->html,
            'css' => $template->css,
        ]);

        $this->toast()->success('Proposal template duplicated.')->send();
    }

    public function delete(int $id): void
    {
        $template = $this->findScoped($id);

        if (! $template) {
            return;
        }

        Gate::authorize('delete', $template);

        $template->delete();

        $this->toast()->success('Proposal template deleted.')->send();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = null;
        $this->html = null;
        $this->css = null;
    }

    /** Never trust a bare `ProposalTemplate::find()` — always re-check company ownership explicitly, same as every other TALL-stack page. */
    private function findScoped(?int $id): ?ProposalTemplate
    {
        if (! $id) {
            return null;
        }

        $template = ProposalTemplate::find($id);

        if (! $template || $template->company_id !== $this->company->id) {
            return null;
        }

        return $template;
    }

    public function render(): View
    {
        $templates = ProposalTemplate::query()
            ->where('company_id', $this->company->id)
            ->orderBy('name')
            ->get()
            ->map(fn (ProposalTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'html' => $template->html,
                'css' => $template->css,
                'updated_relative' => $template->updated_at->diffForHumans(),
            ]);

        return view('livewire.tallstack-proposal-templates', [
            'templates' => $templates,
            'snippetsCount' => ProposalSnippet::query()->where('company_id', $this->company->id)->count(),
        ])->layoutData([
            'company' => $this->company,
            'active' => 'proposals',
            'title' => 'Proposal Templates',
        ]);
    }
}
