# Implementation status

Last updated: 2026-09-04

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

T-06 is complete at the implementation/CI level. The draft PR and physical-device release gates remain independent.

### T-06 staging smoke test

On 2026-09-03, the installable `manga-overlay-core` 0.4.0 artifact from PHP Bootstrap run #13 was installed and activated on the project staging site running WordPress 7.1, PHP 8.4.25, and MySQL 8.4.11.

- Activation completed without a PHP error and registered the Manga Overlay admin menu.
- The six canonical work-type terms were present after activation.
- A published work and chapter were created through the WordPress admin screens.
- Two PNG pages were uploaded, reordered, saved, and verified after a full page reload.
- A `.png` file with invalid image contents was rejected as `Unsupported media type.` and created no page.
- The `/library/` archive and `/series/{work-slug}/` single-work permalink rendered successfully.
- Public MOL read APIs and a chapter reader remain intentionally absent until T-07 and T-09 respectively.

## T-07 — Public data APIs

Implemented and verified:

- Public REST reads now cover runtime capabilities, the filterable/paginated library, individual works, published work chapters, individual chapters, chapter pages, page elements, chapter overlay batches grouped by page, contributors, and public profiles.
- Every public success response is built through explicit presenters that match the frozen OpenAPI DTOs; no repository row or engine-specific representation is exposed directly.
- Library filters cover search, genre, work type, source language, work status, translation status, sort, page, and per-page validation. The intentionally unavailable `most_read` sort returns `400 mol_sort_unavailable` instead of silently changing semantics.
- `ChapterVisibilityPolicy` centralizes descendant visibility: published chapter resources are public; a draft is returned only in an authenticated REST context to a user with `mol_use_editor` or `mol_manage_content`; every other caller receives `404`.
- Public responses carry cacheable headers and collection ETags. Authorized draft responses are explicitly `private, no-store`.
- The plugin exposes the specified public PHP functions for server-rendered theme consumers, backed by the same service/presenter layer as REST.
- Runtime capabilities advertise only image formats supported by the current WordPress installation and include the canonical reader modes, reading directions, element types, and work types.
- The frozen v1.1.3 contract gate passed `49/49` checks before DTO implementation.
- [Database Matrix run #14](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33817852338) passed the public-read unit and REST integration suites on WordPress 7.1 with MySQL 8.4 and MariaDB 10.11.
- [PHP Bootstrap run #14](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33817853246) passed PHP 8.4 lint, Composer/autoload checks, bootstrap tests, and produced the installable 0.5.0 artifact.
- [Frontend PoC run #14](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33817848217) remained green.

T-07 is complete at the implementation/CI level. The draft PR and physical-device release gates remain independent.

### T-07 staging smoke test

On 2026-09-04, the exact 0.5.0 artifact from PHP Bootstrap run #14 was verified against its published SHA-256 digest and installed over 0.4.0 on the project staging site.

- WordPress recognized the update from 0.4.0 to 0.5.0 and completed the replacement successfully.
- Manga Overlay Core remained active after replacement and reported version 0.5.0.
- The existing `/library/` archive rendered the published smoke-test work after the update.
- The existing `/series/manga-overlay-smoke-test/` work permalink rendered after the update with no visible PHP or WordPress error.
- Direct JSON navigation is blocked by the controlled browser client, so endpoint behavior is established by the anonymous/authenticated WordPress REST integration suite in the database matrix rather than by treating that client-side restriction as an application failure.

## T-08 — Public library theme

Implemented and verified:

- A standalone installable classic/hybrid `manga-overlay-theme` renders the Arabic-first public experience without duplicating the plugin's data or authorization rules.
- The home page, filterable library archive, work detail page, work cards, progress summaries, chapter rows, pagination, empty/error states, shared header/footer, and accessible skip navigation are server rendered.
- Library filters preserve validated GET query parameters for shareable URLs and pagination. Search, taxonomy/status filters, translation status, sort order, and 12/24/48 page sizes map to the T-07 public read contract.
- The visual system is RTL-first and uses logical CSS properties, responsive grids, a narrow-screen filter drawer, explicit media dimensions, lazy loading below the first card, and reduced-motion handling.
- Theme JavaScript is limited to progressive UI behavior for the search dialog and mobile filter drawer; content and filtering remain usable without client-side rendering.
- Public-theme unit/integration checks, PHP lint, CSS/JavaScript static checks, and the installable ZIP smoke test passed in [Public Theme run #1](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33875633197).
- [Database Matrix run #15](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33875633071) passed on MySQL 8.4 and MariaDB 10.11, [PHP Bootstrap run #15](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33875632996) passed, and [Frontend PoC run #15](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33875633188) remained green.
- The frozen v1.1.3 contract gate remained green at `49/49` checks.

T-08 is complete at the implementation/CI level. The draft PR and physical-device release gates remain independent.

### T-08 staging smoke test

On 2026-09-04, the exact `manga-overlay-theme` 0.1.0 artifact from Public Theme run #1 was verified against its published SHA-256 digest, installed, and activated on the project staging site.

- WordPress reported Manga Overlay as the active theme; the previously installed Twenty Twenty-Five, Twenty Twenty-Four, and Twenty Twenty-Three themes were retained.
- The Arabic RTL home page rendered the intended masthead, hero, search entry point, latest-work card, and footer at a 1363×936 desktop viewport.
- `/library/` rendered the published smoke-test work, filter controls, result count, translation completion, and chapter count.
- A shareable library URL using `search=Manga Overlay Smoke Test`, `sort=latest_work`, and `per_page=12` preserved all three values and returned exactly one work card.
- `/series/manga-overlay-smoke-test/` rendered the work metadata, description, 100% translation summary, and one published chapter row.
- The document language and direction were `lang="ar"` and `dir="rtl"`; browser logs contained no site/theme JavaScript errors. Messages emitted only by the controlled browser extension were excluded from the application result.
- Physical iOS/Android interaction and narrow-viewport visual evidence remain part of the existing T-02 release gate; this staging smoke test does not waive that requirement.

## T-09 — Public chapter reader and overlay controls

Implemented and verified:

- The canonical `/series/{work}/chapter/{chapter}/` route now renders a focused, server-rendered reader shell without converting the public site into an SPA.
- Webtoon and single-page modes share the same physical image-space renderer. Paged navigation honors chapter/work `rtl|ltr` direction without mirroring image geometry, and desktop arrow keys follow that direction.
- The chapter overlay batch is embedded once per chapter. Arabic content is written with DOM `textContent`; only parameter-generated SVG shapes are created, and renderer style values are normalized against explicit allowlists and bounds.
- The translation toggle hides every DOM/SVG overlay immediately without changing or reloading any source image, and its preference is stored locally.
- Authenticated progress uses the strict frozen `PUT /reading-progress` contract with throttled saves and WordPress REST nonces. Guest progress uses the exact `mol_progress_{chapterId}` localStorage key and restores page, normalized in-page progress, and reader mode.
- Zoom buttons, wheel zoom, double-click reset, pointer pinch, and pan are supported. Paged mode resets zoom on page changes while webtoon retains vertical scrolling when unzoomed.
- Reader navigation includes previous/next chapter, the complete published chapter list, return-to-work, translation status, page/element counts, and contributor profiles outside the image surface.
- The first page is eager/high-priority, later images are lazy, only the nearby page is promoted, dimensions and responsive sources are explicit, and distant overlay rendering uses IntersectionObserver/content visibility.
- Reader unit checks cover normalized geometry, allowed mode/direction values, strict progress payloads, and safe style normalization. Playwright verifies normalized placement, no HTML execution, instant translation toggle, limited nearby preload, RTL/LTR keyboard behavior, and zoom reset at 360/768/1440 widths.
- [Frontend PoC run #27](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33889868281) passed unit, TypeScript/build, and Chromium/Firefox/WebKit Playwright checks.
- [Database Matrix run #19](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33889868310) passed the reading-progress REST/PHP contract and theme reader context on WordPress 7.1 with MySQL 8.4 and MariaDB 10.11.
- [Public Theme run #7](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33889868268) passed PHP lint, reader JavaScript checks, and installable theme 0.2.0 packaging.
- [PHP Bootstrap run #23](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33889868264) passed PHP 8.4 lint, Composer/autoload checks, and installable core 0.6.0 packaging.

T-09 is complete at the implementation/CI level. Core 0.6.0 and theme 0.2.0 are installed on staging as recorded below. The draft PR and physical-device release gates remain independent.

### T-09 staging smoke test

On 2026-09-04, the exact artifacts produced for PR head `a005b25` were installed on the project staging site after verifying the GitHub artifact SHA-256 digests.

- Manga Overlay Core was replaced from 0.5.0 to 0.6.0, remained active, and WordPress reported the new version.
- The active Manga Overlay theme was replaced from 0.1.0 to 0.2.0 and its theme-details dialog reported the new version.
- The existing published smoke chapter resolved through the canonical reader URL with two 800×1200 pages and a focused reader shell.
- Webtoon displayed both pages continuously. Paged displayed one page at a time, reported `rtl`, and advanced from page 1 to page 2 with the desktop `ArrowLeft` key.
- Zoom advanced from 100% to 125% and reset to 100% when the paged reader moved to the next page.
- Authenticated progress reported a successful save; a full reload restored paged mode and page 2.
- The first image remained eager/high-priority, the nearby second image was promoted with low fetch priority, and both retained explicit width/height attributes.
- No JavaScript error originated from the staging site or reader. Messages emitted only by the controlled browser extension were excluded from the application result.
- The smoke chapter currently contains zero Arabic translation elements, so WordPress correctly rendered the translation toggle disabled. CI verifies safe DOM/SVG rendering, instant hide/show without image reload, and normalized placement at 360/768/1440; a visual staging overlay check remains pending until demo data includes at least one translation element.
- Physical iOS/Android and final Safari/Arabic-font evidence remain release gates and are not waived by this staging smoke test.

## T-10 — React editor shell

Implemented and verified:

- The canonical `/series/{work}/chapter/{chapter}/edit/` query now resolves to a plugin-owned document instead of falling through to the public reader template.
- Guests are redirected through WordPress authentication. Logged-in users without `mol_use_editor` or `mol_manage_content` receive `403`, while authorized editors can load published or draft chapter context without exposing drafts publicly.
- `EditorContextService` resolves the requested work/chapter, pages, and Arabic elements through the existing repositories and centralized chapter visibility policy. Data is presented through the existing DTO presenters and embedded with JSON hex escaping.
- Core is advanced to `0.7.1`. The installable artifact includes the committed production React bundle and does not require Node on WordPress.
- React owns URL-backed page routing through the project-prefixed `?mol_page=N` parameter, local selection/zoom/Preview state, a centered image stage, physical non-mirrored element outlines, a collapsible layers panel, and a read-only properties panel.
- The initial `?page=N` implementation collided with WordPress's reserved `page` query variable on direct reload. Core 0.7.1 removes that reserved parameter, uses `mol_page`, and includes a regression test for deep-link restoration.
- Save status and Preview remain visible. Creation controls are intentionally disabled and the UI says that no changes are saved; T-11 owns element editing/renderer integration and T-12 owns REST writes/autosave.
- Unit tests cover route bounds, reducer transitions, zoom limits, and physical geometry. Playwright covers Chromium/Firefox/WebKit routing, safe malicious text handling, layer/property selection, Preview chrome removal, and page restoration. The WordPress DB integration covers draft authorization, target-language grouping, template resolution, asset loading, `403`, and JSON script-boundary escaping.
- [Frontend PoC run #34](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33906988863) passed unit, TypeScript/build, and all Playwright suites on Chromium, Firefox, and WebKit.
- [Database Matrix run #26](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33906988914) passed the editor integration suite on WordPress 7.1 with MySQL 8.4 and MariaDB 10.11.
- [PHP Bootstrap run #30](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33906988857) passed PHP 8.4 lint, Composer/autoload checks, and produced the installable Core 0.7.1 artifact with both editor assets.
- [Public Theme run #14](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33906988789) remained green.

T-10 is complete at the implementation, CI, and staging levels. Existing physical iOS/Android release gates remain independent.

### T-10 staging smoke test

On 2026-09-04, the exact Core 0.7.1 artifact from PHP Bootstrap run #30 at PR head `571f00c` was verified against the GitHub artifact SHA-256 digest and installed over Core 0.7.0 on the project staging site.

- WordPress completed the replacement successfully; Manga Overlay Core remained active and reported version 0.7.1.
- The authorized editor route opened the existing two-page smoke chapter and showed the T-10 read-only shell with `Core 0.7.1`.
- Advancing to page 2 produced `?mol_page=2`. A full reload preserved the editor route and restored page 2 of 2 instead of redirecting to the work page.
- Zoom advanced from 100% to 125%, and Fit reset it to 100%.
- Preview hid the editor chrome and “Return to editor” restored the layers and read-only properties panels without changing the deep link.
- The smoke chapter still contains zero translation elements, so the layers panel correctly displayed its empty state; element editing remains assigned to T-11.
- Browser logs contained no warning or error from the staging origin.

## T-11 — Element editing and shared renderer

Implemented and verified:

- The production editor locally creates all four canonical element types: `bubble`, `narration`, `free_text`, and `sfx`.
- Editor and reader use the shared safe renderer. Arabic content is assigned through DOM `textContent`, and ellipse/rectangle/cloud/burst/impact visuals are parameter-generated SVG rather than stored markup.
- Selection is synchronized between the image stage and layer list. Moveable supplies drag, eight-direction resize, and rotation, while normalized X/Y/W/H/rotation fields, nudge buttons, and keyboard arrows remain available alternatives.
- Local commits update normalized geometry only at the end of a gesture. The renderer preserves each outer element node across data refreshes so Moveable never retains a detached target.
- Type-aware properties cover Arabic content, font, size, weight, line height, alignment, safe colors, background/opacity, borders, padding, bubble tail controls, SFX stroke/scale/burst controls, and safe shadows.
- Elements can be duplicated, deleted, and moved through the layer stack. Preview reuses the same renderer while removing editor chrome and Moveable controls.
- Persistence is intentionally absent: the status identifies changes as local, page navigation retains them for the current React session, and a full reload restores server data. REST writes and autosave remain owned by T-12.
- [Frontend PoC run #41](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33928162983) passed unit, TypeScript/build, and Playwright across Chromium, Firefox, and WebKit, including live Moveable drag/resize and retained-target regression coverage.
- [Database Matrix run #33](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33928162961) passed on WordPress 7.1 with MySQL 8.4 and MariaDB 10.11.
- [PHP Bootstrap run #37](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33928162960) passed PHP 8.4 checks and produced the installable Core 0.8.0 artifact.
- [Public Theme run #21](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33928162968) remained green.

T-11 is complete at the implementation, CI, and staging levels. T-12 is tracked below. Physical iOS/Android and final Safari/Arabic-font evidence remain independent release gates.

### T-11 staging smoke test

On 2026-09-04, the exact Core 0.8.0 artifact from PHP Bootstrap run #37 at PR head `22bb66b` was installed over Core 0.7.1 on the project staging site. The published GitHub artifact digest `a760e64f5b3914cf092df84e875ad09a1602dd620f827d0c1187fd8121e8fea2` matched the downloaded archive; the inner installable ZIP SHA-256 was `1928a29263e0b0cbca09d8c473948a8f0da389d4ef59f8f7824bb95e5c3c209f`.

- WordPress completed the replacement successfully; Manga Overlay Core remained active and reported version 0.8.0.
- The authorized editor opened the existing two-page smoke chapter. Page 1 initially contained zero persisted translation elements, then locally created exactly one element of each canonical type and selected the newest SFX element.
- Arabic SFX content, burst SVG, and 18-degree rotation rendered immediately. Numeric X positioning, the right-nudge control, pointer drag, and east-handle resize all updated normalized fields while the Moveable box and its rotation/resize controls remained visible after renderer refreshes.
- Bubble cloud rendering and tail enable/disable worked; disabling the tail removed only its generated SVG path. Narration and free-text content/style changes rendered immediately.
- An HTML-like free-text payload remained literal text and created no nested or page-level image node.
- Moving the SFX down changed its layer from z-index 4 to 3. Duplicate raised the local element count from four to five, delete restored it to four, with one element of each canonical type remaining.
- Preview hid the toolbar, properties, and Moveable controls while retaining all four overlays. Returning restored the editor.
- `?mol_page=2` showed the empty second page; returning to `?mol_page=1` retained the four local edits during the session. A full reload kept the editor route but correctly restored zero server-backed elements and the ready state because network persistence belongs to T-12.
- Browser logs contained no warning or error from the staging origin. Repeated metadata messages emitted only by the controlled browser extension were excluded from the application result.

## T-12 — Strict element writes and autosave

Implemented and verified:

- Protected element `POST`, `PATCH`, and `DELETE` routes now use strict request schemas, reject unknown properties, enforce editor authorization and chapter visibility, and return only presenter-backed DTOs.
- Create requests require an application `Idempotency-Key`; safe client retries reuse the same key and the server stores/replays the canonical response instead of creating a duplicate element.
- Persisted updates and deletes require both a valid 45-second element lease and `If-Match`. Missing preconditions return `428`, stale versions return `412`, and unavailable/invalid leases return `423` without mutating the row.
- Successful writes advance the integer element version and return a matching quoted `ETag`. Style responses are resolved through the explicit preset, default preset, and server-owned base-style chain rather than trusting incomplete client JSON.
- Element deletion owns its dependent contribution/report/lock cleanup in a transaction. The write service also applies the project rate-limit contract and preserves the frozen error envelope.
- The React editor marks text/style changes dirty and debounces them for 1,200 ms. Create, update, and delete use the strict REST client; persisted edits acquire a lease first, while completed pointer and keyboard transforms flush immediately.
- Dirty changes survive network loss inside the current tab. The status distinguishes dirty, saving, saved, offline, and retryable errors; reconnect and the explicit retry action resume the queued write without claiming that unsent data was persisted.
- Moveable commits only a real completed drag/resize/rotation. Per-frame pointer updates and zero-distance clicks perform no network write, preventing duplicate PATCH traffic during gesture rendering.
- WordPress integration tests cover successful create/update/delete, idempotent replay, ETags, lease ownership, stale/missing preconditions, strict payload rejection, rate limiting, style resolution, and dependency cleanup on both supported database engines.
- Editor unit tests report `12/12` passing, and the production TypeScript/Vite build succeeds. The editor Playwright suite reports `15/15` passing across Chromium, Firefox, and WebKit, including autosave, offline recovery, strict request sequencing, and end-of-gesture persistence.
- [Frontend PoC run #45](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33931637153) passed all unit/build and browser suites.
- [Database Matrix run #37](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33931637161) passed on WordPress 7.1 with MySQL 8.4 and MariaDB 10.11.
- [PHP Bootstrap run #41](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33931637162) passed PHP 8.4 checks and produced the installable Core 0.9.0 artifact.
- [Public Theme run #25](https://github.com/barod1986-ship-it/Manga-Overlay/actions/runs/33931637174) remained green.

T-12 is complete at the implementation and CI levels. Installing Core 0.9.0 and exercising persistence on staging remain the next verification gate. T-13 retains lease renewal/release/force-release, complete conflict UX, and reverse-proxy concurrency validation; those concerns are not claimed by T-12.
