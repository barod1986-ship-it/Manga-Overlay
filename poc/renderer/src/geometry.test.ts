import { describe, expect, it } from 'vitest';
import type { Geometry } from './domain';
import { assertGeometry, toCssGeometry, toPixelGeometry, unitToPixels } from './geometry';

const geometry: Geometry = {
  x_unit: 250_000,
  y_unit: 100_000,
  w_unit: 500_000,
  h_unit: 200_000,
  rotation_mdeg: -12_500,
  z_index: 7,
};

describe('normalized image geometry', () => {
  it('maps units to physical CSS percentages without RTL mirroring', () => {
    expect(toCssGeometry(geometry)).toEqual({
      left: '25%',
      top: '10%',
      width: '50%',
      height: '20%',
      rotation: '-12.5deg',
      zIndex: '7',
    });
  });

  it('keeps the same proportions at mobile and desktop sizes', () => {
    const mobile = toPixelGeometry(geometry, 360, 540);
    const desktop = toPixelGeometry(geometry, 720, 1_080);

    expect(mobile).toEqual({ x: 90, y: 54, width: 180, height: 108 });
    expect(desktop).toEqual({ x: 180, y: 108, width: 360, height: 216 });
    expect(desktop.x / mobile.x).toBe(2);
    expect(desktop.height / mobile.height).toBe(2);
  });

  it('rejects elements that extend beyond the image', () => {
    expect(() =>
      assertGeometry({
        ...geometry,
        x_unit: 800_000,
        w_unit: 300_000,
      }),
    ).toThrow('element exceeds the physical image width');
  });

  it('rejects non-integer contract values', () => {
    expect(() => assertGeometry({ ...geometry, rotation_mdeg: 1.5 })).toThrow(
      'rotation_mdeg must be an integer',
    );
  });

  it('converts style units against displayed image width', () => {
    expect(unitToPixels(25_000, 800)).toBe(20);
  });
});
