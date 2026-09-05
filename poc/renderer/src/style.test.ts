import { describe, expect, it } from 'vitest';
import { normalizeElementStyle } from './renderer';

describe('shared editor/reader style normalization', () => {
  it('uses the frozen base style and rejects unsafe values', () => {
    const bubble = normalizeElementStyle('bubble', {
      fontId: 'javascript:alert(1)',
      color: 'url(javascript:alert(1))',
      shape: '<svg onload=alert(1)>',
      fontSizeUnit: 999_999,
    });

    expect(bubble.fontId).toBe('cairo');
    expect(bubble.color).toBe('#111111');
    expect(bubble.shape).toBe('ellipse');
    expect(bubble.fontSizeUnit).toBe(200_000);
    expect(bubble.tail?.enabled).toBe(true);
  });

  it('enforces type-specific shapes and SFX scale bounds', () => {
    expect(normalizeElementStyle('narration', { shape: 'cloud' }).shape).toBe('rounded_rect');
    expect(normalizeElementStyle('free_text', { shape: 'impact' }).shape).toBe('none');
    const sfx = normalizeElementStyle('sfx', { shape: 'impact', scaleX: 4, scaleY: 0.1 });
    expect(sfx).toMatchObject({ shape: 'impact', scaleX: 2, scaleY: 0.5 });
  });
});
