<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Proposals']]" title="Proposals">
        <x-slot:actions>
            {{-- Wrapped in our own flex-wrap row: the shared page-header's
                 own actions container (resources/views/components/
                 tallstack/page-header.blade.php) is a plain non-wrapping
                 `flex items-center gap-2` — with 3 buttons plus a long
                 "Proposal Templates"/"Proposal Snippets" label, a narrow
                 viewport had no room to grow so each button's own text
                 wrapped internally instead (2-line "Proposal Templates" /
                 "Proposal Snippets" buttons next to a 1-line "New
                 Proposal" button — jagged, mismatched heights). This inner
                 wrapper lets whole buttons wrap to the next line instead,
                 and `whitespace-nowrap` keeps each button's own label on
                 one line either way. --}}
            <div class="flex flex-wrap items-center gap-2">
                {{-- Secondary tab-style links to the two supporting library
                     screens — now real TallStackUI pages (prompt 23), no
                     longer placeholders into the Filament admin resources. --}}
                <x-button text="Proposal Templates" icon="document-text" color="gray" sm class="h-9 whitespace-nowrap" href="{{ route('tallstack.proposal-templates', $company) }}" />
                <x-button text="Proposal Snippets" icon="square-2-stack" color="gray" sm class="h-9 whitespace-nowrap" href="{{ route('tallstack.proposal-snippets', $company) }}" />
                {{-- color="blue", not "primary" — see app.blade.php's own
                     "+New" button for why: a general action shouldn't borrow
                     the tenant's brand color. --}}
                <x-button text="New Proposal" icon="plus" color="blue" sm class="h-9 whitespace-nowrap" href="{{ route('tallstack.proposals.create', $company) }}" />
            </div>
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                {{-- Status filter pills — every real ProposalStatus case,
                     not an invented merged grouping. --}}
                <x-tallstack.filter-dropdown
                    label="{{ $status === null ? 'All statuses' : collect($statuses)->firstWhere('value', $status)?->getLabel() }}"
                    :options="collect([['value' => null, 'label' => 'All statuses']])->concat(collect($statuses)->map(fn ($case) => ['value' => $case->value, 'label' => $case->getLabel()]))"
                    :active="$status"
                    method="filterStatus"
                />

                <div class="w-full sm:w-64">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search title or client…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'title', 'label' => 'Title'],
            ['index' => 'client', 'label' => 'Client'],
            ['index' => 'status', 'label' => 'Status'],
            ['index' => 'amount', 'label' => 'Amount', 'align' => 'right'],
            ['index' => 'valid_until', 'label' => 'Valid until'],
            ['index' => 'invoice_number', 'label' => 'Converted to invoice'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$proposals" paginate loading>
            @interact('column_status', $row)
                <x-badge text="{{ $row['status_label'] }}" :color="$row['status_color']" sm light />
            @endinteract

            {{-- "Converted to invoice" — a small link icon + invoice
                 number when set, a muted dash otherwise (prompt 12's own
                 wording), never an editable field. --}}
            @interact('column_invoice_number', $row)
                @if ($row['invoice_number'])
                    <span class="inline-flex items-center gap-1 text-xs font-medium text-gray-700 dark:text-gray-200!">
                        <x-icon name="link" class="w-3.5 h-3.5 shrink-0 text-gray-400" />
                        {{ $row['invoice_number'] }}
                    </span>
                @else
                    <span class="text-gray-300 dark:text-gray-600!">—</span>
                @endif
            @endinteract

            @interact('column_actions', $row, $company)
                @php
                    $extraActions = [];
                    $extraActions[] = ['text' => 'Edit', 'icon' => 'pencil-square', 'href' => route('tallstack.proposals.edit', [$company, $row['id']])];
                    $extraActions[] = ['text' => 'Duplicate', 'icon' => 'document-duplicate', 'click' => 'duplicate('.$row['id'].')'];
                    if ($row['status'] !== \App\Enums\ProposalStatus::Accepted) {
                        $extraActions[] = ['text' => 'Mark accepted', 'icon' => 'check-circle', 'color' => 'green', 'click' => 'markAccepted('.$row['id'].')'];
                    }
                    if ($row['status'] !== \App\Enums\ProposalStatus::Declined) {
                        $extraActions[] = ['text' => 'Mark declined', 'icon' => 'x-circle', 'color' => 'red', 'click' => 'markDeclined('.$row['id'].')', 'confirm' => 'Mark this proposal declined?'];
                    }
                @endphp
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="eye" href="{{ route('tallstack.proposals.edit', [$company, $row['id']]) }}" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Open" />
                    <x-tallstack.row-actions :items="$extraActions" />
                </div>
            @endinteract

            <x-slot:empty>No proposals yet — New Proposal to get started.</x-slot:empty>
        </x-table>
    </x-card>
</div>
