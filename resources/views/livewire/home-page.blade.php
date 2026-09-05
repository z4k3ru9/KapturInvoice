<div class="min-h-screen">
    <header class="border-b border-gray-200 dark:border-gray-800">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-3">
                @if ($company->logo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($company->logo_path) }}" alt="{{ $company->name }}" class="h-9 w-auto">
                @endif
                <span class="text-lg font-semibold">{{ $company->name }}</span>
            </div>

            <x-badge text="{{ strtoupper($company->currency_code) }}" color="gray" />
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-6 py-16">
        <section class="mb-16 text-center">
            <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">
                Welcome to {{ $company->name }}
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-600 dark:text-gray-400">
                This page is served for <code class="rounded bg-gray-100 px-1.5 py-0.5 text-sm dark:bg-gray-800">{{ request()->getHost() }}</code>
                — each entity KapturInvoice runs gets its own domain and branding from the same codebase.
            </p>
        </section>

        <section class="grid gap-6 sm:grid-cols-2">
            <x-card header="Get in touch">
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    Send us a message and we'll get back to you.
                </p>

                @if ($submitted)
                    <div class="rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300">
                        Thanks — your message has been sent.
                    </div>
                @else
                    <form wire:submit="submit" class="space-y-4">
                        <x-input label="Name" wire:model="name" placeholder="Jane Doe" />
                        <x-input label="Email" type="email" wire:model="email" placeholder="jane@example.com" />
                        <x-input label="Phone (optional)" wire:model="phone" placeholder="+1 555 0100" />
                        <x-textarea label="Message" wire:model="message" rows="4" placeholder="How can we help?" />

                        <x-button type="submit" text="Send message" color="primary" class="w-full" />
                    </form>
                @endif
            </x-card>

            <x-card header="Company details">
                <dl class="space-y-3 text-sm">
                    @if ($company->email)
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                            <dd>{{ $company->email }}</dd>
                        </div>
                    @endif
                    @if ($company->phone)
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Phone</dt>
                            <dd>{{ $company->phone }}</dd>
                        </div>
                    @endif
                    @if ($company->address_line_1)
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Address</dt>
                            <dd class="text-right">
                                {{ $company->address_line_1 }}<br>
                                {{ collect([$company->city, $company->state, $company->postal_code])->filter()->implode(', ') }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </x-card>
        </section>
    </main>
</div>
