<div class="max-w-[1100px] mx-auto px-4 sm:px-6 py-6 flex flex-col gap-5">

    <x-tallstack.page-header :crumbs="[['label' => $company->name], ['label' => 'Settings'], ['label' => 'Branding']]" title="Branding">
        <x-slot:actions>
            <x-button text="Save" icon="check" color="blue" sm class="h-9" wire:click="save" />
        </x-slot:actions>
    </x-tallstack.page-header>

    <x-tallstack.settings-tabs :company="$company" active="branding">
    <x-card>
        <x-slot:header>
            <div>
                <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">Logo & colors</span>
                <p class="text-xs text-gray-400">Printed on invoice/credit PDFs and used on the public homepage/portal for this entity.</p>
            </div>
        </x-slot:header>

        <div class="flex flex-col gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1.5">Logo</label>
                @if ($existingLogoDataUri && ! $logo)
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-16 h-16 shrink-0 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                            <img src="{{ $existingLogoDataUri }}" alt="" class="w-full h-full object-cover">
                        </div>
                        <x-button text="Remove" icon="trash" color="red" sm wire:click="removeLogo" />
                    </div>
                @endif
                <x-upload wire:model="logo" accept="image/jpeg,image/png,image/webp" tip="JPG, PNG, WEBP · max 2 MB." :preview="true" />
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1.5">Primary color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" wire:model="primary_color" class="h-9 w-9 rounded-md border border-gray-200 dark:border-gray-700 cursor-pointer bg-transparent p-0 shrink-0">
                        <x-input wire:model="primary_color" placeholder="#RRGGBB" class="flex-1" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1.5">Secondary color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" wire:model="secondary_color" class="h-9 w-9 rounded-md border border-gray-200 dark:border-gray-700 cursor-pointer bg-transparent p-0 shrink-0">
                        <x-input wire:model="secondary_color" placeholder="#RRGGBB" class="flex-1" />
                    </div>
                </div>
            </div>
        </div>
    </x-card>

    <x-card>
        <x-slot:header>
            <div>
                <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">Signatory & banking</span>
                <p class="text-xs text-gray-400">For a future signature block and payment instructions on invoice/quotation PDFs — not yet printed on generated documents.</p>
            </div>
        </x-slot:header>

        <div class="flex flex-col gap-4">
            <div class="grid sm:grid-cols-2 gap-4">
                <x-input wire:model="signatory_name" label="Signatory name" />
                <x-input wire:model="signatory_title" label="Signatory title" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1.5">Signature image</label>
                @if ($existingSignatureDataUri && ! $signature)
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-16 h-16 shrink-0 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-white">
                            <img src="{{ $existingSignatureDataUri }}" alt="" class="w-full h-full object-contain">
                        </div>
                        <x-button text="Remove" icon="trash" color="red" sm wire:click="removeSignature" />
                    </div>
                @endif
                <x-upload wire:model="signature" accept="image/jpeg,image/png,image/webp" tip="JPG, PNG, WEBP · max 2 MB." :preview="true" />
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <x-input wire:model="bank_name" label="Bank name" />
                <x-input wire:model="bank_account_number" label="Bank account number" />
                <div class="sm:col-span-2">
                    <x-input wire:model="bank_account_name" label="Beneficiary account name" hint="Beneficiary name as registered with the bank." />
                </div>
            </div>

            <x-textarea wire:model="payment_instructions" label="Payment instructions" rows="3" />
        </div>
    </x-card>
    </x-tallstack.settings-tabs>
</div>
