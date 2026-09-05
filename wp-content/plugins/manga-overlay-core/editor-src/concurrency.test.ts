import { describe, expect, it } from 'vitest';
import { reapplyLocalChanges } from './concurrency';
import type { EditorElement } from './types';

function element(overrides: Partial<EditorElement> = {}): EditorElement {
  return {
    id: 7,
    page_id: 41,
    target_lang: 'ar',
    element_type: 'bubble',
    x_unit: 100_000,
    y_unit: 100_000,
    w_unit: 300_000,
    h_unit: 200_000,
    rotation_mdeg: 0,
    z_index: 1,
    content: 'قديم',
    style: { color: '#111111', shape: 'ellipse' },
    version: 3,
    ...overrides,
  };
}

describe('T-13 conflict reapplication', () => {
  it('reapplies only locally changed fields over the current server version', () => {
    const baseline = element();
    const yours = element({ content: 'تغييري', x_unit: 140_000 });
    const current = element({ y_unit: 180_000, style: { color: '#cc0000' }, version: 4 });

    expect(reapplyLocalChanges(baseline, yours, current)).toEqual({
      ...current,
      content: 'تغييري',
      x_unit: 140_000,
    });
  });

  it('preserves identity and the latest version from the server', () => {
    const baseline = element();
    const yours = element({ version: 3, z_index: 8 });
    const current = element({ id: 7, version: 12, content: 'نسخة الخادم' });

    const merged = reapplyLocalChanges(baseline, yours, current);
    expect(merged.version).toBe(12);
    expect(merged.content).toBe('نسخة الخادم');
    expect(merged.z_index).toBe(8);
  });
});
