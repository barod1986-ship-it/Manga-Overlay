# Manga Overlay Core

T-03 through T-06 WordPress core, persistence, work content, and chapter/page management for Master Spec v1.1.3.

## Scope

- Composer PSR-4 autoloading from `MOL\\` to `src/`.
- Idempotent activation and runtime version checks.
- Versioned `dbDelta()` migration for the nine canonical InnoDB tables.
- `mol_roles_version` and the canonical MVP roles/capabilities.
- Repositories for every domain table, with explicit scalar/JSON normalization.
- Geometry, dictionary, style, and preset-scope validators.
- Explicit transaction start/commit/rollback boundaries.
- Public `mol_work` CPT and the four canonical taxonomies.
- Registered work metadata with typed Core REST schemas and capability-gated mutation.
- `/library/`, `/series/{slug}/`, chapter/editor, and user-profile rewrite contracts.
- Capability-gated chapter create/update/delete and translation-review REST routes.
- JPEG/PNG/WebP page upload with actual MIME/decoder validation, request limits, idempotency, and optional explicit WebP derivatives.
- Transactional two-phase page reorder and service-owned cascade cleanup for page/chapter deletion.
- Chapter and upload admin screens with natural filename sorting, previews, a two-request queue, and page-order controls.
- Opt-in-only uninstall cleanup through `mol_delete_data_on_uninstall=1`.

Public MOL read routes, the public theme, and editor integration deliberately belong to later tasks.

## Check

```bash
composer install
composer run check
```

The `PHP Bootstrap` workflow also publishes a 14-day `manga-overlay-core-candidate`
artifact containing an installable ZIP with the authoritative production
autoloader. `Database Matrix` activates the same source on WordPress 7.1 with
MySQL 8.4 and MariaDB 10.11.
