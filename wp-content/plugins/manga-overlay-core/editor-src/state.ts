import type { EditorAction, EditorElement, EditorState } from './types';

export const MIN_ZOOM = 0.5;
export const MAX_ZOOM = 2;
export const GEOMETRY_UNIT = 1_000_000;

export function clampPagePosition(position: number, pageCount: number): number {
  if (pageCount < 1) {
    return 0;
  }
  if (!Number.isFinite(position)) {
    return 0;
  }
  return Math.min(Math.max(Math.trunc(position), 0), pageCount - 1);
}

export function pagePositionFromSearch(search: string, pageCount: number): number {
  const source = new URLSearchParams(search).get('page');
  if (source === null || !/^\d+$/.test(source)) {
    return 0;
  }
  return clampPagePosition(Number(source) - 1, pageCount);
}

export function searchForPage(search: string, pagePosition: number): string {
  const parameters = new URLSearchParams(search);
  parameters.set('page', String(pagePosition + 1));
  const serialized = parameters.toString();
  return serialized === '' ? '' : `?${serialized}`;
}

export function clampZoom(zoom: number): number {
  if (!Number.isFinite(zoom)) {
    return 1;
  }
  return Math.min(Math.max(zoom, MIN_ZOOM), MAX_ZOOM);
}

export function initialEditorState(search: string, pageCount: number): EditorState {
  return {
    pagePosition: pagePositionFromSearch(search, pageCount),
    selectedElementId: null,
    zoom: 1,
    preview: false,
    layersCollapsed: false,
  };
}

export function editorReducer(state: EditorState, action: EditorAction): EditorState {
  switch (action.type) {
    case 'go-page':
      return {
        ...state,
        pagePosition: clampPagePosition(action.position, action.pageCount),
        selectedElementId: null,
      };
    case 'select-element':
      return { ...state, selectedElementId: action.id };
    case 'set-zoom':
      return { ...state, zoom: clampZoom(action.zoom) };
    case 'toggle-preview':
      return { ...state, preview: !state.preview };
    case 'toggle-layers':
      return { ...state, layersCollapsed: !state.layersCollapsed };
  }
}

export function physicalGeometryStyle(element: EditorElement): Readonly<Record<string, string | number>> {
  return {
    left: `${(element.x_unit / GEOMETRY_UNIT) * 100}%`,
    top: `${(element.y_unit / GEOMETRY_UNIT) * 100}%`,
    width: `${(element.w_unit / GEOMETRY_UNIT) * 100}%`,
    height: `${(element.h_unit / GEOMETRY_UNIT) * 100}%`,
    transform: `rotate(${element.rotation_mdeg / 1_000}deg)`,
    zIndex: element.z_index,
  };
}
