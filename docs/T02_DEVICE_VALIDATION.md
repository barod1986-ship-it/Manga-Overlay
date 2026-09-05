# T-02 physical device validation

This is the evidence sheet for the Master Spec v1.1.3 actual-device gate. Automated browser emulation does not complete this checklist.

## Start the candidate

Use a development machine and phones/tablets on the same trusted network:

```bash
npm ci
npm run dev:editor-poc
```

Open the `Network` URL printed by Vite from each device. This PoC holds fixture data in memory, makes no editor REST calls, and must not be exposed as a production service.

Alternatively, download the `t02-device-preview` artifact from the latest successful `Frontend PoC` GitHub Actions run. Extract it on a computer connected to the same trusted network, then serve the extracted directory:

```bash
python3 -m http.server 4174 --bind 0.0.0.0
```

Open `http://<computer-lan-ip>:4174/` on the physical devices. The artifact is retained for 14 days and contains the exact candidate verified by that workflow run.

## Device record

Fill one row per physical device. Use exact OS and browser versions.

| Result | Device | OS version | Browser/version | Screen/CSS viewport | Tester | Date |
|---|---|---|---|---|---|---|
| Pending | iPhone or iPad | — | Safari — | — | — | — |
| Pending | Android phone or tablet | — | Chrome — | — | — | — |

Allowed results: `PASS`, `FAIL`, or `BLOCKED` with a linked issue for every non-pass result.

## Interaction checklist

Run every item in portrait and repeat items 1–8 in landscape.

- [ ] 1. Select each bubble, narration, free-text, and SFX element without accidental page scrolling.
- [ ] 2. Drag an element with one finger; its position remains attached to the page and is committed after release.
- [ ] 3. Resize from visible edge and corner handles; handles remain usable at 100% and fitted zoom.
- [ ] 4. Rotate from the separate rotation handle; the element does not jump at gesture start.
- [ ] 5. Pinch outside an element to zoom the stage, then pan the enlarged page.
- [ ] 6. Pinch/transform a selected element without activating stage zoom.
- [ ] 7. Open the properties sheet and enter mixed Arabic, punctuation, and numerals in the textarea.
- [ ] 8. Open and close the software keyboard; the active textarea and close control remain reachable.
- [ ] 8a. Switch the properties sheet between 45% and 85% while the keyboard is open.
- [ ] 9. Change X/Y/W/H/rotation numerically and use every nudge/width/rotation step button.
- [ ] 10. Open and close the layers sheet and select every layer.
- [ ] 11. Add every element type and confirm the correct initial appearance.
- [ ] 12. Toggle Preview and confirm controls/panels/guides disappear while geometry stays unchanged.
- [ ] 13. Reset the fixture and confirm the initial three elements and 100% zoom return.
- [ ] 14. Repeat rapid drag/resize/rotate sequences and confirm there is no stuck handle or page scroll lock.

## Evidence to attach

- Portrait screenshot with a selected element and Moveable controls.
- Landscape screenshot with the properties sheet and Arabic keyboard open.
- A short screen recording showing drag, resize, rotate, and stage pinch.
- Console errors, reproduction steps, and the exported device/browser versions for any failure.

## Scope boundary

The T-02 candidate validates renderer and input interaction only. The broader mobile scenarios for persisted presets, REST autosave, lease renewal, and lock conflict require their backend/editor tasks and are not falsely simulated here. This means the integrated mobile gate remains open even after this interaction checklist passes.
