# Manga Overlay Core

T-03 WordPress plugin bootstrap for Master Spec v1.1.3.

## Scope

- Composer PSR-4 autoloading from `MOL\\` to `src/`.
- Idempotent activation and runtime version checks.
- `mol_db_version` bootstrap baseline (`0` because T-03 creates no tables).
- `mol_roles_version` and the canonical MVP roles/capabilities.
- Opt-in-only uninstall cleanup through `mol_delete_data_on_uninstall=1`.

Database tables, CPTs, REST routes, and editor integration deliberately belong to later tasks.

## Check

```bash
composer install
composer run check
```
