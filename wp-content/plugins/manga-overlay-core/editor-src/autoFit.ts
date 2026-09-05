import type { ElementStyle } from './types';

export function autoFitTogglePatch(
  style: ElementStyle,
  enabled: boolean,
  fittedFontSizeUnit: number | null,
): Partial<ElementStyle> {
  if (enabled) return { autoFit: true };
  const fitted = fittedFontSizeUnit !== null && Number.isInteger(fittedFontSizeUnit)
    ? Math.min(style.fontSizeUnit, Math.max(1_000, fittedFontSizeUnit))
    : style.fontSizeUnit;

  return { autoFit: false, fontSizeUnit: fitted };
}

export function fittedFontSizeUnit(elementId: number): number | null {
  const node = Array.from(document.querySelectorAll<HTMLElement>('[data-element-id]'))
    .find((candidate) => candidate.dataset.elementId === String(elementId));
  const value = Number(node?.dataset.fittedFontSizeUnit);
  return Number.isInteger(value) && value > 0 ? value : null;
}
