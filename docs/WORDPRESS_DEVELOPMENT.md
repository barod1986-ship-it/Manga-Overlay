# WordPress foundation

The core plugin now contains the T-03 bootstrap, the nine canonical SQL tables from T-04, transaction ownership, a chapter DTO/repository, normalized geometry validation, and the T-05 work CPT/taxonomy/meta registrations. T-04 remains partial: the other repositories, style/request validators and content services still need implementation.

## Build and activate on development WordPress

Use WordPress 7.1.x, PHP 8.4.x and MySQL 8.4 LTS or the MariaDB versions listed in the frozen specification. The production infrastructure remains the user's xCloud / NGINX / MySQL application; this document does not assert access to it or its installed versions.

```bash
composer dump-autoload --working-dir=wp-content/plugins/manga-overlay-core --classmap-authoritative
```

Copy the complete `manga-overlay-core` directory, including the generated `vendor/` autoloader, into the development application's plugin directory. Activate it through WordPress. Source code resolves runtime paths from the plugin location and WordPress APIs. It does not load the PoC into a public editor route yet.

Activation installs the canonical schema using actual `dbDelta()` and verifies InnoDB engines before updating `mol_db_version`. A connection-scoped migration lock prevents concurrent upgrades. The frozen SQL is checked against `DATABASE_SCHEMA.md` by the source contract check. Roles have a separate version marker, so normal requests do not reapply revoked individual grants. Deactivation retains data and capabilities.

`mol_work` is manageable in core WordPress administration. Four taxonomies are registered and the six canonical work types are seeded. Registered metadata includes alternative titles, reading mode and direction. `mol_manage_content` does not grant `manage_options` or `mol_upload_content` to users who do not already have it.

Work deletion/trashing is blocked when chapter rows exist, so core WordPress deletion cannot bypass the future cascading service. No general uninstall cleanup is implemented in this foundation: deleting plugin files retains project data. Network-wide activation is not supported by this initial single-application implementation; per-site activation is required.

## Integration testing

The GitHub job `wordpress-foundation` runs PHP 8.4 and WordPress 7.1 against both `mysql:8.4` and `mariadb:10.11`. Containers, database names and generated accounts are disposable CI fixtures, unrelated to production credentials. The database listener is part of the temporary CI environment. The test script refuses to run without `MOL_TEST_ENV=1`, WP-CLI and `WP_ENVIRONMENT_TYPE=local`.

Tests cover the non-default `qa_` table prefix, nine InnoDB/utf8mb4 tables, idempotent migrations, permission grants/revocation, core REST work creation and protected metadata, the six work types, DECIMAL-to-float normalization, UTC dates, commit/rollback, nested-transaction rejection, geometry bounds, failed-migration version markers and data retention on deactivation.

The job is not a deployment workflow. Tests do not prove production reverse-proxy behavior, mobile devices, performance or the future `/mol/v1` controllers. PHPStan/PHPCS and the remaining domain/REST tests are still outstanding gates.

Primary implementation references: [WordPress table creation](https://developer.wordpress.org/plugins/creating-tables-with-plugins/), [CPT registration](https://developer.wordpress.org/reference/functions/register_post_type/) and [registered metadata](https://developer.wordpress.org/reference/functions/register_meta/).
