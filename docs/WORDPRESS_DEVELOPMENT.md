# WordPress foundation

The core plugin contains the T-03 bootstrap, all nine canonical SQL tables, transaction ownership, typed chapter/page/library/profile repositories, T-05 work registrations, T-06 content administration and T-07 public data APIs. The accompanying theme adds T-08 and the T-09 reader foundation. T-15 adds preset management and T-17 adds report services and moderation. Element mutations, validation, locks and contributions are implemented in Core 0.7.0.

## Build and activate on development WordPress

Use WordPress 7.1.x, PHP 8.4.x and MySQL 8.4 LTS or the MariaDB versions listed in the frozen specification. CI uses these versions. On 2026-09-09, the user authorized installation on the target site: WordPress displayed version 7.1, Core 0.10.1 and Theme 0.1.2 were installed. Hosting configuration and its PHP/database versions have not been independently inspected in this session.

```bash
composer dump-autoload --working-dir=wp-content/plugins/manga-overlay-core --classmap-authoritative
```

Copy the complete `manga-overlay-core` directory, including the generated `vendor/` autoloader and frontend assets, into the development application's plugin directory. Activate it through WordPress. Source code resolves runtime paths from the plugin location and WordPress APIs. The authenticated editor route loads the connected T-10 shell; the input PoC remains a separate development experience.

Activation installs the canonical schema using actual `dbDelta()` and verifies InnoDB engines before updating `mol_db_version`. A connection-scoped migration lock prevents concurrent upgrades. The frozen SQL is checked against `DATABASE_SCHEMA.md` by the source contract check. Roles have a separate version marker, so normal requests do not reapply revoked individual grants. Deactivation retains data and capabilities.

`mol_work` is manageable in core WordPress administration. Four taxonomies are registered and the six canonical work types are seeded. Registered metadata includes alternative titles, reading mode and direction. `mol_manage_content` does not grant `manage_options` or `mol_upload_content` to users who do not already have it.

Work deletion/trashing is blocked when chapter rows exist, so core WordPress deletion cannot bypass the future cascading service. No general uninstall cleanup is implemented in this foundation: deleting plugin files retains project data. Network-wide activation is not supported by this initial single-application implementation; per-site activation is required.

## Installable development ZIP

After all required checks pass, the `development-package` GitHub job builds `manga-overlay-core-development.zip` and its SHA256 checksum, then installs and activates that exact ZIP on a fresh WordPress 7.1 application. Download the `manga-overlay-development-package` Actions artifact, extract the artifact wrapper, and select the inner plugin ZIP in WordPress Plugins → Add New → Upload Plugin on a development site. The dependency autoloader and built PoC are included; Node and Composer are not needed on that site. The package now includes public reader assets. Install the accompanying `manga-overlay-theme-development.zip` through Appearance → Themes to enable the Arabic library/work/profile/reader screens. Core 0.10.1 persists translation edits, leases, versions and contributions; account reading progress is also implemented.

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

## Connected editor shell T-10 (Core 0.5.0 / Theme 0.1.1)

The plugin owns `templates/editor.php` and `assets/dist/editor/`, built with `vite.editor.config.ts`. Its canonical route is `/series/{work-slug}/chapter/{chapter-slug}/edit/`. Grant `mol_use_editor` to enter; membership alone is insufficient. The reader and content admin expose the link only to authorized users. A route marker upgrade refreshes the existing rewrite table once.

T-10 introduced a read-only shell; T-11 adds session-only editing described below. It fetches the chapter, page list and selected page's elements from the existing authenticated REST endpoints, preserving IDs/versions in state. Browser fragments identify page/element IDs across reload and history navigation. The stage uses actual image dimensions and the shared renderer; preview/toggle retain the original image node. Desktop side panels become mobile sheets. No mutation endpoints, new REST contracts or local draft persistence are introduced.

CI adds route/capability/revocation and private-bootstrap integration coverage, plus browser scenarios for portrait/landscape geometry, layers/properties, history restoration, empty content, failed reads, stale-response cancellation and session expiry. The package gate requires the editor template and its built assets. Exact head/run results are maintained in PR #1. Testing on the real xCloud application and physical devices remains deferred/open.


## Connected element editing T-11 (Core 0.6.0)

`mol_use_editor` grants inspection; `mol_edit_translations` enables local edits; deletion additionally checks `mol_delete_translation_elements`. The private bootstrap exposes booleans for these grants. This is UI gating; REST write authorization/validation remains T-12/T-13 work.

Working elements retain an immutable server baseline. New/duplicated elements have a session key and no invented server ID/version/attribution. Per-page edits survive in-app navigation and preview, but do not persist across reload or leave. A beforeunload warning and an explicit unsaved status make that boundary visible. Access failures hide the stage and clear the session copies. No mutation requests, locks, autosave or browser draft storage are implemented in this increment.

Moveable drag/resize/rotate operates in pixels during the gesture and commits bounded normalized geometry at its end, using actual portrait/landscape image dimensions. Numeric inputs and step buttons cover every transform; keyboard shortcuts ignore native fields and IME composition. Structured style fields cover all four types, including type-specific tail/burst options. Preview retains the original image and shared renderer. Snapping/presets and full auto-fit remain T-15; physical touch and keyboard acceptance remain T-16. The existing PoC stays independently available.

CI tests actual WordPress data, independent grants, source immutability, plain-text safety, page-draft isolation, duplicate/delete/undo, numeric/keyboard controls, structured effects, real mouse transforms at zoom, navigation and a guarded reload. See PR #1 for exact head/run and package links. No real xCloud server test or deployment is part of this batch.

## Editor persistence T-12/T-13/T-14 (Core 0.7.0)

The connected editor now writes to the frozen element and lock routes. Text/property changes debounce at 1200 ms; Moveable end events request immediate saves. Pending edits and tokens remain in memory, with a beforeunload warning while unsaved. The source snapshot advances only after the server confirms the response. New POST retries retain both the original body and idempotency key. Ambiguous PATCH failures require a current-version check before retry. Conflict recovery offers current versus local text/style/geometry and reapplies only changed fields after explicit user choice.

The server validates closed create/patch schemas, immutable type-specific styles and combined image geometry. It serializes chapter/element/lease row access in the same order as cascading deletion; version/lease checks, persistence and contribution UPSERT are one transaction. Lease tokens use 32 random bytes; only the owner receives them. Leases last 45 seconds and renew every 15 seconds in the active editor. The manager force-release uses DELETE on the same lock route. Element writes default to 180/minute and lock acquisition to 120/minute, per user through persistent atomic counters. Renew/release are not rate limited.

The element contract evaluator is deliberately scoped to the exported frozen vocabulary, not a general-purpose JSON Schema implementation. CI compares 2924 generated cases against Python jsonschema Draft202012Validator and validates real response DTOs. References: [JSON Schema object composition](https://json-schema.org/understanding-json-schema/reference/object) and [conditional validation](https://json-schema.org/understanding-json-schema/reference/conditionals). The base styles live in database/base-styles.json for both clients and PHP. Preset resolution is available for element creation; preset management remains T-15.

CI exercises actual HTTP ETag/If-Match, simultaneous identical POSTs and same-version PATCH races, rollback on contribution failure, permission errors and lease lifecycle. Browser scenarios verify save/reload, an acknowledged-on-server but lost POST response, lock conflicts, explicit reapplication and renewal loss. Test credentials and mutable content are confined to disposable fixtures. The actual NGINX/CDN header path still needs the deferred server test; this is a development package, not T-20 release acceptance.


## Page deletion concurrency correction (Core 0.7.1)

Element creation and page deletion first discover the parent through an ordinary read, then acquire the chapter lock. Under REPEATABLE READ, a second ordinary read may retain a page that another transaction has already deleted. The post-lock check now uses SELECT FOR UPDATE, as does the remaining-page list used for deletion/reordering. This retains the chapter → page → element → lease lock order without changing the frozen schema.

The disposable WordPress content harness adds two real database connections and deterministic transaction interleaving. Its control shows that an ordinary read retains the deleted page while a locking read sees its deletion. Regression cases require failed creation to leave no element, contribution or successful idempotency journal entry, and require competing page deletes to use current page IDs. The session isolation level is restored after the test. All verification for this correction runs in GitHub CI; xCloud/NGINX and physical-device acceptance remain deferred.

Reference: [MySQL consistent nonlocking reads](https://dev.mysql.com/doc/refman/8.4/en/innodb-consistent-read.html).


## Presets, fitting and snapping T-15 (Core 0.8.0)

The authenticated preset API implements the frozen three scopes and immutable type/scope, with additional work/global capabilities and private personal ownership. Preset styles are validated against the stored element type. A scope advisory lock serializes empty-set creation before the transaction locks matching rows and replaces the default. Work mutations also use the existing parent-deletion lock; permanent work deletion removes its presets before releasing that lock. Invalid GET filters return an empty list within the frozen GET's declared status set.

The properties strip applies styles through ordinary element autosave, saves named presets in permitted scopes, updates a selected preset from the current style and deletes it independently of saved elements. Default style resolution is shared with the editor preview; stale optional effects are explicitly cleared during application. Failed writes require refreshing the list before repeating a preset mutation, since this route has no idempotency contract.

The shared renderer records its measured fitted size; disabling auto-fit freezes that size while retaining box geometry. A minimum-size overflow message asks for more space or shorter text. Page/element edges and centers are Moveable guidelines, with a constant five displayed-pixel threshold, Alt bypass, and numeric centering alternatives. See [Moveable Snappable API](https://daybrush.com/moveable/release/latest/doc/Moveable.Snappable.html).

CI includes two-request concurrent default creation/switching, scope privacy and capability revocation, transaction rollback and schema conformance, and connected browser scenarios for the Arabic preset flow, fitting/freezing and snapping after zoom. This is development verification, not physical-device, manual SFX visual or xCloud/NGINX acceptance.


## Mobile editor T-16 (Core 0.9.0)

The connected stage arbitrates native touch sequences before Moveable: a selected element uses one finger; blank-area dragging pans; a second stage finger cancels any uncommitted element transform and owns the sequence until all fingers lift. Zoom is 0.5–3 and preserves the image point under the moving midpoint, subject to scroll bounds. Viewport gestures never call element persistence. `touchcancel`, layout changes and teardown cannot commit stale pixels. Preview retains browser-native panning/pinch behavior.

On phones, property sections start collapsed around the always-available Arabic textarea. The 45%/85% sheet follows `visualViewport.height/offsetTop`, clamps above the measured toolbar, and scrolls the focused native field into its usable area. Touch scrolling remains native in the sheet. Moveable controls retain 44px hit areas with larger visible handles and a separate rotation control; numeric and step controls remain available.

`tests/wordpress/e2e/mobile.spec.ts` sends Chromium CDP touch input and models a reduced VisualViewport for keyboard geometry. These tests run only in GitHub CI and supplement the existing desktop/phone save, lease and recovery tests. They do not establish real iOS/Android keyboard, pinch or Arabic/SFX acceptance. Physical devices and the user's target server remain deferred. Sources: [VisualViewport](https://developer.mozilla.org/en-US/docs/Web/API/VisualViewport), [CDP touch input](https://chromedevtools.github.io/devtools-protocol/tot/Input/#method-dispatchTouchEvent). The exact verified commit and installation artifact are recorded in PR #1.


## Reports T-17 (Core 0.10.0 / Theme 0.1.2)

The reader now exposes a native Arabic report dialog; install the matching theme to include its mount/bootstrap. Members require only `mol_report_issue`. `GET /reports` and `PATCH /reports/{id}` require the independent `mol_moderate_reports` capability and a valid REST nonce. The “بلاغات الترجمة” admin screen requires that same capability, without granting content management or editor rights. Its read-only context link rechecks both moderation access and chapter visibility before redirecting to the editor or public reader.

Frozen ReportCreate/ReportPatch schemas are exported and compared with the source. Parent membership and draft visibility are checked under chapter/page/element locks before insert. An element-only report stores its derived page so deletion preserves the remaining context. The existing deletion services retain reports when descendants disappear and remove them with the chapter. Reports do not alter chapter review status or translation contributions.

Report creation defaults to 10 attempts per minute per account via the existing atomic database counter (`mol_reports_per_minute`). 429 returns Retry-After. The UI does not automatically retry ambiguous POST responses because this frozen endpoint has no idempotency contract; it retains the draft and asks the reader to verify delivery with a moderator. Definitive rate rejection allows manual retry after the countdown; session revocation stops writing. Moderation serializes state writes with a row lock; repeated identical states preserve resolution attribution, while reopening clears it. There is no optimistic version contract for reports, so the latest completed state change takes effect. After an ambiguous PATCH the UI requires a fresh list before another action.

All new tests run in GitHub CI only: authorization, nullable references, cross-parent rejection, independent contract vectors, two-connection deletion race, actual HTTP concurrency/cache headers, member report submission and moderator review on both browser layouts. The reduced-device and target-server gates from T-16 remain open. Current verification and install artifacts are linked from PR #1. References: [WordPress multiline sanitization](https://developer.wordpress.org/reference/functions/sanitize_textarea_field/), [native dialog behavior](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/dialog).

## Managed-host administration compatibility (Core 0.10.1, 2026-09-09)

The user authorized installation and live tests on the target WordPress site, superseding the earlier server deferral for this scope. Core 0.10.0 and Theme 0.1.2 installed and activated successfully. The reader and connected editor initialized, reading progress saved, and a test report was accepted. The content and reports administration modules did not initialize. A direct browser navigation to the content `.mjs` asset returned `ERR_BLOCKED_BY_CLIENT`; response headers were not available, so an incorrect MIME type is a compatibility hypothesis, not a measured server fact.

Core 0.10.1 builds both administration entries and their relative imports into `assets/dist/admin/*.js`, matching the working reader/editor packaging. This removes the requirement for a managed host to recognize `.mjs`, without changing server security settings. The chapter form stays disabled until its REST handler is ready, preventing accidental native GET submissions if assets fail. The ZIP build and install gates require both administration bundles. CI exercises both screens with `.mjs` served as `application/octet-stream`, and checks that a failed content bundle leaves the form disabled. See [MDN module MIME requirements](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Modules). The exact CI #38 ZIP was subsequently installed on the target site: both administration screens initialized, a draft chapter and two image uploads succeeded, page order survived reload, and a report was resolved. Element text persisted, but a PATCH success response did not satisfy the editor’s strict ETag comparison; explicit recovery and reload confirmed the stored text. The user subsequently authorized xCloud access. The measured origin Brotli response weakened the ETag; the scoped hosting correction below restored normal autosave acknowledgement and persistence across reload on 2026-09-10. The draft remains unpublished. See docs/verification/RESULTS.md for measured HTTP results and remaining acceptance limits.

## xCloud REST response compatibility (2026-09-10)

The deployed NGINX 1.31.4 origin returned a strong version ETag with identity encoding and a weak ETag with Brotli. Through Cloudflare, PATCH replies carried weak ETags even when the visitor requested identity. The editor correctly refused to advance its confirmed baseline. The application, frozen contract and installed Core 0.10.1 / Theme 0.1.2 packages did not need changes.

Two site-level files in `scripts/deployment/` are saved and active in xCloud Nginx Customization:

| File | xCloud config type | Purpose |
|---|---|---|
| `xcloud-mol-rest-strong-etag.conf` | Inside PHP Location Block | Disable gzip/Brotli only for MOL REST, add `Cache-Control: no-transform`, and merge inherited response headers |
| `xcloud-mol-rest-query-routing.conf` | Inside Main Location Block | Internally rewrite MOL `rest_route` requests to `/index.php`, retaining the original query and HTTP method |

Use the names `mol-rest-strong-etag` and `mol-rest-query-routing` in xCloud. The header file requires NGINX **1.29.3 or newer**, the Brotli module, and an existing PHP location; it is not a generic configuration for older NGINX or subdirectory WordPress installations. `add_header_inherit merge` preserves parent security headers. The query rewrite fixes PATCH returning NGINX 405 when the root `try_files` selects the existing directory before WordPress can route the request.

Review the existing site configuration, run xCloud's **Run & Debug** (which tests and reloads NGINX), then **Save Config**. Both files passed syntax validation and reload on the target site. To roll back, disable these two custom configs through xCloud and validate/reload; the original application files and global compression settings do not need replacement. Repeat the HTTP probe after changing the CDN or hosting configuration.

### Manual staging probe

Copy `scripts/deployment/etag-smoke.php` to a private path outside the web root. Run from the WordPress directory with an authorized editor ID and an existing draft page ID:

```bash
php8.4 /usr/local/bin/wp eval-file /private/path/etag-smoke.php USER_ID DRAFT_PAGE_ID pretty
php8.4 /usr/local/bin/wp eval-file /private/path/etag-smoke.php USER_ID DRAFT_PAGE_ID query
```

The target terminal's default PHP was 8.3.33, so invoking `wp` without `php8.4` fails the installed Composer PHP requirement. The probe creates one synthetic element, replays its creation key, acquires and renews its lease, checks missing/stale If-Match and three Accept-Encoding values, then deletes the element. It expires only its own temporary login session. It logs selected HTTP fields, never cookies, nonces, lease tokens or response bodies. This is a manual staging diagnostic, not a plugin endpoint or an automatically executed CI test. If cleanup fails, inspect the reported synthetic element ID before removing it through the normal element service; never bulk-delete chapter content. Remove the private probe and its logs when finished.

For origin diagnosis only, append `origin` and an optional trusted origin certificate path after the route argument. This resolves the same HTTPS hostname to `127.0.0.1` while retaining certificate validation; do not disable TLS verification. The ordinary probe uses the public hostname through the actual CDN path.

References: [Cloudflare compression and no-transform](https://developers.cloudflare.com/speed/optimization/content/compression/), [Cloudflare ETag behavior](https://developers.cloudflare.com/cache/reference/etag-headers/), [NGINX header inheritance](https://nginx.org/en/docs/http/ngx_http_headers_module.html), [NGINX gzip contexts](https://nginx.org/en/docs/http/ngx_http_gzip_module.html), [NGINX rewrite processing](https://nginx.org/en/docs/http/ngx_http_rewrite_module.html).
