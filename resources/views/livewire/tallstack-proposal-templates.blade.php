<div class="max-w-[1400px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header
        :crumbs="[
            ['label' => $company->name, 'url' => route('tallstack.proposals', $company)],
            ['label' => 'Proposals', 'url' => route('tallstack.proposals', $company)],
            ['label' => 'Proposal Templates'],
        ]"
        title="Proposal Templates"
    >
        <x-slot:actions>
            <x-button text="New template" icon="plus" color="blue" sm class="h-9" wire:click="create" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{-- Templates/Snippets tab switcher — the same two library screens
         Proposals' own header links to, sketched as tabs on both per
         prompt 23 ("structurally identical to Templates"). --}}
    <div class="flex items-center gap-1 border-b border-gray-200 dark:border-gray-800">
        <a href="{{ route('tallstack.proposal-templates', $company) }}"
           class="px-3 py-2 text-sm font-semibold border-b-2 border-[color:var(--ts-primary)] text-[color:var(--ts-primary)]">
            Templates ({{ $templates->total() }})
        </a>
        <a href="{{ route('tallstack.proposal-snippets', $company) }}"
           class="px-3 py-2 text-sm font-semibold border-b-2 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
            Snippets ({{ $snippetsCount }})
        </a>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Templates</span>
                <div class="w-full sm:w-64">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search name…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'preview', 'label' => 'Preview', 'sortable' => false],
            ['index' => 'name', 'label' => 'Name'],
            ['index' => 'updated_at', 'label' => 'Last updated'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$templates" paginate loading>
            @interact('column_preview', $row)
                <x-tallstack.document-thumbnail :html="$row['html']" :css="$row['css']" :size="48" />
            @endinteract

            @interact('column_name', $row)
                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $row['name'] }}</span>
            @endinteract

            @interact('column_updated_at', $row)
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $row['updated_relative'] }}</span>
            @endinteract

            @interact('column_actions', $row)
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="pencil" sm color="gray" scope="icon-action" class="h-9 w-9" wire:click="edit({{ $row['id'] }})" tooltip="Edit" />
                    <x-dropdown icon="ellipsis-vertical" scope="row-action">
                        <x-dropdown.items text="Edit" icon="pencil-square" wire:click="edit({{ $row['id'] }})" />
                        <x-dropdown.items text="Duplicate" icon="document-duplicate" wire:click="duplicate({{ $row['id'] }})" />
                        <x-dropdown.items text="Delete" icon="trash" wire:click="delete({{ $row['id'] }})" wire:confirm="Delete this proposal template? This cannot be undone." />
                    </x-dropdown>
                </div>
            @endinteract

            <x-slot:empty>No proposal templates yet — New template to get started.</x-slot:empty>
        </x-table>
    </x-card>

    <x-modal wire="showModal" title="{{ $editingId ? 'Edit proposal template' : 'New proposal template' }}" center="lg" size="4xl" scrollable>
        <div class="flex flex-col gap-4">
            <x-input wire:model="name" label="Name" required />

            {{-- Large HTML/CSS split-pane editor — two monospace
                 textareas side by side, matching a lightweight
                 code-editing feel rather than a rich-text WYSIWYG (prompt
                 23's own spec: templates are raw HTML/CSS, not formatted
                 prose). A live preview pane sits below, driven by the
                 same debounced round-trip every other search input on
                 this app already uses. --}}
            <div class="grid sm:grid-cols-2 gap-4">
                <x-textarea wire:model.live.debounce.400ms="html" label="HTML" rows="12" class="font-mono text-xs" placeholder="<h1>Scope of Work</h1>..." />
                <x-textarea wire:model.live.debounce.400ms="css" label="CSS" rows="12" class="font-mono text-xs" placeholder="h1 { color: #111; }" />
            </div>

            <div>
                <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1.5">Live preview</span>
                <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-800 bg-white">
                    <iframe
                        srcdoc="<style>html,body{{ '{' }}margin:0;padding:16px;{{ '}' }}{{ $css }}</style>{{ $html }}"
                        style="width: 100%; height: 260px; border: 0;"
                        sandbox=""
                        title="Template preview"
                    ></iframe>
                </div>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex items-center justify-end gap-2">
                <x-button text="Cancel" color="gray" wire:click="$set('showModal', false)" />
                <x-button text="Save template" color="blue" wire:click="save" />
            </div>
        </x-slot:footer>
    </x-modal>
</div>
