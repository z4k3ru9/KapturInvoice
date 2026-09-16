<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Credits', 'icon' => 'receipt-refund']]" title="Credits">
        <x-slot:badge>
            <x-badge text="Read-only register" color="gray" sm />
        </x-slot:badge>
    </x-tallstack.page-header>

    {{-- No "New Credit" action anywhere on this page — per prompt 15
         (docs/rebuild/outputs/ui-rebuild/26-stitch-missing-screens-prompts.md) and
         FINALIZED-DECISIONS.md §7, new credit-note creation is deferred
         scope. A muted explanatory line rather than a disabled/greyed-out
         button is the deliberate UX: it explains the screen's own limits
         instead of reading as unfinished. --}}
    <p class="text-xs text-gray-500 dark:text-gray-400! -mt-3">
        Credits are historical records from data import and are not created from this screen.
    </p>

    {{-- Two stacked rows: the three currency (Rupiah) cards get their own
         wider row, the one plain-count card keeps a tight row below — same
         col-span-full pattern as the Dashboard's own stat row; the outer
         wrapper's own classes are unchanged. Title + number only, no footer. --}}
    <div class="grid grid-cols-2 min-[820px]:!grid-cols-4 gap-2.5">
        <div class="col-span-full grid grid-cols-1 sm:grid-cols-3 gap-2.5">
            <x-stats scope="compact" title="Total" icon="banknotes" color="blue">
                <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['total'] }}</span>
            </x-stats>

            <x-stats scope="compact" title="Linked" icon="link" color="green">
                <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['linked'] }}</span>
            </x-stats>

            <x-stats scope="compact" title="Unlinked" icon="exclamation-triangle" color="amber">
                <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['unlinkedBalance'] }}</span>
            </x-stats>
        </div>
        <div class="col-span-full grid grid-cols-1 gap-2.5">
            <x-stats scope="compact" title="Credits" icon="receipt-refund" color="blue">
                <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['count'] }}</span>
            </x-stats>
        </div>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <div class="flex items-center gap-2 w-full sm:flex-1 sm:min-w-[240px]">
                    <div class="flex-1 min-w-0">
                        <x-input wire:model.live.debounce.400ms="search" placeholder="Search number, client or invoice…" icon="magnifying-glass" clearable />
                    </div>
                    <x-tallstack.quantity-select />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'number', 'label' => 'Number'],
            ['index' => 'client', 'label' => 'Client'],
            ['index' => 'invoice_number', 'label' => 'Related invoice', 'sortable' => false],
            ['index' => 'amount', 'label' => 'Amount', 'align' => 'right'],
            ['index' => 'credit_date', 'label' => 'Credit date'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$credits" :sort="$sort" striped paginate loading>
            {{--
                Same dense-number convention as every other TALL-stack
                register (see App\Support\TallStack\DocumentNumber's own
                docblock) — the full number is a hover tooltip and one
                click away on the credit's own View page.
            --}}
            @interact('column_number', $row)
                <span class="font-mono text-xs font-medium text-gray-700 dark:text-gray-200!" title="{{ $row['number'] }}">
                    {{ \App\Support\TallStack\DocumentNumber::short($row['number']) }}
                </span>
            @endinteract

            @interact('column_invoice_number', $row, $company)
                @if ($row['invoice_id'])
                    <a href="{{ route('tallstack.invoices.edit', [$company, $row['invoice_id']]) }}" class="text-xs font-medium text-[color:var(--ts-primary)] hover:underline">
                        {{ $row['invoice_number'] ?? '—' }}
                    </a>
                @else
                    <span class="text-xs text-gray-400">—</span>
                @endif
            @endinteract

            @interact('column_amount', $row)
                <span class="text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100!">{{ $row['amount'] }}</span>
            @endinteract

            @interact('column_actions', $row, $company)
                @php
                    // No TALL-stack detail page exists for a single
                    // Credit (this phase is the register only) — the
                    // previous "View" link here pointed at
                    // `filament.admin.resources.credits.view`, a route
                    // that no longer exists now that Filament has been
                    // fully removed (a real, previously-unknown 500-on-
                    // every-row bug, found while wiring the Force delete
                    // action below — see the ForceDeleteCredit rollout
                    // report). Removed rather than left dead; the
                    // register table itself already shows every column
                    // this would have.
                    $extraActions = [];
                    // Only ever shown for an unapplied credit — the
                    // guard's full predicate (balance still equals face
                    // amount) is still re-checked server-side by
                    // App\Actions\Billing\ForceDeleteCredit.
                    if ($row['unapplied']) {
                        $extraActions[] = ['text' => 'Force delete', 'icon' => 'trash', 'color' => 'red', 'click' => 'forceDelete('.$row['id'].')', 'confirm' => 'Permanently delete this credit? This cannot be undone.'];
                    }
                @endphp
                <x-tallstack.row-actions :items="$extraActions" />
            @endinteract

            <x-slot:empty>No credits on record for this company.</x-slot:empty>
        </x-table>
    </x-card>
</div>
