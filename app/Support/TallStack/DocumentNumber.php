<?php

namespace App\Support\TallStack;

/**
 * Every document number App\Services\DocumentNumberGenerator produces is
 * `{company code}-{document type}-{YYYYMM}{4-digit sequence}` (e.g.
 * "KJA-QUO-2026090101") — the company code and document type are already
 * implied by which module's overview list you're looking at, and the
 * year-month is shared by an entire month's batch of rows, so repeating
 * the full string in every dense list row is mostly redundant width.
 *
 * short() returns just the trailing 4-digit sequence ("0101") for
 * overview/list contexts only — never for a document's own detail page
 * title, breadcrumb, PDF, or anywhere the full official number is the
 * thing being referenced (that always renders the untouched, real
 * `->number` value).
 */
class DocumentNumber
{
    public static function short(string $number): string
    {
        return substr($number, -4);
    }
}
