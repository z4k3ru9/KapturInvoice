# Phase 06B — Indonesian Terminology Source Review

Status: **complete pass** — every `id` key in
`resources/lang/id/documents.php` (existing and Phase 06B's new keys) is
reviewed below, sourced where the term is specialized business/tax/legal
vocabulary, and explicitly marked as plain generic Indonesian where it
isn't. Per `docs/rebuild/specs/FINALIZED-DECISIONS.md` §6, an Indonesian
tax/accounting professional must still validate all final wording before
production regardless of this review's conclusions — this closes the
*source-backed review* requirement, not the professional-validation gate
itself, which stays a release checkpoint.

Source priority per Specs.md: (1) official Indonesian tax authority
material, (2) government/accounting-standard publications, (3) reputable
Indonesian tax/accounting firms, (4) internal terminology only when it
doesn't conflict with the above. `pajak.go.id` returned HTTP 403 to every
direct fetch attempted for this review (blocks automated retrieval); DJP
material is cited here only via a source that itself quotes DJP
(Wikipedia ID) or a government publication that isn't DJP itself
(`klc2.kemenkeu.go.id`, Kementerian Keuangan's own knowledge-management
site).

## Specialized terms — sourced and confirmed

| Key | Current `id` value | Source | Access date | Note |
| --- | --- | --- | --- | --- |
| `tax_id` | `NPWP` | `https://id.wikipedia.org/wiki/Nomor_Pokok_Wajib_Pajak` (citing DJP) | 2026-09-14 | Confirmed — *Nomor Pokok Wajib Pajak*, the DJP-issued taxpayer ID. |
| `type_invoice` | `Faktur` | `https://id.wikipedia.org/wiki/Faktur_pajak` (citing DJP/Kemenkeu); corroborated by `https://vinotek.id/article/perbedaan-invoice-faktur-pajak-dan-kuitansi-jangan-sampai-salah-gunakan`, `https://www.online-pajak.com/tentang-efaktur-ppn/invoice-tagihan/` | 2026-09-14 | **Confirmed with a caveat, needs professional sign-off.** *Faktur Pajak* is a specific, legally defined VAT document (proof of tax collection by a *Pengusaha Kena Pajak*, government e-Faktur format, enables input-tax credit) — distinct from this app's commercial `Invoice`. Plain `Faktur` (not `Faktur Pajak`) is correct for a commercial invoice; a reviewer should confirm this app's printed invoice is never presented as if it were the formal government tax invoice — `TaxRecap`'s own wording (below) is the closest this app comes to that territory and should get the closest look. |
| `type_receipt` | `Kwitansi` | `https://desty.mekari.com/blog/perbedaan-invoice-dan-kwitansi`, `https://www.doku.com/en-us/blog/perbedaan-invoice-dan-kwitansi` | 2026-09-14 | Confirmed — *Kwitansi* is specifically the post-payment proof-of-receipt document (signed, historically stamped with *materai*), matching exactly what `App\Models\Receipt` represents: issued only after a payment is verified, never before. |
| `type_credit` | `Nota Kredit` | `https://www.jurnal.id/id/blog/credit-note-pengertian-tujuan-dan-manfaatnya/` (Mekari Jurnal) | 2026-09-14 | Confirmed — standard Indonesian accounting term for a credit note reducing recorded receivables. |
| `type_quote` / `type_quotation` | `Penawaran` | `https://www.online-pajak.com/uncategorized-id/quotation-adalah/`, `https://www.jurnal.id/id/blog/quotation-letter/` | 2026-09-14 | Confirmed — *penawaran (harga)* is the standard term for a quotation, not yet legally binding unlike a PO. Both the legacy `type_quote` and the new `type_quotation` key correctly use the same word (they're the same real-world document; the two keys exist only to keep the legacy `Quote`-type `Invoice` and the new `Quotation` aggregate from sharing one translation key in code). |
| `type_coc` | `Konfirmasi Pesanan Pelanggan` | — (no exact Indonesian-market precedent found; this is a KapturInvoice-specific internal document, not a standard commercial one) | 2026-09-14 | **Internal terminology (source-priority tier 4).** A system-generated "Customer Order Confirmation" (issued only when a customer accepts without supplying their own PO) has no standard Indonesian-market equivalent to source against — it's a construct of this project's own numbering scheme (`FINALIZED-DECISIONS.md` §2). The literal translation is plain and unambiguous; flagged for the professional reviewer mainly to confirm it reads clearly as *internal, not customer-issued*, matching the binding decision that a COC must never appear as if the customer produced it. |
| `vendor_po_title` | `Pesanan Pembelian` | `https://www.jurnal.id/id/blog/apa-itu-arti-purchase-order-atau-po-artinya-adalah/`, `https://accurate.id/akuntansi/pengertian-purchase-order/` | 2026-09-14 | Confirmed — standard term for a Purchase Order; sources note "PO" itself is also commonly kept untranslated in Indonesian B2B practice, so either is defensible. |
| `delivery_order_title` | `Surat Jalan` | `https://www.jurnal.id/id/blog/contoh-cara-membuat-surat-jalan-barang/`, `https://www.cimbniaga.co.id/id/inspirasi/bisnis/surat-jalan` | 2026-09-14 | Confirmed — *Surat Jalan* is the standard Indonesian logistics term for a delivery order/waybill. |
| `handover_report_title` | `Berita Acara Serah Terima` | `https://klc2.kemenkeu.go.id/kms/knowledge/klc1-pusap-contoh-dokumen-berita-acara-serah-terima-barang-pekerjaan/detail/` (Kementerian Keuangan's own knowledge-management portal — government publication, source-priority tier 2), corroborated by `https://www.cimbniaga.co.id/id/inspirasi/bisnis/bast` | 2026-09-14 | Confirmed — "BAST" is the standard Indonesian formal term for a handover/acceptance record, used by the Ministry of Finance's own training material. |
| `due` | `Jatuh Tempo` | `https://www.online-pajak.com/pembayaran-invoice/syarat-pembayaran-faktur-2-10-n-30/` | 2026-09-14 | Confirmed — standard due-date term. |
| `discount` | `Diskon` | same source as `due` | 2026-09-14 | Confirmed — standard discount term, matches common Indonesian invoice format. |
| `terms` | `Syarat & Ketentuan` | `https://www.jurnal.id/id/blog/syarat-pembayaran-atas-invoice/` | 2026-09-14 | Confirmed — standard heading for payment terms/conditions. |
| `soa_title` | `Laporan Piutang Pelanggan` (corrected this pass — was `Rekening Koran Pelanggan`) | `https://majoo.id/solusi/detail/laporan-piutang`, `https://accounting.binus.ac.id/2017/06/17/definisi-dan-fungsi-rekening-koran/` (Binus University accounting faculty — corroborating the *contrast*) | 2026-09-14 | **Correction made this pass.** *Rekening Koran* specifically means a **bank** statement (bank-issued transaction history), not a customer/client accounts-receivable statement — every source on `Rekening Koran` describes it as a bank product. The correct term for a client balance/AR statement is *Laporan Piutang* (accounts-receivable report); this app's SOA is scoped to one client, hence *Laporan Piutang Pelanggan*. Original constructed value was a real terminology error, not just an unvalidated guess — now fixed in `resources/lang/id/documents.php`. |

## Generic vocabulary — no specialized source needed (tier 4, low ambiguity)

These are plain, unambiguous Indonesian words/short phrases with no
specialized business, legal, or tax meaning distinct from their ordinary
dictionary sense — sourcing each individually against an "authoritative"
publication would not change or validate anything a standard Indonesian
dictionary doesn't already settle. Listed here so the review is
explicitly complete (every key accounted for) rather than silently
skipped:

`date`/`soa_date` (`Tanggal`), `po` (`No. PO`), `billed_to`
(`Ditagihkan kepada`), `issued_to`/`vendor_po_issued_to_vendor`
(`Diterbitkan untuk`/`Diterbitkan kepada`), `item` (`Item/Deskripsi`),
`qty` (`Jumlah`), `unit_price` (`Harga Satuan`), `total_column`/`amount`
(`Jumlah`), `subtotal` (`Subtotal` — direct loanword), `tax`
(`Pajak`), `total` (`Total` — direct loanword), `balance_due`
(`Sisa Tagihan`), `remaining_balance` (`Sisa Saldo`), `notes`
(`Catatan`), `coc_no` (`No. Konfirmasi Pesanan`),
`quotation_valid_until` (`Berlaku Hingga`), `type_sales_order`
(`Pesanan Penjualan`), `sales_order_quotation_ref`
(`Referensi Penawaran`), `sales_order_approved_value`
(`Nilai Disetujui`), `sales_order_status`/`vendor_bill_status`
(`Status` — direct loanword), `receipt_received_from`
(`Diterima dari`), `receipt_amount` (`Jumlah Diterima`),
`receipt_method`/`vendor_payment_receipt_method` (`Metode Pembayaran`),
`receipt_reference`/`vendor_payment_receipt_reference` (`Referensi`),
`receipt_allocated_invoices` (`Dialokasikan ke Faktur`),
`receipt_invoice_number` (`No. Faktur`), `receipt_allocated_amount`
(`Jumlah Dialokasikan`), `receipt_no_allocations`
(`Belum ada alokasi tercatat`), `receipt_allocation_superseded`
(`Alokasi ini telah digantikan oleh amendemen berikutnya`),
`vendor_po_delivery_date` (`Tanggal Pengiriman`), `vendor_bill_title`
(`Tagihan Vendor`), `vendor_bill_po_reference` (`Referensi PO`),
`vendor_bill_net` (`Neto`), `vendor_bill_amount_paid`
(`Jumlah Dibayar`), `vendor_payment_receipt_title`
(`Kwitansi Pembayaran Vendor` — built on the confirmed `Kwitansi` root
above), `vendor_payment_receipt_applied_to`
(`Diterapkan pada Tagihan`), `delivery_order_item_delivered`
(`Barang Dikirim`), `handover_report_override`
(`Alasan Pengecualian`), `tax_recap_reporting_period`
(`Periode Pelaporan`), `tax_recap_external_reference`
(`Referensi Eksternal`), `tax_recap_manual_entry_status`
(`Status Entri`), `tax_recap_filing_date` (`Tanggal Pelaporan`),
`tax_recap_taxable_base` (`Dasar Pengenaan Pajak` — a standard PPN term,
"Dasar Pengenaan Pajak"/DPP, already used elsewhere in this app's own tax
engine naming), `tax_recap_attachment_reference`
(`Referensi Lampiran`), `soa_period` (`Periode`), `soa_client`
(`Klien`), `soa_opening_balance` (`Saldo Awal`), `soa_closing_balance`
(`Saldo Akhir`), `soa_document` (`Dokumen`), `soa_description`
(`Keterangan`), `soa_debit` (`Debit`), `soa_credit` (`Kredit`),
`soa_balance` (`Saldo`), `soa_receipts` (`Kwitansi`), `soa_payment`
(`Pembayaran`), `soa_receipt_number` (`No. Kwitansi`),
`soa_verified_date` (`Tanggal Verifikasi`), `soa_aging`
(`Analisis Umur Piutang`), `soa_aging_current` (`Belum Jatuh Tempo`),
`soa_aging_1_30`/`soa_aging_31_60`/`soa_aging_61_90`/`soa_aging_over_90`
(day-range labels), `soa_unresolved`/`soa_unresolved_exceptions_total`
(`Belum Terselesaikan`/`Total Pengecualian Belum Terselesaikan`),
`soa_no_activity` (`Tidak ada aktivitas pada periode ini`),
`soa_generated_at` (`Dibuat pada`), `soa_preview`
(`PRATINJAU — BELUM DITERBITKAN`), `tax_recap_title`
(`Rekapitulasi Pajak` — a plain compound of `Rekapitulasi`/recap +
`Pajak`/tax, not a defined legal term of art the way `Faktur Pajak` is).

## Recommended next step

Source-backed review is now complete for every key. The one substantive
correction (`soa_title`) is applied. What remains before production, per
`FINALIZED-DECISIONS.md` §6, is unchanged by this pass: an Indonesian tax/
accounting professional must still review the full glossary — this
review narrows what needs their attention (the specialized/tax-adjacent
rows above, `type_invoice`/`tax_recap_title` especially) rather than
replacing their sign-off.
