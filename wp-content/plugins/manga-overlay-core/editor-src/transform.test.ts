import { describe, expect, it } from 'vitest';
import { createLocalElement } from './elementModel';
import {
  moveElementByPixels,
  nudgeElementByUnits,
  resizeElementFromPixels,
  rotateElementToDegrees,
  setPercentGeometry,
} from './transform';

const element = createLocalElement(41, 'ar', 'bubble', 101, 1);

describe('T-11 transform commits', () => {
  it('commits drag pixels as physical normalized geometry', () => {
    const moved = moveElementByPixels(element, 80, 120, { width: 800, height: 1_200 });
    expect(moved.x_unit).toBe(element.x_unit + 100_000);
    expect(moved.y_unit).toBe(element.y_unit + 100_000);
  });

  it('clamps resize, numeric input, nudge, and rotation to valid geometry', () => {
    const resized = resizeElementFromPixels(
      element,
      { translateX: -40, translateY: -60, width: 320, height: 240 },
      { width: 800, height: 1_200 },
    );
    expect(resized.w_unit).toBe(400_000);
    expect(resized.h_unit).toBe(200_000);
    expect(setPercentGeometry(element, 'x_unit', 99).x_unit).toBe(700_000);
    expect(nudgeElementByUnits(element, -2_000_000, -2_000_000)).toMatchObject({ x_unit: 0, y_unit: 0 });
    expect(rotateElementToDegrees(element, 370).rotation_mdeg).toBe(10_000);
  });
});
