# Implementation status

Last updated: 2026-09-03

## T-00 — Environment

- Node.js target fixed to major 24 via `.nvmrc` and `engines`.
- Frontend PoC CI runs on Node 24.
- PHP 8.4 and Composer are exercised by the plugin-bootstrap CI job.
- WordPress 7.1, MySQL 8.4, and MariaDB 10.11 are exercised by the real migration matrix.
- The local bootstrap environment has Node 24 but does not expose PHP, Composer, or a database client; backend compatibility is therefore established by the pinned GitHub Actions jobs below.

## T-01 — PoC renderer

Implemented:

- Physical image-space geometry using `MOL_UNIT = 1,000,000`.
- DOM text with `textContent`; no raw HTML injection.
- Parameter-generated SVG shapes behind Arabic text.
- Bubble, narration, free-text, and SFX fixtures.
- ResizeObserver-based scale refresh and translation visibility toggle.
- Unit tests proving proportional geometry at different displayed widths.
- Playwright checks for Chromium, Firefox, and WebKit at mobile and desktop widths.

Still required before T-01 is marked complete:

- Final visual approval in real Chrome, Firefox, and Safari (WebKit automation is not a substitute for physical Safari).
- Confirm Arabic shaping and SFX stroke behavior with the final self-hosted fonts.
- Capture reference screenshots at mobile, tablet, and desktop widths.

## T-02 — PoC editor input

Candidate implemented:

- React editor shell backed by the shared T-01 renderer package.
- Moveable drag, eight-direction resize, rotation, snapping, and element guidelines.
- Normalized-state commits only at gesture end; pointer moves stay local and issue no network requests.
- Safe live Arabic textarea editing through renderer `textContent`.
- Numeric X/Y/W/H/rotation fields plus nudge, width, and rotation buttons as non-drag alternatives.
- Desktop properties/layers layout and a mobile bottom toolbar with bottom sheets.
- Touch targets of at least 44px for primary mobile controls.
- Stage zoom buttons and a two-pointer pinch handler outside selected elements.
- Explicit 100% and fit-width zoom actions; holding `Alt` temporarily disables snapping on desktop.
- One-finger stage pan while zoomed, with physical-axis scrolling kept independent from RTL.
- Properties bottom sheet supports 45%/85% states and follows `visualViewport` height when the software keyboard changes the usable viewport.
- Moveable control padding is enlarged for the physical-device touch-target check.
- Eight unit tests for normalized transform/zoom/pan state and Playwright coverage for Chromium, Firefox, and mobile WebKit.
- A repeatable physical-device evidence sheet in `docs/T02_DEVICE_VALIDATION.md`.
- CI publishes a 14-day `t02-device-preview` artifact for same-network testing without a full repository checkout.

Still required before T-02 is marked complete:

- Run drag, resize, rotate, Arabic textarea, keyboard, and pinch-zoom checks on a physical iOS device.
- Run the same checks on a physical Android device.
- Record browser/device/OS versions and any Moveable quirks in this status file.
- Review the candidate visually at desktop and narrow mobile widths.
- Persisted preset, REST autosave, lease-renewal, and lock-conflict scenarios remain integration gates for their backend/editor tasks; the local interaction PoC does not simulate them.

## Release gate

T-02 remains incomplete until its physical iOS and Android checks pass. Owner-directed parallel backend work does not waive that release requirement.

## Owner-directed parallel work

The owner repeatedly directed implementation to continue after the open hardware gate was reported. T-03 through T-06 therefore continue on the same draft PR without marking T-02 complete and without treating the branch as release-ready.

## T-03 — Plugin bootstrap

Implemented and verified:

- Real `manga-overlay-core` WordPress plugin entry point with direct-access guard and missing-autoloader admin notice.
- Composer PSR-4 mapping from `MOL\\` to `src/`, requiring PHP 8.4.
- Idempotent activation/runtime version manager.
- T-03 introduced the non-autoloaded DB-version mechanism without creating schema; T-04 now advances it to version `1` and never downgrades a later version.
- Versioned canonical roles/capabilities from `USER_ROLES_PERMISSIONS.md`, including administrator access without role-name authorization checks.
- No data removal on deactivation; uninstall cleanup requires the explicit `mol_delete_data_on_uninstall=1` option.
- PHP 8.4 lint, bootstrap smoke tests, Composer validation, and an authoritative production-autoloader check in CI.
- CI packages the production autoloader into a short-lived installable plugin ZIP.
- `PHP Bootstrap` run #1 passed all steps for commit `9e0c9e5`: <https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33766290181>.
- Activation passed inside a real WordPress 7.1 installation in the T-04 database matrix.

T-03 is complete at the implementation/CI level. The draft PR and physical-device release gates remain independent.

## T-04 — Schema and repositories

Implemented and verified:

- Database version `1` creates exactly the nine canonical `mol_*` tables through WordPress `dbDelta()`.
- Every migrated table is checked for its complete ordered column set, required indexes, unique constraints, and InnoDB engine.
- The migration deliberately contains no foreign keys, `ENUM`, or read-counter table.
- Idempotent migration reruns are executed against a real WordPress 7.1 install.
- Repository classes cover chapters, pages, elements, element locks, contributions, reports, reading progress, style presets, and idempotency keys.
- Chapter `sort_order decimal(14,4)` is explicitly normalized to PHP `float` at the repository boundary.
- JSON writes use `wp_json_encode()` and object validation; stored style/response JSON is decoded with exceptions on corruption.
- Geometry, canonical dictionary, element-style, and preset-scope validators enforce the frozen contracts.
- `TransactionManager` supplies explicit start/commit/rollback behavior and preserves the original application exception.
- Contribution and reading-progress UPSERT behavior, preset resolution order, repository normalization, UTC writes, and rollback are integration-tested.
- Explicit opt-in uninstall now removes the nine tables as well as roles/version options; default uninstall remains non-destructive.
- [Database Matrix run #3](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33785889346) passed on MySQL 8.4.11 and MariaDB 10.11.19 using PHP 8.4 and WordPress 7.1.

T-04 is complete at the implementation/CI level.

## T-05 — Work CPT, taxonomies, and permalinks

Implemented and verified:

- Public `mol_work` CPT with Core REST exposure, `/library/` archive, `/series/{slug}/` singles, and exactly the required editor/content/thumbnail/custom-fields supports.
- Meta-cap mapping keeps public reads available while mapping work creation, editing, publishing, private reads, and deletion to `mol_manage_content` rather than role names.
- `mol_genre`, `mol_work_type`, `mol_source_language`, and `mol_work_status` are registered for `mol_work`, exposed through Core REST, and managed/assigned through `mol_manage_content`.
- The six canonical work-type slugs (`manga`, `manhwa`, `manhua`, `comic`, `webtoon`, `other`) are synchronized idempotently on activation.
- `_mol_alt_titles`, `_mol_default_reader_mode`, and `_mol_reading_direction` are registered as single protected meta with sanitize/auth callbacks, typed Core REST schemas, and safe defaults.
- Core REST returns those fields publicly for published works, rejects invalid reader-mode values, denies creation to an unprivileged member, and permits a content manager to update them.
- Rewrite rules cover the chapter reader/editor and user-profile paths reserved by v1.1.3, without implementing future chapter/editor controllers early.
- Rewrite flushing is versioned through a non-autoloaded option and runs on activation or a stale rewrite contract, not on every request.
- [Database Matrix run #6](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33788214237) passed the database and content suites on WordPress 7.1 with MySQL 8.4.11 and MariaDB 10.11.19.
- [PHP Bootstrap run #10](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33788214114) passed PHP 8.4 lint, unit/smoke checks, and installable packaging.

T-05 is complete at the implementation/CI level.

## T-06 — Chapter and page management

Implemented and verified:

- Protected MOL REST routes cover chapter create/update/delete, page upload/delete/reorder, and the narrow translation-review transition.
- Every permission callback distinguishes unauthenticated `401 mol_not_authenticated` from authenticated `403 mol_forbidden`; body validation rejects unknown properties and returns the frozen error codes.
- `mol_manage_content`, `mol_upload_content`, and `mol_review_translations` remain independent. Integration fixtures prove upload-only cannot manage chapters and manage-only cannot upload; a moderator can mark `needs_review`/`completed` but cannot create, delete, or reorder.
- Chapter slugs are generated from title or chapter label, receive bounded `-2`, `-3`, … suffixes, and retain the unique database index as the race guard.
- Page upload validates the real extension/MIME pair and successfully decodes the image through the active WordPress image editor. JPEG/PNG/WebP are the admin-client baseline; AVIF remains server-conditional and is not exposed by the client before the future capabilities route advertises it.
- Uploads enforce configurable byte/dimension/pixel limits, a per-user soft limiter with `Retry-After`, one required application idempotency key, and attachment cleanup when the database transaction fails.
- Optional WebP derivatives are created explicitly when enabled and supported; the original remains a safe fallback when generation is disabled or unavailable.
- Page reorder locks the chapter and its complete page set, validates an exact permutation, shifts indices into a disjoint temporary range, and assigns final `0..N-1` indices in the same transaction.
- Page/chapter deletion owns its cascade across elements, leases, contributions, reports, reading progress, and idempotency records; page deletion also compacts the remaining indices.
- WordPress admin screens provide chapter creation/editing/deletion and a multi-file upload queue with natural sorting, thumbnails, pre/post-upload movement, at most two concurrent requests, deletion, and order persistence.
- [Database Matrix run #8](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33813543412) passed the complete T-06 REST/media/cascade suite on WordPress 7.1 with MySQL 8.4.11 and MariaDB 10.11.19.
- [PHP Bootstrap run #12](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33813543388) passed PHP 8.4 lint, unit/smoke checks, authoritative autoloading, and installable packaging.

T-06 is complete at the implementation/CI level. T-07 (public library/work/chapter/page APIs, visibility, DTOs, and cache behavior) is next. The draft PR and physical-device release gates remain independent.

### T-06 staging smoke test

On 2026-09-03, the installable `manga-overlay-core` 0.4.0 artifact from PHP Bootstrap run #13 was installed and activated on the project staging site running WordPress 7.1, PHP 8.4.25, and MySQL 8.4.11.

- Activation completed without a PHP error and registered the Manga Overlay admin menu.
- The six canonical work-type terms were present after activation.
- A published work and chapter were created through the WordPress admin screens.
- Two PNG pages were uploaded, reordered, saved, and verified after a full page reload.
- A `.png` file with invalid image contents was rejected as `Unsupported media type.` and created no page.
- The `/library/` archive and `/series/{work-slug}/` single-work permalink rendered successfully.
- Public MOL read APIs and a chapter reader remain intentionally absent until T-07 and T-09 respectively.
