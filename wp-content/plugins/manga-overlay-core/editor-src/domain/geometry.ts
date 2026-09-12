import type { Geometry } from './types.ts';

export const MOL_UNIT = 1_000_000;
export interface ImageSize { width: number; height: number }
export interface PixelBox { x: number; y: number; width: number; height: number; rotation: number }

function finite(value: number): number {
  if (!Number.isFinite(value)) throw new RangeError('Geometry must be finite.');
  return value;
}
export function clamp(value: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, finite(value)));
}
function validSize(size: ImageSize): void {
  if (finite(size.width) <= 0 || finite(size.height) <= 0) {
    throw new RangeError('The displayed image must have positive dimensions.');
  }
}
export function normalizeGeometry(geometry: Geometry): Geometry {
  const width = Math.round(clamp(geometry.w_unit, 1, MOL_UNIT));
  const height = Math.round(clamp(geometry.h_unit, 1, MOL_UNIT));
  return {
    x_unit: Math.round(clamp(geometry.x_unit, 0, MOL_UNIT - width)),
    y_unit: Math.round(clamp(geometry.y_unit, 0, MOL_UNIT - height)),
    w_unit: width,
    h_unit: height,
    rotation_mdeg: Math.round(clamp(geometry.rotation_mdeg, -360_000, 360_000)),
    z_index: Math.round(clamp(geometry.z_index, -1_000, 10_000)),
  };
}
export function toPixels(geometry: Geometry, size: ImageSize): PixelBox {
  validSize(size);
  return {
    x: geometry.x_unit * size.width / MOL_UNIT,
    y: geometry.y_unit * size.height / MOL_UNIT,
    width: geometry.w_unit * size.width / MOL_UNIT,
    height: geometry.h_unit * size.height / MOL_UNIT,
    rotation: geometry.rotation_mdeg / 1_000,
  };
}
export function fromPixels(box: PixelBox, size: ImageSize, zIndex: number): Geometry {
  validSize(size);
  return normalizeGeometry({
    x_unit: box.x / size.width * MOL_UNIT,
    y_unit: box.y / size.height * MOL_UNIT,
    w_unit: box.width / size.width * MOL_UNIT,
    h_unit: box.height / size.height * MOL_UNIT,
    rotation_mdeg: box.rotation * 1_000,
    z_index: zIndex,
  });
}
/** Style lengths use displayed image width, keeping glyph proportions at every viewport. */
export function stylePixels(unit: number | undefined, imageWidth: number): number {
  return (unit ?? 0) / MOL_UNIT * imageWidth;
}
