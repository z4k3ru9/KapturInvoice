<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Quotations', 'icon' => 'document-text']]" title="Quotations">
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
            <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['active'] }}</span>
            <x-slot:footer>Draft, approved or sent</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Awaiting decision" icon="clock" color="amber">
            <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['awaitingDecision'] }}</span>
            <x-slot:footer>Sent to client</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Expiring in 7 days" icon="exclamation-triangle" color="red">
            <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['expiringSoon'] }}</span>
            <x-slot:footer>Sent, valid until soon</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Acceptance rate" icon="check-circle" color="blue">
            <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['acceptanceRate'] === null ? '—' : $stats['acceptanceRate'].'%' }}</span>
            <x-slot:footer>Of decided quotations</x-slot:footer>
        </x-stats>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                {{-- Status filter pills — every real QuotationStatus case, not
                     an invented merged grouping, so this never implies a
                     state the enum doesn't have. --}}
                <x-tallstack.filter-dropdown
                    label="{{ $status === null ? 'All statuses' : collect($statuses)->firstWhere('value', $status)?->getLabel() }}"
                    :options="collect([['value' => null, 'label' => 'All statuses']])->concat(collect($statuses)->map(fn ($case) => ['value' => $case->value, 'label' => $case->getLabel()]))"
                    :active="$status"
                    method="filterStatus"
                />

                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <div class="w-full sm:w-64">
                        <x-input wire:model.live.debounce.400ms="search" placeholder="Search number or client…" icon="magnifying-glass" clearable />
                    </div>
                    <x-tallstack.quantity-select />
                </div>
            </div>
        </x-slot:header>

        {{-- Sortable: number, client (joined on `clients.name` in
             TallStackQuotations::render()) and quotation_date — the three
             dimensions requested. valid_until is a second real date column
             but not one of the three asked for, so — like total/status —
             it's marked sortable => false explicitly rather than left to
             the component's own sortable-by-default behavior. --}}
        <x-table :headers="[
            ['index' => 'number', 'label' => 'Number'],
            ['index' => 'client', 'label' => 'Client'],
            ['index' => 'quotation_date', 'label' => 'Date'],
            ['index' => 'valid_until', 'label' => 'Valid until', 'sortable' => false],
            ['index' => 'total', 'label' => 'Total', 'align' => 'right', 'sortable' => false],
            ['index' => 'status', 'label' => 'Status', 'sortable' => false],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$quotations" :sort="$sort" striped paginate loading>
            {{--
                Dense overview list: just the generated sequence (last 4
                digits), not the full company-type-year-month number — the
                module context (this table) already implies company/type,
                and the full number is one click away on the quotation's
                own page (its page-header title is never shortened).
                title="" gives the full number as a native hover tooltip.
            --}}
            @interact('column_number', $row)
                <span class="font-mono text-xs font-medium text-gray-700 dark:text-gray-200!" title="{{ $row['number'] }}">
                    {{ \App\Support\TallStack\DocumentNumber::short($row['number']) }}
                </span>
            @endinteract

            @interact('column_status', $row)
                <div class="flex items-center gap-1.5">
                    <x-badge text="{{ $row['status_label'] }}" :color="$row['status_color']" sm light />
                    {{-- Informational only — App\Livewire\Portal\SignQuotation
                         writes viewed_at the first time the client opens the
                         portal link; deliberately not a status value, see
                         quotations.viewed_at's own migration docblock. --}}
                    @if ($row['viewed_at'])
                        <x-icon name="eye" class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500!" title="Viewed {{ $row['viewed_at'] }}" />
                    @endif
                </div>
            @endinteract

            @interact('column_actions', $row, $company)
                @php
                    $primaryActions = [
                        ['text' => 'Open', 'icon' => 'eye', 'href' => route('tallstack.quotations.edit', [$company, $row['id']])],
                    ];
                    $extraActions = [
                        ['text' => 'Download PDF', 'icon' => 'document-arrow-down', 'href' => route('quotations.pdf', $row['id']), 'target' => '_blank'],
                        // Client-side clipboard copy of the public
                        // drawn-signature acceptance link (App\Livewire\Portal\SignQuotation)
                        // — same pattern as tallstack-client-portal-invitations.blade.php's
                        // own "Copy portal link" action.
                        ['text' => 'Copy client acceptance link', 'icon' => 'clipboard', 'xclick' => "window.navigator.clipboard.writeText('".route('portal.quotation', $row['portal_key'])."')"],
                    ];
                    if ($row['status'] === \App\Enums\QuotationStatus::Draft) {
                        $extraActions[] = ['text' => 'Approve', 'icon' => 'check-circle', 'color' => 'green', 'click' => 'approve('.$row['id'].')'];
                    }
                    if ($row['status'] === \App\Enums\QuotationStatus::Approved) {
                        $extraActions[] = ['text' => 'Send', 'icon' => 'paper-airplane', 'click' => 'send('.$row['id'].')'];
                    }
                    if ($row['status'] === \App\Enums\QuotationStatus::Sent) {
                        $extraActions[] = ['text' => 'Accept', 'icon' => 'hand-thumb-up', 'color' => 'green', 'click' => 'openAcceptModal('.$row['id'].')'];
                        $extraActions[] = ['text' => 'Reject', 'icon' => 'x-circle', 'color' => 'red', 'click' => 'reject('.$row['id'].')'];
                        // Manual "Mark expired" removed — a Sent quotation
                        // past its valid_until already expires
                        // automatically every night (the ExpireQuotations
                        // scheduled command, routes/console.php), so a
                        // manual early-trigger was redundant.
                    }
                    if ($row['status'] === \App\Enums\QuotationStatus::Accepted) {
                        $extraActions[] = ['text' => 'Create job', 'icon' => 'briefcase', 'click' => 'createJob('.$row['id'].')'];
                    }
                    if ($row['signed_at']) {
                        $extraActions[] = ['text' => 'View signature', 'icon' => 'pencil', 'click' => 'viewSignature('.$row['id'].')'];
                    }
                    if (! \App\Enums\QuotationStatus::from($row['status']->value)->isTerminal()) {
                        $extraActions[] = ['text' => 'Cancel', 'icon' => 'no-symbol', 'color' => 'red', 'click' => 'cancel('.$row['id'].')', 'confirm' => 'Cancel this quotation?'];
                    }
                    // Only ever shown for a Draft row — the guard's full
                    // predicate (no job created from it) is still
                    // re-checked server-side by App\Actions\Sales\ForceDeleteQuotation.
                    if ($row['status'] === \App\Enums\QuotationStatus::Draft) {
                        $extraActions[] = ['text' => 'Force delete', 'icon' => 'trash', 'color' => 'red', 'click' => 'forceDelete('.$row['id'].')', 'confirm' => 'Permanently delete this quotation? This cannot be undone.'];
                    }
                @endphp
                <x-tallstack.row-actions :primary="$primaryActions" :items="$extraActions" />
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
            <p class="text-sm text-gray-600 dark:text-gray-400!">
                Accepted by <strong>{{ $viewingSignature['signed_by_name'] }}</strong> on {{ $viewingSignature['signed_at'] }}.
            </p>
            @if ($viewingSignature['has_signature_image'])
                <img src="{{ $viewingSignature['signature'] }}" alt="Signature" class="mt-3 h-24 rounded border border-gray-200 dark:border-gray-700! bg-white">
            @endif
        @endif

        <x-slot:footer>
            <x-button text="Close" color="gray" wire:click="$set('showSignatureModal', false)" />
        </x-slot:footer>
    </x-modal>
</div>
