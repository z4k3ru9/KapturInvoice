import { Passkeys } from '@laravel/passkeys';

// Exposed globally rather than imported per-page: this app has no
// per-page JS bundles (TALL stack — Blade/Alpine/Livewire only, one
// shared app.js), so the Login page and the passkey-management UI
// (resources/views/livewire/login.blade.php,
// resources/views/livewire/settings/passkeys.blade.php) call
// `window.Passkeys.register(...)`/`.verify(...)` directly from a plain
// Alpine `x-on:click`.
window.Passkeys = Passkeys;
