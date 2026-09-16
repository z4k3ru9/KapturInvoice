<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Documents']]" title="Documents" />

    {{-- Per prompt 22: no "New document" upload button on this page — a
         Document is always uploaded from its owning record (an invoice,
         expense, or client) elsewhere in the app. --}}
    <p class="text-xs text-gray-500 dark:text-gray-400! -mt-3">
        Documents are uploaded from their related record (an invoice, expense, or client) and listed here for reference.
    </p>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                {{-- File-type filter pills.
                     $typeFilterOptions is built here, outside the component
                     tag's own attribute string, because a nested-quote PHP
                     expression (the arrow function below needed a
                     double-quoted interpolated string inside the
                     already-double-quoted :options="..." attribute) breaks
                     Blade's component-tag attribute parser — confirmed
                     live: it silently stopped parsing the tag partway
                     through and rendered the remainder of the tag
                     (`values()" :active="$typeFilter" method="filterType" />`)
                     as literal page text instead of compiling it. Plain
                     variables passed via `:options="$typeFilterOptions"`
                     don't have this problem, so the array is computed here
                     instead. --}}
                @php
                    $typeLabels = ['all' => 'All', 'pdf' => 'PDFs', 'image' => 'Images', 'other' => 'Other'];
                    $typeFilterOptions = collect($typeLabels)
                        ->map(fn ($label, $value) => ['value' => $value, 'label' => "{$label} ({$counts[$value]})"])
                        ->values();
                @endphp
                <x-tallstack.filter-dropdown
                    label="{{ $typeLabels[$typeFilter] }} ({{ $counts[$typeFilter] }})"
                    :options="$typeFilterOptions"
                    :active="$typeFilter"
                    method="filterType"
                />

                <div class="w-full sm:flex-1 sm:min-w-[240px]">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Filter by filename…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        @if (! $hasAnyDocuments)
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <x-icon name="document" class="w-10 h-10 text-gray-300 dark:text-gray-700!" />
                <p class="text-sm text-gray-500 dark:text-gray-400!">No documents uploaded yet.</p>
            </div>
        @else
            <x-table :headers="[
                ['index' => 'filename', 'label' => 'Filename'],
                ['index' => 'size', 'label' => 'Size', 'align' => 'right'],
                ['index' => 'uploaded_by', 'label' => 'Uploaded by'],
                ['index' => 'uploaded_relative', 'label' => 'Uploaded'],
                ['index' => 'actions', 'label' => '', 'sortable' => false],
            ]" :rows="$documents" paginate loading>
                @interact('column_filename', $row)
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="shrink-0 w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-800! flex items-center justify-center text-gray-500 dark:text-gray-400!">
                            <x-icon name="{{ $row['file_icon'] }}" class="w-4 h-4" />
                        </span>
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-100! truncate" title="{{ $row['filename'] }}">{{ $row['filename'] }}</span>
                    </div>
                @endinteract

                @interact('column_size', $row)
                    <span class="text-xs text-gray-600 dark:text-gray-300!" style="font-variant-numeric: tabular-nums;">{{ $row['size'] }}</span>
                @endinteract

                @interact('column_uploaded_by', $row)
                    <span class="text-xs text-gray-600 dark:text-gray-300!">{{ $row['uploaded_by'] }}</span>
                @endinteract

                @interact('column_uploaded_relative', $row)
                    <span class="text-xs text-gray-400">{{ $row['uploaded_relative'] }}</span>
                @endinteract

                @interact('column_actions', $row)
                    <div class="flex items-center justify-end gap-2">
                        <x-button icon="document-arrow-down" sm color="gray" scope="icon-action" class="h-9 w-9" href="{{ route('documents.download', $row['id']) }}" target="_blank" tooltip="Download" />
                        <x-button icon="trash" sm color="gray" scope="icon-action" class="h-9 w-9" wire:click="delete({{ $row['id'] }})" wire:confirm="Delete this document?" tooltip="Delete" />
                    </div>
                @endinteract

                <x-slot:empty>No documents match this filter.</x-slot:empty>
            </x-table>
        @endif
    </x-card>
</div>
