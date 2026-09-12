import { test } from 'node:test';
import assert from 'node:assert/strict';
import { defaultPreset, presetStyle, type Preset } from '../wp-content/plugins/manga-overlay-core/editor-src/editor/presets.ts';
import { changeElement, workingCopy } from '../wp-content/plugins/manga-overlay-core/editor-src/editor/drafts.ts';
import { patchFor } from '../wp-content/plugins/manga-overlay-core/editor-src/editor/persistence.ts';
import type { Element } from '../wp-content/plugins/manga-overlay-core/editor-src/editor/state.ts';
const preset = (id: number, scope: Preset['scope']): Preset => ({ id, scope, name: scope, element_type: 'bubble', style: { color: '#112233' }, is_default: true });
test('preset precedence is independent of list ordering and filtered by type/default', () => {
  const data = [preset(1, 'global'), preset(2, 'work'), preset(3, 'personal')];
  assert.equal(defaultPreset('bubble', data)?.id, 3);
  assert.equal(defaultPreset('bubble', data.slice(0, 2))?.id, 2);
  assert.equal(defaultPreset('bubble', data.slice(0, 1))?.id, 1);
  assert.equal(defaultPreset('sfx', data), undefined);
  assert.equal(defaultPreset('bubble', data.map(item => ({ ...item, is_default: false }))), undefined);
});
test('applying a preset preserves content/geometry/identity and explicitly clears old optional groups', () => {
  const source: Element = { id: 5, page_id: 8, target_lang: 'ar', element_type: 'bubble', content: 'لا يتغير', version: 3, created_by: 1, updated_by: 1, created_at: '2026-09-07T00:00:00Z', updated_at: '2026-09-07T00:00:00Z',
    x_unit: 100, y_unit: 200, w_unit: 300000, h_unit: 200000, style: { color: '#FFFFFF', tail: { enabled: true }, shadow: { color: '#FFFFFF' }, strokeWidthUnit: 2000 } };
  const value = changeElement(workingCopy(source), { style: presetStyle('bubble', preset(1, 'personal')) });
  const patch = patchFor(value);
  assert.deepEqual(Object.keys(patch).sort(), ['element_type', 'style']);
  assert.equal(patch.style?.tail, null); assert.equal(patch.style?.shadow, null); assert.equal(patch.style?.strokeWidthUnit, 0);
  assert.equal(value.content, source.content); assert.equal(value.x_unit, source.x_unit); assert.equal(value.source?.version, 3);
  assert.equal(source.style.tail?.enabled, true);
  assert.throws(() => presetStyle('sfx', preset(1, 'personal')), /mismatch/);
});
