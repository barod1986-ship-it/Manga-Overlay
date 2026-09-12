import { test } from 'node:test';
import assert from 'node:assert/strict';
import { pageDelta, readerMode, validProgress } from '../wp-content/plugins/manga-overlay-core/editor-src/reader/state.ts';

test('reader choice respects per-work preference before chapter and work defaults', () => {
  assert.equal(readerMode('webtoon', 'paged', 'paged'), 'webtoon');
  assert.equal(readerMode('invalid', 'paged', 'webtoon'), 'paged');
  assert.equal(readerMode(null, null, 'webtoon'), 'webtoon');
});
test('horizontal keys follow the content direction rather than interface RTL', () => {
  assert.equal(pageDelta('ArrowLeft', 'rtl'), 1);
  assert.equal(pageDelta('ArrowRight', 'rtl'), -1);
  assert.equal(pageDelta('ArrowRight', 'ltr'), 1);
  assert.equal(pageDelta('ArrowLeft', 'ltr'), -1);
  assert.equal(pageDelta('ArrowDown', 'rtl'), 0);
});
test('stored progress cannot select a removed page or a different chapter', () => {
  const progress = { chapter_id: 8, page_index: 2, progress_unit: 450000, reader_mode: 'webtoon', updated_at: '2026-09-06T10:00:00Z' };
  assert.deepEqual(validProgress(progress, 8, 3), progress);
  assert.equal(validProgress(progress, 8, 2), null);
  assert.equal(validProgress(progress, 9, 3), null);
  assert.equal(validProgress({ ...progress, progress_unit: 1000001 }, 8, 3), null);
  assert.equal(validProgress({ ...progress, updated_at: 'broken' }, 8, 3), null);
  assert.equal(validProgress({ ...progress, page_index: '2' }, 8, 3), null);
});
