# Implementation status

Last updated: 2026-09-03

## T-00 — Environment

- Node.js target fixed to major 24 via `.nvmrc` and `engines`.
- Frontend PoC CI runs on Node 24.
- PHP 8.4, WordPress 7.1.x, MySQL 8.4, and MariaDB 10.11 gates remain pending until the plugin/database phases exist.
- The local bootstrap environment used for this change has Node 24 but does not expose PHP, Composer, or a database client; no backend compatibility claim is made yet.

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

## Next gate

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
- Five unit tests for normalized transform state and Playwright coverage for Chromium, Firefox, and mobile WebKit.
- A repeatable physical-device evidence sheet in `docs/T02_DEVICE_VALIDATION.md`.

Still required before T-02 is marked complete:

- Run drag, resize, rotate, Arabic textarea, keyboard, and pinch-zoom checks on a physical iOS device.
- Run the same checks on a physical Android device.
- Record browser/device/OS versions and any Moveable quirks in this status file.
- Review the candidate visually at desktop and narrow mobile widths.
- Persisted preset, REST autosave, lease-renewal, and lock-conflict scenarios remain integration gates for their backend/editor tasks; the local interaction PoC does not simulate them.

## Next gate

Do not begin T-03 WordPress/API scaffolding until the physical iOS and Android T-02 checks pass or the Master Spec is amended through its decision process.
