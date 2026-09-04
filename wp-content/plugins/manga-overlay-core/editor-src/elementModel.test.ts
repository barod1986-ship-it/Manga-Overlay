import { describe, expect, it } from 'vitest';
import {
  createLocalElement,
  duplicateLocalElement,
  highestZIndex,
  moveElementLayer,
  resolvedStyle,
  updateElementStyle,
} from './elementModel';

describe('T-11 local element model', () => {
  it('creates all four types with safe normalized styles and temporary versions', () => {
    const types = ['bubble', 'narration', 'free_text', 'sfx'] as const;
    const elements = types.map((type, index) => createLocalElement(41, 'ar', type, -index - 1, index + 1));

    expect(elements.map((element) => element.element_type)).toEqual(types);
    expect(elements.every((element) => element.version === 0)).toBe(true);
    expect(resolvedStyle(elements[0]!).shape).toBe('ellipse');
    expect(resolvedStyle(elements[3]!).strokeWidthUnit).toBe(3_500);
  });

  it('duplicates locally without reusing the persisted id or version', () => {
    const source = createLocalElement(41, 'ar', 'bubble', 101, 2);
    const duplicate = duplicateLocalElement(source, -1, 3);

    expect(duplicate.id).toBe(-1);
    expect(duplicate.version).toBe(0);
    expect(duplicate.content).toBe(source.content);
    expect(duplicate.x_unit).toBeGreaterThan(source.x_unit);
    expect(duplicate.z_index).toBe(3);
  });

  it('updates style and layer order without mutating the source array', () => {
    const first = createLocalElement(41, 'ar', 'free_text', -1, 1);
    const second = createLocalElement(41, 'ar', 'sfx', -2, 2);
    const recolored = updateElementStyle(first, { color: '#CC0000' });
    const reordered = moveElementLayer([first, second], first.id, 'up');

    expect(resolvedStyle(recolored).color).toBe('#CC0000');
    expect(reordered.find((element) => element.id === first.id)?.z_index).toBe(2);
    expect(highestZIndex(reordered)).toBe(2);
    expect(first.z_index).toBe(1);
  });
});
