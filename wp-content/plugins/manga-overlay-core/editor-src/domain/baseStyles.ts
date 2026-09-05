import type { ElementStyle, ElementType } from './types';

/** TRANSLATION_EDITOR_SPEC §11.1; these remain independent of personal presets. */
export const BASE_STYLES = {
  bubble: {
    fontId: 'cairo', fontSizeUnit: 26000, fontWeight: 700, lineHeight: 1.35,
    textAlign: 'center', color: '#111111', backgroundColor: '#FFFFFF',
    backgroundOpacity: .96, borderColor: '#111111', borderWidthUnit: 1800,
    borderRadiusUnit: 50000, paddingUnit: 9000, shape: 'ellipse', autoFit: true, minFontSizeUnit: 16000,
  },
  narration: {
    fontId: 'noto-sans-arabic', fontSizeUnit: 24000, fontWeight: 600, lineHeight: 1.4,
    textAlign: 'center', color: '#111111', backgroundColor: '#FFFFFF',
    backgroundOpacity: .94, borderColor: '#111111', borderWidthUnit: 1500,
    borderRadiusUnit: 18000, paddingUnit: 10000, shape: 'rounded_rect', autoFit: true, minFontSizeUnit: 15000,
  },
  free_text: {
    fontId: 'cairo', fontSizeUnit: 26000, fontWeight: 700, lineHeight: 1.3,
    textAlign: 'center', color: '#111111', backgroundOpacity: 0,
    borderWidthUnit: 0, shape: 'none', autoFit: false,
  },
  sfx: {
    fontId: 'sfx-display-1', fontSizeUnit: 52000, fontWeight: 900, lineHeight: 1.1,
    textAlign: 'center', color: '#FFFFFF', backgroundOpacity: 0,
    strokeColor: '#111111', strokeWidthUnit: 3500, shape: 'none',
    scaleX: 1, scaleY: 1, autoFit: false,
  },
} satisfies Record<ElementType, ElementStyle>;

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
