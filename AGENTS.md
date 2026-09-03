# Repository instructions

These instructions apply to the entire repository.

1. Read `docs/master-spec/v1.1.3/README.md` before changing product code.
2. Treat the authority order in that README as binding. Do not invent routes, capabilities, tables, or MVP features.
3. Follow `DEVELOPMENT_PLAN.md` in order. Do not mark a task complete while its Definition of Done or acceptance criteria remain unverified.
4. Keep the frozen files under `docs/master-spec/v1.1.3/` byte-for-byte unchanged. A spec change requires a new versioned directory and an ADR/decision update.
5. WordPress Core owns users, sessions, media, the `mol_work` CPT, and taxonomies. The future `manga-overlay-core` plugin owns domain logic, REST, tables, and the editor. The future theme owns public presentation only.
6. Use PHP 8.4, WordPress 7.1.x, Node 24 LTS, InnoDB, and `utf8mb4` as the project baseline.
7. Preserve Arabic/RTL as first-class behavior. Image-space geometry is physical and must not be mirrored merely because the UI is RTL.
8. Run the relevant checks before committing. State clearly when a hardware, browser, database, or staging gate remains pending.
