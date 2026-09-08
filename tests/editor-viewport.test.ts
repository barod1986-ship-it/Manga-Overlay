import { test } from 'node:test';
import assert from 'node:assert/strict';
import { anchorScroll, clampZoom, distance, imageAnchor, midpoint } from '../wp-content/plugins/manga-overlay-core/editor-src/editor/viewportGeometry.ts';

test('pinch anchor survives zoom and translated fingers on portrait and landscape images', () => {
  for (const height of [720, 180]) {
    const box = { left: 16, top: 250, width: 360, height };
    const fingers = [{ x: 100, y: 300 }, { x: 260, y: 380 }];
    const center = midpoint(fingers[0], fingers[1]);
    const anchor = imageAnchor(center, box);
    const moved = { x: center.x + 15, y: center.y - 20 };
    const resized = { ...box, width: box.width * 2, height: box.height * 2 };
    const scroll = anchorScroll(anchor, moved, resized);
    assert.equal(resized.left + anchor.x * resized.width - scroll.x, moved.x);
    assert.equal(resized.top + anchor.y * resized.height - scroll.y, moved.y);
    assert.deepEqual(anchorScroll(anchor, center, box), { x: 0, y: 0 });
  }
});
test('zoom is finite and constrained even for collapsed or unbounded touch distances', () => {
  assert.equal(clampZoom(0), .5); assert.equal(clampZoom(100), 3);
  assert.equal(clampZoom(NaN), 1); assert.equal(clampZoom(Infinity), 1);
  assert.equal(clampZoom(1.75), 1.75);
  assert.equal(distance({ x: 1, y: 1 }, { x: 4, y: 5 }), 5);
});
