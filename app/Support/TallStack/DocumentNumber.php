<?php

namespace App\Support\TallStack;

use Illuminate\Support\Str;

/**
 * Every auto-generated document number App\Services\DocumentNumberGenerator
 * produces is `{company code}-{document type}-{YYYYMM}{4-digit sequence}`
 * (e.g. "KJA-QUO-2026090101") — the company code and document type are
 * already implied by which module's overview list you're looking at, and
 * the year-month is shared by an entire month's batch of rows, so
 * repeating the full string in every dense list row is mostly redundant
 * width.
 *
 * short() returns just the trailing 4-digit sequence ("0101") for
 * overview/list contexts only — never for a document's own detail page
 * title, breadcrumb, PDF, or anywhere the full official number is the
 * thing being referenced (that always renders the untouched, real
 * `->number` value).
 *
 * A manually typed number is respected and doesn't consume the sequence
 * (see DocumentNumberGenerator's own docblock), so it does not necessarily
 * end in a generated sequence at all — a bare `substr($number, -4)` would
 * silently show an arbitrary trailing fragment (e.g. "Invoice" -> "oice")
 * for any custom number, or for a legacy-imported number that predates
 * this app's own numbering scheme. Only take the shortcut when the number
 * actually matches the generated `-{YYYYMM}{4-digit sequence}` tail
 * (DocumentNumberGenerator concatenates those two with no separator, so
 * the match is one hyphen followed by exactly 10 digits); otherwise fall
 * back to a truncated form of the real number so the column still shows
 * something legible instead of garbage.
 */
class DocumentNumber
{
    public static function short(string $number): string
    {
        if (preg_match('/-\d{10}$/', $number) === 1) {
            return substr($number, -4);
        }

        return Str::limit($number, 10);
    }
}
