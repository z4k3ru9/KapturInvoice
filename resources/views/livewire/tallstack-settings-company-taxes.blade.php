<div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-6">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Settings'], ['label' => 'Company & Taxes']]" title="Company & Taxes">
        <x-slot:actions>
            <x-button text="Save" icon="check" color="blue" sm class="h-9" wire:click="save" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-tallstack.settings-tabs :company="$company" active="company-and-taxes">
    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Identity</span>
        </x-slot:header>

        <div class="flex flex-col gap-5">
            <div class="grid sm:grid-cols-2 gap-5">
                <x-input wire:model="name" label="Company name" required />
                <x-input wire:model="slug" label="Slug" required />
                <x-input wire:model="domain" label="Public homepage domain" />
                <x-input wire:model="email" label="Email" type="email" />
                <x-input wire:model="phone" label="Phone" />
                <x-input wire:model="tax_number" label="Tax ID" hint="Printed on invoice/credit PDFs." />
            </div>

            {{--
                Owner-only — App\Models\User::canAccessTenant() checks
                is_active unconditionally, even for a super-admin, so
                turning this off locks EVERYONE (including whoever just
                clicked it) out of this company immediately, not just
                eventually. wire:confirm is the client-side half of that
                warning; TallStackSettingsCompanyTaxes::save() re-checks
                the Owner role server-side before actually writing it.
            --}}
            <div class="border-t border-gray-200 dark:border-gray-800! pt-5">
                <x-toggle wire:model="is_active" label="Company active"
                    wire:confirm="Deactivating blocks login and portal access for EVERYONE at this company immediately, including you. Are you sure?" />
                <p class="text-[11px] text-gray-400 mt-1">Off blocks admin login and the public portal entirely for this company. Owner only.</p>
            </div>
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Address</span>
        </x-slot:header>

        {{-- Printed on the public homepage's "Get in touch" strip
             (resources/views/livewire/home-page.blade.php) and every
             generated PDF's company header — same field set/validation as
             TallStackVendors's own address block. --}}
        <div class="grid sm:grid-cols-2 gap-5">
            <x-input wire:model="address_line_1" label="Address line 1" class="sm:col-span-2" />
            <x-input wire:model="address_line_2" label="Address line 2" class="sm:col-span-2" />
            <x-input wire:model="city" label="City" />
            <x-input wire:model="state" label="State" />
            <x-input wire:model="postal_code" label="Postal code" />
            <x-input wire:model="country_code" label="Country code" maxlength="2" />
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Document numbering</span>
        </x-slot:header>

        <div class="grid sm:grid-cols-3 gap-5">
            <div>
                @if ($codesLocked)
                    <x-input wire:model="code" label="Company code" disabled
                        hint="Locked: this company has already issued a numbered document." />
                @else
                    {{--
                        Live preview, computed entirely client-side (no
                        wire:model.live round-trip) — <x-tallstack.settings-tabs>'s
                        route-based active-tab detection compares
                        request()->url() against each tab's own href, which is
                        only ever true on the page's own initial GET; on any
                        Livewire AJAX request (a .live update, or even the
                        existing tax_enabled toggle's own wire:model.live) that
                        URL is the Livewire update endpoint instead, so nothing
                        matches and the whole tab panel — not just this field —
                        renders empty. Confirmed live in-browser: typing into a
                        wire:model.live field on this page blanks the entire
                        panel. Real vendor-interaction bug, not something to
                        route more Livewire traffic through — see
                        docs/out-of-scope-findings.md. Doing this preview
                        client-side sidesteps it entirely and is instant besides.
                    --}}
                    <div x-data="{ code: @js($code) }">
                        <x-input wire:model="code" label="Company code" x-on:input="code = $event.target.value" />
                        <span class="dark:text-gray-400! mt-1 block text-sm text-gray-500"
                            x-text="'Used in new document numbers, e.g. ' + (code || 'COM').toUpperCase() + '-INV-{{ now()->format('Ym') }}0001. Locks after the first document is issued.'"></span>
                    </div>
                @endif
            </div>
            <x-input wire:model="invoice_prefix" label="Invoice prefix" />
            <x-input wire:model="quote_prefix" label="Quote prefix" />
            <x-input wire:model="credit_prefix" label="Credit prefix" />
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between w-full">
                <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Payment method</span>
                <x-button text="Add bank account" icon="plus" color="gray" sm class="h-9" wire:click="openCreateBankAccountModal" />
            </div>
        </x-slot:header>

        {{-- Printed as a "Payment Method" section on the Invoice PDF
             (resources/views/pdf/invoice.blade.php) — a company may list
             more than one bank account, e.g. two different banks. --}}
        <x-list :items="$bankAccounts" compact>
            @interact('item_menu', $item)
                <x-dropdown.items text="Edit" icon="pencil" wire:click="openEditBankAccountModal({{ $item['id'] }})" />
                <x-dropdown.items text="Delete" icon="trash" separator wire:click="deleteBankAccount({{ $item['id'] }})" wire:confirm="Remove this bank account? It will no longer be printed on invoices." />
            @endinteract

            <x-slot:empty>No bank accounts yet — add one to have it printed on invoices.</x-slot:empty>
        </x-list>
    </x-card>

    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Taxes</span>
        </x-slot:header>

        {{--
            x-show, not @if — `wire:model.live` would hit the same
            settings-tabs panel-blanking bug the Company code preview's
            own comment documents above. `wire:model` (deferred) still
            carries the real value to save(); the Alpine-only `taxEnabled`
            mirror just drives the instant client-side show/hide, seeded
            from the server value at mount.
        --}}
        <div class="flex flex-col gap-5" x-data="{ taxEnabled: @js($tax_enabled) }">
            <div>
                <x-toggle wire:model="tax_enabled" label="Tax enabled" x-on:click="taxEnabled = !taxEnabled" />
                <p class="text-[11px] text-gray-400 mt-1">When off, every invoice for this company is calculated with zero tax — see App\Services\Tax\TaxCalculationService.</p>
            </div>

            <div class="grid sm:grid-cols-3 gap-5" x-show="taxEnabled">
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

    <x-card>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Dashboard</span>
        </x-slot:header>

        <div class="max-w-xs">
            <x-select.styled wire:model="dashboard_refresh_seconds" label="Auto-refresh"
                :options="[
                    ['value' => 0, 'label' => 'Off — refresh manually'],
                    ['value' => 30, 'label' => 'Every 30 seconds'],
                    ['value' => 60, 'label' => 'Every minute'],
                    ['value' => 120, 'label' => 'Every 2 minutes'],
                    ['value' => 300, 'label' => 'Every 5 minutes'],
                ]" />
            <p class="text-[11px] text-gray-400 mt-1">How often the Dashboard's stats and chart pull fresh data on their own, in the background — applies to everyone viewing this company's dashboard.</p>
        </div>
    </x-card>
    </x-tallstack.settings-tabs>

    <x-modal wire="showBankAccountModal" :title="$editingBankAccountId ? 'Edit bank account' : 'Add bank account'" center="sm">
        <div class="grid sm:grid-cols-2 gap-5">
            <x-input wire:model="ba_bank_name" label="Bank name" required />
            <x-input wire:model="ba_account_name" label="Account holder name" required />
            <x-input wire:model="ba_account_number" label="Account number" required />
            <x-input wire:model="ba_branch" label="Branch" />
            <x-input wire:model="ba_swift_code" label="SWIFT / BIC code" hint="Optional — for international transfers." />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showBankAccountModal', false)" />
            <x-button text="Save" color="blue" wire:click="saveBankAccount" loading="saveBankAccount" spinner="dots" />
        </x-slot:footer>
    </x-modal>

    <x-modal wire="showReopenModal" title="Reopen locked period" center="sm">
        <p class="text-sm text-gray-600 dark:text-gray-400! mb-4">Owner/Accountant only. A reason is required and recorded in the audit log.</p>
        <x-textarea wire:model="reopenReason" label="Reason" required rows="3" />

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showReopenModal', false)" />
            <x-button text="Reopen" color="amber" wire:click="reopenPeriod" loading="reopenPeriod" spinner="dots" />
        </x-slot:footer>
    </x-modal>
</div>
