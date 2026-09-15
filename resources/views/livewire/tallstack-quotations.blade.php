<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Quotations']]" title="Quotations">
        <x-slot:actions>
            {{-- color="blue", not "primary" — see app.blade.php's own
                 "+New" button for why: a general action shouldn't borrow
                 the tenant's brand color. --}}
            <x-button text="New quotation" icon="plus" color="blue" sm class="h-9" href="{{ route('tallstack.quotations.create', $company) }}" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{-- Stat row — same "compact" x-stats scope as the Dashboard, matching the
         Stitch mockup's four-card summary (Active quotations / Awaiting
         decision / Expiring in 7 days / Acceptance rate), computed here from
         real Quotation rows rather than any new stored aggregate. --}}
    <div class="grid grid-cols-2 min-[820px]:!grid-cols-4 gap-2.5">
        <x-stats scope="compact" title="Active quotations" icon="document-text" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['active'] }}</span>
            <x-slot:footer>Draft, approved or sent</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Awaiting decision" icon="clock" color="amber">
            <span class="text-lg font-bold tabular-nums">{{ $stats['awaitingDecision'] }}</span>
            <x-slot:footer>Sent to client</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Expiring in 7 days" icon="exclamation-triangle" color="red">
            <span class="text-lg font-bold tabular-nums">{{ $stats['expiringSoon'] }}</span>
            <x-slot:footer>Sent, valid until soon</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Acceptance rate" icon="check-circle" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['acceptanceRate'] === null ? '—' : $stats['acceptanceRate'].'%' }}</span>
            <x-slot:footer>Of decided quotations</x-slot:footer>
        </x-stats>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                {{-- Status filter pills — every real QuotationStatus case, not
                     an invented merged grouping, so this never implies a
                     state the enum doesn't have. --}}
                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" wire:click="filterStatus(null)"
                            class="px-2.5 py-1 rounded-md text-xs font-semibold {{ $status === null ? 'bg-[color:var(--ts-primary)] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300' }}">
                        All
                    </button>
                    @foreach ($statuses as $case)
                        <button type="button" wire:click="filterStatus('{{ $case->value }}')"
                                class="px-2.5 py-1 rounded-md text-xs font-semibold {{ $status === $case->value ? 'bg-[color:var(--ts-primary)] text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300' }}">
                            {{ $case->getLabel() }}
                        </button>
                    @endforeach
                </div>

                <div class="w-full sm:w-64">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search number or client…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'number', 'label' => 'Number'],
            ['index' => 'client', 'label' => 'Client'],
            ['index' => 'quotation_date', 'label' => 'Date'],
            ['index' => 'valid_until', 'label' => 'Valid until'],
            ['index' => 'total', 'label' => 'Total', 'align' => 'right'],
            ['index' => 'status', 'label' => 'Status'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$quotations" paginate loading>
            {{--
                Dense overview list: just the generated sequence (last 4
                digits), not the full company-type-year-month number — the
                module context (this table) already implies company/type,
                and the full number is one click away on the quotation's
                own page (its page-header title is never shortened).
                title="" gives the full number as a native hover tooltip.
            --}}
            @interact('column_number', $row)
                <span class="font-mono text-xs font-medium text-gray-700 dark:text-gray-200" title="{{ $row['number'] }}">
                    {{ \App\Support\TallStack\DocumentNumber::short($row['number']) }}
                </span>
            @endinteract

            @interact('column_status', $row)
                <x-badge text="{{ $row['status_label'] }}" :color="$row['status_color']" sm light />
            @endinteract

            @interact('column_actions', $row, $company)
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="eye" href="{{ route('tallstack.quotations.edit', [$company, $row['id']]) }}" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Open" />
                    <x-button icon="document-arrow-down" href="{{ route('quotations.pdf', $row['id']) }}" target="_blank" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Download PDF" />
                    {{-- Client-side clipboard copy of the public
                         drawn-signature acceptance link
                         (App\Livewire\Portal\SignQuotation) — same
                         pattern as tallstack-client-portal-invitations.blade.php's
                         own "Copy portal link" action. --}}
                    <button type="button"
                            x-on:click="window.navigator.clipboard.writeText('{{ route('portal.quotation', $row['portal_key']) }}')"
                            title="Copy client acceptance link"
                            class="inline-flex items-center justify-center h-9 w-9 rounded-md text-gray-600 dark:text-gray-300 hover:text-[color:var(--ts-primary)] hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-700 transition-colors">
                        <x-icon name="clipboard" class="w-4 h-4" />
                    </button>
                    <x-dropdown icon="ellipsis-vertical" scope="row-action">
                        @if ($row['status'] === \App\Enums\QuotationStatus::Draft)
                            <x-dropdown.items text="Approve" icon="check-circle" wire:click="approve({{ $row['id'] }})" />
                        @endif
                        @if ($row['status'] === \App\Enums\QuotationStatus::Approved)
                            <x-dropdown.items text="Send" icon="paper-airplane" wire:click="send({{ $row['id'] }})" />
                        @endif
                        @if ($row['status'] === \App\Enums\QuotationStatus::Sent)
                            <x-dropdown.items text="Accept" icon="hand-thumb-up" wire:click="openAcceptModal({{ $row['id'] }})" />
                            <x-dropdown.items text="Reject" icon="x-circle" wire:click="reject({{ $row['id'] }})" />
                            <x-dropdown.items text="Mark expired" icon="clock" wire:click="markExpired({{ $row['id'] }})" />
                        @endif
                        @if ($row['status'] === \App\Enums\QuotationStatus::Accepted)
                            <x-dropdown.items text="Create job" icon="briefcase" wire:click="createJob({{ $row['id'] }})" />
                        @endif
                        @if ($row['signed_at'])
                            <x-dropdown.items text="View signature" icon="pencil" wire:click="viewSignature({{ $row['id'] }})" />
                        @endif
                        @if (! \App\Enums\QuotationStatus::from($row['status']->value)->isTerminal())
                            <x-dropdown.items text="Cancel" icon="no-symbol" wire:click="cancel({{ $row['id'] }})" wire:confirm="Cancel this quotation?" />
                        @endif
                        {{-- Only ever shown for a Draft row — the guard's
                             full predicate (no job created from it) is
                             still re-checked server-side by
                             App\Actions\Sales\ForceDeleteQuotation. --}}
                        @if ($row['status'] === \App\Enums\QuotationStatus::Draft)
                            <x-dropdown.items text="Force delete" icon="trash" wire:click="forceDelete({{ $row['id'] }})" wire:confirm="Permanently delete this quotation? This cannot be undone." />
                        @endif
                    </x-dropdown>
                </div>
            @endinteract

            <x-slot:empty>No quotations found.</x-slot:empty>
        </x-table>
    </x-card>

    {{-- Accept — the only status transition that needs input (an optional
         customer PO number/date; blank generates a system Customer Order
         Confirmation, exactly like AcceptQuotation itself decides — never
         re-decided here). --}}
    <x-modal wire="showAcceptModal" title="Accept quotation" center="sm">
        <div class="flex flex-col gap-4">
            <x-input wire:model="customerPoNumber" label="Customer PO number" hint="Leave blank to generate an internal Customer Order Confirmation instead." />
            <x-date wire:model="customerPoDate" label="Customer PO date" />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showAcceptModal', false)" />
            <x-button text="Accept" color="green" wire:click="accept" />
        </x-slot:footer>
    </x-modal>

    <x-modal wire="showSignatureModal" title="Signature" center="sm">
        @if ($viewingSignature)
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Accepted by <strong>{{ $viewingSignature['signed_by_name'] }}</strong> on {{ $viewingSignature['signed_at'] }}.
            </p>
            @if ($viewingSignature['has_signature_image'])
                <img src="{{ $viewingSignature['signature'] }}" alt="Signature" class="mt-3 h-24 rounded border border-gray-200 dark:border-gray-700 bg-white">
            @endif
        @endif

        <x-slot:footer>
            <x-button text="Close" color="gray" wire:click="$set('showSignatureModal', false)" />
        </x-slot:footer>
    </x-modal>
</div>
