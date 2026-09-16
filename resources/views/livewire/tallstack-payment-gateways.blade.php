<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Settings'], ['label' => 'Payment Gateways']]" title="Payment Gateways">
        <x-slot:actions>
            <x-button text="New gateway" icon="plus" color="blue" sm class="h-9" wire:click="create" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <div class="grid grid-cols-2 gap-2.5">
        <x-stats scope="compact" title="Configured gateways" icon="credit-card" color="blue">
            <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['total'] }}</span>
            <x-slot:footer>All drivers</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Enabled" icon="bolt" color="green">
            <span class="dark:text-gray-300! text-lg font-bold tabular-nums break-words">{{ $stats['enabled'] }}</span>
            <x-slot:footer>Accepting live settlements</x-slot:footer>
        </x-stats>
    </div>

    <x-card>
        <x-slot:header>
            <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100!">Configured gateways</span>
        </x-slot:header>

        <x-list :items="$gateways" searchable search-placeholder="Search gateways…">
            @interact('item_caption', $item)
                <div class="flex flex-wrap items-center gap-1.5">
                    <x-badge text="{{ str($item['driver'])->replace('_', ' ')->title() }}" color="gray" sm />
                    <x-badge :text="$item['is_enabled'] ? 'Enabled' : 'Disabled'" :color="$item['is_enabled'] ? 'green' : 'gray'" sm />
                    @foreach ($item['accepted_credit_cards'] as $card)
                        <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 dark:bg-gray-800! px-1.5 py-0.5 text-[11px] font-medium text-gray-600 dark:text-gray-300!">
                            <x-icon name="credit-card" class="w-3 h-3 shrink-0" />
                            {{ strtoupper($card) }}
                        </span>
                    @endforeach
                </div>
            @endinteract

            @interact('item_action', $item, $testResults)
                <div class="flex flex-col items-end gap-1.5">
                    {{--
                        "Test Connection" gets a distinct amber/bolt
                        styling — a live request, not a form-opening
                        action — per prompt 20's own framing ("a
                        distinct button style with a small plug/bolt
                        icon, since it fires a live request rather than
                        opening a form").
                    --}}
                    <x-button icon="bolt" sm color="amber" scope="icon-action" class="h-9 w-9" wire:click="testConnection({{ $item['id'] }})" wire:loading.attr="disabled" wire:target="testConnection({{ $item['id'] }})" tooltip="Test connection" />

                    @if (isset($testResults[$item['id']]))
                        @php($result = $testResults[$item['id']])
                        <div class="flex items-start gap-1.5 rounded-md px-2 py-1 text-[11px] font-medium w-56 text-right whitespace-normal break-words
                            {{ $result['success'] ? 'bg-green-50 text-green-700 dark:bg-green-950! dark:text-green-400!' : 'bg-red-50 text-red-700 dark:bg-red-950! dark:text-red-400!' }}">
                            <x-icon :name="$result['success'] ? 'check-circle' : 'x-circle'" class="w-3 h-3 shrink-0 mt-0.5" />
                            <span class="text-left">{{ $result['title'] }}@if ($result['message']) — {{ $result['message'] }} @endif</span>
                        </div>
                    @endif
                </div>
            @endinteract

            @interact('item_menu', $item)
                <x-dropdown.items text="Edit" icon="pencil" wire:click="edit({{ $item['id'] }})" />
            @endinteract

            <x-slot:empty>No payment gateways configured yet — New gateway to add your first one.</x-slot:empty>
        </x-list>
    </x-card>

    <x-modal wire="showModal" title="{{ $editingId ? 'Edit payment gateway' : 'New payment gateway' }}" center="lg" scrollable>
        <div class="flex flex-col gap-5">
            <div class="grid sm:grid-cols-2 gap-4">
                <x-input wire:model="name" label="Gateway name" required hint="e.g. &quot;Stripe (cards)&quot; — a friendly label, since one driver can be configured more than once." />
                <x-select.styled wire:model.live="driver" label="Driver" required :options="$driverOptions" />
            </div>
            <x-toggle wire:model="is_enabled" label="Enable gateway routing" hint="Accept live customer invoice settlements through this gateway." />

            <div class="border-t border-gray-200 dark:border-gray-800! pt-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400! mb-3">Credentials</h3>
                <p class="text-xs text-gray-400 mb-3">Stored encrypted at rest.</p>
                <x-input wire:model="configApiKey" type="password" label="API key / secret" />
            </div>

            @if ($driver === 'local_api')
                <div class="border-t border-gray-200 dark:border-gray-800! pt-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400! mb-1">Driver credentials &amp; connectivity</h3>
                    <p class="text-xs text-gray-400 mb-3">Config for App\Services\PaymentGateways\LocalApiPaymentGatewayDriver.</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-input wire:model="configBaseUrl" label="Base URL endpoint" placeholder="https://api.example-id-gateway.test" required />
                        <x-input wire:model="configMerchantId" label="Merchant ID" />
                    </div>
                    <div class="mt-4">
                        <x-select.styled wire:model="configMethods" label="Supported inbound methods" :multiple="true" :options="$methodOptions" />
                    </div>
                </div>
            @endif

            <div class="border-t border-gray-200 dark:border-gray-800! pt-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400! mb-3">Checkout compliance safeguards</h3>
                <div class="mb-4">
                    <x-select.styled wire:model="accepted_credit_cards" label="Accepted credit cards" :multiple="true" :options="$creditCardOptions" />
                </div>
                <div class="flex flex-col gap-3">
                    <x-toggle wire:model="show_address" label="Show billing address at checkout" hint="Includes {{ $company->name }} registered NPWP and tax identity." />
                    <x-toggle wire:model="require_cvv" label="Require CVV / 3D-Secure 2.0" hint="Bank Indonesia Card-not-Present mandatory risk protocol." />
                </div>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-800! pt-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400! mb-3">Fees &amp; statutory tax calculation</h3>
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-input wire:model="fee_amount" label="Fixed fee amount" type="number" step="0.01" />
                    <x-input wire:model="fee_percent" label="Variable fee (%)" type="number" step="0.001" />
                    <x-input wire:model="fee_tax_name" label="Fee tax scheme" />
                    <x-input wire:model="fee_tax_rate" label="Tax rate applied" type="number" step="0.001" />
                </div>
            </div>

            @if ($editingId && isset($testResults[$editingId]))
                @php($result = $testResults[$editingId])
                <div class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium
                    {{ $result['success'] ? 'bg-green-50 text-green-700 dark:bg-green-950! dark:text-green-400!' : 'bg-red-50 text-red-700 dark:bg-red-950! dark:text-red-400!' }}">
                    <x-icon :name="$result['success'] ? 'check-circle' : 'x-circle'" class="w-4 h-4 shrink-0" />
                    <span>{{ $result['title'] }}@if ($result['message']) — {{ $result['message'] }} @endif</span>
                </div>
            @endif
        </div>

        <x-slot:footer>
            <div class="flex items-center justify-between w-full gap-2">
                @if ($editingId)
                    <x-button text="Test connection" icon="bolt" color="amber" wire:click="testConnection({{ $editingId }})" />
                @else
                    <span></span>
                @endif
                <div class="flex items-center gap-2">
                    <x-button text="Cancel" color="gray" wire:click="$set('showModal', false)" />
                    <x-button text="Save gateway changes" color="blue" wire:click="save" />
                </div>
            </div>
        </x-slot:footer>
    </x-modal>
</div>
