import type { ElementStyle, ElementType } from './types';

/** TRANSLATION_EDITOR_SPEC §11.1; these remain independent of personal presets. */
import baseStyles from '../../database/base-styles.json' with { type: 'json' };
export const BASE_STYLES = baseStyles as Record<ElementType, ElementStyle>;

export const FONT_FAMILIES: Record<NonNullable<ElementStyle['fontId']>, string> = {
  cairo: '"Cairo Variable", sans-serif',
  'noto-sans-arabic': '"Noto Sans Arabic Variable", sans-serif',
  tajawal: 'Tajawal, sans-serif',
  'noto-kufi-arabic': '"Noto Kufi Arabic Variable", sans-serif',
  // Provisional local mapping for PoC device tests; not an approved production SFX preset.
  'sfx-display-1': 'Tajawal, sans-serif',
};

export const ELEMENT_LABELS: Record<ElementType, string> = {
  bubble: 'فقاعة', narration: 'سرد', free_text: 'نص حر', sfx: 'مؤثر صوتي',
};
