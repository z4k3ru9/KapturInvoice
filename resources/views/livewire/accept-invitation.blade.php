<div class="mx-auto max-w-md px-4 py-16">
    @if ($accepted)
        <div class="rounded-lg border border-green-200 bg-green-50 p-6 text-center dark:border-green-900 dark:bg-green-950">
            <h1 class="text-lg font-semibold text-green-800 dark:text-green-200">You're all set</h1>
            <p class="mt-2 text-sm text-green-700 dark:text-green-300">
                Your account is ready. You can now sign in to the admin panel.
            </p>
            <a href="/admin/login" class="mt-4 inline-block text-sm font-medium underline">Go to sign in</a>
        </div>
    @elseif (! $invitation->isUsable())
        <div class="rounded-lg border border-red-200 bg-red-50 p-6 text-center dark:border-red-900 dark:bg-red-950">
            <h1 class="text-lg font-semibold text-red-800 dark:text-red-200">Invitation no longer valid</h1>
            <p class="mt-2 text-sm text-red-700 dark:text-red-300">
                This invitation has expired or was already used. Ask an Owner/Admin at
                {{ $invitation->company->name }} to send a new one.
            </p>
        </div>
    @else
        <h1 class="text-xl font-semibold">Join {{ $invitation->company->name }}</h1>
        <p class="mt-1 text-sm text-gray-500">
            You've been invited as {{ $invitation->role->label() }}. Set your name and password to continue.
        </p>

        @error('invitation')
            <p class="mt-4 text-sm text-red-600">{{ $message }}</p>
        @enderror

        <form wire:submit="accept" class="mt-6 space-y-4">
            <div>
                <label class="block text-sm font-medium">Name</label>
                <input type="text" wire:model="name" class="mt-1 w-full rounded border-gray-300">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Password</label>
                <input type="password" wire:model="password" class="mt-1 w-full rounded border-gray-300">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Confirm password</label>
                <input type="password" wire:model="password_confirmation" class="mt-1 w-full rounded border-gray-300">
            </div>
            <button type="submit" class="w-full rounded bg-gray-900 px-4 py-2 text-white">Set password &amp; join</button>
        </form>
    @endif
</div>
