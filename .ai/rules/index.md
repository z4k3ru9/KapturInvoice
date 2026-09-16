# Project rules index

Maps file globs to rule files under `.ai/rules/`. Per the Laravel Boost
guidelines in `CLAUDE.md`, read every rule file whose glob covers the path(s)
you're about to touch, and `grep -rin '<keyword>' .ai/rules` before entering
plan mode or editing/creating any file — a path match alone misses
cross-cutting rules like the TallStackUI one below.

| Glob                                 | Rule file                                                     | Covers |
|---------------------------------------|----------------------------------------------------------------|--------|
| `app/Providers/AppServiceProvider.php`, `resources/views/**/*.blade.php` | [tallstackui-customization.md](tallstackui-customization.md) | Verifying `TallStackUi::customize()->...->block([...])` key paths, and the app.css/tallstackui.css cascade-collision `!important` rule, before writing dark-mode or styling fixes. |
