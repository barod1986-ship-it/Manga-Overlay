import { BASE_STYLES } from '../domain/baseStyles.ts';
import { normalizeGeometry } from '../domain/geometry.ts';
import type { ElementType, Geometry, OverlayDraft } from '../domain/types.ts';
import { overlayView, type Element } from './state.ts';

/** A local working copy. New elements have no server ID, version or attribution. */
export type WorkingElement = OverlayDraft & { source: Element | null };
export type ElementChange = Partial<Geometry & Pick<OverlayDraft, 'content' | 'style'>>;
export interface PageDraft { elements: WorkingElement[]; deleted: WorkingElement | null }
export type Drafts = Record<number, PageDraft>;

export function workingCopy(element: Element): WorkingElement {
  return { ...overlayView(structuredClone(element)), source: structuredClone(element) };
}
export function createElement(type: ElementType, elements: WorkingElement[], key: string): WorkingElement {
  return {
    key, source: null, target_lang: 'ar', element_type: type, content: '',
    x_unit: 350000, y_unit: 300000, w_unit: 300000, h_unit: type === 'sfx' ? 160000 : 120000,
    rotation_mdeg: 0, z_index: Math.min(10000, Math.max(0, ...elements.map(item => item.z_index)) + 1),
    style: structuredClone(BASE_STYLES[type]),
  };
}
export function changeElement(element: WorkingElement, patch: ElementChange): WorkingElement {
  return { ...element, ...normalizeGeometry({ ...element, ...patch }),
    content: patch.content ?? element.content, style: structuredClone(patch.style ?? element.style) };
}
export function duplicateElement(element: WorkingElement, elements: WorkingElement[], key: string): WorkingElement {
  // Copy only editable fields. Never copy server identity, attribution or version into a new element.
  const fresh = createElement(element.element_type, elements, key);
  return changeElement(fresh, { ...normalizeGeometry({ ...element, x_unit: element.x_unit + 15000, y_unit: element.y_unit + 15000, z_index: fresh.z_index }),
    content: element.content, style: element.style });
}
export function workingLayers(elements: WorkingElement[]): WorkingElement[] {
  return [...elements].reverse().sort((a, b) => b.z_index - a.z_index);
}
