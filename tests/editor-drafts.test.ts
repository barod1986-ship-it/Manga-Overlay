import { test } from 'node:test';
import assert from 'node:assert/strict';
import { changeElement, createElement, duplicateElement, workingCopy, workingLayers } from '../wp-content/plugins/manga-overlay-core/editor-src/editor/drafts.ts';
import { BASE_STYLES } from '../wp-content/plugins/manga-overlay-core/editor-src/domain/baseStyles.ts';
import type { Element } from '../wp-content/plugins/manga-overlay-core/editor-src/editor/state.ts';

const saved: Element = { id: 42, page_id: 8, target_lang: 'ar', element_type: 'bubble', content: 'الأصل', style: { tail: { enabled: true, lengthUnit: 40000 } }, version: 7,
  x_unit: 80000, y_unit: 100000, w_unit: 300000, h_unit: 200000, created_by: 2, updated_by: 3, created_at: '2026-09-06T10:00:00Z', updated_at: '2026-09-06T11:00:00Z' };
test('all four new types use independent base styles without fabricated persisted identity', () => {
  for (const type of ['bubble', 'narration', 'free_text', 'sfx'] as const) {
    const element = createElement(type, [], 'draft:' + type);
    assert.deepEqual(element.style, BASE_STYLES[type]);
    assert.notEqual(element.style, BASE_STYLES[type]);
    assert.equal(element.source, null);
    for (const property of ['id', 'page_id', 'version', 'created_by', 'updated_by']) assert.equal(property in element, false);
    assert.equal(element.target_lang, 'ar'); assert.equal(element.content, '');
  }
});
test('local text, style and normalized transforms never mutate source DTO or baseline version', () => {
  const original = structuredClone(saved);
  const copy = workingCopy(saved);
  const next = changeElement(copy, { content: '<img onerror=alert(1)> العربية', x_unit: 999999, y_unit: -200, w_unit: 400000, rotation_mdeg: 400000, z_index: 10001, style: { tail: { enabled: false } } });
  assert.deepEqual(saved, original); assert.deepEqual(next.source, original);
  assert.equal(next.source?.version, 7); assert.equal(copy.content, 'الأصل');
  assert.equal(next.x_unit, 600000); assert.equal(next.y_unit, 0); assert.equal(next.rotation_mdeg, 360000); assert.equal(next.z_index, 10000);
  next.style.tail!.enabled = true;
  assert.deepEqual(copy.style, saved.style);
  assert.throws(() => changeElement(copy, { w_unit: NaN }), RangeError);
});
test('duplicate drops all server identity and deep-copies style, with bounded placement and top layer', () => {
  const original = workingCopy(saved);
  const copy = duplicateElement(original, [original, { ...original, z_index: 10000 }], 'draft:copy');
  assert.equal(copy.source, null); assert.equal(copy.key, 'draft:copy');
  assert.equal('id' in copy, false); assert.equal('version' in copy, false);
  assert.equal(copy.z_index, 10000); assert.equal(copy.x_unit, 95000);
  copy.style.tail!.lengthUnit = 10;
  assert.equal(original.style.tail!.lengthUnit, 40000); assert.equal(saved.style.tail!.lengthUnit, 40000);
});
test('layer ordering matches stacking order for equal z-index without mutating working state', () => {
  const first = workingCopy(saved), second = createElement('sfx', [], 'draft:second');
  first.z_index = second.z_index = 5;
  const source = [first, second];
  assert.deepEqual(workingLayers(source).map(item => item.key), ['draft:second', '42']);
  assert.deepEqual(source.map(item => item.key), ['42', 'draft:second']);
});
