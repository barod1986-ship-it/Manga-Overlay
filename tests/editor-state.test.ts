import { test } from 'node:test';
import assert from 'node:assert/strict';
import { activePage, layers, overlayView, parseRoute, routeHash, type Page, type Element } from '../wp-content/plugins/manga-overlay-core/editor-src/editor/state.ts';

test('editor navigation uses safe resource IDs and round-trips browser history fragments', () => {
  const route = { pageId: 87, elementId: 901 };
  assert.deepEqual(parseRoute(routeHash(route)), route);
  for (const invalid of ['0', '-1', '1.5', '1e3', '9007199254740992', '<script>']) {
    assert.equal(parseRoute('#page=' + invalid).pageId, null);
  }
  assert.equal(routeHash({ pageId: null, elementId: 2 }), '');
});

test('editor links keep the same page after page reorder and recover from removed IDs', () => {
  const page = (id: number, index: number): Page => ({ id, chapter_id: 10, page_index: index, natural_width: 960, natural_height: 420, image: { url: '/page.png', width: 960, height: 420 } });
  const pages = [page(70, 0), page(22, 1)];
  assert.equal(activePage(pages, 22)?.id, 22);
  assert.equal(activePage([...pages].reverse(), 22)?.id, 22);
  assert.equal(activePage(pages, 999)?.id, 70);
  assert.equal(activePage([], 22), undefined);
});

test('layer display order does not mutate DTOs or discard persisted identity and version', () => {
  const element: Element = { id: 7, page_id: 22, target_lang: 'ar', element_type: 'bubble', content: 'نص عربي', style: {}, version: 8,
    created_by: 5, updated_by: 6, created_at: '2026-09-06T10:00:00Z', updated_at: '2026-09-06T11:00:00Z', x_unit: 100, y_unit: 200, w_unit: 10000, h_unit: 20000 };
  const source = [element, { ...element, id: 8, z_index: 2 }];
  assert.deepEqual(layers(source).map(item => item.id), [8, 7]);
  assert.deepEqual(source.map(item => item.id), [7, 8]);
  assert.equal(overlayView(element).rotation_mdeg, 0);
  assert.equal(overlayView(element).z_index, 0);
  assert.equal(overlayView(element).key, '7');
  assert.equal(element.version, 8);
  assert.equal(element.rotation_mdeg, undefined);
});
