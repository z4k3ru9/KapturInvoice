<div class="mx-auto max-w-xl px-4 py-16">
    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100!">Register company</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400!">
        Set up a new billed entity in KapturInvoice. You'll be attached to it as Owner.
    </p>

    <x-card class="mt-6">
        <form wire:submit="register" class="space-y-4">
            <x-input wire:model.live.debounce.500ms="name" label="Name" required />

            <x-input wire:model="slug" label="Slug" required
                hint="Used in the company's TallStackUI/admin URLs." />

            <x-input wire:model="domain" label="Domain"
                hint="Public homepage domain for this entity, e.g. example.com" />

            <x-input wire:model="currency_code" label="Default currency" maxlength="3" required />

            <x-color wire:model="primary_color" label="Primary color" placeholder="#RRGGBB" clearable />

            <div class="flex justify-end pt-2">
                <x-button type="submit" text="Register company" color="blue" wire:loading.attr="disabled" wire:target="register" />
            </div>
        </form>
    </x-card>
</div>
