import { describe, expect, it } from 'vitest';
import type { OverlayElement } from '@mol/poc-renderer';
import {
  moveElementByPixels,
  nudgeElementByUnits,
  resizeElementFromPixels,
  rotateElementToDegrees,
  setPercentGeometry,
  unitsToPercent,
} from './transform';

const element: OverlayElement = {
  id: 1,
  page_id: 1,
  target_lang: 'ar',
  element_type: 'bubble',
  x_unit: 200_000,
  y_unit: 100_000,
  w_unit: 300_000,
  h_unit: 200_000,
  rotation_mdeg: 0,
  z_index: 1,
  content: 'اختبار',
  version: 1,
  style: {
    fontId: 'cairo',
    fontSizeUnit: 26_000,
    fontWeight: 700,
    lineHeight: 1.35,
    textAlign: 'center',
    color: '#111111',
    backgroundColor: '#FFFFFF',
    backgroundOpacity: 0.96,
    borderColor: '#111111',
    borderWidthUnit: 1_800,
    borderRadiusUnit: 50_000,
    paddingUnit: 9_000,
    shape: 'ellipse',
  },
};

describe('editor transform commits', () => {
  it('converts drag pixels to normalized units', () => {
    const moved = moveElementByPixels(element, 120, 180, { width: 1_200, height: 1_800 });
    expect(moved.x_unit).toBe(300_000);
    expect(moved.y_unit).toBe(200_000);
  });

  it('clamps dragged elements to the page', () => {
    const moved = moveElementByPixels(element, 5_000, 5_000, { width: 1_200, height: 1_800 });
    expect(moved.x_unit).toBe(700_000);
    expect(moved.y_unit).toBe(800_000);
  });

  it('commits resize dimensions and translated origin', () => {
    const resized = resizeElementFromPixels(
      element,
      { translateX: -60, translateY: -90, width: 420, height: 450 },
      { width: 1_200, height: 1_800 },
    );
    expect(resized).toMatchObject({
      x_unit: 150_000,
      y_unit: 50_000,
      w_unit: 350_000,
      h_unit: 250_000,
    });
  });

  it('normalizes rotations while keeping the same orientation', () => {
    expect(rotateElementToDegrees(element, 370).rotation_mdeg).toBe(10_000);
    expect(rotateElementToDegrees(element, -190).rotation_mdeg).toBe(170_000);
  });

  it('supports accessible numeric and nudge alternatives', () => {
    const positioned = setPercentGeometry(element, 'x_unit', 35.5);
    const nudged = nudgeElementByUnits(positioned, 5_000, -5_000);
    expect(nudged.x_unit).toBe(360_000);
    expect(nudged.y_unit).toBe(95_000);
    expect(unitsToPercent(nudged.x_unit)).toBe(36);
  });
});
