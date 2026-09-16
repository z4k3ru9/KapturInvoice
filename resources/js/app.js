import { Passkeys } from '@laravel/passkeys';

// Exposed globally rather than imported per-page: this app has no
// per-page JS bundles (TALL stack — Blade/Alpine/Livewire only, one
// shared app.js), so the Login page and the passkey-management UI
// (resources/views/livewire/login.blade.php,
// resources/views/livewire/settings/passkeys.blade.php) call
// `window.Passkeys.register(...)`/`.verify(...)` directly from a plain
// Alpine `x-on:click`.
window.Passkeys = Passkeys;

// Undoes the id-ID display formatting ("." thousands, "," decimal) an
// inline quick-edit currency cell shows its value in, back to a plain
// decimal string a Livewire method can validate/cast. Treats the LAST
// "." or "," in the typed text as the decimal point and strips every
// other one as a grouping separator, so both the id-ID convention the
// field displays ("18.599,75") and a plain-decimal retype
// ("18599.75") parse to the same value; a ambiguous case like "18.599"
// (no comma) is read as 18.599, matching the id-ID decimal reading.
// See resources/views/livewire/tallstack-invoice-form.blade.php and
// tallstack-quotation-form.blade.php's inline unit_cost cell.
window.parseMoneyInput = function (value) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return '';
    }

    const decimalAt = Math.max(text.lastIndexOf(','), text.lastIndexOf('.'));

    if (decimalAt === -1) {
        return text.replace(/[.,]/g, '');
    }

    const whole = text.slice(0, decimalAt).replace(/[.,]/g, '');
    const fraction = text.slice(decimalAt + 1).replace(/[.,]/g, '');

    return fraction === '' ? whole : `${whole}.${fraction}`;
};
