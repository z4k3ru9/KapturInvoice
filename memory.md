# KapturInvoice project memory

Current entry snapshot: TallStackUI/Livewire replaces Filament. Phase 07 import work is partially implemented; Phase 08 owns current release evidence. Both live company sources are InvoiceNinja v5 (`legacy_v5_company_a` and `legacy_v5`); v4 is archive support. Production targets cPanel with cron and no Terminal/SSH assumption. Bootstrap/import routes exist but production bootstrap, imports, and restore checks remain pending evidence.

Binding decisions live in [FINALIZED-DECISIONS.md](docs/rebuild/specs/FINALIZED-DECISIONS.md): separate company deployments, preserved financial history, v5 sources, approved portal signing, cancelled automatic blank rows, and human/compliance gates. The live currency API is deferred.

Keep unresolved work in the Phase 07/08 pending checklists and link current exceptions from [docs/out-of-scope-findings.md](docs/out-of-scope-findings.md). Distinguish implemented, verified on a stated commit, pending evidence, deferred, and cancelled. Do not treat historical test counts or old completion records as current release approval. [PR #20](https://github.com/z4k3ru9/KapturInvoice/pull/20) remains an open historical reference, not a merged fix.
