
# Portal gap analysis — Stitch drafts vs. current implementation

Env note: `vendor/` is not installed in this sandbox, so `composer show` fails — TallStack UI presence verified from `composer.json` (`tallstackui/tallstackui: ^4.1`), `resources/css/app.css` (`@source` for its views), `resources/views/layouts/public.blade.php` (`@tallStackUiStyle`/`@tallStackUiScript`), and the portal Blade already using unprefixed TallStack components (`x-card`, `x-badge`, `x-input`, `x-button` — no `config/tallstackui.php`, so default no prefix). **TallStack UI 4 is installed and is the component library for the portal.**

## Shared current implementation (both screens)

| Concern | File |
|---|---|
| Route | `routes/web.php` — `GET /portal/{invitation:key}` → `App\Livewire\Portal\ViewInvoice` (`portal.invoice`), `GET /portal/{invitation:key}/pdf` (`portal.invoice.pdf`); both inside `ResolveCompanyFromDomain` group |
| Component | `app/Livewire/Portal/ViewInvoice.php` — `mount()` loads invoice+client+settings+items+payments+credits+documents+contact, 404s on company mismatch, stamps `viewed_at`, bumps `Sent→Viewed`; `sign()` writes `signature`/`signed_at` |
| View | `resources/views/livewire/portal/view-invoice.blade.php` — single-invoice page: header (logo via `Storage::url()` + status badge), line-item table, totals, "Payment" card (balance-due only), payment history, notes/terms, attachments (filenames only), **"Acceptance" e-sign form** |
| Layout | `resources/views/layouts/public.blade.php` — bare shell, `$company` shared by middleware, `@stack('head')` |
| Middleware | `app/Http/Middleware/ResolveCompanyFromDomain.php` — Host → `companies.domain`; local/testing fallback to first company; binds `currentCompany` |
| PDF | `app/Http/Controllers/Portal/InvoicePdfController.php` → `resources/views/pdf/invoice.blade.php` |
| Credential | `app/Models/Invitation.php` — `invoice_id`, `contact_id`, `key` (UUID), `sent_at`/`viewed_at`/`signed_at`/`signature`. **No `expires_at`, no `revoked_at`, no `replaced_by`.** One row per (invoice, contact) — the link is invoice-scoped, not client/contact-scoped |
| Settings | `app/Filament/Pages/Settings/EditClientPortalSettings.php` + `app/Models/CompanySetting.php` — `portal_enabled`, `portal_allow_client_payments`, `portal_show_tasks`, `portal_require_signature`. Only `portal_allow_client_payments` and `portal_require_signature` are read by the view; **`portal_enabled` is never enforced anywhere** (helper text promises a "disabled-portal message" that doesn't exist); `portal_show_tasks` is dead |
| Admin | `app/Filament/Resources/Invitations/InvitationResource.php` — list + "Copy portal link" only; no revoke/replace/expiry |
| Tests | `tests/Feature/Portal/ViewInvoicePortalTest.php` (3 tests: domain match, sign, viewed/status bump) |
| i18n | No `lang/` directory; no locale switching anywhere in `app/Http`/`app/Livewire` |
| Data available | `Contact::is_billing_contact` exists (`app/Models/Contact.php`, migration `2026_09_13_090001`). No Receipt, SOA, Handover/BAST, DeliveryOrder, or shared-document model exists yet (`ls app/Models`) |

### Legacy features the outputs pack says must be disabled/re-scoped
`docs/rebuild/outputs/README.md:52`: "The current portal has legacy payment, task, and signature settings that must be disabled or re-scoped for launch." Concretely, in the current code:
1. **E-sign** — `ViewInvoice::sign()`, the "Acceptance" `x-card` + `wire:submit="sign"` form, `portal_require_signature` toggle, `Invitation::signature/signed_at`, `InvitationResource` "Signed" column, test `test_signing_records_the_signature_and_timestamp`. Contradicts FINALIZED-DECISIONS §5 ("No electronic signatures launch now… portal signing are deferred") and 06 Specs "No client upload, payment, or signature action at launch".
2. **Pay** — `portal_allow_client_payments` toggle + the "Online payment isn't available…" paragraph; `PaymentGateway` driver stack is deferred (memory.md "Deferred: payment gateway").
3. **Tasks** — `portal_show_tasks` toggle (never read; generic tasks are out of launch nav per Phase 03).

---

## Screen 1: `client-readonly-portal-axen.html`

### Stitch layout summary
Wrapped in the admin sidebar/topbar shell (Stitch artefact — ignore; portal has no admin chrome). Portal content:
- **Header card**: company mark + "Axen Technology / Client Billing Portal", client name + "Client ID", **EN | ID language toggle**, "Exit Portal" button.
- **4 stat tiles**: Outstanding Balance (due date, terms, unpaid count) · Last Payment (date, ref) · Active Deployments (project count/status) · Escrow Credit Balance with "Apply Offset" button.
- **Bank panel**: bank name, "Virtual Account Validated", VA number + "Copy VA", **"Upload Proof"** button.
- **"Billing Statements & Audit Records"** — filter chips (All / Open Invoices / Paid / Receipts / BAST Slips), one mixed **document table**: Document Identifier (number + e-Faktur tax-invoice no.) · Related Project / Scope (job title + PO ref) · Date Issued · Due / Settled · Amount (IDR, right, tabular) · Status badge · Official Actions. Rows mix invoices, receipts (`RCP-`), and BAST/BAUT handover dossiers. Statuses: Awaiting Payment, Paid & Cleared, Official Settled, Signed & Approved, Customer Accepted. Actions: download, **QR "Pay Now"**, Receipt, BAST Dossier PDF.
- Table footer: "DJP e-Faktur cryptographic signing synchronized", record count + YTD total.
- **Footer card**: named key-account controller (avatar, title), company billing email + phone.
- **Modal**: "Submit Bank Transfer Proof" (target-invoice select, date, drag-drop upload 15MB) + success toast.

### Gaps

**[FIX-NOW]** (invitation/invoice data only, Livewire/Blade + TallStack)
1. **Strip e-sign** — remove the "Acceptance" card and `wire:submit="sign"` form from `view-invoice.blade.php`; remove `sign()`, `$signatureName`, `$justSigned` from `ViewInvoice.php`; keep the Invitation columns (historical/imported data, never deleted) but stop writing them. Replace `test_signing_records_the_signature_and_timestamp` with a test asserting no `sign` action is exposed (spec "Portal cannot expose disabled actions through stale links"). Optionally render a read-only "Accepted by X on date" notice when `signed_at` is filled (imported history).
2. **Strip pay copy** — delete the `portal_allow_client_payments` branch; keep the "Balance due" line.
3. **Enforce `portal_enabled`** — in `ViewInvoice::mount()` and `Portal\InvoicePdfController`, `abort(404)` (or render the calm expired/unavailable page, see Screen 2) when `$invitation->invoice->company->settings?->portal_enabled === false`. Add a test.
4. **Re-scope `EditClientPortalSettings`** — remove `portal_allow_client_payments`, `portal_show_tasks`, `portal_require_signature` toggles from the form (leave DB columns; no migration needed now). Update `tests/Feature/Filament/SettingsPagesTest.php:76-81`, which sets `portal_require_signature`.
5. **Quiet billing overview per DESIGN §11** — above the line items, a 2–3 tile row built from existing data: Outstanding balance (`$invoice->balance`, `due_date`, status), Last payment (`$invoice->payments` latest `payment_date`/`amount`/`method`/`gateway_reference`), Document status. Use plain Tailwind divs or `<x-card>`; no decorative gradients (§16). Drop Stitch's "Active Deployments" and "Escrow Credit" tiles (no data; escrow "Apply Offset" is a financial action → STRIP).
6. **Table-like document list, not cards** — convert the "Payment history" `<ul>` into a table with columns Date · Method/Reference · Amount (right-aligned, `tabular-nums`); render `$invoice->credits` similarly if present. Keep the line-item table.
7. **Status badge semantics** — `x-badge` already maps `InvoiceStatus::getColor()`; ensure `Overdue` derived display (`due_date < today && balance > 0`) uses red, per Specs palette — currently a stored status only.
8. **Company contact footer** — add a footer block from `$company->email`, `phone`, `address_line_1/2`, `city`, `tax_number` (fields exist on `Company`). Skip Stitch's named "Key Account Controller" persona (no data model; invent nothing).
9. **Logo** — `Storage::url($invoice->company->logo_path)` won't resolve for the `local` disk; use `$invoice->company->getLogoDataUri()` (already used by PDFs) or `<img>` guarded with a fallback.
10. **Download PDF** — keep `route('portal.invoice.pdf', $invitation)`; style as the primary row/header action (`x-button`), label "Download PDF".
11. **Amount formatting** — Stitch shows IDR with `.` thousands separators and no decimals; current view uses `number_format(x, 2)`. Use the company currency's formatting (`Currency` model) rather than hard-coding 2 decimals; right-align with `tabular-nums`.
12. **Language toggle (EN/ID)** — DESIGN §11 requires a clear selector independent of document language. Achievable minimally now: add `public string $locale` to `ViewInvoice` (`#[Url]` or session), `app()->setLocale()` in `mount()`/on switch, and wrap the portal's ~25 static strings in `__()` with `lang/en/portal.php` + `lang/id/portal.php`. Note: no `lang/` dir exists yet; Phase 06B Slice 2 owns the glossary — keep the ID strings to UI chrome only (no legal document terms) to avoid pre-empting 06B terminology validation. If the implementer prefers to defer, tag as [NEW-PHASE 06B Slice 2].
13. **Attachments** — currently lists `$document->filename` as plain text with no link; either link through a portal-scoped download route (domain-guarded, same pattern as the PDF controller) or hide the card until Phase 06 shared-documents exist. Never expose `Storage::url()` (FINALIZED §5 "never expose public URLs").

**[NEW-PHASE]** (needs Phase 06/06B model)
- **Client-scoped portal with full billing history** (multiple invoices, receipts, balances, filter chips) — 06 Specs "Portal is company/client/contact scoped… Only designated billing contacts see complete client history; ordinary contacts see explicitly shared documents"; FINALIZED §5; 06B Slice 5 "Portal full billing history for a designated billing contact / Ordinary-contact explicit-share restrictions". Requires a contact-level access link (not `invitations` per invoice), a shared-documents pivot, and `Contact::is_billing_contact` gating.
- **Receipts rows (`RCP-`) and receipt PDFs** — Phase 04 receipt model (`RCT` numbering, memory.md) doesn't exist yet in `app/Models`.
- **BAST/BAUT handover dossiers and Delivery Orders** — Phase 05 (`05-procurement-and-delivery`), then surfaced in the portal in 06.
- **Related Project / Scope column** (job title + customer PO) — needs the Phase 04 invoice→`SalesOrder` link; `invoices.po_number` exists today but a job link doesn't.
- **e-Faktur tax-invoice number under the invoice ID** — Phase 04 tax fields; also gate on Axen (tax-enabled) vs Karunia (non-tax) per memory.md company decisions.
- **Statement of Account entry/link** — 06B Slice 1 (SOA action + A4 PDF, immutable snapshot). Not in the Stitch draft but in scope; add as a portal action later.
- **Expiry/revoke/replace of links** — FINALIZED §5 "revocable, replaceable, expiry-configured (30-day default)"; needs `expires_at`/`revoked_at`/`replaced_by_id` on the access model (see Screen 2).
- **Bank/VA payment-instruction panel** — only if company payment identity fields land in Phase 06 ("configurable company legal/payment identity"); render as static instructions with copy button, no gateway.

**[STRIP]** (contradicts DESIGN §16 restraint or approved scope)
- "Pay Now" QR action — payment gateway deferred (memory.md; 06 Specs "No client … payment … action at launch").
- "Upload Proof" button + "Submit Bank Transfer Proof" modal + drag-drop zone + success toast — client uploads deferred (memory.md; FINALIZED §5 attachments are staff-side).
- "Escrow Credit Balance / Apply Offset" — allocation is a staff financial action; the portal is read-only.
- "Exit Portal" button — there is no session to exit (magic link); drop.
- "DJP Indonesian e-Faktur 2026 cryptographic signing synchronized", "Audit Encrypted", "Virtual Account Validated", "Client ID: TDE-JKT-88910" — certification/compliance claims forbidden by memory.md ("not a certified tax-compliance system; do not add certification … scope") and FINALIZED §9; also §16 "manufactured flourish" tells (middle-dot meta strings, eyebrow labels).
- Named "Dedicated Key Account Controller" persona card — no data; replace with plain company contact block.
- "Active Deployments / Under SLA" tile — no SLA model; not in spec.
- Admin sidebar/topbar wrapping the portal — Stitch shell artefact; portal renders in `layouts.public`.
- Material Symbols icon font — not in the stack; use TallStack UI's built-in Heroicons (`x-icon`) if icons are needed.

---

## Screen 2: `client-portal-access-expired.html`

### Stitch layout summary
Same admin shell wrapper (ignore). Centered card: company mark + "Client Portal / KapturInvoice Secure Gateway", **EN | ID toggle**, "TLS 1.3 256-bit" lock chip. Body: `link_off` icon, code `SESSION_EXPIRED_0x410`, H1 "Secure Document Link Expired", paragraph, "Security & Confidentiality Notice" (links expire after 14 days or revocation; nothing disclosed), primary button **"Request New Access Link"** ("dispatched exclusively to the registered billing contact"), then a post-submit **"Verification Request Dispatched"** state with token reference `AXN-9284-REV`, "TTL: 15m", inbox/junk instruction. "Need immediate clearance?" → company billing mailto + tel. Footer chips: "Audit Boundary Active · Ref: 0xEE4F·Revoked", "End-to-End Cryptographic Audit", "DJP e-Faktur Certified", company division line.

The "verification flow" it implies: expired page → one click → server emails a fresh one-time link to the billing contact on file (no email input, so no enumeration); page shows a neutral "if this matches an active account…" confirmation with a short-lived reference. It is a **request-new-link** flow, not OTP/login.

### Current implementation
None. An unknown/invalid `key` falls through Laravel route-model binding to a framework 404 (`resources/views/errors/` doesn't exist). A disabled company also 404s via `ResolveCompanyFromDomain`. `portal_enabled=false` is not handled at all. No expiry/revocation exists on `Invitation`.

### Gaps

**[FIX-NOW]**
1. **Calm unavailable page** — add `resources/views/portal/unavailable.blade.php` (extends `layouts.public`, TallStack `x-card`), headline "This link is no longer available", one neutral paragraph, company contact (`$company->email`/`phone` from middleware share) as `mailto:`/`tel:`. Must not say whether a client/contact/document exists (DESIGN §11).
2. **Wire it to the existing failure points** — in `routes/web.php` the portal route uses implicit binding; add a `->missing(fn () => response()->view('portal.unavailable', [], 404))` on both portal routes so an unknown key renders the calm page instead of the framework 404 (company is still resolved by the middleware, so branding is available). In `ViewInvoice::mount()` and `Portal\InvoicePdfController`, replace the bare `abort_unless(..., 404)` company-mismatch check and add the `portal_enabled` check to return the same view with 404 status. Do not distinguish the reasons in copy.
3. **Test** — extend `tests/Feature/Portal/ViewInvoicePortalTest.php`: unknown key → 404 + calm view; other-company key → 404 + calm view; `portal_enabled=false` → 404 + calm view; assert response does not contain the invoice number or client name.
4. **Language toggle** — same mechanism as Screen 1 item 12 (only if that lands).

**[NEW-PHASE]** (Phase 06 portal access model)
- **Actual expiry/revocation** — `expires_at` (30-day default, company-configurable per FINALIZED §5, not Stitch's 14 days), `revoked_at`, `replaced_by_id` on the contact-scoped access link; admin actions Revoke/Replace/Resend on the Invitations (or successor) resource; audit events. 06B Slice 5 requires browser coverage for "expired, revoked, and replaced portal links".
- **"Request new link" action** — DESIGN §11: "provide a safe request-new-link action to the designated contact only." Implementation: POST from the unavailable page carrying only the dead key; server resolves the link's contact silently, emails a fresh link **only if** that contact is still `is_billing_contact`/designated, and always renders the same neutral "If this link was valid, a new one has been sent" state; rate-limit per key/IP. Depends on the Phase 06 link model (needs the dead key to still resolve to a contact — today an unknown key resolves to nothing, so there is no "contact on file" to target). Don't build before the model exists.

**[STRIP]**
- `SESSION_EXPIRED_0x410`, `Ref: 0xEE4F·Revoked`, `Token reference: AXN-9284-REV`, `TTL: 15m` — fake system codes/generic tells (§16); the "TTL" also leaks whether a request matched anything.
- "TLS 1.3 256-bit", "End-to-End Cryptographic Audit", "DJP e-Faktur Certified", "Audit Boundary Active", "cryptographic session key" — certification/compliance claims contrary to memory.md/FINALIZED §9 and the compliance addendum's own non-certified disclaimer requirement; the addendum's "14-day limit" conflicts with the ratified 30-day default — spec wins.
- "Verification Request Dispatched / primary finance officer / inspect inbox within 3 minutes" copy — reduce to one neutral sentence once the request-new-link action exists; no OTP/email-entry/verification form (none in spec).
- Admin shell wrapper and Material Symbols — same as Screen 1.

---

## Suggested implementation order for the [FIX-NOW] set
1. Strip e-sign/pay/task (Screen 1 items 1–4) + settings page + tests.
2. Calm unavailable page + `->missing()` + `portal_enabled` enforcement + tests (Screen 2 items 1–3).
3. Overview tiles, tabular payment history, contact footer, logo data-URI, currency formatting (Screen 1 items 5–11).
4. Optional EN/ID chrome toggle (Screen 1 item 12) — or hand to 06B Slice 2.
Run `php artisan test tests/Feature/Portal tests/Feature/Filament/SettingsPagesTest.php` and `vendor/bin/pint --dirty` after each step.
