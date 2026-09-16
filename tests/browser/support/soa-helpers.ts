import type { Locator } from '@playwright/test';

/**
 * TallStackUI's `<x-date>` component (Statement of Account's "Period
 * start"/"Period end" fields on `App\Livewire\TallStackClientDetail`'s
 * modal — see `resources/views/livewire/tallstack-client-detail.blade.php`)
 * is NOT the Filament `DateTimePicker` `tests/browser/support/tenants.ts`'s
 * own `pickDate()` was written against (`.fi-fo-date-time-picker-panel`,
 * `x-data="dateTimePickerFormComponent"`, a year *text input*, a month
 * `<select>`). It's TallStackUI's own Alpine component
 * (`vendor/tallstackui/tallstackui/src/resources/views/components/form/date.blade.php`,
 * `x-data="tallstackui_formDate(...)"`): the visible field renders as a
 * real `<input type="text">` but its own `x-on:keydown="$event.preventDefault()"`
 * blocks typing entirely — the only way to set a value is to click
 * through the floating calendar panel's own year-grid -> month-grid ->
 * day-grid, confirmed by driving the real running app (not read from
 * source alone).
 *
 * The floating panel is NOT nested under the field/modal in the DOM (it's
 * TallStackUI's own `Floating` component, positioned independently), so
 * this deliberately locates every step by role/text on the page as a
 * whole rather than scoping into a container element. That's safe here
 * because Alpine's `x-show`/`x-if` toggle the closed state off the
 * accessibility tree entirely (`display: none`), so at most one calendar
 * panel's controls are ever visible/queryable at once — confirmed live:
 * selecting a day auto-closes that field's own panel (this component is
 * never used in `range`/`multiple` mode here), so two panels are never
 * open at the same time as long as each `pickSoaDate()` call is allowed
 * to run to completion before the next one starts.
 */
const MONTH_NAMES = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

export async function pickSoaDate(field: Locator, isoDate: string): Promise<void> {
    const page = field.page();
    const [yearStr, monthStr, dayStr] = isoDate.split('-');
    const year = Number(yearStr);
    const month = Number(monthStr);
    const day = Number(dayStr);

    await field.click();

    // Opens the year-grid. The header's year button is the only visible
    // 4-digit button before the year-grid itself opens (the year-grid's
    // own per-year buttons don't exist in the DOM yet at this point).
    await page.getByRole('button', { name: /^\d{4}$/ }).click();

    // The year-grid shows a 20-year window anchored 11 years before the
    // field's currently displayed year (`range.year.start = year - 11`,
    // per the component's own Alpine data) — this test's target years
    // (2020 and 2030) both fall inside that window whenever "today" is
    // between 2022 and 2031, true for this app's whole simulated
    // timeline (see memory.md — "today" is 2026 throughout). No
    // previous/next-year pagination click is needed as a result.
    await page.getByRole('button', { name: String(year), exact: true }).click();

    // Back on the day-grid, now showing the target year. Opens the
    // month-grid via the header's month-name button — matched by a
    // full-month-name pattern rather than a hardcoded name, since the
    // initially-displayed month is whatever "today" happens to be.
    const monthNamePattern = new RegExp(`^(${MONTH_NAMES.join('|')})$`);
    await page.getByRole('button', { name: monthNamePattern }).click();

    // The month-grid's own buttons use 3-letter abbreviations.
    const monthAbbreviation = MONTH_NAMES[month - 1].slice(0, 3);
    await page.getByRole('button', { name: monthAbbreviation, exact: true }).click();

    // Back on the day-grid for the target month/year — select the day.
    await page.getByRole('button', { name: String(day), exact: true }).click();

    // TallStackUI's <x-date> does not reliably auto-close on a
    // single-date select in every layout position — confirmed live: it
    // can stay mounted/visible (just visually overlapping or scrolled
    // out of the viewport) rather than actually toggling its own `show`
    // flag off. Force it closed by clicking the field again (its click
    // handler unconditionally toggles `show`) whenever the panel is
    // still present, so a second `<x-date>` field's own panel is never
    // opened while this one is still around — two open panels would
    // otherwise make every role/text query below ambiguous between them
    // (both render identically-labelled year/month/day buttons).
    const yearHeaderButton = page.getByRole('button', { name: /^\d{4}$/ });
    if (await yearHeaderButton.count() > 0) {
        await field.click();
        await yearHeaderButton.first().waitFor({ state: 'hidden' }).catch(() => undefined);
    }
}
