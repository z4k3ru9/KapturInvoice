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

    // Phase 06B (docs/rebuild/specs/06b-ux-browser-soa) — required launch
    // document coverage for Quotation/COC, Sales Order, Receipt, Vendor
    // PO/Bill/Payment Receipt, Delivery Order, Handover Report, Tax
    // Recap, and Statement of Account. Same "constructed, not yet
    // professionally validated" status as the rest of this file.
    'type_quotation' => 'Penawaran',
    'type_coc' => 'Konfirmasi Pesanan Pelanggan',
    'coc_no' => 'No. Konfirmasi Pesanan',
    'quotation_valid_until' => 'Berlaku Hingga',

    'type_sales_order' => 'Pesanan Penjualan',
    'sales_order_quotation_ref' => 'Referensi Penawaran',
    'sales_order_approved_value' => 'Nilai Disetujui',
    'sales_order_status' => 'Status',

    'type_receipt' => 'Kwitansi',
    'receipt_received_from' => 'Diterima dari',
    'receipt_amount' => 'Jumlah Diterima',
    'receipt_method' => 'Metode Pembayaran',
    'receipt_reference' => 'Referensi',
    'receipt_allocated_invoices' => 'Dialokasikan ke Faktur',
    'receipt_invoice_number' => 'No. Faktur',
    'receipt_allocated_amount' => 'Jumlah Dialokasikan',
    'receipt_no_allocations' => 'Belum ada alokasi tercatat',
    'receipt_allocation_superseded' => 'Alokasi ini telah digantikan oleh amendemen berikutnya',

    'vendor_po_title' => 'Pesanan Pembelian',
    'vendor_po_issued_to_vendor' => 'Diterbitkan kepada',
    'vendor_po_delivery_date' => 'Tanggal Pengiriman',

    'vendor_bill_title' => 'Tagihan Vendor',
    'vendor_bill_po_reference' => 'Referensi PO',
    'vendor_bill_net' => 'Neto',
    'vendor_bill_amount_paid' => 'Jumlah Dibayar',
    'vendor_bill_status' => 'Status',

    'vendor_payment_receipt_title' => 'Kwitansi Pembayaran Vendor',
    'vendor_payment_receipt_method' => 'Metode Pembayaran',
    'vendor_payment_receipt_reference' => 'Referensi',
    'vendor_payment_receipt_applied_to' => 'Diterapkan pada Tagihan',

    'delivery_order_title' => 'Surat Jalan',
    'delivery_order_item_delivered' => 'Barang Dikirim',

    'handover_report_title' => 'Berita Acara Serah Terima',
    'handover_report_override' => 'Alasan Pengecualian',

    'service_report_title' => 'Laporan Servis',
    'service_report_technician' => 'Teknisi',
    'service_report_problem_reported' => 'Masalah Dilaporkan',
    'service_report_diagnosis' => 'Diagnosis',
    'service_report_action_taken' => 'Tindakan Dilakukan',
    'service_report_parts_used' => 'Suku Cadang Digunakan',
    'service_report_result' => 'Hasil',
    'service_report_follow_up_notes' => 'Catatan Tindak Lanjut',
    'service_report_customer_acknowledgement' => 'Diketahui oleh Pelanggan',

    'tax_recap_title' => 'Rekapitulasi Pajak',
    'tax_recap_reporting_period' => 'Periode Pelaporan',
    'tax_recap_external_reference' => 'Referensi Eksternal',
    'tax_recap_manual_entry_status' => 'Status Entri',
    'tax_recap_filing_date' => 'Tanggal Pelaporan',
    'tax_recap_taxable_base' => 'Dasar Pengenaan Pajak',
    'tax_recap_attachment_reference' => 'Referensi Lampiran',

    'soa_title' => 'Laporan Piutang Pelanggan',
    'soa_period' => 'Periode',
    'soa_client' => 'Klien',
    'soa_opening_balance' => 'Saldo Awal',
    'soa_closing_balance' => 'Saldo Akhir',
    'soa_date' => 'Tanggal',
    'soa_document' => 'Dokumen',
    'soa_description' => 'Keterangan',
    'soa_debit' => 'Debit',
    'soa_credit' => 'Kredit',
    'soa_balance' => 'Saldo',
    'soa_receipts' => 'Kwitansi',
    'soa_payment' => 'Pembayaran',
    'soa_receipt_number' => 'No. Kwitansi',
    'soa_verified_date' => 'Tanggal Verifikasi',
    'soa_aging' => 'Analisis Umur Piutang',
    'soa_aging_current' => 'Belum Jatuh Tempo',
    'soa_aging_1_30' => '1–30 Hari',
    'soa_aging_31_60' => '31–60 Hari',
    'soa_aging_61_90' => '61–90 Hari',
    'soa_aging_over_90' => '> 90 Hari',
    'soa_unresolved' => 'Belum Terselesaikan',
    'soa_unresolved_exceptions_total' => 'Total Pengecualian Belum Terselesaikan',
    'soa_no_activity' => 'Tidak ada aktivitas pada periode ini',
    'soa_generated_at' => 'Dibuat pada',
    'soa_preview' => 'PRATINJAU — BELUM DITERBITKAN',
];
