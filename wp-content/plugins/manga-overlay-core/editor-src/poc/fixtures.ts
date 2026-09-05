import type { ElementType, OverlayDraft } from '../domain/types';
import { BASE_STYLES } from '../domain/baseStyles';

export function createDraft(type: ElementType, key: string = crypto.randomUUID()): OverlayDraft {
  return {
    key, element_type: type, target_lang: 'ar', content: '',
    x_unit: 350000, y_unit: 240000, w_unit: 300000, h_unit: 120000,
    rotation_mdeg: 0, z_index: 1, style: structuredClone(BASE_STYLES[type]),
  };
}
export function referenceElements(): OverlayDraft[] {
  return [
    { ...createDraft('bubble', 'bubble-reference'), content: 'لماذا أتيت إلى هنا؟', x_unit: 600000, y_unit: 80000, w_unit: 280000, h_unit: 115000, z_index: 1 },
    { ...createDraft('narration', 'narration-reference'), content: 'في صباحٍ هادئ، بدأت الحكاية.', x_unit: 80000, y_unit: 325000, w_unit: 500000, h_unit: 68000, z_index: 2 },
    { ...createDraft('sfx', 'sfx-reference'), content: 'دوووم!', x_unit: 605000, y_unit: 575000, w_unit: 300000, h_unit: 110000, rotation_mdeg: -15000, z_index: 3 },
    { ...createDraft('free_text', 'text-reference'), content: 'لا يزال الطريق طويلًا…', x_unit: 100000, y_unit: 795000, w_unit: 520000, h_unit: 68000, z_index: 4 },
  ];
}
