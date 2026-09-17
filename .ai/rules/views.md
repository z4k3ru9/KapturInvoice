---
paths:
  - 'resources/views/**/*.blade.php'
---

# Views

## Never truncate or abbreviate a monetary/report value
This is an accounting/invoicing app — every currency amount, total, or balance shown anywhere (stat cards, tables, portal, PDFs, reports) must render its full, exact value. Never use CSS `truncate`/ellipsis on a figure, and never abbreviate it ("Rp 1,2 Jt", "$1.2K"). If a value is too wide for its box, let it wrap (`break-words`), never shrink/clip/abbreviate it — a card that grows taller is fine, one that hides digits is not. See docs/rebuild/DESIGN.md §8 "Numeric and currency values are never truncated or abbreviated" and docs/engineering.md for the concrete fix (App\Console\Commands\PatchTallStackUiStatsAsset). Exception: a document's own short-ID display (App\Support\TallStack\DocumentNumber::short() in dense table listings, full value always in a hover title) is a distinct, already-settled identifier convention, not a reported financial value.
