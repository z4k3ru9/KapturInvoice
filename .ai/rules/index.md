# Project rules index

Read rule files whose globs cover the paths you will edit. Search the directory
with `rg` when a cross-cutting concern may apply.

| Glob | Rule |
|---|---|
| `app/Providers/AppServiceProvider.php`, `resources/views/**/*.blade.php` | [`tallstackui-customization.md`](tallstackui-customization.md) |
| `resources/views/**/*.blade.php` | [`views.md`](views.md) |

The source rule files remain authoritative; this index is only their routing
map. Keep it synchronized when adding or removing a rule.
