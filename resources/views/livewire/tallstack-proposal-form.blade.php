<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header
        :crumbs="[
            ['label' => $company->name, 'url' => route('tallstack.proposals', $company)],
            ['label' => 'Proposals', 'url' => route('tallstack.proposals', $company)],
            ['label' => $proposal ? $proposal->title : 'New'],
        ]"
        :title="$proposal ? $proposal->title : 'New proposal'"
    >
        <x-slot:badge>
            <x-badge text="{{ \App\Enums\ProposalStatus::from($status)->getLabel() }}" :color="$statusColor" sm />
        </x-slot:badge>
        <x-slot:actions>
            {{-- Wrapped in our own flex-wrap row + whitespace-nowrap on
                 each button — see TallStackProposals's own actions-slot
                 comment for why: the shared page-header's actions
                 container doesn't wrap on its own, so two buttons at a
                 narrow width would otherwise wrap their OWN label text
                 internally instead of wrapping as whole buttons. --}}
            <div class="flex flex-wrap items-center gap-2">
                @if ($proposal)
                    <x-button icon="document-arrow-down" text="Preview PDF" href="{{ route('proposals.pdf', $proposal) }}" target="_blank" color="gray" sm class="h-9 whitespace-nowrap" />
                @endif
                {{-- color="blue" — see TallStackQuotationForm's own comment:
                     a general-function button never borrows the tenant's
                     brand color, which is reserved for identity chrome. --}}
                <x-button text="Save" icon="document-check" color="blue" sm class="h-9 whitespace-nowrap" wire:click="save" />
            </div>
        </x-slot:actions>
    </x-tallstack.page-header>

    {{--
        "Convert to Invoice" — disabled with a tooltip until Accepted, per
        prompt 12's own spec, reinforcing the one-way/one-time conversion
        (App\Services\ProposalConverter never re-decided here). Already
        converted proposals show the resulting invoice number instead of
        the action, since a second conversion is refused by the service
        itself.
    --}}
    @if ($proposal)
        <div class="flex flex-wrap items-center gap-2">
            @if ($proposal->invoice_id)
                <x-badge text="Converted to invoice {{ $proposal->invoice->number }}" color="green" sm />
            @elseif (\App\Enums\ProposalStatus::from($status) === \App\Enums\ProposalStatus::Accepted)
                <x-button text="Convert to Invoice" icon="arrow-right-circle" color="green" sm wire:click="convertToInvoice" wire:confirm="Convert this proposal to an invoice? This cannot be undone." />
            @else
                <x-button text="Convert to Invoice" icon="arrow-right-circle" color="gray" sm disabled tooltip="Available once this proposal is Accepted" />
            @endif
        </div>
    @endif

    <div class="grid lg:grid-cols-[1fr_320px] gap-4 items-start">
        <div class="flex flex-col gap-4 min-w-0">
            {{-- Compact header strip — Title, Client, Template, Amount,
                 Valid until, plus Status (directly editable, matching
                 ProposalForm's own select — see TallStackProposalForm's
                 docblock for why this differs from Quotation's
                 action-only status). --}}
            <x-card>
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Proposal details</span>
                </x-slot:header>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <x-input wire:model="title" label="Title" required />
                    </div>
                    <x-select.styled wire:model="client_id" label="Client" searchable clearable
                        :options="$clients->map(fn ($c) => ['label' => $c->name, 'value' => (string) $c->id])->all()" />
                    <x-select.styled wire:model.live="proposal_template_id" label="Start from template" clearable
                        hint="Copies the template's content below — only applies once, when picked."
                        :options="$templates->map(fn ($t) => ['label' => $t->name, 'value' => (string) $t->id])->all()" />
                    <x-input wire:model="amount" label="Amount" type="number" step="0.01" required />
                    <x-date wire:model="valid_until" label="Valid until" />
                    <x-select.styled wire:model="status" label="Status" required
                        :options="collect($statuses)->map(fn ($s) => ['label' => $s->getLabel(), 'value' => $s->value])->all()" />
                </div>
            </x-card>

            {{-- Content — the rich-text/HTML editor, taking most of the
                 page height per prompt 12's spec. TallStackUI's own
                 native <x-editor> (bold/italic/headings/lists/table/
                 image toolbar built in) is reused as-is rather than
                 pulling in a new JS dependency — its own toolbar has no
                 extension point for a custom "Insert snippet" button, so
                 that action lives as its own button in this card's
                 header instead, opening the snippet picker modal below. --}}
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between w-full">
                        <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Content</span>
                        @if ($proposal)
                            <x-button text="Insert snippet" icon="squares-plus" color="gray" sm wire:click="openSnippetPicker" />
                        @endif
                    </div>
                </x-slot:header>

                @if (! $proposal)
                    <p class="text-sm text-gray-400">Save the proposal first to edit its content and insert snippets.</p>
                @else
                    <div wire:key="proposal-editor-{{ $proposal->id }}-{{ $editorRevision }}">
                        <x-editor wire:model="html" min-height="420px" placeholder="Write the proposal's cover letter / statement of work…" />
                    </div>

                    <div class="mt-4">
                        <x-textarea wire:model="css" label="Custom CSS (optional)" rows="4" class="font-mono text-xs" />
                    </div>
                @endif
            </x-card>
        </div>

        {{-- Summary sidebar --}}
        <div class="flex flex-col gap-4 min-w-0">
            <x-card>
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Summary</span>
                </x-slot:header>
                <div class="flex flex-col gap-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400!">Amount</span>
                        <span class="font-bold text-lg tabular-nums">{{ $amountFormatted }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400!">Valid until</span>
                        <span>{{ $valid_until ? \Illuminate\Support\Carbon::parse($valid_until)->format('d M Y') : '—' }}</span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">A proposal converts to exactly one invoice — its own line item, one-way, one-time (see the action above).</p>
                </div>
            </x-card>
        </div>
    </div>

    {{-- Insert-snippet modal — listing every ProposalSnippet by name with
         a small thumbnail preview, reusing the same square-thumbnail
         convention as Products (base64 data URI). Wrapped in a
         fixed-size block-level div (w-10 h-10 shrink-0), not a bare
         <img>, since TallStackUI's global `img{max-width:100%}` silently
         shrinks an unconstrained thumbnail inside a flex row otherwise —
         the same table-thumbnail quirk already documented for Phase 8's
         Products picker. --}}
    <x-modal wire="showSnippetPicker" title="Insert snippet" center="sm" scrollable>
        <div class="flex flex-col gap-2">
            @forelse ($snippets as $snippet)
                <button type="button" wire:click="insertSnippet({{ $snippet['id'] }})"
                        class="flex items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-800! p-2.5 text-left hover:bg-gray-50 dark:hover:bg-gray-800!">
                    <div class="w-10 h-10 shrink-0 rounded overflow-hidden bg-gray-100 dark:bg-gray-800! grid place-items-center">
                        @if ($snippet['thumbnail'])
                            <img src="{{ $snippet['thumbnail'] }}" alt="" class="w-full h-full object-cover">
                        @else
                            <x-icon name="rectangle-stack" class="w-4 h-4 text-gray-400" />
                        @endif
                    </div>
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-100!">{{ $snippet['name'] }}</span>
                </button>
            @empty
                <p class="text-sm text-gray-400">No proposal snippets yet.</p>
            @endforelse
        </div>

        <x-slot:footer>
            <x-button text="Close" color="gray" wire:click="$set('showSnippetPicker', false)" />
        </x-slot:footer>
    </x-modal>
</div>
