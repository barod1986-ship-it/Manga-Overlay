import type { ElementStyle, ElementType, OverlayElement } from '@mol/poc-renderer';

const BASE_STYLES: Readonly<Record<ElementType, ElementStyle>> = {
  bubble: {
    fontId: 'cairo',
    fontSizeUnit: 26_000,
    fontWeight: 700,
    lineHeight: 1.35,
    textAlign: 'center',
    color: '#111111',
    backgroundColor: '#FFFFFF',
    backgroundOpacity: 0.96,
    borderColor: '#111111',
    borderWidthUnit: 1_800,
    borderRadiusUnit: 50_000,
    paddingUnit: 9_000,
    shape: 'ellipse',
  },
  narration: {
    fontId: 'noto-sans-arabic',
    fontSizeUnit: 24_000,
    fontWeight: 600,
    lineHeight: 1.4,
    textAlign: 'center',
    color: '#111111',
    backgroundColor: '#F8F0DF',
    backgroundOpacity: 0.96,
    borderColor: '#111111',
    borderWidthUnit: 1_500,
    borderRadiusUnit: 18_000,
    paddingUnit: 10_000,
    shape: 'rounded_rect',
  },
  free_text: {
    fontId: 'cairo',
    fontSizeUnit: 28_000,
    fontWeight: 700,
    lineHeight: 1.3,
    textAlign: 'center',
    color: '#FFF9EC',
    backgroundColor: '#000000',
    backgroundOpacity: 0,
    borderColor: '#000000',
    borderWidthUnit: 0,
    borderRadiusUnit: 0,
    paddingUnit: 0,
    shape: 'none',
    shadow: {
      xUnit: 2_000,
      yUnit: 2_000,
      blurUnit: 4_000,
      color: '#000000',
      opacity: 0.9,
    },
  },
  sfx: {
    fontId: 'sfx-display-1',
    fontSizeUnit: 52_000,
    fontWeight: 900,
    lineHeight: 1.1,
    textAlign: 'center',
    color: '#FFF9EC',
    backgroundColor: '#B5231C',
    backgroundOpacity: 0.9,
    borderColor: '#181512',
    borderWidthUnit: 2_500,
    borderRadiusUnit: 0,
    paddingUnit: 10_000,
    shape: 'burst',
    strokeColor: '#181512',
    strokeWidthUnit: 3_500,
  },
};

const STARTING_CONTENT: Readonly<Record<ElementType, string>> = {
  bubble: 'لن أتراجع الآن.',
  narration: 'قبل الغروب بقليل…',
  free_text: 'همس',
  sfx: 'دَوِيّ!',
};

export function createLocalElement(elementType: ElementType, id: number): OverlayElement {
  const offset = ((id * 37) % 140_000) - 70_000;
  return {
    id,
    page_id: 1,
    target_lang: 'ar',
    element_type: elementType,
    x_unit: 350_000 + offset,
    y_unit: 290_000 + Math.abs(offset),
    w_unit: elementType === 'narration' ? 390_000 : 300_000,
    h_unit: elementType === 'sfx' ? 180_000 : 130_000,
    rotation_mdeg: elementType === 'sfx' ? -8_000 : 0,
    z_index: id,
    content: STARTING_CONTENT[elementType],
    style: BASE_STYLES[elementType],
    version: 1,
  };
}

export const INITIAL_ELEMENTS: readonly OverlayElement[] = [
  {
    ...createLocalElement('bubble', 201),
    x_unit: 575_000,
    y_unit: 55_000,
    w_unit: 320_000,
    h_unit: 145_000,
    content: 'لن أتراجع الآن… الطريق أمامي.',
  },
  {
    ...createLocalElement('narration', 202),
    x_unit: 65_000,
    y_unit: 380_000,
    w_unit: 405_000,
    h_unit: 95_000,
  },
  {
    ...createLocalElement('sfx', 203),
    x_unit: 560_000,
    y_unit: 555_000,
    w_unit: 330_000,
    h_unit: 175_000,
  },
];

export const ELEMENT_TYPE_LABELS: Readonly<Record<ElementType, string>> = {
  bubble: 'فقاعة',
  narration: 'سرد',
  free_text: 'نص حر',
  sfx: 'مؤثر',
};
