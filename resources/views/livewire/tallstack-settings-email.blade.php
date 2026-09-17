<div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-6">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Settings'], ['label' => 'Email & Reminders']]" title="Email & Reminders">
        <x-slot:actions>
            <x-button text="Save" icon="check" color="blue" sm class="h-9" wire:click="save" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-tallstack.settings-tabs :company="$company" active="email">
    <x-card minimize>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Templates</span>
        </x-slot:header>

        <div class="flex flex-col gap-4">
            @php
                $bodyToolbar = ['bold', 'italic', 'underline', 'ordered-list', 'unordered-list', 'link', 'clear-format', 'undo', 'redo'];
            @endphp
            <div class="flex flex-col gap-2">
                <x-input wire:model="invoice_email_subject" label="Invoice — subject" />
                <x-editor wire:model="invoice_email_body" label="Invoice — body" min-height="8rem" max-height="18rem" :toolbar="$bodyToolbar" />
            </div>
            <div class="flex flex-col gap-2">
                <x-input wire:model="quote_email_subject" label="Quote — subject" />
                <x-editor wire:model="quote_email_body" label="Quote — body" min-height="8rem" max-height="18rem" :toolbar="$bodyToolbar" />
            </div>
            <div class="flex flex-col gap-2">
                <x-input wire:model="quotation_email_subject" label="Quotation — subject" />
                <x-editor wire:model="quotation_email_body" label="Quotation — body" min-height="8rem" max-height="18rem" :toolbar="$bodyToolbar" />
            </div>
            <div class="flex flex-col gap-2">
                <x-input wire:model="payment_email_subject" label="Payment receipt — subject" />
                <x-editor wire:model="payment_email_body" label="Payment receipt — body" min-height="8rem" max-height="18rem" :toolbar="$bodyToolbar" />
            </div>
        </div>
    </x-card>

    <x-card minimize>
        <x-slot:header>
            <div>
                <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Reminders</span>
                <p class="text-xs text-gray-400">Sent automatically relative to an invoice's due date or send date.</p>
            </div>
        </x-slot:header>

        <div class="flex flex-col gap-4 divide-y divide-gray-100 dark:divide-gray-800!">
            @foreach ($reminders as $n => $reminder)
                <div class="grid sm:grid-cols-4 gap-4 items-end {{ $loop->first ? '' : 'pt-4' }}">
                    <x-toggle wire:model.live="reminders.{{ $n }}.enabled" label="Reminder {{ $n }}" />
                    @if ($reminder['enabled'])
                        <x-input wire:model="reminders.{{ $n }}.days" label="Days" type="number" />
                        <x-select.styled wire:model="reminders.{{ $n }}.direction" label="Direction"
                            :options="[['label' => 'Before', 'value' => 'before'], ['label' => 'After', 'value' => 'after']]" />
                        <x-select.styled wire:model="reminders.{{ $n }}.field" label="Relative to"
                            :options="[['label' => 'Due date', 'value' => 'due_date'], ['label' => 'Invoice date', 'value' => 'invoice_date']]" />
                    @endif
                </div>
            @endforeach
        </div>
    </x-card>

    <x-card minimize>
        <x-slot:header>
            <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Late fees</span>
        </x-slot:header>

        <div class="flex flex-col gap-4">
            @foreach ($lateFees as $n => $lateFee)
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-input wire:model="lateFees.{{ $n }}.amount" label="Tier {{ $n }} amount" type="number" step="0.01" />
                    <x-input wire:model="lateFees.{{ $n }}.percent" label="Tier {{ $n }} percent" type="number" step="0.001" />
                </div>
            @endforeach
        </div>
    </x-card>

    <x-card minimize="mount">
        <x-slot:header>
            <div>
                <span class="font-semibold text-sm text-gray-900 dark:text-gray-100!">Outbound mail</span>
                <p class="text-xs text-gray-400">Optional — leave the host blank to keep using this server's own default mailer for this company.</p>
            </div>
        </x-slot:header>

        {{--
            CompanySetting::mail_config, an encrypted array
            (App\Services\CompanyMailerResolver) — previously every
            company silently shared the single app-wide .env mailer, with
            no way to configure a company's own SMTP/API credentials from
            here at all. Starts collapsed (minimize="mount") since most
            companies will never touch it.
        --}}
        <div class="grid sm:grid-cols-2 gap-5">
            <x-input wire:model="mail_host" label="SMTP host" placeholder="smtp.mailgun.org" />
            <x-input wire:model="mail_port" label="Port" type="number" placeholder="587" />
            <x-select.styled wire:model="mail_encryption" label="Encryption" clearable
                :options="[['label' => 'TLS', 'value' => 'tls'], ['label' => 'SSL', 'value' => 'ssl']]" />
            <x-input wire:model="mail_username" label="Username" />
            <x-input wire:model="mail_password" label="Password / API key" type="password" />
            <div></div>
            <x-input wire:model="mail_from_address" label="From address" type="email" />
            <x-input wire:model="mail_from_name" label="From name" />
        </div>
    </x-card>
    </x-tallstack.settings-tabs>
</div>
