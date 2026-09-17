<div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-6">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Settings'], ['label' => 'Taxes']]" title="Taxes">
        <x-slot:actions>
            <x-button text="Save" icon="check" color="blue" sm class="h-9" wire:click="save" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-tallstack.settings-tabs :company="$company" active="taxes">
    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Taxes</span>
        </x-slot:header>

        {{--
            x-show, not @if — the toggle drives an instant client-side
            reveal of the rate/DPP fields via an Alpine mirror seeded
            from the server value, rather than a wire:model.live round
            trip (see the Company code preview's own comment elsewhere
            in this Settings area for why: a real, now-fixed
            vendor-interaction bug in <x-tallstack.settings-tabs>).
            Deliberately NOT touching Period Lock below — that's a
            general bookkeeping-integrity control, meaningful for a
            non-tax company too, not specific to PPN.
        --}}
        <div x-data="{ taxEnabled: @js($tax_enabled) }">
            <x-toggle wire:model="tax_enabled" label="Tax enabled" x-on:click="taxEnabled = !taxEnabled" />
            <p class="text-[11px] text-gray-400 mt-1">When off, every invoice for this company is calculated with zero tax — see App\Services\Tax\TaxCalculationService.</p>

            <div class="grid sm:grid-cols-3 gap-5 mt-5" x-show="taxEnabled">
                <x-input wire:model="standard_tax_rate" label="PPN rate" type="number" step="0.01" suffix="%" hint="Standard output/input VAT rate applied to standard-taxable lines." />
                <x-input wire:model="dpp_factor_numerator" label="DPP Nilai Lain — numerator" type="number" hint="e.g. 11" />
                <x-input wire:model="dpp_factor_denominator" label="DPP Nilai Lain — denominator" type="number" hint="e.g. 12" />
            </div>
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Period lock</span>
        </x-slot:header>

        {{--
            App\Services\PeriodLockService existed fully built (close/
            reopen, role-gated reopen with a required audited reason) but
            had no caller anywhere in the app until now — same shape as
            the Hold/Release-hold gap the Jobs page already had.
        --}}
        <div class="flex flex-col gap-5">
            @if ($periodLockedThrough)
                <div class="flex items-center justify-between gap-4 rounded-lg border border-amber-200 dark:border-amber-800! bg-amber-50 dark:bg-amber-950! px-4 py-3">
                    <div>
                        <p class="text-sm font-medium text-amber-800 dark:text-amber-300!">Closed through {{ \Illuminate\Support\Carbon::parse($periodLockedThrough)->toFormattedDateString() }}</p>
                        <p class="text-[11px] text-amber-700 dark:text-amber-400!">Dates on or before this are locked — reopening is Owner/Accountant only and requires a reason (audited).</p>
                    </div>
                    <x-button text="Reopen" icon="lock-open" color="amber" sm wire:click="openReopenModal" />
                </div>
            @else
                <p class="text-[11px] text-gray-400">No period is currently locked.</p>
            @endif

            <div class="grid sm:grid-cols-3 gap-5">
                <x-date wire:model="closeThroughDate" label="Close through date"
                    hint="Locks every date on or before this. Not itself destructive — any settings-capable role may close a period." />
                <div class="flex items-end">
                    <x-button text="Close period" icon="lock-closed" color="gray" wire:click="closePeriod" loading="closePeriod" spinner="dots" />
                </div>
            </div>
        </div>
    </x-card>
    </x-tallstack.settings-tabs>

    <x-modal wire="showReopenModal" title="Reopen locked period" center="sm">
        <p class="text-sm text-gray-600 dark:text-gray-400! mb-4">Owner/Accountant only. A reason is required and recorded in the audit log.</p>
        <x-textarea wire:model="reopenReason" label="Reason" required rows="3" />

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showReopenModal', false)" />
            <x-button text="Reopen" color="amber" wire:click="reopenPeriod" loading="reopenPeriod" spinner="dots" />
        </x-slot:footer>
    </x-modal>
</div>
