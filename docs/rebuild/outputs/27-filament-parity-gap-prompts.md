# KapturInvoice — Stitch Prompts for the Filament-Parity Gap

Continuation of the Google Stitch project **"KapturInvoice Admin Workflow
UI"** (`projectId 17287642508359312726`), continuing the numbering from
[`26-stitch-missing-screens-prompts.md`](26-stitch-missing-screens-prompts.md)
(10-17, itself continuing the original project's own
`kapturinvoice-stitch-prompts.md` 1-9).

These cover the areas a pre-Filament-removal gap audit found still
missing a TallStackUI page: `App\Filament\Pages\Tenancy\RegisterCompany`,
`Expenses`, `PaymentGateways`, `Invitations`, `Documents`,
`ProposalTemplates`/`ProposalSnippets`. (Settings → Numbering/Client
Portal are two small field additions to the already-mockup-covered
"Company & Taxes Settings"/"Settings — Tax Rates & Small Lookups" screens
and don't need their own new mockup.)

Same shared visual system as every earlier prompt: Karunia Abadi red
`#E63934` on near-black `#050708`; Axen Technology Indonesia blue
`#5065A8` on cool neutrals; Filament-shell conventions (left nav drawer
with grouped sections, top bar with search/notifications/account menu,
thin brand accent rail, restrained flat-card styling, no decorative
shadows/gradient washes/all-caps eyebrow labels/arrow-suffixed buttons).
Fields below are read directly from each model's `#[Fillable]` attribute,
not guessed.

Paste each numbered prompt into Stitch separately (or via
`mcp__Google_Stich__generate_screen_from_text` with
`projectId: "17287642508359312726"`), one screen per call.

---

## 18. Company registration / first-company onboarding

Standalone, NOT inside the admin shell — this screen is reached by an
authenticated user who has zero companies yet, so there is no tenant to
render a sidebar/brand for. A simple centered card on a plain neutral
background (no sidebar, no top bar), KapturInvoice's own generic mark
(not a tenant's logo, since none exists yet) at the top.

**Form fields**: Company name (live-updates a Slug field below it as the
user types), Slug, Public homepage domain (helper text: "e.g.
example.com"), Default currency (3-letter code, defaults "USD"), Primary
brand color (color picker). A single "Create company" primary button
below the form. Small print below the button: "You'll be the Owner of
this company."

Empty state: not applicable — this is always a fresh form.

---

## 19. Expenses — register and detail

Same KapturInvoice Filament admin shell. A non-job cost bucket, separate
from Vendor Bills (which ARE tied to a job/PO) — this is the plain
"business expense" register.

**Expenses list table**: Columns: Date, Vendor, Category, Client (link,
dash if none — an expense can optionally be billed through to a client),
Amount (right-aligned, tabular numerals), "Should be invoiced" (boolean
icon), Transaction reference, row actions (View/Edit/Delete). A "New
expense" primary button top-right, plus a compact search box and a
Category filter dropdown. Sketch ~6 rows.

**Expense detail/edit (as a modal, matching this app's small-resource
convention)**: Vendor (searchable select), Expense category (searchable
select), Client (searchable select, optional), Related invoice (optional,
shown only once a client is picked), Expense date, Currency, Exchange
rate, Subtotal, "Should be invoiced" toggle, Transaction reference,
Private notes (textarea). Tax total/Total shown as read-only computed
fields below the form once subtotal is entered.

Empty state: "No expenses recorded yet — New expense to add one."

---

## 20. Payment Gateways — register and configuration

Same shell/brand. A short, infrastructure-feeling settings-style list —
this app has ONE real target gateway (an Indonesian local payment API),
so design for a small number of rows (1-3), not a long list.

**Payment Gateways list table**: Name, Driver (badge, e.g. "Local API"),
Enabled (toggle-style boolean), Accepted credit cards (small icon row),
row actions ("Test Connection" — a distinct button style with a small
plug/bolt icon, since it fires a live request rather than opening a
form — and Edit). A "New gateway" button top-right.

**Gateway config (as a modal)**: Name, Driver (select), Enabled (toggle),
a nested "Config" section whose fields depend on the driver (for "Local
API": Base URL, API key, Merchant ID, a multi-select of supported
methods — Virtual Account/QRIS/Card), Show address at checkout (toggle),
Require CVV (toggle), a "Fees" sub-section (Fee amount, Fee percent, Fee
tax name, Fee tax rate). "Test Connection" result shown as a small
inline success/error banner (green checkmark "Connected" / red X with
the error message) rather than a separate page.

Empty state: "No payment gateways configured yet — New gateway to add
your first one."

---

## 21. Client Portal Invitations — register (admin-side, read-mostly)

Same shell/brand. This is the admin's read-mostly list of every
`Invitation` (the per-invoice magic-link credential) ever generated —
distinct from the "Portal Links" relation manager already designed on
the Client detail screen (prompt 10), which is the broader,
revocable, contact-scoped kind. This one is invoice-scoped and
essentially permanent (an audit trail of who was sent what and whether
they opened/signed it), so no revoke action here.

**Invitations list table**: Invoice (link), Contact (name + email),
Sent (relative date, dash if never sent), Viewed (relative date + a small
green "Viewed" badge, dash + gray "Not yet viewed" if not), Signed
(relative date + a green checkmark if signed, dash otherwise), row action
"Copy portal link" only (no edit/delete — these are audit records). A
compact search box (invoice number/contact name/email) and a "Viewed" /
"Signed" filter toggle pair, no create button (invitations are generated
automatically when an invoice is sent, never created by hand here).

Empty state: "No portal invitations sent yet."

---

## 22. Documents — register

Same shell/brand. A generic uploaded-file library — company logos,
attachments, signed contracts, etc. — company-scoped, no relation to any
specific invoice/client shown in this list (though a document CAN be
attached to one elsewhere in the app; this register is the flat, "every
file we have" view).

**Documents list table**: Filename (with a small file-type icon derived
from mime_type — PDF/image/generic), Size (human-readable, e.g. "2.4
MB"), Uploaded by (user name), Uploaded (relative date), row actions
(Download, Delete). A compact search box (filename) and a file-type
filter pill row (All/PDFs/Images/Other). No "New document" upload button
in THIS specific mockup — documents in this app are attached to their
owning record (an invoice, a client, etc.) rather than uploaded loose
here; note this register is upload-view-only, matching how Credits'
register (prompt 15) explains its own no-create-button design rather
than looking unfinished: a small muted info line under the title,
"Documents are uploaded from their related record (an invoice, expense,
or client) and listed here for reference."

Empty state: "No documents uploaded yet."

---

## 23. Proposal Templates & Snippets — small library register

Same shell/brand, and the same "design ONE representative screen, note
the other follows the identical pattern" scoping prompt 13 used for Tax
Rates/Expense Categories/Task Statuses — these two are Proposals'
supporting library, reached via the tab-style links prompt 12 already
sketched on the Proposals register's header ("Proposal Templates" /
"Proposal Snippets").

**Proposal Templates list table** (design this one in full): Name,
Preview (a small thumbnail rendering the template's own `html`/`css` at
reduced scale, matching the square-thumbnail convention used elsewhere —
never circular), row actions (Edit/Duplicate/Delete). A "New template"
button top-right.

**Template editor (as a modal or a simple full-page form — this app's own
established convention for a small resource, your call)**: Name, then a
large HTML/CSS split-pane editor (two side-by-side code-style textareas,
monospace font, matching a lightweight code-editing feel rather than a
rich-text WYSIWYG — templates are raw HTML/CSS, not formatted prose) with
a live preview pane below or beside it.

**Proposal Snippets** — same table shape (Name, Preview thumbnail — note
a snippet CAN have a product's picture pre-embedded per this app's
`ProposalSnippetSync`, so some thumbnails show a real product photo, not
just rendered HTML — plus a small "Linked to product" badge on those
rows, Edit/Delete) — sketch this as a smaller secondary panel/tab rather
than a second full screen, since it's structurally identical to
Templates.

Empty state (Templates): "No proposal templates yet — New template to
get started." Empty state (Snippets): "No snippets yet — snippets are
created from a product's own 'Create proposal snippet' action, or built
here directly."
