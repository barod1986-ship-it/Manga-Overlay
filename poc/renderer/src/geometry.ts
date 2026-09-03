import { MOL_UNIT, type Geometry } from './domain';

export interface CssGeometry {
  readonly left: string;
  readonly top: string;
  readonly width: string;
  readonly height: string;
  readonly rotation: string;
  readonly zIndex: string;
}

export interface PixelGeometry {
  readonly x: number;
  readonly y: number;
  readonly width: number;
  readonly height: number;
}

function assertInteger(value: number, field: string): void {
  if (!Number.isInteger(value)) {
    throw new RangeError(`${field} must be an integer`);
  }
}

export function assertGeometry(geometry: Geometry): void {
  const fields: ReadonlyArray<readonly [string, number]> = [
    ['x_unit', geometry.x_unit],
    ['y_unit', geometry.y_unit],
    ['w_unit', geometry.w_unit],
    ['h_unit', geometry.h_unit],
    ['rotation_mdeg', geometry.rotation_mdeg],
    ['z_index', geometry.z_index],
  ];

  for (const [field, value] of fields) {
    assertInteger(value, field);
  }

  if (geometry.x_unit < 0 || geometry.x_unit > MOL_UNIT) {
    throw new RangeError('x_unit is outside the image');
  }
  if (geometry.y_unit < 0 || geometry.y_unit > MOL_UNIT) {
    throw new RangeError('y_unit is outside the image');
  }
  if (geometry.w_unit < 1 || geometry.w_unit > MOL_UNIT) {
    throw new RangeError('w_unit is outside the allowed range');
  }
  if (geometry.h_unit < 1 || geometry.h_unit > MOL_UNIT) {
    throw new RangeError('h_unit is outside the allowed range');
  }
  if (geometry.x_unit + geometry.w_unit > MOL_UNIT) {
    throw new RangeError('element exceeds the physical image width');
  }
  if (geometry.y_unit + geometry.h_unit > MOL_UNIT) {
    throw new RangeError('element exceeds the physical image height');
  }
  if (geometry.rotation_mdeg < -360_000 || geometry.rotation_mdeg > 360_000) {
    throw new RangeError('rotation_mdeg is outside the allowed range');
  }
  if (geometry.z_index < -1_000 || geometry.z_index > 10_000) {
    throw new RangeError('z_index is outside the allowed range');
  }
}

function asPercentage(value: number): string {
  return `${(value / MOL_UNIT) * 100}%`;
}

export function toCssGeometry(geometry: Geometry): CssGeometry {
  assertGeometry(geometry);

  return {
    left: asPercentage(geometry.x_unit),
    top: asPercentage(geometry.y_unit),
    width: asPercentage(geometry.w_unit),
    height: asPercentage(geometry.h_unit),
    rotation: `${geometry.rotation_mdeg / 1_000}deg`,
    zIndex: String(geometry.z_index),
  };
}

export function toPixelGeometry(
  geometry: Geometry,
  imageWidth: number,
  imageHeight: number,
): PixelGeometry {
  assertGeometry(geometry);

  if (imageWidth <= 0 || imageHeight <= 0) {
    throw new RangeError('displayed image dimensions must be positive');
  }

  return {
    x: (geometry.x_unit / MOL_UNIT) * imageWidth,
    y: (geometry.y_unit / MOL_UNIT) * imageHeight,
    width: (geometry.w_unit / MOL_UNIT) * imageWidth,
    height: (geometry.h_unit / MOL_UNIT) * imageHeight,
  };
}

export function unitToPixels(value: number, imageWidth: number): number {
  if (!Number.isFinite(value) || !Number.isFinite(imageWidth) || imageWidth <= 0) {
    throw new RangeError('unit conversion received invalid values');
  }

  return (value / MOL_UNIT) * imageWidth;
}
