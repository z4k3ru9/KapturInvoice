<?php

/**
 * Bahasa Indonesia labels for printed documents (invoice/quote/credit PDF
 * views) — docs/rebuild/specs/06-documents-portal-reporting/Specs.md:
 * "Printed documents default to Bahasa Indonesia with per-document English
 * override. Required document labels use the approved Indonesian
 * glossary."
 *
 * ASSUMPTION FLAGGED: no pre-existing approved Indonesian glossary
 * artifact exists in this repo. These are standard Indonesian
 * business/invoicing terms constructed for this task and should be
 * reviewed against the client's own approved glossary if/when one exists.
 */
return [
    // Document type word used in the printed title (App\Enums\InvoiceType
    // plus the separate Credit model — see resources/views/pdf/*.blade.php).
    'type_invoice' => 'Faktur',
    'type_quote' => 'Penawaran',
    'type_credit' => 'Nota Kredit',

    'tax_id' => 'NPWP',
    'date' => 'Tanggal',
    'due' => 'Jatuh Tempo',
    'po' => 'No. PO',
    'billed_to' => 'Ditagihkan kepada',
    'issued_to' => 'Diterbitkan untuk',

    'item' => 'Item/Deskripsi',
    'qty' => 'Jumlah',
    'unit_price' => 'Harga Satuan',
    'total_column' => 'Jumlah',

    'subtotal' => 'Subtotal',
    'discount' => 'Diskon',
    'tax' => 'Pajak',
    'total' => 'Total',
    'balance_due' => 'Sisa Tagihan',

    'amount' => 'Jumlah',
    'remaining_balance' => 'Sisa Saldo',

    'notes' => 'Catatan',
    'terms' => 'Syarat & Ketentuan',
];
