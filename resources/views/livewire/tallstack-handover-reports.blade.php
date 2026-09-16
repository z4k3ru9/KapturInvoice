<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Handover Reports']]" title="Handover Reports" />

    {{-- Stat row — same "compact" x-stats convention as the Delivery
         Orders register beside it. No status badge column below either:
         a HandoverReport is only ever written, complete, through
         App\Actions\Delivery\CompleteHandover — there is no draft/pending
         handover to filter by, only whether it was an Owner/Admin
         override. --}}
    <div class="grid grid-cols-2 min-[820px]:!grid-cols-4 gap-2.5">
        <x-stats scope="compact" title="Total handovers" icon="document-check" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['total'] }}</span>
            <x-slot:footer>All recorded handovers</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="This month" icon="calendar-days" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['thisMonth'] }}</span>
            <x-slot:footer>Handed over so far this month</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Overrides" icon="exclamation-triangle" color="amber">
            <span class="text-lg font-bold tabular-nums">{{ $stats['overrides'] }}</span>
            <x-slot:footer>Owner/Admin completeness override</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Jobs served" icon="briefcase" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['distinctJobs'] }}</span>
            <x-slot:footer>Distinct jobs with a handover</x-slot:footer>
        </x-stats>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">All handover reports</span>
                <div class="w-full sm:w-64">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search number, job or client…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'number', 'label' => 'Number'],
            ['index' => 'job_number', 'label' => 'Job'],
            ['index' => 'client', 'label' => 'Client'],
            ['index' => 'handover_date', 'label' => 'Date'],
            ['index' => 'is_override', 'label' => 'Override'],
            ['index' => 'created_by', 'label' => 'Recorded by'],
            ['index' => 'signed_at', 'label' => 'Signature'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$handoverReports" paginate loading>
            @interact('column_number', $row)
                <span class="font-mono text-xs font-medium text-gray-700 dark:text-gray-200!" title="{{ $row['number'] }}">
                    {{ \App\Support\TallStack\DocumentNumber::short($row['number']) }}
                </span>
            @endinteract

            @interact('column_job_number', $row, $company)
                @if ($row['job_id'])
                    <a href="{{ route('tallstack.jobs.show', [$company, $row['job_id']]) }}" class="text-xs font-medium text-[color:var(--ts-primary)] hover:underline">
                        {{ $row['job_number'] }}
                    </a>
                @else
                    <span class="text-xs text-gray-400">—</span>
                @endif
            @endinteract

            @interact('column_is_override', $row)
                @if ($row['is_override'])
                    <span title="{{ $row['override_reason'] }}">
                        <x-badge text="Override" color="amber" sm />
                    </span>
                @else
                    <span class="text-xs text-gray-400">—</span>
                @endif
            @endinteract

            {{-- Client e-signature captured on the public portal link
                 (App\Livewire\Portal\SignHandoverReport) — same
                 drawn-signature capability as the invoice portal,
                 extended to Handover Reports. This register has no
                 separate detail page, so it's surfaced here directly. --}}
            @interact('column_signed_at', $row)
                @if ($row['signed_at'])
                    <button type="button" wire:click="viewSignature({{ $row['id'] }})" class="flex items-center gap-1.5 text-green-600 dark:text-green-400! hover:underline">
                        <x-icon name="check-circle" class="w-4 h-4" />
                        <span class="text-xs font-medium">{{ $row['signed_at'] }}</span>
                    </button>
                @else
                    <span class="text-xs text-gray-400">Not signed</span>
                @endif
            @endinteract

            @interact('column_actions', $row, $company)
                @php
                    $extraActions = [];
                    if ($row['job_id']) {
                        $extraActions[] = ['text' => 'Open job', 'icon' => 'briefcase', 'href' => route('tallstack.jobs.show', [$company, $row['job_id']])];
                    }
                    // Client-side clipboard copy (App\Livewire\Portal\SignHandoverReport)
                    // — same pattern as tallstack-quotations.blade.php's own
                    // "Copy client acceptance link". Passed through row-actions
                    // as a raw x-on:click since it's not a wire:click.
                    $extraActions[] = ['text' => 'Copy client signing link', 'icon' => 'clipboard', 'xclick' => "window.navigator.clipboard.writeText('".route('portal.handover-report', $row['portal_key'])."')"];
                @endphp
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="document-arrow-down" href="{{ route('handover-reports.pdf', $row['id']) }}" target="_blank" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Download PDF" />
                    <x-tallstack.row-actions :items="$extraActions" />
                </div>
            @endinteract

            <x-slot:empty>No handover reports recorded yet.</x-slot:empty>
        </x-table>
    </x-card>

    <x-modal wire="showSignatureModal" title="Signature" center="sm">
        @if ($viewingSignature)
            <p class="text-sm text-gray-600 dark:text-gray-400!">
                Confirmed by <strong>{{ $viewingSignature['signed_by_name'] }}</strong> on {{ $viewingSignature['signed_at'] }}.
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
