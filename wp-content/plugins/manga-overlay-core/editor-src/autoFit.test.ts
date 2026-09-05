import { describe, expect, it } from 'vitest';
import { autoFitTogglePatch } from './autoFit';
import type { ElementStyle } from './types';

const style = { fontSizeUnit: 30_000 } as ElementStyle;

describe('autoFitTogglePatch', () => {
  it('enables auto-fit without changing the requested font size', () => {
    expect(autoFitTogglePatch(style, true, 18_000)).toEqual({ autoFit: true });
  });

  it('converts the measured result to a fixed size when disabled', () => {
    expect(autoFitTogglePatch(style, false, 18_000)).toEqual({ autoFit: false, fontSizeUnit: 18_000 });
    expect(autoFitTogglePatch(style, false, null)).toEqual({ autoFit: false, fontSizeUnit: 30_000 });
  });
});
