<?php

namespace Tests\Unit;

use App\Support\TallStack\DocumentNumber;
use Tests\TestCase;

/**
 * DocumentNumber::short() previously assumed every document number ends
 * in a 4-digit generated sequence (substr($number, -4)) — true only for
 * App\Services\DocumentNumberGenerator output, not for a manually typed
 * custom number (a feature this app explicitly supports: "a manually
 * typed number is respected and doesn't consume the sequence") or a
 * legacy-imported number. Confirmed reproducing live as literal garbage
 * (e.g. "Invoice" -> "oice") in the Invoices list's Number column.
 */
class DocumentNumberTest extends TestCase
{
    public function test_short_returns_trailing_sequence_for_a_generated_number(): void
    {
        $this->assertSame('0101', DocumentNumber::short('KJA-QUO-2026090101'));
        $this->assertSame('0001', DocumentNumber::short('ATI-INV-2026090001'));
    }

    public function test_short_returns_trailing_sequence_for_a_hyphenated_document_type_code(): void
    {
        // Amendment numbers use a hyphenated document-type code
        // (e.g. "INV-A"), but the generated {YYYYMM}{sequence} tail is
        // still immediately preceded by a single hyphen.
        $this->assertSame('0001', DocumentNumber::short('KJA-INV-A-2026090001'));
    }

    public function test_short_falls_back_to_a_truncated_full_number_for_a_manually_typed_number(): void
    {
        $this->assertSame('Invoice', DocumentNumber::short('Invoice'));
        $this->assertSame('PO-99', DocumentNumber::short('PO-99'));
    }

    public function test_short_truncates_a_long_manually_typed_number_with_an_ellipsis(): void
    {
        $this->assertSame('Custom Num...', DocumentNumber::short('Custom Number 12345'));
    }
}
