# Phase 02: Parties and Catalog

> **Status (verified 2026-09-15):** ✅ Complete, merged into `main`. See `memory.md` "Current state" and `docs/rebuild/outputs/HISTORY.md`'s Phase 02 entry.

## Goal

Build company-scoped clients, contacts, vendors, catalog items, and prices that can feed quotations, jobs, invoices, and procurement.

## Primary files

- canonical migrations and models for clients, contacts, vendors, addresses, portal links, catalog items, and prices
- Filament resources and relation managers for party/catalog administration
- policies, factories, seeders, and feature tests
- migration source-reference adapters

## Requirements

- A client belongs to exactly one company.
- Contacts belong to clients and may be designated for portal access.
- Vendors are entities without login at launch.
- Catalog item types are `product`, `service`, `labor`, and `other`.
- Catalog items support unit, default price, descriptions, `standard taxable` or `non-taxable` category, and optional stock flag.
- Stock flag must never imply inventory availability.
- Price lists can be imported without creating full inventory behavior.
- Search starts after two characters, is debounced, bounded, and server-paginated.
- Preserve source system/version/legacy ID for migrated records.

## Required tests

- Company scoping for every party/catalog resource.
- Duplicate names across companies remain separate.
- Contact portal eligibility is scoped to its client/company.
- All four catalog types can be selected by later quotation code.
- Search does not load unbounded records.
- Soft deletion of unused master data does not remove historical snapshots.

## Acceptance criteria

The next phase can create a quotation from either catalog lines or custom lines without querying legacy tables directly.

## Pause checkpoint

Stop after party/catalog CRUD, policies, indexes, and tests pass. Next phase: `03-sales-and-job`.
