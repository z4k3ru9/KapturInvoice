<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Users & Roles']]" title="Users & Roles">
        <x-slot:actions>
            {{-- color="blue" — see app.blade.php's own "+New" button: a
                 general action shouldn't borrow the tenant's brand color. --}}
            <x-button text="Invite user" icon="user-plus" color="blue" sm class="h-9" wire:click="openInviteModal" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{-- Stat row — real counts from this company's own membership/invitation rows. --}}
    <div class="grid grid-cols-2 min-[820px]:!grid-cols-3 gap-2.5">
        <x-stats scope="compact" title="Members" icon="users" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $activeCount }}</span>
            <x-slot:footer>Active company members</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Invited" icon="envelope" color="amber">
            <span class="text-lg font-bold tabular-nums">{{ $invitedCount }}</span>
            <x-slot:footer>Awaiting acceptance</x-slot:footer>
        </x-stats>

        <x-stats scope="compact" title="Roles" icon="shield-check" color="primary">
            <span class="text-lg font-bold tabular-nums">{{ count($roles) }}</span>
            <x-slot:footer>Owner, Admin, Accountant, Sales, Staff, Auditor</x-slot:footer>
        </x-stats>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Company members</span>
                <div class="w-full sm:w-64">
                    <x-input wire:model.live.debounce.400ms="search" placeholder="Search name, email or role…" icon="magnifying-glass" clearable />
                </div>
            </div>
        </x-slot:header>

        <x-table :headers="[
            ['index' => 'avatar', 'label' => '', 'sortable' => false],
            ['index' => 'name', 'label' => 'Name'],
            ['index' => 'email', 'label' => 'Email'],
            ['index' => 'role', 'label' => 'Role'],
            ['index' => 'status', 'label' => 'Status'],
            ['index' => 'actions', 'label' => '', 'sortable' => false],
        ]" :rows="$members">
            {{--
                Circular avatar with initials — deliberately the ONE place
                this app uses a circular avatar/initials chip (matching the
                app shell header's own <x-avatar>), contrasted with
                Products' SQUARE picture thumbnails per DESIGN.md §"table
                contexts". No <img> involved here at all (initials only),
                so the Phase 8 `img{max-width:100%}` shrink bug (a `<td>`
                image with no explicit sized wrapper) does not apply.
            --}}
            @interact('column_avatar', $row, $roleColors)
                <x-avatar text="{{ $row['initials'] }}" :color="$roleColors[$row['role']?->value] ?? 'gray'" sm class="h-8 w-8 mx-auto" />
            @endinteract

            @interact('column_name', $row)
                <div class="flex items-center gap-1.5">
                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $row['name'] }}</span>
                    @if ($row['is_super_admin'])
                        {{-- "Is super admin" shown only as a small shield
                             icon (prompt 11: "never a full column, this is
                             an edge-case flag not a normal attribute"). --}}
                        <x-icon name="shield-check" class="w-4 h-4 text-gray-400 shrink-0" title="Cross-tenant super admin — bypasses membership checks entirely." />
                    @endif
                </div>
            @endinteract

            @interact('column_email', $row)
                <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $row['email'] }}</span>
            @endinteract

            @interact('column_role', $row, $roleColors)
                <x-badge text="{{ $row['role']?->label() ?? '—' }}" :color="$roleColors[$row['role']?->value] ?? 'gray'" sm />
            @endinteract

            @interact('column_status', $row)
                @if ($row['is_active'])
                    <x-badge text="Active" color="green" sm />
                @else
                    <x-badge text="Removed" color="gray" sm />
                @endif
            @endinteract

            @interact('column_actions', $row)
                <div class="flex items-center justify-end gap-2">
                    <x-button icon="pencil-square" sm color="gray" scope="icon-action" class="h-9 w-9" wire:click="openEditRoleModal({{ $row['id'] }})" tooltip="Edit role" />
                    @if ($row['is_active'])
                        <x-button icon="user-minus" sm color="red" scope="icon-action" class="h-9 w-9" wire:click="openRemoveModal({{ $row['id'] }})" tooltip="Remove from company" />
                    @else
                        <x-button icon="arrow-path" sm color="gray" scope="icon-action" class="h-9 w-9" wire:click="restoreMember({{ $row['id'] }})" tooltip="Restore access" />
                    @endif
                </div>
            @endinteract

            <x-slot:empty>No company members found.</x-slot:empty>
        </x-table>
    </x-card>

    @if ($invitations->isNotEmpty())
        <x-card>
            <x-slot:header>
                <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Pending invitations</span>
            </x-slot:header>

            <x-table :headers="[
                ['index' => 'email', 'label' => 'Email', 'sortable' => false],
                ['index' => 'role', 'label' => 'Role', 'sortable' => false],
                ['index' => 'expires_at', 'label' => 'Expires', 'sortable' => false],
                ['index' => 'actions', 'label' => '', 'sortable' => false],
            ]" :rows="$invitations->map(fn ($i) => ['id' => $i->id, 'email' => $i->email, 'role' => $i->role, 'expires_at' => $i->expires_at->format('d M Y')])">
                @interact('column_role', $row, $roleColors)
                    <x-badge text="{{ $row['role']->label() }}" :color="$roleColors[$row['role']->value] ?? 'gray'" sm />
                @endinteract

                @interact('column_actions', $row)
                    <div class="flex items-center justify-end gap-2">
                        <x-button icon="user-minus" sm color="red" scope="icon-action" class="h-9 w-9" wire:click="revokeInvitation({{ $row['id'] }})" wire:confirm="Revoke this invitation?" tooltip="Revoke invitation" />
                    </div>
                @endinteract
            </x-table>
        </x-card>
    @endif

    {{-- Role permission-boundary reference — the six roles' plain-language
         descriptions, sourced live from App\Enums\CompanyRole::description()
         (never hand-copied prose), matching the Stitch mockup's
         "Company Permission Hierarchy" reference block. --}}
    <x-card>
        <x-slot:header>
            <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Role permission boundaries</span>
        </x-slot:header>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach ($roles as $role)
                <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800">
                    <div class="flex items-center gap-1.5 mb-1">
                        <span class="h-2 w-2 rounded-full shrink-0" style="{{ $role === \App\Enums\CompanyRole::Owner ? 'background: var(--ts-primary)' : '' }}"
                              @class(['bg-blue-500' => $role === \App\Enums\CompanyRole::Admin, 'bg-teal-500' => $role === \App\Enums\CompanyRole::Accountant, 'bg-amber-500' => $role === \App\Enums\CompanyRole::Sales, 'bg-gray-400' => $role === \App\Enums\CompanyRole::Staff, 'bg-slate-500' => $role === \App\Enums\CompanyRole::Auditor])></span>
                        <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">{{ $role->label() }}</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 leading-snug">{{ $role->description() }}</p>
                </div>
            @endforeach
        </div>
    </x-card>

    {{-- Invite user --}}
    <x-modal wire="showInviteModal" title="Invite user" center="sm">
        <div class="flex flex-col gap-4">
            <x-input wire:model="invite_email" label="Email" type="email" required />
            <x-select.styled wire:model.live="invite_role" label="Role" required
                :options="collect($roles)->map(fn ($r) => ['label' => $r->label(), 'value' => $r->value])->all()" />
            <div class="p-2.5 rounded bg-gray-50 dark:bg-gray-900 text-xs text-gray-600 dark:text-gray-300">
                {{ \App\Enums\CompanyRole::from($invite_role)->description() }}
            </div>
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showInviteModal', false)" />
            <x-button text="Send invitation" color="blue" wire:click="invite" />
        </x-slot:footer>
    </x-modal>

    {{--
        Edit-role — Name/Email read-only (a person's identity isn't
        editable from this screen, per prompt 11), a single Role select
        with a live-updating one-line plain-language description sourced
        from CompanyRole::description() — never a static/hand-copied
        string set.
    --}}
    <x-modal wire="showEditRoleModal" title="Edit role — {{ $editingUserName }}" center="sm">
        <div class="flex flex-col gap-4">
            <x-input label="Name" :value="$editingUserName" readonly disabled />
            <x-input label="Email" :value="$editingUserEmail" readonly disabled />

            <x-select.styled wire:model.live="edit_role" label="Assigned role" required
                :options="collect($roles)->map(fn ($r) => ['label' => $r->label(), 'value' => $r->value])->all()" />

            <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-900 flex flex-col gap-1">
                <span class="text-xs font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-1.5">
                    <x-icon name="information-circle" class="w-4 h-4 text-blue-500 shrink-0" />
                    Permission boundary
                </span>
                <p class="text-xs text-gray-600 dark:text-gray-400 leading-snug">
                    {{ \App\Enums\CompanyRole::from($edit_role)->description() }}
                </p>
            </div>
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showEditRoleModal', false)" />
            <x-button text="Save changes" color="blue" wire:click="saveRole" />
        </x-slot:footer>
    </x-modal>

    {{-- Remove from company — reuses CompanyMembershipService::disable(),
         which requires a reason and preserves membership history. --}}
    <x-modal wire="showRemoveModal" title="Remove {{ $removingUserName }} from company" center="sm">
        <div class="flex flex-col gap-4">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                This blocks {{ $removingUserName }}'s access to {{ $company->name }} immediately. Their history (documents, approvals, audit trail) is preserved — this never deletes anything they authored.
            </p>
            <x-textarea wire:model="removeReason" label="Reason" rows="3" required />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showRemoveModal', false)" />
            <x-button text="Remove from company" color="red" wire:click="removeMember" />
        </x-slot:footer>
    </x-modal>
</div>
