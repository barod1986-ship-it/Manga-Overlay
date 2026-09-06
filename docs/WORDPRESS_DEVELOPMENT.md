# WordPress foundation

The core plugin contains the T-03 bootstrap, all nine canonical SQL tables, transaction ownership, typed chapter/page/library/profile repositories, T-05 work registrations, T-06 content administration and T-07 public data APIs. The accompanying theme adds T-08 and the T-09 reader foundation. T-04 remains partial for translation mutation/locks/presets/report services and validators.

## Build and activate on development WordPress

Use WordPress 7.1.x, PHP 8.4.x and MySQL 8.4 LTS or the MariaDB versions listed in the frozen specification. The production infrastructure remains the user's xCloud / NGINX / MySQL application; this document does not assert access to it or its installed versions.

```bash
composer dump-autoload --working-dir=wp-content/plugins/manga-overlay-core --classmap-authoritative
```

Copy the complete `manga-overlay-core` directory, including the generated `vendor/` autoloader, into the development application's plugin directory. Activate it through WordPress. Source code resolves runtime paths from the plugin location and WordPress APIs. It does not load the PoC into a public editor route yet.

Activation installs the canonical schema using actual `dbDelta()` and verifies InnoDB engines before updating `mol_db_version`. A connection-scoped migration lock prevents concurrent upgrades. The frozen SQL is checked against `DATABASE_SCHEMA.md` by the source contract check. Roles have a separate version marker, so normal requests do not reapply revoked individual grants. Deactivation retains data and capabilities.

`mol_work` is manageable in core WordPress administration. Four taxonomies are registered and the six canonical work types are seeded. Registered metadata includes alternative titles, reading mode and direction. `mol_manage_content` does not grant `manage_options` or `mol_upload_content` to users who do not already have it.

Work deletion/trashing is blocked when chapter rows exist, so core WordPress deletion cannot bypass the future cascading service. No general uninstall cleanup is implemented in this foundation: deleting plugin files retains project data. Network-wide activation is not supported by this initial single-application implementation; per-site activation is required.

## Installable development ZIP

After all required checks pass, the `development-package` GitHub job builds `manga-overlay-core-development.zip` and its SHA256 checksum, then installs and activates that exact ZIP on a fresh WordPress 7.1 application. Download the `manga-overlay-development-package` Actions artifact, extract the artifact wrapper, and select the inner plugin ZIP in WordPress Plugins → Add New → Upload Plugin on a development site. The dependency autoloader and built PoC are included; Node and Composer are not needed on that site. The package now includes public reader assets. Install the accompanying `manga-overlay-theme-development.zip` through Appearance → Themes to enable the Arabic library/work/profile/reader screens. Translation editing persistence remains outside this development increment; account reading progress is implemented.

For a local package, first build the frontend and Composer autoloader, then run `python scripts/package-development.py`. Output goes to `build/`, which is excluded from git.

## Integration testing

The GitHub job `wordpress-foundation` runs PHP 8.4 and WordPress 7.1 against both `mysql:8.4` and `mariadb:10.11`. Containers, database names and generated accounts are disposable CI fixtures, unrelated to production credentials. The database listener is part of the temporary CI environment. The test script refuses to run without `MOL_TEST_ENV=1`, WP-CLI and `WP_ENVIRONMENT_TYPE=local`.

Tests cover the non-default `qa_` table prefix, nine InnoDB/utf8mb4 tables, idempotent migrations, permission grants/revocation, core REST work creation and protected metadata, the six work types, DECIMAL-to-float normalization, UTC dates, commit/rollback, nested-transaction rejection, geometry bounds, failed-migration version markers and data retention on deactivation.

The job is not a deployment workflow. Tests do not prove production reverse-proxy behavior, mobile devices, performance or completion of the remaining translation-mutation `/mol/v1` controllers. PHPStan/PHPCS and the remaining domain/REST tests are still outstanding gates.

Primary implementation references: [WordPress table creation](https://developer.wordpress.org/plugins/creating-tables-with-plugins/), [CPT registration](https://developer.wordpress.org/reference/functions/register_post_type/) and [registered metadata](https://developer.wordpress.org/reference/functions/register_meta/).

## Public reader increment (2026-09-06)

Core 0.4.0 and Theme 0.1.0 implement chapter/page administration, uploads, public reads, SSR library/work/profile pages, the shared React overlay reader, and authenticated reading-progress writes. Both ZIPs are installed from the actual generated artifacts in CI. CI runs real cookie/nonce HTTP tests and desktop/mobile-emulation reader tests on disposable WordPress instances for MySQL and MariaDB; it never connects to xCloud.

The new routes are exactly the frozen public URLs: library, series/work/chapter, and `/u/{username}/`. Normal plugin init performs a one-time rewrite refresh when the public route marker changes. Pretty permalinks are required for the specified public URL scheme. Use WordPress Settings → Permalinks on a development instance if configuring from scratch; NGINX configuration is outside this increment.

Work mutation protection uses a connection-scoped named database lock around chapter creation and the whole WordPress work deletion/trash operation. Locks also release during shutdown if another plugin interrupts core deletion. References: [wp_delete_post hooks](https://developer.wordpress.org/reference/functions/wp_delete_post/), [wp_trash_post hooks](https://developer.wordpress.org/reference/functions/wp_trash_post/) and [rewrite rules](https://developer.wordpress.org/reference/functions/add_rewrite_rule/).

Reader preferences are per work in localStorage. Guest progress uses `mol_progress_{chapterId}`; signed-in progress is supplied from the current user's database row only, avoiding reuse of another visitor's local state. Reader HTML and MOL REST replies are private/no-store. This does not claim protection for raw media URLs; deployment media-access/cache policy remains to be verified on the actual server.
