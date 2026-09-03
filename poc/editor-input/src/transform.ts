import { MOL_UNIT, type OverlayElement } from '@mol/poc-renderer';

export interface StageSize {
  readonly width: number;
  readonly height: number;
}

export interface ResizeDraft {
  readonly translateX: number;
  readonly translateY: number;
  readonly width: number;
  readonly height: number;
}

export type PercentGeometryField = 'x_unit' | 'y_unit' | 'w_unit' | 'h_unit';

function clamp(value: number, minimum: number, maximum: number): number {
  return Math.min(Math.max(value, minimum), maximum);
}

function requireStageSize(stage: StageSize): void {
  if (!Number.isFinite(stage.width) || !Number.isFinite(stage.height) || stage.width <= 0 || stage.height <= 0) {
    throw new RangeError('stage dimensions must be positive');
  }
}

function xPixelsToUnits(value: number, stage: StageSize): number {
  return Math.round((value / stage.width) * MOL_UNIT);
}

function yPixelsToUnits(value: number, stage: StageSize): number {
  return Math.round((value / stage.height) * MOL_UNIT);
}

export function moveElementByPixels(
  element: OverlayElement,
  translateX: number,
  translateY: number,
  stage: StageSize,
): OverlayElement {
  requireStageSize(stage);
  const x = clamp(element.x_unit + xPixelsToUnits(translateX, stage), 0, MOL_UNIT - element.w_unit);
  const y = clamp(element.y_unit + yPixelsToUnits(translateY, stage), 0, MOL_UNIT - element.h_unit);

  return { ...element, x_unit: x, y_unit: y };
}

export function resizeElementFromPixels(
  element: OverlayElement,
  draft: ResizeDraft,
  stage: StageSize,
): OverlayElement {
  requireStageSize(stage);
  const x = clamp(element.x_unit + xPixelsToUnits(draft.translateX, stage), 0, MOL_UNIT - 1);
  const y = clamp(element.y_unit + yPixelsToUnits(draft.translateY, stage), 0, MOL_UNIT - 1);
  const width = clamp(xPixelsToUnits(draft.width, stage), 1, MOL_UNIT - x);
  const height = clamp(yPixelsToUnits(draft.height, stage), 1, MOL_UNIT - y);

  return {
    ...element,
    x_unit: x,
    y_unit: y,
    w_unit: width,
    h_unit: height,
  };
}

export function rotateElementToDegrees(element: OverlayElement, degrees: number): OverlayElement {
  if (!Number.isFinite(degrees)) {
    throw new RangeError('rotation must be finite');
  }

  const milliDegrees = Math.round(degrees * 1_000);
  const wrapped = ((milliDegrees + 180_000) % 360_000 + 360_000) % 360_000 - 180_000;
  return { ...element, rotation_mdeg: wrapped };
}

export function nudgeElementByUnits(
  element: OverlayElement,
  deltaX: number,
  deltaY: number,
): OverlayElement {
  const x = clamp(element.x_unit + Math.round(deltaX), 0, MOL_UNIT - element.w_unit);
  const y = clamp(element.y_unit + Math.round(deltaY), 0, MOL_UNIT - element.h_unit);
  return { ...element, x_unit: x, y_unit: y };
}

export function setPercentGeometry(
  element: OverlayElement,
  field: PercentGeometryField,
  percent: number,
): OverlayElement {
  if (!Number.isFinite(percent)) {
    return element;
  }

  const units = Math.round(percent * 10_000);
  switch (field) {
    case 'x_unit':
      return { ...element, x_unit: clamp(units, 0, MOL_UNIT - element.w_unit) };
    case 'y_unit':
      return { ...element, y_unit: clamp(units, 0, MOL_UNIT - element.h_unit) };
    case 'w_unit':
      return { ...element, w_unit: clamp(units, 1, MOL_UNIT - element.x_unit) };
    case 'h_unit':
      return { ...element, h_unit: clamp(units, 1, MOL_UNIT - element.y_unit) };
  }
}

export function unitsToPercent(units: number): number {
  return Number((units / 10_000).toFixed(2));
}
