<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Client Portal Invitations']]" title="Client Portal Invitations">
        <x-slot:badge>
            <x-badge text="Read-only audit register" color="gray" sm />
        </x-slot:badge>
    </x-tallstack.page-header>

    {{-- No "New invitation" action anywhere on this page — invitations are
         generated automatically when an invoice is sent
         (App\Services\BillingMailer), never created by hand here. Same
         muted-explanatory-line convention as TallStackCredits. --}}
    <p class="text-xs text-gray-500 dark:text-gray-400! -mt-3">
        Invitations are generated automatically when an invoice is sent and are not created from this screen.
    </p>

    <div class="grid grid-cols-2 min-[820px]:!grid-cols-3 gap-2.5">
        <x-stats scope="compact" title="Total invitations" icon="link" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['total'] }}</span>
            <x-slot:footer>Ever generated</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Viewed" icon="eye" color="green">
            <span class="text-lg font-bold tabular-nums">{{ $stats['viewed'] }}</span>
            <x-slot:footer>Opened by a contact</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Signed" icon="pencil" color="green">
            <span class="text-lg font-bold tabular-nums">{{ $stats['signed'] }}</span>
            <x-slot:footer>E-signed on the portal</x-slot:footer>
        </x-stats>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <div class="w-full sm:w-72">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search invoice #, contact name or email…" icon="magnifying-glass" clearable />
                </div>

                <x-tallstack.filter-dropdown
                    label="{{ ['viewed' => 'Viewed', 'signed' => 'Signed'][$filter] ?? 'All' }}"
                    :options="[['value' => null, 'label' => 'All'], ['value' => 'viewed', 'label' => 'Viewed'], ['value' => 'signed', 'label' => 'Signed']]"
                    :active="$filter"
                    method="filterBy"
                />
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'invoice_number', 'label' => 'Invoice'],
            ['index' => 'contact_name', 'label' => 'Contact'],
            ['index' => 'sent_at', 'label' => 'Sent'],
            ['index' => 'viewed_at', 'label' => 'Viewed'],
            ['index' => 'signed_at', 'label' => 'Signed'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$invitations" paginate loading>
            @interact('column_invoice_number', $row, $company)
                @if ($row['invoice_id'])
                    <a href="{{ route('tallstack.invoices.edit', [$company, $row['invoice_id']]) }}" class="text-xs font-medium text-[color:var(--ts-primary)] hover:underline">
                        {{ $row['invoice_number'] }}
                    </a>
                @else
                    <span class="text-xs text-gray-400">—</span>
                @endif
            @endinteract

            @interact('column_contact_name', $row)
                <div class="flex flex-col">
                    <span class="text-xs font-medium text-gray-900 dark:text-gray-100!">{{ $row['contact_name'] }}</span>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400!">{{ $row['contact_email'] }}</span>
                </div>
            @endinteract

            @interact('column_sent_at', $row)
                <span class="text-xs text-gray-600 dark:text-gray-300!">{{ $row['sent_at'] ?? '—' }}</span>
            @endinteract

            @interact('column_viewed_at', $row)
                @if ($row['is_viewed'])
                    <div class="flex flex-col gap-1">
                        <x-badge text="Viewed" color="green" sm />
                        <span class="text-[11px] text-gray-500 dark:text-gray-400!">{{ $row['viewed_at'] }}</span>
                    </div>
                @else
                    <div class="flex flex-col gap-1">
                        <x-badge text="Not yet viewed" color="gray" sm />
                        <span class="text-[11px] text-gray-400">—</span>
                    </div>
                @endif
            @endinteract

            @interact('column_signed_at', $row)
                @if ($row['is_signed'])
                    <div class="flex items-center gap-1.5 text-green-600 dark:text-green-400!">
                        <x-icon name="check-circle" class="w-4 h-4" />
                        <span class="text-xs font-medium">{{ $row['signed_at'] }}</span>
                    </div>
                @else
                    <span class="text-xs text-gray-400">—</span>
                @endif
            @endinteract

            @interact('column_actions', $row)
                <div class="flex items-center justify-end gap-2">
                    {{--
                        Same client-side clipboard-copy pattern as the
                        equivalent resource's own "Copy portal link" row
                        action from the pre-TallStackUI Filament admin — no
                        server round-trip, the invitation's key is never
                        mutated.
                    --}}
                    <button type="button"
                            x-on:click="window.navigator.clipboard.writeText('{{ url('/portal/'.$row['key']) }}')"
                            title="Copy invoice magic link credential"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-semibold text-gray-600 dark:text-gray-300! hover:text-[color:var(--ts-primary)] hover:bg-gray-100 dark:hover:bg-gray-800! border border-gray-200 dark:border-gray-700! transition-colors">
                        <x-icon name="clipboard" class="w-3.5 h-3.5" />
                        Copy portal link
                    </button>
                </div>
            @endinteract

            <x-slot:empty>No portal invitations sent yet.</x-slot:empty>
        </x-table>
    </x-card>
</div>
