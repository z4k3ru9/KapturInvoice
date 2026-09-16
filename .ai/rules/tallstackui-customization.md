# TallStackUI: verify customize() blocks and cascade collisions before writing

## Never guess a `TallStackUi::customize()->X()->block([...])` key path

`TallStackUi::customize()->component()->block(['dot.path' => 'classes'])` throws
`InvalidArgumentException` at boot time (every request, not just when the
component renders) if `dot.path` doesn't exist in that component's own
`customization()` array. Guessing a key from a partial `grep` of the vendor
source is not reliable — components nest their arrays inconsistently (e.g.
`Card`'s background is `wrapper.second`, but `Signature`'s is `wrapper.first`;
`Swap`'s button color is `input.color`, not a top-level `color` key). Two
components (`Signature`, `Swap`) were guessed wrong this way during the
2026-09-16 dark-mode audit and only caught because `php artisan migrate` (or
any command that boots the framework) throws immediately on an invalid key —
that's the cheapest way to catch a typo, but it's a safety net, not a
substitute for looking the key up first.

**Before writing any `->block([...])` call, check one of these, in order:**

1. **This project's `tallstackui` MCP server** (`.mcp.json`) — call
   `get_component` or `search_customization` for the component in question.
   Always current with the installed version.
2. **The vendor package's own bundled AI docs**:
   `vendor/tallstackui/tallstackui/.ai/components/<name>.md` — every
   component has an "Available Blocks" table with the exact dot-notation key
   and its purpose (e.g. `card.md`'s table lists `wrapper.second`,
   `header.text.color`, `loading.overlay`, etc. verbatim). The index is at
   `vendor/tallstackui/tallstackui/.ai/index.md`. Internal `scope="..."`
   names (used when a component renders nested components, e.g. Dropdown's
   `row-action`/`toolbar` scopes) are listed in
   `vendor/tallstackui/tallstackui/.ai/soft-customization-internal-scopes.md`.
3. Only if neither is available, read the FULL `Component.php` for that
   class under `vendor/tallstackui/tallstackui/src/Components/**` — not a
   `grep` excerpt — since `Arr::dot()`'s flattening depends on the real
   nesting, which a grep hit alone doesn't show.

After writing the calls, run any artisan command (`php artisan about` is
cheap) before assuming they're correct — it boots `AppServiceProvider` and
will throw immediately on any invalid block name, naming the exact allowed
list for that component.

## The `dark:` cascade-collision rule

`vendor/tallstackui/tallstackui/dist/tallstackui.css` (`@tallStackUiStyle` in
`resources/views/components/tallstack/app.blade.php`) loads **after**
`app.css` and independently compiles many plain, unconditional Tailwind
utility classes (used by its own bundled component templates) with **no**
`dark:`/`@media (prefers-color-scheme: dark)` variant of their own. At equal
CSS specificity, the later-loaded, always-active vendor rule beats an
earlier, correctly `@media`-gated `dark:` utility from `app.css` — regardless
of the visitor's actual color-scheme preference, and regardless of whether
the class in question is TallStackUI's own package-default styling or
hand-written Blade markup in this app.

This means **any** `dark:` utility this app writes — whether inside a
`TallStackUi::customize()->block([...])` call or directly in a `.blade.php`
file — needs a trailing `!` (Tailwind v4 important, e.g. `dark:bg-gray-900!`)
whenever the non-`dark:` counterpart class could plausibly also be compiled
unconditionally somewhere in that 384KB vendor stylesheet. In practice this
has turned out to mean **every** `dark:` utility in this app's TallStackUI
surface needs the trailing `!`, including plain hand-written ones like
`resources/views/components/tallstack/page-header.blade.php`'s title/
breadcrumb text — not just `TallStackUi::customize()` calls. When in doubt,
add the `!`; it's a no-op if there's no actual collision.

Reordering the two `<head>` `<style>`/`@vite`/`@tallStackUiStyle` includes
was considered and deliberately rejected (see
`resources/views/components/tallstack/app.blade.php`'s own comment on
`<body>`'s `dark:bg-gray-950!`) — it would fix this class of bug globally in
one shot, but risks breaking whatever currently depends on
`tallstackui.css` loading last. Don't revisit that without discussing it
first; keep applying `!important` per class instead.

## Package's own `dark-*` color tokens are structurally inert here

Separately from the cascade-collision issue above: TallStackUI's own
`dark-*` color scale (`dark-50` through `dark-950`, defined in
`vendor/tallstackui/tallstackui/css/v4.css`) can ONLY ever be compiled by
`tallstackui.css` — this app's own `resources/css/app.css` has no idea the
tokens exist. And `tallstackui.css`'s `dark:` variant is compiled as
`:where(.dark, .dark *)` (a literal `.dark` class needed on an ancestor),
**not** `@media (prefers-color-scheme: dark)` — and this app never adds a
`.dark` class anywhere. So any class built from a `dark-*` token (e.g.
`dark:bg-dark-800`, `dark:text-dark-300`) is permanently inert here,
`!important` or not. The fix is to swap the token for the closest standard
Tailwind `gray-*` shade (by `oklch` lightness — `dark-800`→`gray-900`,
`dark-700`→`gray-800`, `dark-600`→`gray-700`, `dark-500`→`gray-500`,
`dark-400`→`gray-400`, `dark-300`→`gray-300`, `dark-200`→`gray-200`,
`dark-100`→`gray-100`, `dark-50`→`gray-50`, `dark-900`/`dark-950`→`gray-950`
— see `App\Providers\AppServiceProvider::registerContentSurfaceDarkModeFix()`'s
own docblock for the full lightness table), THEN add `!important` per the
rule above.
