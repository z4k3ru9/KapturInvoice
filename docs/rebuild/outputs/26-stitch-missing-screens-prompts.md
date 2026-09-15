# KapturInvoice — Stitch Prompts for Missing Screens

Continuation of the Google Stitch project **"KapturInvoice Admin Workflow
UI"** (`projectId 17287642508359312726`) and its own
`kapturinvoice-stitch-prompts.md` (prompts 1-9: Dashboard, the 7 Job
workspace tabs, Products picture upload). Same numbering scheme,
continued from 10 — these cover the areas flagged in
[`25-tallstack-full-rebuild-plan.md`](25-tallstack-full-rebuild-plan.md)
as **"no dedicated Stitch screen found"**: Clients, Users, Proposals, Tax
Rates and other small settings lookups, Price List Items, Credits,
Recurring Invoices, Statement of Accounts.

Paste each numbered prompt into Stitch separately (or via
`mcp__Google_Stich__generate_screen_from_text` with
`projectId: "17287642508359312726"`), one screen per call. Same shared
visual system as the existing prompts: Karunia Abadi red `#E63934` on
near-black `#050708`; Axen Technology Indonesia blue `#5065A8` on cool
neutrals; Filament-shell conventions (left nav drawer with the grouped
sections, top bar with search/notifications/account menu, thin brand
accent rail, restrained flat-card styling, no decorative shadows/gradient
washes/all-caps eyebrow labels/arrow-suffixed buttons — see the design
integrity note at the top of the original prompts file, it applies
identically here).

Fields below are read directly from each model's `#[Fillable]` attribute
and its Filament Form/Table class, not guessed — keep every prompt's
field list matching those exactly if the code changes before these
mockups are generated.

---

## 10. Clients — register and detail

Same KapturInvoice Filament admin shell (Karunia Abadi red/near-black
brand). Two views: the Clients list table, and a client's detail page
with its Contacts relation manager.

**Clients list table:** Columns: Name, Email, Phone, Currency, Balance
(right-aligned, tabular numerals, red text when > 0), Paid to date
(right-aligned), Tax number, row actions (View/Edit) on the right. A
"New Client" primary button top-right, plus a compact search box
filtering by name/email. Sketch ~6 rows.

**Client detail page (breadcrumb: Clients > PT Sumber Makmur > View):**
- Header: client name as page title, no status badge (clients don't have
  a lifecycle status) — instead a small "Since [date]" muted subtitle.
- A two-column info card: left column — Email, Phone, Website, Tax
  number, ID number; right column — Address (multi-line: address lines,
  city, state, postal code, country), Currency.
- **Billing defaults** panel: Default discount (amount or %, with a
  small toggle-style indicator matching the Job Workspace Commercial
  tab's pricing-mode indicator), shown as "Prefilled onto new invoices
  for this client — editable per invoice" helper text, not an editable
  field on this read view.
- **Contacts** relation manager table below: Name, Email, Phone, "Is
  billing contact" (badge, since only the billing contact sees a
  client's *full* billing history in the future portal — everyone else
  sees only explicitly shared documents), row actions Edit/Delete. A
  "New contact" button top-right of this panel.
- **Portal Links** relation manager (read-only list): Contact, Created,
  Expires, Status (badge: Active/Revoked/Expired), a "Revoke" row action
  for active links only.
- A financial summary strip near the top: Total invoiced, Total paid,
  Outstanding balance, Open quotations count — four stat tiles, same
  tabular-numeral/right-alignment convention as the Dashboard.

Empty state (Contacts): "No contacts yet — New contact to add one."

---

## 11. Users & roles

Same shell/brand. This is company-scoped user management — every row is
a `company_user` pivot (a person can belong to multiple companies with
different roles in each), not a global user list.

**Users list table:** Avatar (initials, circular — the one place a
circular avatar is correct, contrast this deliberately with Products'
square picture thumbnails if both screens are viewed together), Name,
Email, Role (badge — one of Owner/Admin/Accountant/Sales/Staff/Auditor,
each role a distinct but restrained color, e.g. Owner=brand accent,
Admin=blue, Accountant=teal, Sales=amber, Staff=gray, Auditor=slate),
"Is super admin" shown only as a small shield icon for the rare
cross-tenant super-admin account (never a full column, this is an
edge-case flag not a normal attribute), row actions (Edit role/Remove
from company). A "New user" / "Invite user" primary button top-right.

**Edit-role modal:** Name, Email (both read-only once invited — a
person's identity isn't editable from this screen), a single Role select
(the six roles above) with a one-line description beneath the select
that updates on selection, e.g. selecting "Auditor" shows "Read-only
across every module — cannot create, edit, or approve anything, ever."
selecting "Owner" shows "Full access, including force-delete and
financial-close overrides — only role that can override an outstanding-
balance job closure." This description text is the key UX detail: every
role's real permission boundary stated in plain language right where
it's assigned, not left to the badge color alone.

Empty state: not applicable (a company always has at least one Owner).

---

## 12. Proposals — register and editor

Same shell/brand. Proposals are a full HTML/CSS cover-letter/SOW
document, kept deliberately separate from Quotations/Invoices — design
both the list and the free-form editor.

**Proposals list table:** Title, Client, Status (badge: Draft/Sent/
Accepted/Rejected/Expired), Amount (right-aligned, tabular numerals),
Valid until, "Converted to invoice" (a small link icon + invoice number
when set, muted dash otherwise), row actions (View/Edit/Duplicate). A
"New Proposal" button top-right, and a secondary "Proposal Templates" /
"Proposal Snippets" tab-style link near the page title for the two
supporting library screens (sketch those as tab targets only, not in
full).

**Proposal editor (Title, Client, Template, Amount, Valid until fields
in a compact header strip above a large content area):** The content
area is a rich-text/HTML editor taking most of the page height —
standard formatting toolbar (bold/italic/headings/lists/table/image), a
"Insert snippet" dropdown button in the toolbar listing available
Proposal Snippets by name with a small thumbnail preview per item
(reusing the same square-thumbnail convention as Products, since
snippets can embed a product's picture as a base64 image), and a
"Preview PDF" secondary button plus "Save" primary button in the header
strip. Below the editor, a slim "Convert to Invoice" action is shown
disabled with tooltip "Available once this proposal is Accepted" until
that status is reached — reinforcing the one-way, one-time conversion
(a proposal converts to exactly one invoice, never editable back).

Empty state (list): "No proposals yet — New Proposal to get started."

---

## 13. Settings — small lookup tables (Tax Rates, Expense Categories,
    Task Statuses)

Same shell/brand. These three are simple, single-purpose lookup tables —
design ONE representative screen (Tax Rates) in full and note the other
two follow the identical pattern with different fields, rather than
three near-duplicate mockups.

**Tax Rates list table:** Name, Rate (right-aligned, shown as a
percentage, e.g. "11.000%"), "Inclusive" (boolean icon — whether the
rate is included in or added on top of the line price), row actions
(Edit/Delete, Delete disabled with tooltip "In use by N invoice items"
when referenced). A compact "New Tax Rate" button top-right — this is a
small settings table, so keep the whole screen visually lightweight, no
stat tiles or charts above it, just the table and its create action.
Sketch ~5 rows including one at a company with `tax_enabled = false`
shown as an empty state instead: centered icon, "Tax is disabled for
this company — no tax rates are used." with no create button (since
Karunia Abadi's `CompanyTaxSetting.tax_enabled` is false by design, this
table should never invite adding rates that will never apply).

**Expense Categories / Task Statuses** — same table shape (Name + row
actions only, no Rate/Inclusive columns), same lightweight single-table
screen; Task Statuses additionally show a small color swatch per row
(these are the statuses for the now-nav-hidden generic Task model, kept
for existing data only).

---

## 14. Price List Items (Catalog — vendor pricelist reference)

Same shell/brand. This is a large, read-mostly reference catalog
(Hikvision/HiLook, Ruijie/Reyee pricelists), explicitly separate from the
real invoiceable Product catalog — the screen's whole point is "browse
the vendor's current price sheet, then create/refresh a real Product
from a chosen row," never edit the pricelist rows by hand.

**Price List Items table:** Brand (badge/logo-style chip, e.g.
"Hikvision"), SKU, Description (truncated), List price (right-aligned,
tabular numerals), Last updated (relative date, e.g. "3 days ago"), row
action "Create/update product" (a distinct button style from a normal
row-edit action — this one creates or refreshes a real `Product`, not
this row) plus a small "Linked" badge on rows that already have a
`product_id` link, so it's visually obvious which rows are already
catalog-backed vs. reference-only. Filter bar above the table: Brand
select, a search box (SKU/description), and a prominent "Import
pricelist" primary button top-right opening a file-upload modal (drag a
spreadsheet, "Brand" select, "Import" confirm) with helper text "Files
are matched by SKU — re-uploading a revised sheet refreshes existing
rows instead of duplicating them." Sketch ~8 rows across two different
brands to show the brand-chip variety.

Empty state: "No pricelist imported yet — Import pricelist to get
started."

---

## 15. Credits — register

Same shell/brand. Credits are historical/imported records only in the
current build (new credit creation is deliberately disabled per an
approved decision) — the screen must read as a legitimate, complete
register, never as a broken or half-built "create" flow.

**Credits list table:** Number, Client, Related invoice (link, dash if
none), Amount (right-aligned, tabular numerals), Credit date, row action
View only (no Edit/Delete — these are historical financial records). No
"New Credit" button in the header — instead a small muted info line
under the page title: "Credits are historical records from data import
and are not created from this screen." This absent-button-plus-
explanation pattern (rather than a disabled greyed-out button with no
context) is the key UX detail — an intentionally limited screen should
explain itself, not look unfinished.

Empty state: "No credits on record for this company."

---

## 16. Recurring Invoices — register

Same shell/brand, following the same table conventions as the Customer
Invoices Register screen (prompt set already covers Invoices in depth —
reuse its column/badge/action conventions here, this is its scheduling-
focused sibling).

**Recurring Invoices list table:** Client, Frequency (badge: Weekly/
Monthly/Quarterly/Annually), Next invoice date, Amount (right-aligned,
tabular numerals), Status (badge: Active/Paused/Ended), Invoices
generated so far (count, small muted text), row actions (Pause/Resume
toggle depending on current state, Edit, View generated invoices). A
"New Recurring Invoice" button top-right.

**Edit form (as a page, not a modal — recurring invoices carry line
items same as a normal invoice):** Client, Frequency select, Start date,
End date (optional, "No end date" checkbox), then the same line-items
table/editor pattern as a normal Invoice/Quotation (Title, Quantity, Unit
cost, Line total, add/remove rows), Subtotal/Total summary footer.

Empty state: "No recurring invoices set up — New Recurring Invoice to
get started."

---

## 17. Statement of Account — preview and generate

Same shell/brand. Reached from a Client's detail page (prompt 10) via a
"Generate Statement of Account" / "Preview" row action — design the
resulting document view here.

**SOA document preview (A4-proportioned, print-oriented layout matching
the existing Invoice/Quotation A4 print prompts' conventions — company
letterhead top-left with logo, client details top-right, document
number + period top-center):**
- Header: "Laporan Piutang Pelanggan" (or "Statement of Account" in the
  English document-language variant) as the document title, Client name
  and address, Statement period (date range), Generated date, a
  distinguishing badge if this is a **Preview** (not yet a persisted
  numbered document) vs. an **Issued** SOA with its own `SOA`-prefixed
  number — this distinction must be visually unmistakable, e.g. a large
  diagonal "PREVIEW — NOT YET GENERATED" watermark-style banner across
  the top of the preview-only variant, absent on the issued one.
- Opening balance line, then a chronological transaction table: Date,
  Document (Invoice/Payment/Credit, with its number as a link), Debit,
  Credit, Running balance (right-aligned, tabular numerals throughout).
- Aging summary panel at the bottom: Current / 1-30 / 31-60 / 61-90 /
  90+ days columns, each showing an outstanding amount, computed as of
  the period end (not "today") — label this explicitly ("Aging as of
  [period end date]") since that's a real, easy-to-misread distinction.
- Closing balance, clearly totaled and visually separated (a top border,
  bold weight) from the transaction rows above it.
- Below the document itself (admin-chrome, not part of the printed
  page): for the Preview variant, a single "Generate & Issue" primary
  button ("this becomes a permanent numbered record — cannot be
  un-generated" as helper text); for the Issued variant, "Download PDF"
  and "Email to client" actions instead, and no way to edit or
  regenerate its frozen snapshot.

Also sketch, as a smaller secondary panel: the **Statement of Accounts
relation manager** shown on the Client detail page (prompt 10) — a
simple read-only list of every previously generated SOA for that client:
Number, Period, Generated date, row action "View" (reopens the Issued
variant above).
