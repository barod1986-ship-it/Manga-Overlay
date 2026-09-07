import type { components } from '../generated/api';
import type { OverlayDraft } from '../domain/types';

export type Chapter = components['schemas']['Chapter'];
export type Page = components['schemas']['Page'];
export type Element = components['schemas']['Element'];
export interface EditorRoute { pageId: number | null; elementId: number | null }
export interface Bootstrap { chapterId: number; workTitle: string; api: string; nonce: string; backUrl: string; backLabel: string; canEdit: boolean; canDelete: boolean; userId?: number; canManageWorkPresets?: boolean; canManageGlobalPresets?: boolean }

const positiveId = (value: string | null): number | null => value && /^[1-9]\d*$/.test(value) && Number.isSafeInteger(Number(value)) ? Number(value) : null;
export function parseRoute(hash: string): EditorRoute {
  const params = new URLSearchParams(hash.replace(/^#/, ''));
  return { pageId: positiveId(params.get('page')), elementId: positiveId(params.get('element')) };
}
export function routeHash(route: EditorRoute): string {
  const params = new URLSearchParams();
  if (route.pageId !== null) params.set('page', String(route.pageId));
  if (route.pageId !== null && route.elementId !== null) params.set('element', String(route.elementId));
  return params.size ? '#' + params.toString() : '';
}
export function activePage(pages: Page[], requested: number | null): Page | undefined {
  return pages.find(page => page.id === requested) ?? pages[0];
}
export function layers(elements: Element[]): Element[] {
  return [...elements].sort((a, b) => (b.z_index ?? 0) - (a.z_index ?? 0) || b.id - a.id);
}
/** A view adapter only: server IDs, versions and attribution stay in Element state. */
export function overlayView(element: Element): OverlayDraft {
  return { ...element, key: String(element.id), rotation_mdeg: element.rotation_mdeg ?? 0, z_index: element.z_index ?? 0 };
}
