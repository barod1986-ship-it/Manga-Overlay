# T-02 editor-input PoC

This workspace exercises the interaction risks called out by Master Spec v1.1.3 before the WordPress editor is built.

## Included

- React 19 editor shell.
- Shared DOM/SVG renderer from `@mol/poc-renderer`.
- Moveable drag, resize, rotate, pinchable transforms, and snapping.
- Arabic textarea input and normalized numeric transform alternatives.
- Responsive desktop side panels and mobile bottom toolbar/sheets.

The fixture is local-only. It has no REST client, authentication, autosave, upload path, or production persistence.

## Commands

```bash
npm run check:editor-poc
npm run dev:editor-poc
npm run test:e2e:editor-poc
```

Automated WebKit emulation does not satisfy the actual-device gate. T-02 remains incomplete until the checklist in `docs/IMPLEMENTATION_STATUS.md` passes on physical iOS and Android devices.
