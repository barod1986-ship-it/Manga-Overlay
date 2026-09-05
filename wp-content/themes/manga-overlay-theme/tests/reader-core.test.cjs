'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const reader = require('../assets/js/reader.js');

test('reader geometry stays physical and normalized', () => {
    assert.equal(reader.asPercentage(250_000), '25%');
    assert.equal(reader.asPercentage(1_500_000), '100%');
    assert.equal(reader.asPercentage(-1), '0%');
});

test('reader modes and directions reject unsupported values', () => {
    assert.equal(reader.normalizeMode('paged'), 'paged');
    assert.equal(reader.normalizeMode('spread'), 'webtoon');
    assert.equal(reader.normalizeDirection('ltr'), 'ltr');
    assert.equal(reader.normalizeDirection('vertical'), 'rtl');
});

test('reading progress payload follows the OpenAPI contract', () => {
    assert.deepEqual(reader.createProgressPayload(17, 3, 750_000, 'paged'), {
        chapter_id: 17,
        page_index: 3,
        progress_unit: 750_000,
        reader_mode: 'paged',
    });
    assert.equal(reader.progressStorageKey(17), 'mol_progress_17');
});

test('renderer style normalization keeps only safe values', () => {
    const style = reader.normalizeStyle('bubble', {
        fontId: 'javascript:alert(1)',
        color: 'url(javascript:alert(1))',
        backgroundColor: '#ffffff',
        fontSizeUnit: 999_999,
        shape: '<svg onload=alert(1)>',
    });

    assert.equal(style.fontId, 'noto-sans-arabic');
    assert.equal(style.color, '#111111');
    assert.equal(style.backgroundColor, '#ffffff');
    assert.equal(style.fontSizeUnit, 200_000);
    assert.equal(style.shape, 'ellipse');
});

test('auto-fit binary search keeps the box-independent size inside bounds', () => {
    assert.equal(reader.largestFittingFontSize(12, 30, () => true), 30);
    const fitted = reader.largestFittingFontSize(12, 30, (size) => size <= 21.25);
    assert.ok(fitted > 21.24 && fitted <= 21.25);
    assert.equal(reader.largestFittingFontSize(12, 30, () => false), 12);
});
