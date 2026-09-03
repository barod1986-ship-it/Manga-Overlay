# Manga Overlay Core

T-03/T-04 WordPress core and persistence foundation for Master Spec v1.1.3.

## Scope

- Composer PSR-4 autoloading from `MOL\\` to `src/`.
- Idempotent activation and runtime version checks.
- Versioned `dbDelta()` migration for the nine canonical InnoDB tables.
- `mol_roles_version` and the canonical MVP roles/capabilities.
- Repositories for every domain table, with explicit scalar/JSON normalization.
- Geometry, dictionary, style, and preset-scope validators.
- Explicit transaction start/commit/rollback boundaries.
- Opt-in-only uninstall cleanup through `mol_delete_data_on_uninstall=1`.

CPTs, REST routes, and editor integration deliberately belong to later tasks.

## Check

```bash
composer install
composer run check
```

The `PHP Bootstrap` workflow also publishes a 14-day `manga-overlay-core-candidate`
artifact containing an installable ZIP with the authoritative production
autoloader. `Database Matrix` activates the same source on WordPress 7.1 with
MySQL 8.4 and MariaDB 10.11.
