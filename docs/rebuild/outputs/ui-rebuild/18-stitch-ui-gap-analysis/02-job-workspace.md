# Job workspace gap analysis — Stitch drafts vs `SalesOrderResource`

Note: `vendor/` is **not installed** in this sandbox, so Filament v5.7.8 (`composer.lock`) API names below are from the v4/v5 API and marked *(verify)* where I could not open the class.

## 0. What exists today (baseline)

| File | State |
|---|---|
| `app/Filament/Resources/SalesOrders/Pages/ViewSalesOrder.php` | bare `ViewRecord`; no `getHeaderActions()`, no subheading, no title override, no widgets |
| `app/Filament/Resources/SalesOrders/Schemas/SalesOrderInfolist.php` | flat 13 `TextEntry`s, no `Section`/`Grid`; `quotation.*` PO fields read the **live** quotation, not `source_snapshot` |
| `app/Filament/Resources/SalesOrders/RelationManagers/ItemsRelationManager.php` | read-only; columns title/quantity/unit_cost/line_total only |
| `.../MilestonesRelationManager.php` | full CRUD gated to `Draft`; percentage→amount live compute already matches Stitch modal behaviour |
| `.../VariationsRelationManager.php` | read-only + "Record variation" header action via `ApproveJobVariation` — already matches Stitch immutability |
| `app/Filament/Resources/SalesOrders/Tables/SalesOrdersTable.php` | Approve / Advance status / Cancel exist **only as table row actions** |
| `app/Models/SalesOrder.php` | fields: number, status, approved_value, source_snapshot, approved_at, operational_closed_at, financial_closed_at, cancelled_at; relations client/quotation/items/milestones/variations; `isFullyClosed()` |
| `app/Enums/SalesOrderStatus.php` | 8 states, forward-only + Cancelled; `getColor()` maps Delivered/HandedOver → `warning` |
| `app/Services/AuditLogger.php` + `app/Models/AuditEvent.php` | only `job_variation.approved` is logged for jobs (`app/Actions/Sales/ApproveJobVariation.php:59`); `ApproveSalesOrder`/`TransitionSalesOrderStatus` write **no** audit event |
| Missing data (no column/table anywhere) | `invoices.sales_order_id`/milestone link, job `type` (goods/installation/service), vendor PO/bill/allocation, delivery order, handover, service report, job cost |

Link back: `app/Filament/Resources/Quotations/Tables/QuotationsTable.php:106` (`createJob` action) and `app/Filament/Resources/Quotations/Schemas/QuotationInfolist.php:33` (`salesOrder.number`, plain text, not a link).

---

## 1. Shared header + progress tracker

**Stitch (all 15 files, identical structure; -axen = token swap only):**
- Breadcrumb `Jobs > KJA-SO-… > View`; H1 `Job {number}` + status badge; one-line job description subtitle.
- Right actions: `Approve`, `Advance status`, `Cancel` (icon+text).
- Summary row (6 tiles): Client (+NPWP), Approved value, Paid amount, Outstanding balance, Operational completion %, Next action chip.
- Tracker: `Quote → Accepted → Procurement → In Progress → Delivered → Handover → Paid → Closed`; done=green check, current=brand solid, future=outlined number, Handover renders `Not required` pill for goods-only.
- Tab bar: `Overview | Commercial | Billing (badge) | Procurement (badge) | Delivery (badge) | Margin (lock) | Activity`.
- Also present: "Last autosaved 14:02:18", "Tenant: Karunia Abadi (KJA)" badge, "Excl. 11% PPN" under Approved value.

**Current:** Filament default breadcrumb/title (`number`), no actions on the view page, no summary row, no tracker, no tab bar (RMs render below content as Filament's default tab strip).

**Gaps:**
- [FIX-NOW] Header actions: move `approve`/`advance`/`cancel` from `SalesOrdersTable.php` into a shared factory (e.g. new `app/Filament/Resources/SalesOrders/Support/SalesOrderActions.php` with `approve()/advance()/cancel()` returning `Filament\Actions\Action`) and register in `ViewSalesOrder::getHeaderActions()`. Keep the `RuntimeException → Notification` pattern; the table keeps using the same factory.
- [FIX-NOW] `ViewSalesOrder::getTitle()` → `"Job {$this->record->number}"`; `getSubheading()` → client name (or nothing — there is no job description field; the Stitch subtitle line has no data source → [STRIP] unless `source_snapshot.quotation_number` is used).
- [FIX-NOW] Summary row + tracker as a custom Blade partial mounted first in the infolist: `Filament\Schemas\Components\View::make('filament.resources.sales-orders.job-header')->columnSpanFull()` (new `resources/views/filament/resources/sales-orders/job-header.blade.php`). Data available now: client, approved_value, status, next action. **Do not** render Paid/Outstanding/Completion tiles with placeholder zeros — omit them until the data exists (DESIGN §16 restraint):
  - Paid / Outstanding → [NEW-PHASE] Phase 04 (`docs/rebuild/specs/04-billing-and-receivables/Specs.md`; balances derived from allocations only).
  - Operational completion % → [NEW-PHASE] Phase 05 (delivery/handover evidence; `05-procurement-and-delivery/Specs.md`). Do not fake it from status ordinal.
  - Next action → [FIX-NOW] derive in a small domain helper (e.g. `SalesOrder::nextRequiredAction(): ?string` or `app/Support/Sales/JobNextAction.php`): `Draft` → "Approve job" / "Milestones must equal approved value" (re-use `ApproveSalesOrder`'s sum check, don't duplicate the tolerance); otherwise label of `status->allowedNextStates()[0]` (excluding Cancelled); terminal → null.
- [FIX-NOW] Tracker stage mapping from `SalesOrderStatus` (put in the helper, not Blade): Quote ✓ and Accepted ✓ always (a job only exists from an Accepted quotation); Procurement/In Progress/Delivered/Handover ✓ when the enum ordinal ≥ that state; current = `$record->status`; Paid = `financial_closed_at !== null` (true source arrives Phase 04); Closed = `Closed`. Cancelled: badge red, tracker rendered muted, no "current" step. Icons + labels, never colour alone.
  - Handover `Not required` pill → [NEW-PHASE] Phase 05 (needs job `type` goods/installation/service, `FINALIZED-DECISIONS.md` §5 l.90). Until then render Handover as a normal stage.
- [STRIP] "Last autosaved" (view page is not a draft editor; DESIGN §6 autosave is draft-only), "Tenant: … (KJA)" header badge (DESIGN §2: tenant identity = accent rail, not a badge), "Excl. 11% PPN" (Karunia is non-tax; Axen is 12% with 11/12 DPP — `FINALIZED-DECISIONS.md` §3 l.28), NPWP in client tile (fine as `client.tax_number` but keep it in the Overview card, not the summary strip).
- Minor: `SalesOrderStatus::getColor()` maps Delivered/HandedOver → `warning`; Stitch + Phase 06 palette says green complete / blue in-progress. Not blocking; flag for the implementer, don't change without the palette decision.

---

## 2. Per tab

### Proposed tab order + mechanism (Filament v5)

Use combined content + relation-manager tabs on `ViewSalesOrder` *(verify in vendor: `hasCombinedRelationManagerTabsWithContent(): bool`, `getContentTabLabel()`, `getContentTabIcon()`, `getContentTabPosition(): ?Filament\Resources\Pages\Enums\ContentTabPosition`)*, and `Filament\Resources\RelationManagers\RelationGroup::make('Label', [...])` in `SalesOrderResource::getRelations()` to put several managers in one tab.

| Tab | Now (FIX-NOW) | Later |
|---|---|---|
| **Overview** (content tab, `ContentTabPosition::Before`) | infolist: job-header View + Section "Job" + collapsible Section "Commercial snapshot" | — |
| **Items** | `ItemsRelationManager` | — |
| **Milestones** (badge = count) | `MilestonesRelationManager` | Phase 04: rename tab to **Billing** via `RelationGroup::make('Billing', [Milestones, Invoices])`, add Status/Invoice columns |
| **Variations** (badge = count) | `VariationsRelationManager` | — |
| **Activity** | new read-only `AuditEventsRelationManager` (see §2.7) | Phase 06 timeline view |
| Procurement / Delivery / Margin | not registered | Phase 05 `RelationGroup`s |

Stitch/DESIGN §4 put Items+Milestones+Variations *inside* Overview and Commercial as its own tab. A faithful 7-tab bar with Commercial as a separate non-relation tab needs a custom page view; pure-Filament gives the table above (DESIGN §4 "heavy relationship sections load when opened" supports RM tabs). Record this as a temporary, documented deviation; revisit when Phase 05 adds the remaining tabs. Alternative if a separate Commercial tab is required now: `Filament\Schemas\Components\Tabs` inside the infolist (`Tabs::make()->tabs([Tab::make('Overview')…, Tab::make('Commercial')…])->columnSpanFull()`) — costs a second tab row; not recommended.

### 2.1 Overview

**Stitch:** 2-col identity card (Number, "Standard Job" badge, Client link + NPWP, Source quotation link, Customer PO/COC + supplied/generated badge | Approved value, Approved at + by, Created, Operational/Financial closed, Cancelled); then Items table (Description & Scope, Qty, Unit, Unit cost, Line total, edit/delete), Milestones table (Stage type, Description, Amount, Is %, Percent, Due, **Status**) + "New payment milestone" + modal (types Down Payment/Progressive/Retention/Material Delivery; "Save as Draft"; "Available unallocated quota 25%"; "Milestone Deliverable Pre-requisite" textarea), Variations table (+ "cryptographically chained" footer).

**Current:** flat infolist; three RMs as separate default tabs.

- [FIX-NOW] Restructure `SalesOrderInfolist.php` with `Filament\Schemas\Components\Section` + `Grid` (pattern: `app/Filament/Resources/Clients/Schemas/ClientInfolist.php`): Section "Job" `->columns(2)`: left `number`, `client.name` (`->url(ClientResource::getUrl('view', …))`), `client.tax_number`, `quotation.number` (`->url(QuotationResource::getUrl('view', ['record' => $record->quotation_id]))`), `source_snapshot.customer_po_number`, PO-type `->badge()` from `source_snapshot.customer_po_is_system_generated` (`'System-generated COC'` info / `'Customer-supplied PO'` gray); right `approved_value`, `approved_at`, `created_at`, `operational_closed_at`, `financial_closed_at`, `cancelled_at`, `deleted_at`. **Switch PO fields from `quotation.*` to `source_snapshot.*`** (snapshot is the frozen truth; live quotation may differ).
- [FIX-NOW] `ItemsRelationManager`: add `description` (`->limit(80)->tooltip`), `product.unit` (Product has `unit`, Phase 02), `discount` + `discount_is_percentage`, right-align numerics (`->alignEnd()`), consistent money formatting (`->money('IDR')` — the codebase uses `->numeric()` everywhere; pick one and apply across all three RMs), `->emptyStateHeading()/Description()`.
- [FIX-NOW] `MilestonesRelationManager`: `public static function getBadge(Model $ownerRecord, string $pageClass): ?string` → count *(verify signature)*; `->emptyStateHeading('No payment milestones')->emptyStateDescription(...)->emptyStateActions([CreateAction …])`; in the form, `TextInput::make('percentage')` helper showing remaining unallocated % is a cheap, honest addition (`100 - sum(percentage)`); show helper text on the disabled Amount already exists. Add a non-blocking "Milestones total X of approved value Y" line (table `->description()` or header) so the `ApproveSalesOrder` failure is predictable.
- [FIX-NOW] `VariationsRelationManager`: `getBadge()` count; signed amount formatting (`+`/`−` prefix via `formatStateUsing`); empty state "No variations recorded".
- [STRIP] Item row edit/delete (items are an immutable snapshot — `ItemsRelationManager` docblock; scope changes go through variations). Milestone `Status` column and "Invoiced/Paid" badges → [NEW-PHASE] Phase 04. Milestone types Down Payment/Progressive/Retention/Material → keep `MilestoneType` (FullPayment/Custom per Specs 03). "Save as Draft" (a milestone is a row, not a document). "Milestone Deliverable Pre-requisite" field (no column; trigger conditions are Phase 04/05). "Standard Job" badge (job type is Phase 05). "Cryptographically chained / tamper-evident" footer, Actions column on variations.

### 2.2 Commercial

**Stitch:** "Snapshot at acceptance — record is frozen" banner + link to quotation; snapshot summary card (Source quotation, Quotation date, Acceptance timestamp, Valid until, Pricing mode badge, Discount amount/%, "Exchange baseline", Subtotal, PPN 11%, Total); Customer PO card (PO number/date, badge supplied vs COC + "Generated internally — not customer-issued", "Authorized Signatory", payment terms, currency, signed PDF via PrivyID); read-only items table (#, item+spec, SKU/category, Qty, Unit, Unit price, Disc, Line total) + filter/Export CSV/Print; totals with PPN; Terms & Conditions and Notes blocks; "Internal approval" signature block.

**Current:** nothing; `source_snapshot` carries quotation_number, pricing_mode, discount, discount_is_percentage, subtotal, total, customer_po_number/date/is_system_generated, accepted_at, items[]. `terms`/`notes` are **not** in the snapshot (only on `Quotation`).

- [FIX-NOW] Collapsible `Section::make('Commercial snapshot')->description('Snapshot at acceptance — not live')->collapsed()->columns(3)->columnSpanFull()` in `SalesOrderInfolist.php` reading `source_snapshot.quotation_number`, `.pricing_mode` (badge via `App\Enums\PricingMode`), `.discount` + `.discount_is_percentage` (`IconEntry::boolean()`), `.subtotal`, `.total`, `.accepted_at`, `.customer_po_number`, `.customer_po_date`, PO-type badge with note "Generated internally — not customer-issued" when true. `quotation.quotation_date`/`valid_until` are not snapshotted → read live but label them, or add to snapshot (see next).
- [FIX-NOW, optional data change] Add `terms`, `notes`, `quotation_date`, `valid_until` to `source_snapshot` in `app/Actions/Sales/CreateSalesOrderFromQuotation.php:50-69`; update `tests/Feature/Sales/SalesOrderWorkflowTest.php::test_job_created_from_accepted_quotation_preserves_the_source_snapshot`. Existing jobs keep the smaller snapshot → entries need `->placeholder('-')`. Without this, show `quotation.terms`/`quotation.notes` labelled "from quotation (live)" — acceptable but weaker.
- [FIX-NOW] Amber inline warning when `source_snapshot.customer_po_number` is null: `TextEntry` with `->color('warning')->icon(Heroicon::OutlinedExclamationTriangle)->visible(fn ($record) => blank(data_get($record->source_snapshot, 'customer_po_number')))`.
- [STRIP] PPN 11% lines/"Total Commercial Commitment" (tax is Phase 04 and 12%/11/12 DPP for Axen; Karunia non-tax; `QuotationTotalsCalculator.total` is pre-tax — checkpoint report l.102-111). "Exchange Baseline", "Authorized Signatory", "Digitally Signed via PrivyID" (e-signing deferred), "Standard Enterprise v4.2", internal-approval signature block, Export CSV / Print Schedule / filter (not spec'd), "catalog items allocated from Central Warehouse… dispatch requires approved DO" (inventory deferred), SKU/category column (SalesOrderItem has `product_id` only; `product.sku` could be shown but keep Items table single-sourced in the Items tab).

### 2.3 Billing

**Stitch:** 4 stat tiles (Approved, Invoiced, Paid, Outstanding); milestones table with Trigger deliverable, Invoicing status, "Issue Invoice" per row (disabled unless Approved); Issue-invoice review modal (DPP, PPN 11%, "Withholding Art 23", deliverable checklist incl. "e-Faktur NSFP allocation", bank remittance block); Issued invoices table (number, milestone ref, dates, total incl. PPN, paid, balance, status, PDF/Receipt).

**Current:** no invoice↔job link; no issuance action.

- [NEW-PHASE] Everything except the milestone table → Phase 04 (`04-billing-and-receivables/Specs.md`: invoice states Draft/Approved/Issued/Partially Paid/Paid, allocations-derived balances, review-summary-before-issue per DESIGN §7 Approval; memory.md l.51-55: `invoices` evolved in place, job link required when the client has an open job). When it lands: `InvoicesRelationManager` grouped with Milestones as `RelationGroup::make('Billing', …)`.
- [FIX-NOW] Only the "Issue Invoice requires Approved job" tooltip semantics can be prepared: nothing to build now.
- [STRIP] PPN 11%, e-Faktur/NSFP/DJP claims, "Withholding Article 23", "immutable tax invoice" wording tied to e-Faktur, remittance bank block in the modal (Phase 04 decides), "Adjust Schedule" button (milestones are editable only in Draft via existing RM).

### 2.4 Procurement

**Stitch:** tiles (PO count, committed spend, bills received, vendor payments); Vendor PO table (View/Receive/Cancel); shared-allocation matrix per PO line (Job, qty, amount, %, status; unallocated remainder; over-allocation blocker); Vendor Bills table (e-Faktur ref, paid/balance, Verify); "New Vendor PO"; "Export Procurement Audit Trail (.pdf)"; "dual authorization" governance banner.

- [NEW-PHASE] All → Phase 05 (`05-procurement-and-delivery/Specs.md` l.15-22; DESIGN §7 Shared procurement). Legacy `Vendor`/`Expense` models exist but are not job-linked — do not wire them as a stand-in.
- [STRIP] e-Faktur refs on bills, "Reserved Inventory"/warehouse bin/buffer-stock row (inventory deferred), export buttons, "dual authorization" banner (vendor-bill approval is Accountant+ self-approve, `FINALIZED-DECISIONS.md` l.40), "Filament Financial Engine … DJP e-Faktur 3.0".

### 2.5 Delivery

**Stitch:** "New Delivery Order / Record Handover" split button; Delivery Orders table (DO, date, carrier/driver/serials, status, evidence file, Track GPS); Handover (BAST) card with "Not required — goods-only" state + "Override: Record Optional Handover"; site photos; Operational-closure checklist card (Delivery ✓ / Handover not required / "Warehouse dispatch reconciliation") + Financial closure status block; "Reopen Operational Scope".

- [NEW-PHASE] All → Phase 05 (job `type`, Delivery Order, Handover, Service Report list beside DOs per DESIGN §4 "Serviced" stage, operational-closure gating; `SalesOrder::isFullyClosed()` is the hook). Financial-closure override is Owner-only with summary+reason (`FINALIZED-DECISIONS.md` l.45).
- [FIX-NOW] The two-signal principle can be shown now in the Overview "Job" section: `operational_closed_at` / `financial_closed_at` as two separate labelled entries with badges ("Open"/"Closed") — already columns.
- [STRIP] GPS tracking, "Warehouse Dispatch Reconciliation / Inventory deducted / serial registers" (inventory), "Delivery Records In Sync" cloud banner, "Reopen Operational Scope" (no approved rule), "BAST Protocol ID-KPT-GOV-2026 / Exempt Rule 4.2".

### 2.6 Margin

**Stitch:** "Restricted" lock; role-gated banner; tiles (Approved, allocated direct cost, gross margin amount, %, "internal hurdle rate 45%"); bar chart + accessible text summary; cost table (source, ref/payee, description, source total, share %, allocated, date, verification). Axen variant adds "Margin Analysis Breakdown", "Contract Margin Covenant Verification", "Margin Sensitivity & Exposure" sections (structural divergence — ignore, base variant is canonical).

- [NEW-PHASE] All → Phase 05 (job cost = allocated gross vendor cost; unallocated shown separately, `FINALIZED-DECISIONS.md` l.34; acceptance criteria l.52-54). Gate with `RelationManager::canViewForRecord()` using `User::companyRole()` (`app/Models/User.php:77`) against Owner/Admin/Accountant. Chart → load `dataviz` skill then; text summary is mandatory.
- [STRIP] hurdle rate / target / "vs Target", "Ledger Hash", "Export Margin Audit (.xlsx)", covenant/sensitivity panels, "4 records audited".

### 2.7 Activity

**Stitch:** filter chips (Status changes / Milestones / Variations / Invoices / Procurement / Deliveries / Security-Auth), user filter, date range; vertical timeline with icon, one-line description, relative+absolute time, actor, attachments/hashes; "Load earlier activity"; no edit/delete.

**Current:** `audit_events` table (company_id, user_id, action, entity_type, entity_id, reason, before, after, created_at) — only `job_variation.approved` is written for jobs. No relation from `SalesOrder` to `AuditEvent`.

- [FIX-NOW] (a) Log the missing events in the domain actions — `app/Actions/Sales/ApproveSalesOrder.php` (`sales_order.approved`) and `app/Actions/Sales/TransitionSalesOrderStatus.php` (`sales_order.status_changed`, before/after `status`; `sales_order.cancelled` when `$to === Cancelled`) via `AuditLogger::record($salesOrder->company, …, $salesOrder, …)`; these actions currently lack an audit call. Add tests in `tests/Feature/Sales/SalesOrderWorkflowTest.php`. Milestone create/update/delete audit → hook in `MilestonesRelationManager` action `after()` closures or model observer (keep in domain: prefer a small `App\Actions\Sales\{Create,Update,Delete}PaymentMilestone` — optional, larger). (b) Add `SalesOrder::auditEvents(): HasMany` → `hasMany(AuditEvent::class, 'entity_id')->where('entity_type', self::class)->latest('created_at')` (check what `AuditLogger::record()` stores in `entity_type` — `$subject::class` vs morph alias — before hardcoding). (c) New read-only `app/Filament/Resources/SalesOrders/RelationManagers/AuditEventsRelationManager.php` (`$relationship = 'auditEvents'`, `isReadOnly() true`, no header/record actions), columns `created_at` (dateTime + `->since()` tooltip), `action` (badge), `actor.name`, `reason`, `before`/`after` via `->formatStateUsing(json)`; `->filters([SelectFilter::make('action')…])` as the chip stand-in; `->defaultPaginationPageOption(25)`. Register as the last tab.
- [NEW-PHASE] Timeline rendering (custom Blade, not a table), invoice/procurement/delivery event types, attachments → Phase 06 (`06-documents-portal-reporting/Specs.md`).
- [STRIP] "Security / Auth" chip (membership events belong to the company audit page, not a job), "WAL-v2.1 / SHA-256 / Karunia Digital Key Authorization" copy, "Faktur Generated"/NSFP entries.

---

## 3. [FIX-NOW] execution list (ordered)

1. **Actions factory** — new `app/Filament/Resources/SalesOrders/Support/SalesOrderActions.php`; refactor `Tables/SalesOrdersTable.php:44-98` to use it; add `getHeaderActions()` to `Pages/ViewSalesOrder.php`. Test: Livewire `->callAction('approve')` on `ViewSalesOrder` (pattern in `tests/Feature/RelationManagerViewPageActionsTest.php`).
2. **Next-action + tracker helper** — domain helper (model method or `app/Support/Sales/JobTracker.php`) returning stages `[label, state: done|current|upcoming|not_required, icon]` and next action; unit-test against every `SalesOrderStatus` incl. Cancelled.
3. **Header partial** — `resources/views/filament/resources/sales-orders/job-header.blade.php` (Tailwind, DESIGN §1/§10 tokens, 8px rounding, no nested shadows, tabular numerals via `tabular-nums`); mount as first `View` component in `SalesOrderInfolist`. Tiles: Client, Approved value, Next action only. Run `npm run build` if new classes are added outside Filament's theme scan (check `resources/css/app.css` `@source`).
4. **Infolist sections** — rewrite `Schemas/SalesOrderInfolist.php` per §2.1/§2.2; switch PO fields to `source_snapshot.*`; add PO-missing warning.
5. **Tabs** — in `ViewSalesOrder`: `hasCombinedRelationManagerTabsWithContent(): true`, `getContentTabLabel(): 'Overview'`, `getContentTabPosition(): ContentTabPosition::Before` *(verify names in vendor once installed)*. In `SalesOrderResource::getRelations()` keep order Items, Milestones, Variations, AuditEvents.
6. **RM polish** — columns/badges/empty states per §2.1; `getBadge()` on Milestones/Variations.
7. **Audit coverage + Activity RM** — per §2.7 (a)(b)(c); tests for the new audit rows.
8. **Quotation back-link** — `QuotationInfolist.php:33` `salesOrder.number` → `->url(SalesOrderResource::getUrl('view', ['record' => $record->salesOrder]))` (DESIGN §4 deep links both ways).
9. Optional: snapshot `terms/notes/quotation_date/valid_until` in `CreateSalesOrderFromQuotation.php` + test update.
10. `vendor/bin/pint --dirty`, `php artisan test tests/Feature/Sales`.

Docs to touch after: `docs/rebuild/outputs/checkpoints/17-phase-03-checkpoint-report.md` (tab-order deviation), `memory.md` (decision: Commercial is an Overview section until Phase 05 tabs exist).
