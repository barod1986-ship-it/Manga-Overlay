import type { EditorElement } from './types';

const MUTABLE_FIELDS = [
  'x_unit',
  'y_unit',
  'w_unit',
  'h_unit',
  'rotation_mdeg',
  'z_index',
  'content',
  'style',
] as const satisfies readonly (keyof EditorElement)[];

function equalValue(left: unknown, right: unknown): boolean {
  if (left === right) return true;
  if (typeof left !== 'object' || left === null || typeof right !== 'object' || right === null) return false;
  return JSON.stringify(left) === JSON.stringify(right);
}

export function reapplyLocalChanges(
  baseline: EditorElement,
  yours: EditorElement,
  current: EditorElement,
): EditorElement {
  let result = current;
  for (const field of MUTABLE_FIELDS) {
    if (!equalValue(baseline[field], yours[field])) {
      result = { ...result, [field]: yours[field] };
    }
  }

  return result;
}
