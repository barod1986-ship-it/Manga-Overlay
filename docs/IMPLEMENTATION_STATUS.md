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

Still required before T-01 is marked complete:

- Visual checks on current Chrome, Firefox, and Safari.
- Confirm Arabic shaping and SFX stroke behavior with the final self-hosted fonts.
- Capture reference screenshots at mobile, tablet, and desktop widths.

## Next gate

T-02 must test React + Moveable drag/resize/rotate and Arabic text input on physical iOS and Android devices before the full editor architecture is committed.
