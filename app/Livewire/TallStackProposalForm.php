<?php

namespace App\Livewire;

use App\Enums\ProposalStatus;
use App\Livewire\Concerns\AutosavesDraft;
use App\Models\Client;
use App\Models\Company;
use App\Models\Proposal;
use App\Models\ProposalSnippet;
use App\Models\ProposalTemplate;
use App\Services\ProposalConverter;
use App\Support\Dashboard\Money;
use App\Support\TallStack\StatusColor;
use App\Support\Tenancy\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;
use TallStackUi\Traits\Interactions;

/**
 * The TALL-stack create/edit editor for a Proposal — the sibling page to
 * App\Livewire\TallStackProposals, following the same shape as
 * App\Livewire\TallStackQuotationForm (see that class's docblock). Mirrors
 * the equivalent pre-TallStackUI Filament proposal form field-for-field:
 * title, client_id, status, amount, valid_until, proposal_template_id,
 * html, css — no field is added or dropped. Unlike Quotation, Proposal's
 * own Filament form already exposed `status` as a directly editable
 * select (that same resource's table "Mark accepted"/"Mark declined" row
 * actions were a convenience shortcut on top of the same column, not the
 * only path to it) — this page keeps that same directness rather than
 * inventing a stricter state machine the Filament resource didn't enforce.
 *
 * `App\Services\ProposalConverter::convertToInvoice()` is reused
 * unmodified — the one-way, one-time proposal-to-invoice conversion rule
 * is enforced entirely inside that service and is never re-decided here.
 */
#[Layout('components.tallstack.app')]
class TallStackProposalForm extends Component
{
    use AutosavesDraft, Interactions;

    public Company $company;

    public ?Proposal $proposal = null;

    // Header fields — exactly ProposalForm's own field set (minus the
    // legacy import id, which is import-traceability metadata only and
    // carries no operational meaning on this screen).
    public ?string $client_id = null;

    public ?string $title = null;

    public string $status = ProposalStatus::Draft->value;

    public float $amount = 0;

    public ?string $valid_until = null;

    public ?string $proposal_template_id = null;

    public ?string $html = null;

    public ?string $css = null;

    /**
     * Bumped whenever a snippet is inserted (or the page loads an
     * existing proposal) so the wrapping `wire:key` below changes and
     * Livewire destroys+recreates the <x-editor>'s DOM node instead of
     * leaving it alone — TallStackUI's editor marks itself `wire:ignore`
     * once bound to a wire:model (its internal Alpine state is meant to
     * survive re-renders untouched while the person is typing), so a
     * plain server-side append to $html would otherwise never reach the
     * screen. Forcing a fresh mount is the supported way to push a new
     * value into it from outside the editor itself.
     */
    public int $editorRevision = 0;

    public bool $showSnippetPicker = false;

    public function mount(Company $company, ?Proposal $proposal = null): void
    {
        abort_unless(auth()->user()->canAccessTenant($company), 403);

        $this->company = $company;

        app(Tenancy::class)->set($company);

        // Route binding for `proposal` happens before this mount() body
        // runs and before app(Tenancy::class)->set() above activates
        // BelongsToCompany's scope — same explicit re-check every other
        // TALL-stack detail page uses since this route sits outside
        // Filament's own tenant-scoped binding.
        if ($proposal) {
            abort_unless($proposal->company_id === $company->id, 404);

            $this->proposal = $proposal->loadMissing(['client', 'template', 'invoice']);
            $this->client_id = $proposal->client_id ? (string) $proposal->client_id : null;
            $this->title = $proposal->title;
            $this->status = $proposal->status->value;
            $this->amount = (float) $proposal->amount;
            $this->valid_until = $proposal->valid_until?->toDateString();
            $this->proposal_template_id = $proposal->proposal_template_id ? (string) $proposal->proposal_template_id : null;
            $this->html = $proposal->html;
            $this->css = $proposal->css;

            $this->initializeAutosaveVersion();
        }
    }

    /** Same "start from template" copy-once behavior as ProposalForm's own afterStateUpdated(). */
    public function updatedProposalTemplateId(?string $value): void
    {
        if (! $value) {
            return;
        }

        if ($template = ProposalTemplate::query()->where('company_id', $this->company->id)->find($value)) {
            $this->html = $template->html;
            $this->css = $template->css;
            $this->editorRevision++;
        }
    }

    // --- Draft autosave ---------------------------------------------------
    //
    // Wired only onto the free-form content fields — title, html, css —
    // never client_id, status, amount, valid_until, or proposal_template_id,
    // matching every other document form's own convention (see e.g.
    // App\Livewire\TallStackInvoiceForm's own "Draft autosave" section):
    // those either already go through save()'s own validation/redirect
    // path or carry business meaning this raw conditional-UPDATE autosave
    // deliberately never re-derives. This was the one remaining document
    // edit form without autosave — see App\Livewire\Concerns\AutosavesDraft's
    // docblock for the full algorithm.

    public function updatedTitle(): void
    {
        $this->autosaveDraft();
    }

    public function updatedHtml(): void
    {
        $this->autosaveDraft();
    }

    public function updatedCss(): void
    {
        $this->autosaveDraft();
    }

    protected function autosaveModel(): ?Model
    {
        return $this->proposal;
    }

    /** @return list<string> */
    protected function autosaveFields(): array
    {
        return ['title', 'html', 'css'];
    }

    /**
     * Same "Auditor is read-only everywhere" boundary save() enforces via
     * its own explicit $this->authorize('update', ...) call — autosave
     * must not become a side channel that bypasses it just because it
     * never routes through save()'s validated form submission.
     */
    protected function autosaveGuard(): bool
    {
        return $this->proposal !== null
            && $this->proposal->status === ProposalStatus::Draft
            && Gate::allows('update', $this->proposal);
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('company_id', $this->company->id)],
            'status' => ['required'],
            'amount' => ['required', 'numeric', 'min:0'],
            'valid_until' => ['nullable', 'date'],
            'proposal_template_id' => ['nullable', Rule::exists('proposal_templates', 'id')->where('company_id', $this->company->id)],
            'html' => ['nullable', 'string'],
            'css' => ['nullable', 'string'],
        ]);

        $payload = [
            'title' => $data['title'],
            'client_id' => $data['client_id'],
            'status' => $data['status'],
            'amount' => $data['amount'],
            'valid_until' => $data['valid_until'],
            'proposal_template_id' => $data['proposal_template_id'],
            'html' => $data['html'],
            'css' => $data['css'],
        ];

        if ($this->proposal) {
            $this->authorize('update', $this->proposal);

            $this->proposal->update($payload);
            $this->proposal->refresh();

            $this->toast()->success('Proposal saved.')->send();

            return;
        }

        $this->authorize('create', Proposal::class);

        $payload['company_id'] = $this->company->id;

        $proposal = Proposal::create($payload);

        $this->toast()->success('Proposal created.')->send();

        $this->redirect(route('tallstack.proposals.edit', [$this->company, $proposal]), navigate: false);
    }

    // --- Snippets ---------------------------------------------------

    public function openSnippetPicker(): void
    {
        $this->showSnippetPicker = true;
    }

    /**
     * Appends the chosen snippet's HTML to the end of the current
     * content — the editor itself has no external "insert at cursor" API
     * exposed for a picker outside the toolbar, so this always appends
     * rather than guessing a caret position (matches how the editor's
     * own toolbar-driven inserts behave: user context, not a silent
     * mid-document splice). Only staged on the component's own `$html`
     * property, same as every other field on this page — an explicit
     * Save persists it, it isn't written to the database on its own.
     */
    public function insertSnippet(int $id): void
    {
        $snippet = ProposalSnippet::query()->where('company_id', $this->company->id)->find($id);

        if (! $snippet) {
            return;
        }

        $this->html = ($this->html ?? '').$snippet->html;
        $this->editorRevision++;
        $this->showSnippetPicker = false;

        $this->toast()->success('Snippet inserted.', 'Added to the end of the content — save to keep it.')->send();
    }

    // --- Conversion ---------------------------------------------------

    /**
     * Gated only on "not already converted" (App\Services\ProposalConverter's
     * own check), exactly matching the equivalent pre-TallStackUI Filament
     * proposals table's "Convert to invoice" row action — there is no
     * server-side status
     * requirement to match here. The Blade view's "disabled until
     * Accepted" button (prompt 12's own spec) is a UI-only nudge on this
     * page, not a new business rule: it prevents a normal click before
     * Accepted without changing what the underlying service allows.
     */
    public function convertToInvoice(): void
    {
        if (! $this->proposal) {
            return;
        }

        $this->authorize('update', $this->proposal);

        try {
            $invoice = app(ProposalConverter::class)->convertToInvoice($this->proposal);

            $this->proposal->refresh();
            $this->toast()->success('Converted to invoice', "Created invoice {$invoice->number}.")->send();
        } catch (RuntimeException $e) {
            $this->toast()->error('Could not convert', $e->getMessage())->send();
        }
    }

    public function render(): View
    {
        $currency = $this->company->currency_code;

        return view('livewire.tallstack-proposal-form', [
            'clients' => Client::query()->where('company_id', $this->company->id)->orderBy('name')->get(['id', 'name']),
            'templates' => ProposalTemplate::query()->where('company_id', $this->company->id)->orderBy('name')->get(['id', 'name']),
            'snippets' => ProposalSnippet::query()->where('company_id', $this->company->id)->with('product')->orderBy('name')->get()
                ->map(fn (ProposalSnippet $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'thumbnail' => $s->product?->getImageDataUri(),
                ]),
            'statuses' => ProposalStatus::cases(),
            'statusColor' => StatusColor::map(ProposalStatus::from($this->status)->getColor()),
            'currency' => $currency,
            'amountFormatted' => Money::format($this->amount, $currency),
        ])->layoutData([
            'company' => $this->company,
            'active' => 'proposals',
            'title' => $this->proposal ? $this->proposal->title : 'New proposal',
        ]);
    }
}
