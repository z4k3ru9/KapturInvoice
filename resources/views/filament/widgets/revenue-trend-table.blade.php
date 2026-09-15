<x-filament-widgets::widget>
    <x-filament::section>
        <details>
            <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-200">
                View as table
            </summary>

            <div class="mt-3 overflow-x-auto">
                <table class="fi-ta-table w-full text-start text-sm">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-start font-medium">Interval</th>
                            <th class="px-3 py-2 text-start font-medium">Invoiced (legacy)</th>
                            <th class="px-3 py-2 text-start font-medium">Cash collected</th>
                            <th class="px-3 py-2 text-start font-medium">Variance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['label'] }}</td>
                                <td class="px-3 py-2">{{ $row['invoiced'] }}</td>
                                <td class="px-3 py-2">{{ $row['collected'] }}</td>
                                <td class="px-3 py-2">{{ $row['variance'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-2" colspan="4">No data for this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </details>
    </x-filament::section>
</x-filament-widgets::widget>
