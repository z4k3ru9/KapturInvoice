# KapturInvoice Localization and Terminology

> **⚠️ SUPERSEDED — early planning draft.** The locale-behavior rules here
> are now covered by [`docs/rebuild/Specs.md`](../Specs.md) §13. The
> starting glossary below is an early draft and contains at least one
> confirmed wrong term ("Statement of Account" → "Laporan Rekening",
> which specifically means a *bank* statement — corrected to "Laporan
> Piutang Pelanggan" during Phase 06B, see
> `22-phase-06b-terminology-sources.md`). The actual, sourced-and-reviewed
> glossary lives in `resources/lang/{id,en}/documents.php`. Kept for
> historical reference only — do not copy terms from this file.

## Decision

Generated and printed business documents must be available in Bahasa Indonesia when required. Bahasa Indonesia is the first required document locale and must use terminology aligned with Indonesian business practice.

This requirement applies to:

- customer quotations,
- customer invoices,
- customer payment receipts,
- vendor purchase orders,
- vendor payment receipts/evidence,
- Delivery Orders,
- Handover Reports,
- Statements of Account,
- amendments and void/reissue documents,
- per-transaction tax recap PDFs.

Full translation of the Filament administration panel is not part of the first release unless separately approved. The client-facing portal and marketing site may use company-configured language, but document output is the required localization boundary.

## Locale behavior

- Default document locale is configurable per company.
- A document may override the company default when a customer or vendor requires another language.
- The locale affects labels, statuses, payment terms, tax wording, date formatting, number formatting, and explanatory text.
- The locale does not alter company legal identity, tax number, bank details, document number, source values, or monetary calculations.
- The language used at issuance is stored with the document snapshot so an amendment or historical PDF remains reproducible.
- Translation strings are centralized; templates must not contain one-off translated labels.

## Starting terminology glossary

| English concept | Bahasa Indonesia document label |
| --- | --- |
| Quotation | Penawaran |
| Sales Order / Job | Pesanan Penjualan |
| Invoice | Faktur |
| Payment Receipt | Kwitansi Pembayaran |
| Purchase Order | Pesanan Pembelian |
| Delivery Order | Surat Jalan |
| Handover Report | Berita Acara Serah Terima |
| Tax Recap Report | Laporan Rekap Pajak |
| Statement of Account | Laporan Rekening |
| Client | Pelanggan |
| Vendor | Pemasok |
| Down Payment | Uang Muka |
| Progress Payment | Pembayaran Progres |
| Final Payment | Pelunasan |
| Due Date | Jatuh Tempo |
| Subtotal | Subtotal |
| Discount | Diskon |
| Tax | Pajak |
| Balance Due | Sisa Tagihan |
| Paid | Lunas |
| Partially Paid | Dibayar Sebagian |
| Overdue | Lewat Jatuh Tempo |
| Voided | Dibatalkan |
| Amendment | Perubahan |

The business/accounting owner must review this glossary before production PDFs are used for tax or contractual purposes. Any legally prescribed Indonesian term takes precedence over a literal translation.

## Acceptance criteria

- Every required PDF can be generated in Bahasa Indonesia.
- The same term is used consistently across PDFs, portal views, email templates, and document status labels where applicable.
- Tax-inclusive and tax-exclusive labels remain unambiguous in Bahasa Indonesia.
- Indonesian Rupiah values display correctly and consistently.
- Date and number formatting is locale-aware and does not change stored values.
- Amendments preserve the original document locale and can use a separately selected locale for the amendment.
- Browser and PDF tests cover Bahasa output for quotation, invoice, receipt, Delivery Order, Handover Report, Statement of Account, and tax recap.

