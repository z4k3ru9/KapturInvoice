<div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header
        :crumbs="[
            ['label' => $company->name],
            ['label' => 'Delivery Orders', 'url' => route('tallstack.delivery-orders', $company)],
            ['label' => $deliveryOrder->number],
        ]"
        :title="$deliveryOrder->number"
    >
        <x-slot:actions>
            @if ($job)
                <x-button icon="briefcase" text="Open job" href="{{ route('tallstack.jobs.show', [$company, $job->id]) }}" color="gray" sm class="h-9" />
            @endif
            <x-button icon="document-arrow-down" text="Download PDF" href="{{ route('delivery-orders.pdf', $deliveryOrder) }}" target="_blank" color="blue" sm class="h-9" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{-- Header facts — only fields App\Models\DeliveryOrder really has.
         The Stitch "Delivery Order Detail & Logistics Verification"
         mockup also shows GPS/serial-number/digital-signature panels;
         those aren't part of this model's schema, so they're
         deliberately not rendered here rather than faked. --}}
    <x-card>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            <div>
                <div class="text-gray-400 text-xs">Job</div>
                <div>
                    @if ($job)
                        <a href="{{ route('tallstack.jobs.show', [$company, $job->id]) }}" class="text-[color:var(--ts-primary)] hover:underline">{{ $job->number }}</a>
                    @else
                        —
                    @endif
                </div>
            </div>
            <div><div class="text-gray-400 text-xs">Client</div><div>{{ $job?->client?->name ?? '—' }}</div></div>
            <div><div class="text-gray-400 text-xs">Delivery date</div><div>{{ $deliveryOrder->delivery_date?->format('d M Y') ?? '—' }}</div></div>
            <div><div class="text-gray-400 text-xs">Recorded by</div><div>{{ $deliveryOrder->createdBy?->name ?? '—' }}</div></div>
        </div>
        @if ($deliveryOrder->notes)
            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                <div class="text-gray-400 text-xs mb-1">Notes</div>
                <p class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-line">{{ $deliveryOrder->notes }}</p>
            </div>
        @endif
    </x-card>

    <x-card>
        <x-slot:header><span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Consignment lines</span></x-slot:header>
        <x-table :headers="[
            ['index' => 'title', 'label' => 'Item'],
            ['index' => 'description', 'label' => 'Description'],
            ['index' => 'quantity_delivered', 'label' => 'Qty this delivery', 'align' => 'right'],
            ['index' => 'delivered_to_date', 'label' => 'Delivered to date', 'align' => 'right'],
            ['index' => 'ordered_quantity', 'label' => 'Job line qty', 'align' => 'right'],
            ['index' => 'fully_delivered', 'label' => 'Status'],
        ]" :rows="$items">
            @interact('column_quantity_delivered', $row)
                <span class="tabular-nums">{{ rtrim(rtrim(number_format((float) $row['quantity_delivered'], 4), '0'), '.') }}</span>
            @endinteract

            @interact('column_delivered_to_date', $row)
                <span class="tabular-nums text-gray-500 dark:text-gray-400">
                    {{ $row['delivered_to_date'] === null ? '—' : rtrim(rtrim(number_format((float) $row['delivered_to_date'], 4), '0'), '.') }}
                </span>
            @endinteract

            @interact('column_ordered_quantity', $row)
                <span class="tabular-nums text-gray-500 dark:text-gray-400">
                    {{ $row['ordered_quantity'] === null ? '—' : rtrim(rtrim(number_format((float) $row['ordered_quantity'], 4), '0'), '.') }}
                </span>
            @endinteract

            @interact('column_fully_delivered', $row)
                @if ($row['fully_delivered'] === null)
                    <span class="text-xs text-gray-400">—</span>
                @elseif ($row['fully_delivered'])
                    <x-badge text="Fully delivered" color="green" sm />
                @else
                    <x-badge text="Partial" color="amber" sm />
                @endif
            @endinteract

            <x-slot:empty>No lines recorded on this delivery order.</x-slot:empty>
        </x-table>
    </x-card>
</div>
