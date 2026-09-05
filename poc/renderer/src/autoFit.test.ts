import { describe, expect, it } from 'vitest';
import { largestFittingFontSize } from './renderer';

describe('largestFittingFontSize', () => {
  it('keeps the desired size when it fits', () => {
    expect(largestFittingFontSize(12, 30, () => true)).toBe(30);
  });

  it('finds the largest fitting size without going below the minimum', () => {
    const fitted = largestFittingFontSize(12, 30, (size) => size <= 21.25);
    expect(fitted).toBeGreaterThan(21.24);
    expect(fitted).toBeLessThanOrEqual(21.25);
    expect(largestFittingFontSize(12, 30, () => false)).toBe(12);
  });
});
