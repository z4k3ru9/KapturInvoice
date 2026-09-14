# Phase 06B — Indonesian Terminology Source Review

Status: **partial pass** — covers the two highest-risk existing terms in
full, with a real finding on one of them; the rest of
`resources/lang/id/documents.php` (existing and Phase 06B's new keys) is
not yet source-reviewed. This does not close
`docs/rebuild/specs/06b-ux-browser-soa/Specs.md`'s "Source-backed
terminology review recorded for every approved label group" requirement —
it is a documented start, not the finished gate. Per
`docs/rebuild/specs/FINALIZED-DECISIONS.md` §6, an Indonesian tax/
accounting professional must still validate all final wording before
production regardless of how this review concludes.

Source priority per Specs.md: (1) official Indonesian tax authority
material, (2) government/accounting-standard publications, (3) reputable
Indonesian tax/accounting firms, (4) internal terminology only when it
doesn't conflict with the above.

## Reviewed

| Key | Current value | Source | Access date | Note |
| --- | --- | --- | --- | --- |
| `tax_id` | `NPWP` | `https://id.wikipedia.org/wiki/Nomor_Pokok_Wajib_Pajak` (citing Direktorat Jenderal Pajak, DJP) | 2026-09-14 | **Confirmed.** NPWP = *Nomor Pokok Wajib Pajak*, the taxpayer identification number issued by DJP. Using the abbreviation as the label matches how it's universally printed on Indonesian invoices. |
| `type_invoice` | `Faktur` | `https://id.wikipedia.org/wiki/Faktur_pajak` (citing DJP / Ministry of Finance) | 2026-09-14 | **Confirmed with a caveat, needs professional sign-off.** *Faktur Pajak* is a specific, legally defined VAT document (proof of tax collection by a *Pengusaha Kena Pajak*, enabling input-tax credit) — a distinct government-format document (normally issued via DJP's e-Faktur system), not the same thing as this app's commercial `Invoice`. `type_invoice` here labels the ordinary commercial invoice, so the plain word `Faktur` (not `Faktur Pajak`) is the correct choice — but a reviewer should confirm this app never implies its printed invoice *is* a Faktur Pajak, and that `TaxRecap`'s own wording (see below) doesn't blur that line either. |

## Not yet reviewed

Every other existing key (`type_quote`, `type_credit`, `date`, `due`,
`po`, `billed_to`, `issued_to`, `item`, `qty`, `unit_price`,
`total_column`, `subtotal`, `discount`, `tax`, `total`, `balance_due`,
`amount`, `remaining_balance`, `notes`, `terms`) and every Phase 06B key
added this phase (`type_quotation`, `type_coc`, `coc_no`,
`quotation_valid_until`, `type_sales_order`, `sales_order_*`,
`type_receipt`, `receipt_*`, `vendor_po_*`, `vendor_bill_*`,
`vendor_payment_receipt_*`, `delivery_order_*`, `handover_report_*`,
`tax_recap_*`, `soa_*`) are still the constructed, standard-Indonesian-
business-terminology best effort flagged in
`resources/lang/id/documents.php`'s own docblock — plausible common usage,
not yet checked against an authoritative source one by one. Most are
generic business vocabulary (`Tanggal`/date, `Catatan`/notes,
`Subtotal`/subtotal) with low ambiguity risk; the tax-adjacent ones
(`tax_recap_*` in particular, since it prints alongside real tax figures)
are the highest-priority remainder for a follow-up pass.

## Recommended next step

Before this phase's completion gate closes, either: complete a full
key-by-key pass using the same source priority above (a natural follow-up
task, not blocking the rest of Phase 06B's slices), or have the
commissioning Indonesian tax/accounting professional review the whole
file directly — either satisfies
`docs/rebuild/specs/FINALIZED-DECISIONS.md` §6, but the review itself
must happen before production use either way.
