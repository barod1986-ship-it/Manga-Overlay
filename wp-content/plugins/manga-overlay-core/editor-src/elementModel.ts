import { normalizeElementStyle } from '@mol/poc-renderer';
import type { EditorElement, ElementStyle, ElementType } from './types';

export const ELEMENT_LABELS: Readonly<Record<ElementType, string>> = {
  bubble: 'فقاعة',
  narration: 'سرد',
  free_text: 'نص حر',
  sfx: 'مؤثر صوتي',
};

export const SHAPE_OPTIONS: Readonly<Record<ElementType, readonly { value: string; label: string }[]>> = {
  bubble: [
    { value: 'ellipse', label: 'بيضاوي' },
    { value: 'rounded_rect', label: 'مستطيل مستدير' },
    { value: 'rect', label: 'مستطيل' },
    { value: 'cloud', label: 'سحابة' },
  ],
  narration: [
    { value: 'rounded_rect', label: 'مستطيل مستدير' },
    { value: 'rect', label: 'مستطيل' },
  ],
  free_text: [
    { value: 'none', label: 'بدون خلفية' },
    { value: 'rounded_rect', label: 'خلفية مستديرة' },
    { value: 'rect', label: 'خلفية مستطيلة' },
  ],
  sfx: [
    { value: 'none', label: 'بدون خلفية' },
    { value: 'burst', label: 'انفجار' },
    { value: 'impact', label: 'صدمة' },
  ],
};

const STARTING_CONTENT: Readonly<Record<ElementType, string>> = {
  bubble: 'اكتب الحوار هنا…',
  narration: 'اكتب السرد هنا…',
  free_text: 'نص جديد',
  sfx: 'دَوِيّ!',
};

function clamp(value: number, minimum: number, maximum: number): number {
  return Math.min(Math.max(value, minimum), maximum);
}

export function resolvedStyle(element: EditorElement): ElementStyle {
  return normalizeElementStyle(element.element_type, element.style) as ElementStyle;
}

export function updateElementStyle(
  element: EditorElement,
  patch: Partial<ElementStyle>,
): EditorElement {
  const current = resolvedStyle(element);
  return { ...element, style: { ...current, ...patch } };
}

export function createLocalElement(
  pageId: number,
  targetLanguage: string,
  elementType: ElementType,
  id: number,
  zIndex: number,
): EditorElement {
  const offset = (Math.abs(id) * 37_000) % 130_000;
  const width = elementType === 'narration' ? 390_000 : 300_000;
  const height = elementType === 'sfx' ? 190_000 : 145_000;
  return {
    id,
    page_id: pageId,
    target_lang: targetLanguage,
    element_type: elementType,
    x_unit: clamp(330_000 + offset, 0, 1_000_000 - width),
    y_unit: clamp(240_000 + offset, 0, 1_000_000 - height),
    w_unit: width,
    h_unit: height,
    rotation_mdeg: elementType === 'sfx' ? -8_000 : 0,
    z_index: clamp(zIndex, -1_000, 10_000),
    content: STARTING_CONTENT[elementType],
    style: { ...normalizeElementStyle(elementType, {}) },
    version: 0,
  };
}

export function duplicateLocalElement(
  source: EditorElement,
  id: number,
  zIndex: number,
): EditorElement {
  return {
    ...source,
    id,
    x_unit: clamp(source.x_unit + 20_000, 0, 1_000_000 - source.w_unit),
    y_unit: clamp(source.y_unit + 20_000, 0, 1_000_000 - source.h_unit),
    z_index: clamp(zIndex, -1_000, 10_000),
    style: { ...resolvedStyle(source) },
    version: 0,
  };
}

export function highestZIndex(elements: readonly EditorElement[]): number {
  return elements.reduce((highest, element) => Math.max(highest, element.z_index), 0);
}

export function moveElementLayer(
  elements: readonly EditorElement[],
  id: number,
  direction: 'up' | 'down',
): EditorElement[] {
  const ordered = [...elements].sort((left, right) => left.z_index - right.z_index || left.id - right.id);
  const position = ordered.findIndex((element) => element.id === id);
  const target = direction === 'up' ? position + 1 : position - 1;
  if (position < 0 || target < 0 || target >= ordered.length) return [...elements];
  const current = ordered[position];
  const neighbour = ordered[target];
  if (current === undefined || neighbour === undefined) return [...elements];
  ordered[position] = neighbour;
  ordered[target] = current;
  return ordered.map((element, index) => ({ ...element, z_index: index + 1 }));
}

export function elementName(element: EditorElement, position: number): string {
  const preview = element.content.trim().replace(/\s+/g, ' ').slice(0, 28);
  return preview === '' ? `${ELEMENT_LABELS[element.element_type]} ${position + 1}` : preview;
}
