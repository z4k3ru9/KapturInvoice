<div class="max-w-[1400px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header
        :crumbs="[
            ['label' => $company->name, 'url' => route('tallstack.proposals', $company)],
            ['label' => 'Proposals', 'url' => route('tallstack.proposals', $company)],
            ['label' => 'Proposal Snippets'],
        ]"
        title="Proposal Snippets"
    >
        <x-slot:actions>
            <x-button text="New snippet" icon="plus" color="blue" sm class="h-9" wire:click="create" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{-- Same tab switcher as TallStackProposalTemplates — this page is
         Templates' smaller sibling, not a separate top-level screen. --}}
    <div class="flex items-center gap-1 border-b border-gray-200 dark:border-gray-800">
        <a href="{{ route('tallstack.proposal-templates', $company) }}"
           class="px-3 py-2 text-sm font-semibold border-b-2 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
            Templates ({{ $templatesCount }})
        </a>
        <a href="{{ route('tallstack.proposal-snippets', $company) }}"
           class="px-3 py-2 text-sm font-semibold border-b-2 border-[color:var(--ts-primary)] text-[color:var(--ts-primary)]">
            Snippets ({{ $snippets->total() }})
        </a>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Snippets</span>
                <div class="w-full sm:w-64">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search name…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'preview', 'label' => 'Preview', 'sortable' => false],
            ['index' => 'name', 'label' => 'Name'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$snippets" paginate loading>
            @interact('column_preview', $row)
                <x-tallstack.document-thumbnail :html="$row['html']" :image="$row['thumbnail']" :size="48" />
            @endinteract

            @interact('column_name', $row)
                <div class="flex items-center gap-2">
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $row['name'] }}</span>
                    @if ($row['product_name'])
                        <span title="Generated from {{ $row['product_name'] }}'s own 'Create proposal snippet' action.">
                            <x-badge text="Linked to product" color="blue" sm />
                        </span>
                    @endif
                </div>
            @endinteract

            @interact('column_actions', $row)
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="pencil" sm color="gray" scope="icon-action" class="h-9 w-9" wire:click="edit({{ $row['id'] }})" tooltip="Edit" />
                    <x-button icon="trash" sm color="red" scope="icon-action" class="h-9 w-9" wire:click="delete({{ $row['id'] }})" wire:confirm="Delete this snippet? This cannot be undone." tooltip="Delete" />
                </div>
            @endinteract

            <x-slot:empty>No snippets yet — snippets are created from a product's own "Create proposal snippet" action, or built here directly.</x-slot:empty>
        </x-table>
    </x-card>

    <x-modal wire="showModal" title="{{ $editingId ? 'Edit snippet' : 'New snippet' }}" center="sm" scrollable>
        <div class="flex flex-col gap-4">
            <x-input wire:model="name" label="Name" required />

            <x-textarea wire:model.live.debounce.400ms="html" label="HTML" rows="10" class="font-mono text-xs" placeholder="<p>Reusable clause or block…</p>" />

            <div>
                <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">Live preview</span>
                <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-800 bg-white">
                    <iframe
                        srcdoc="<style>html,body{{ '{' }}margin:0;padding:16px;{{ '}' }}</style>{{ $html }}"
                        style="width: 100%; height: 160px; border: 0;"
                        sandbox=""
                        title="Snippet preview"
                    ></iframe>
                </div>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex items-center justify-end gap-2">
                <x-button text="Cancel" color="gray" wire:click="$set('showModal', false)" />
                <x-button text="Save snippet" color="blue" wire:click="save" />
            </div>
        </x-slot:footer>
    </x-modal>
</div>
