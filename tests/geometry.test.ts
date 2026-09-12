import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fromPixels, normalizeGeometry, toPixels } from '../wp-content/plugins/manga-overlay-core/editor-src/domain/geometry.ts';
import { fitFont } from '../wp-content/plugins/manga-overlay-core/editor-src/renderer/autoFit.ts';

const sample = { x_unit: 420123, y_unit: 180789, w_unit: 202123, h_unit: 111987, rotation_mdeg: -12345, z_index: 3 };

for (const width of [360, 768, 1440, 2160]) {
  test(`normalized geometry round-trips at image width ${width}`, () => {
    const image = { width, height: width * 1.5 };
    assert.deepEqual(fromPixels(toPixels(sample, image), image, 3), sample);
  });
}
test('portrait image uses height for Y and H, independent of viewport padding', () => {
  const box = toPixels({ ...sample, x_unit: 500000, y_unit: 500000, w_unit: 250000, h_unit: 250000 }, { width: 400, height: 1800 });
  assert.equal(box.x, 200); assert.equal(box.y, 900);
  assert.equal(box.width, 100); assert.equal(box.height, 450);
});
test('end interaction rounds to integers and enforces contract bounds', () => {
  assert.deepEqual(normalizeGeometry({ x_unit: -1, y_unit: 1000001, w_unit: 0, h_unit: 1000001, rotation_mdeg: -400000, z_index: 10001 }),
    { x_unit: 0, y_unit: 0, w_unit: 1, h_unit: 1000000, rotation_mdeg: -360000, z_index: 10000 });
});
test('boxes remain inside the image as required by DATABASE_SCHEMA section 7', () => {
  const box = normalizeGeometry({ ...sample, x_unit: 990000, y_unit: 999999 });
  assert.equal(box.x_unit + box.w_unit, 1000000);
  assert.equal(box.y_unit + box.h_unit, 1000000);
  assert.equal(box.w_unit, sample.w_unit);
});
test('rejects zero image dimensions and non-finite geometry', () => {
  assert.throws(() => toPixels(sample, { width: 0, height: 100 }), RangeError);
  assert.throws(() => normalizeGeometry({ ...sample, x_unit: NaN }), RangeError);
  assert.throws(() => normalizeGeometry({ ...sample, w_unit: Infinity }), RangeError);
});
test('auto-fit finds largest available size without exceeding the requested size', () => {
  assert.ok(Math.abs(fitFont(40, 12, size => size <= 23.7) - 23.7) < .01);
  assert.equal(fitFont(18, 12, () => true), 18);
});
test('auto-fit respects its lower bound even if content cannot fit', () => {
  assert.equal(fitFont(40, 12, () => false), 12);
  assert.equal(fitFont(10, 18, () => false), 10);
});
