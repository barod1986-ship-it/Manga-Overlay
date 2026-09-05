import { describe, expect, it } from 'vitest';
import {
  clampPagePosition,
  clampZoom,
  editorReducer,
  initialEditorState,
  pagePositionFromSearch,
  physicalGeometryStyle,
  searchForPage,
} from './state';
import type { EditorElement } from './types';

describe('editor shell state', () => {
  it('maps the one-based page query to a safe zero-based position', () => {
    expect(pagePositionFromSearch('?mol_page=2', 3)).toBe(1);
    expect(pagePositionFromSearch('?mol_page=99', 3)).toBe(2);
    expect(pagePositionFromSearch('?mol_page=-1', 3)).toBe(0);
    expect(pagePositionFromSearch('?page=2', 3)).toBe(0);
    expect(clampPagePosition(Number.NaN, 3)).toBe(0);
    expect(searchForPage('?mode=review&page=9', 1)).toBe('?mode=review&mol_page=2');
  });

  it('keeps zoom and page transitions within shell limits', () => {
    const initial = initialEditorState('', 2);
    const selected = editorReducer(initial, { type: 'select-element', id: 17 });
    const changedPage = editorReducer(selected, { type: 'go-page', position: 1, pageCount: 2 });

    expect(changedPage.pagePosition).toBe(1);
    expect(changedPage.selectedElementId).toBeNull();
    expect(clampZoom(9)).toBe(2);
    expect(clampZoom(-2)).toBe(0.5);
  });

  it('uses physical left/top geometry even when the interface is RTL', () => {
    const element = {
      id: 1,
      page_id: 2,
      target_lang: 'ar',
      element_type: 'bubble',
      x_unit: 100_000,
      y_unit: 200_000,
      w_unit: 300_000,
      h_unit: 250_000,
      rotation_mdeg: 12_500,
      z_index: 4,
      content: 'اختبار',
      style: {},
      version: 1,
    } satisfies EditorElement;

    expect(physicalGeometryStyle(element)).toMatchObject({
      left: '10%',
      top: '20%',
      width: '30%',
      height: '25%',
      transform: 'rotate(12.5deg)',
    });
  });
});
