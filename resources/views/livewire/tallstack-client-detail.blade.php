<div class="w-[93%] mx-auto py-6 flex flex-col gap-5">

    {{-- Clients have no lifecycle status (prompt 10) — a muted "Since
         [date]" subtitle sits next to the title instead of a status
         badge. --}}
    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Clients', 'url' => route('tallstack.clients', $company)], ['label' => $client->name]]" :title="$client->name">
        <x-slot:badge>
            <span class="text-xs text-gray-400">Since {{ $client->created_at->format('d M Y') }}</span>
        </x-slot:badge>
        <x-slot:actions>
            <x-button text="Preview / Generate SOA" icon="document-text" color="gray" sm class="h-9" wire:click="openSoaModal" />
            <x-button text="Edit" icon="pencil" color="blue" sm class="h-9" wire:click="openEditModal" />
        </x-slot:actions>
    </x-tallstack.page-header>

    {{-- Financial summary strip — same tabular-numeral/compact-stat
         convention as the Dashboard/Quotations register. --}}
    <div class="grid grid-cols-2 min-[820px]:!grid-cols-4 gap-2.5">
        <x-stats scope="compact" title="Total invoiced" icon="document-currency-dollar" color="blue">
            <span class="text-lg font-bold tabular-nums">{{ $stats['totalInvoiced'] }}</span>
        </x-stats>
        <x-stats scope="compact" title="Total paid" icon="banknotes" color="green">
            <span class="text-lg font-bold tabular-nums">{{ $stats['totalPaid'] }}</span>
        </x-stats>
        <x-stats scope="compact" title="Outstanding balance" icon="exclamation-triangle" color="red">
            <span class="text-lg font-bold tabular-nums">{{ $stats['outstandingBalance'] }}</span>
        </x-stats>
        <x-stats scope="compact" title="Open quotations" icon="document-text" color="amber">
            <span class="text-lg font-bold tabular-nums">{{ $stats['openQuotations'] }}</span>
        </x-stats>
    </div>

    {{-- Tabbed body — client name + ledger stats above stay pinned outside
         these tabs (see the header and stats strip above). Grouping mirrors
         this page's actual sections (no Quotations/Jobs/Invoices lists live
         here — those numbers are already summarized in the stats strip
         above): Overview (contact/address/billing-default detail),
         Contacts, Portal Links, and Statements of Account, one tab each for
         what used to be four separately-stacked <x-card> sections. --}}
    <x-tab selected="overview" scroll-on-mobile>
        <x-tab.items tab="overview" title="Overview">
            {{-- Two-column info card — left: email/phone/website/tax
                 number/id number, right: address/currency — literally
                 prompt 10's own layout. --}}
            <x-card>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="flex flex-col gap-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Contact</h3>
                        <dl class="flex flex-col gap-2 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-gray-500">Email</dt><dd class="text-gray-900 dark:text-gray-100">{{ $client->email ?: '—' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-gray-500">Phone</dt><dd class="text-gray-900 dark:text-gray-100">{{ $client->phone ?: '—' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-gray-500">Website</dt><dd class="text-gray-900 dark:text-gray-100">{{ $client->website ?: '—' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-gray-500">Tax number</dt><dd class="text-gray-900 dark:text-gray-100">{{ $client->tax_number ?: '—' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-gray-500">ID number</dt><dd class="text-gray-900 dark:text-gray-100">{{ $client->id_number ?: '—' }}</dd></div>
                        </dl>
                    </div>
                    <div class="flex flex-col gap-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Address &amp; currency</h3>
                        <div class="text-sm text-gray-900 dark:text-gray-100">
                            @if ($client->address_line_1 || $client->address_line_2 || $client->city || $client->state || $client->postal_code)
                                <p>{{ $client->address_line_1 }}</p>
                                @if ($client->address_line_2)
                                    <p>{{ $client->address_line_2 }}</p>
                                @endif
                                <p>{{ collect([$client->city, $client->state, $client->postal_code])->filter()->implode(', ') }}</p>
                                <p>{{ $client->country_code ?: '—' }}</p>
                            @else
                                <p class="text-gray-500">No address on file.</p>
                            @endif
                        </div>
                        <div class="flex justify-between gap-4 text-sm">
                            <span class="text-gray-500">Currency</span>
                            <span class="text-gray-900 dark:text-gray-100">{{ $client->currency_code ?: '—' }}</span>
                        </div>
                    </div>
                </div>
            </x-card>

            {{-- Billing defaults — read-only helper text, per prompt 10:
                 "not an editable field on this read view." --}}
            <x-card class="mt-5">
                <x-slot:header>
                    <span class="font-bold text-[15px] text-gray-900 dark:text-gray-100">Billing defaults</span>
                </x-slot:header>
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="text-sm text-gray-900 dark:text-gray-100">
                            Default discount:
                            <span class="font-semibold tabular-nums">{{ number_format((float) $client->default_discount, 2) }}</span>
                            <x-badge :text="$client->default_discount_is_percentage ? 'Percentage' : 'Fixed amount'" color="gray" sm />
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Prefilled onto new invoices for this client — editable per invoice.</p>
                    </div>
                </div>
            </x-card>
        </x-tab.items>

        {{-- Contacts relation manager. NOTE: deliberately no live-count
             <x-slot:right> badge here — TallStackUI's <x-tab.items> bakes
             its right-slot content into a one-time Alpine `x-init`
             call (tabs.push(...)) that runs again, duplicating this tab's
             header entry, whenever Livewire's morph sees that baked
             content change between renders (verified live: adding/
             deleting a contact changed this count and produced a second
             "Contacts" tab header in the nav). Keep tab labels static;
             counts belong on the stats strip above, not in a tab. --}}
        <x-tab.items tab="contacts" title="Contacts">
            <div class="flex items-center justify-end mb-4">
                <x-button text="New contact" icon="plus" color="blue" sm class="h-9" wire:click="addContact" />
            </div>

            <x-table :headers="[
                ['index' => 'name', 'label' => 'Name'],
                ['index' => 'email', 'label' => 'Email'],
                ['index' => 'phone', 'label' => 'Phone'],
                ['index' => 'is_billing_contact', 'label' => 'Is billing contact'],
                ['index' => 'actions', 'label' => '', 'sortable' => false],
            ]" :rows="$contacts">
                @interact('column_is_billing_contact', $row)
                    <x-badge :text="$row['is_billing_contact'] ? 'Billing contact' : 'Ordinary'" :color="$row['is_billing_contact'] ? 'green' : 'gray'" sm />
                @endinteract

                @interact('column_actions', $row)
                    <div class="flex items-center justify-end gap-2">
                        <x-button icon="pencil" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Edit" wire:click="editContact({{ $row['id'] }})" />
                        <x-dropdown icon="ellipsis-vertical" scope="row-action">
                            <x-dropdown.items text="Generate portal link" icon="link" wire:click="generatePortalLink({{ $row['id'] }})" />
                            <x-dropdown.items text="Delete" icon="trash" wire:click="deleteContact({{ $row['id'] }})" wire:confirm="Delete this contact?" />
                        </x-dropdown>
                    </div>
                @endinteract

                <x-slot:empty>No contacts yet — New contact to add one.</x-slot:empty>
            </x-table>
        </x-tab.items>

        {{-- Portal Links relation manager — read-only, "Revoke" for active
             links only, same as PortalLinksRelationManager. --}}
        <x-tab.items tab="portal-links" title="Portal Links">
            <x-table :headers="[
                ['index' => 'contact', 'label' => 'Contact'],
                ['index' => 'created_at', 'label' => 'Created'],
                ['index' => 'expires_at', 'label' => 'Expires'],
                ['index' => 'status_label', 'label' => 'Status'],
                ['index' => 'actions', 'label' => '', 'sortable' => false],
            ]" :rows="$portalLinks">
                @interact('column_status_label', $row)
                    <x-badge text="{{ $row['status_label'] }}" :color="$row['status_color']" sm light />
                @endinteract

                @interact('column_actions', $row)
                    <div class="flex items-center justify-end gap-2">
                        @unless ($row['revoked'])
                            {{-- Client-side clipboard copy of the portal
                                 link's own URL — same pattern as
                                 tallstack-quotations.blade.php's "Copy
                                 client acceptance link" action. Without
                                 this, a link only ever existed in the
                                 3s-default "generated and emailed" toast;
                                 this row action is the durable way back
                                 to it (e.g. the email step failed, or the
                                 admin didn't copy it in time). --}}
                            <button type="button"
                                    x-on:click="window.navigator.clipboard.writeText('{{ route('portal.client-home', $row['key']) }}')"
                                    title="Copy portal link"
                                    class="inline-flex items-center justify-center h-9 w-9 rounded-md text-gray-600 dark:text-gray-300 hover:text-[color:var(--ts-primary)] hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-700 transition-colors">
                                <x-icon name="clipboard" class="w-4 h-4" />
                            </button>
                            <x-button text="Revoke" color="red" scope="row-action" sm wire:click="revokePortalLink({{ $row['id'] }})" wire:confirm="Revoke this portal link?" />
                        @endunless
                    </div>
                @endinteract

                <x-slot:empty>No portal links generated yet.</x-slot:empty>
            </x-table>
        </x-tab.items>

        {{-- Statements of Account relation manager — read-only, reopens
             every previously generated one. --}}
        <x-tab.items tab="statements" title="Statements of Account">
            <x-table :headers="[
                ['index' => 'number', 'label' => 'Number'],
                ['index' => 'period', 'label' => 'Period'],
                ['index' => 'generated_at', 'label' => 'Generated'],
                ['index' => 'actions', 'label' => '', 'sortable' => false],
            ]" :rows="$statements">
                @interact('column_actions', $row, $company, $client)
                    <div class="flex items-center justify-end gap-1">
                        <x-button icon="eye" href="{{ route('tallstack.clients.statement-of-account', ['company' => $company, 'client' => $client, 'statementOfAccount' => $row['id']]) }}" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="View" />
                        <x-button icon="document-arrow-down" href="{{ route('statement-of-accounts.pdf', $row['id']) }}" target="_blank" sm color="gray" scope="icon-action" class="h-9 w-9" tooltip="Download PDF" />
                    </div>
                @endinteract

                <x-slot:empty>No statements generated yet.</x-slot:empty>
            </x-table>
        </x-tab.items>
    </x-tab>

    {{-- Edit client. --}}
    <x-modal wire="showClientModal" title="Edit client" center="lg">
        <div class="flex flex-col gap-5">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Client</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-input wire:model="name" label="Name" class="sm:col-span-2" />
                    <x-input wire:model="email" label="Email address" />
                    <x-input wire:model="phone" label="Phone" />
                    <x-input wire:model="website" label="Website" />
                    <x-select.styled wire:model="currency_code" label="Currency" searchable
                        :options="$currencies->map(fn ($code) => ['label' => $code, 'value' => $code])->all()" />
                    <x-input wire:model="tax_number" label="Tax number" />
                    <x-input wire:model="id_number" label="ID number" />
                    <x-input wire:model="legacy_client_id" label="Legacy InvoiceNinja client id" hint="For import traceability." />
                </div>
            </div>

            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Billing defaults</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Prefilled onto new invoices for this client (editable per invoice).</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-input wire:model="default_discount" label="Default discount" type="number" step="0.01" />
                    <div class="flex items-end pb-2">
                        <x-toggle wire:model="default_discount_is_percentage" label="Discount is a percentage" />
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Address</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-input wire:model="address_line_1" label="Address line 1" class="sm:col-span-2" />
                    <x-input wire:model="address_line_2" label="Address line 2" class="sm:col-span-2" />
                    <x-input wire:model="city" label="City" />
                    <x-input wire:model="state" label="State" />
                    <x-input wire:model="postal_code" label="Postal code" />
                    <x-input wire:model="country_code" label="Country code" maxlength="2" />
                </div>
            </div>

            <x-editor wire:model="notes" label="Notes" min-height="8rem" max-height="18rem"
                :toolbar="['bold', 'italic', 'underline', 'ordered-list', 'unordered-list', 'link', 'clear-format', 'undo', 'redo']" />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showClientModal', false)" />
            <x-button text="Save" color="blue" wire:click="saveClient" />
        </x-slot:footer>
    </x-modal>

    {{-- New/edit contact. --}}
    <x-modal wire="showContactModal" :title="$editingContactId ? 'Edit contact' : 'New contact'" center="sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-input wire:model="contact_first_name" label="First name" />
            <x-input wire:model="contact_last_name" label="Last name" />
            <x-input wire:model="contact_email" label="Email" class="sm:col-span-2" />
            <x-input wire:model="contact_phone" label="Phone" class="sm:col-span-2" />
            <x-toggle wire:model="contact_is_primary" label="Primary contact" />
            <x-toggle wire:model="contact_is_billing_contact" label="Billing contact" hint="Sees this client's full billing history in the portal." />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showContactModal', false)" />
            <x-button text="Save" color="blue" wire:click="saveContact" />
        </x-slot:footer>
    </x-modal>

    {{-- Preview / Generate Statement of Account. --}}
    <x-modal wire="showSoaModal" title="Statement of Account" center="sm">
        <div class="flex flex-col gap-4">
            <x-date wire:model="soaPeriodStart" label="Period start" />
            <x-date wire:model="soaPeriodEnd" label="Period end" />
        </div>

        <x-slot:footer>
            <x-button text="Cancel" color="gray" wire:click="$set('showSoaModal', false)" />
            <x-button text="Preview" icon="eye" color="gray"
                href="{{ route('tallstack.clients.statement-of-account', ['company' => $company, 'client' => $client, 'period_start' => $soaPeriodStart, 'period_end' => $soaPeriodEnd]) }}" />
            <x-button text="Generate" color="green" wire:click="generateStatementOfAccount" loading="generateStatementOfAccount" spinner="dots" />
        </x-slot:footer>
    </x-modal>
</div>
