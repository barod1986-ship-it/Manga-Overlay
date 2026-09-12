import { BASE_STYLES } from '../domain/baseStyles.ts';
import type { ElementStyle, ElementType } from '../domain/types.ts';
import type { components } from '../generated/api';
export type Preset = components['schemas']['Preset'];
export type PresetCreate = components['schemas']['PresetCreate'];
export type PresetPatch = components['schemas']['PresetPatch'];
export const SCOPE_LABELS = { personal: 'شخصي', work: 'لهذا العمل', global: 'عام' };

/** A preset replaces the visual style; optional groups must explicitly clear old values on PATCH. */
export function presetStyle(type: ElementType, preset?: Preset): ElementStyle {
  if (preset && preset.element_type !== type) throw new Error('Preset type mismatch');
  const reset: ElementStyle = { backgroundColor: '#FFFFFF', backgroundOpacity: 0, borderColor: '#111111', borderWidthUnit: 0, borderRadiusUnit: 0,
    paddingUnit: 0, strokeColor: '#111111', strokeWidthUnit: 0, shadow: null, minFontSizeUnit: 1000,
    ...(type === 'bubble' ? { tail: null } : {}), ...(type === 'sfx' ? { burst: null, scaleX: 1, scaleY: 1 } : {}) };
  return structuredClone({ ...reset, ...BASE_STYLES[type], ...preset?.style });
}
export function defaultPreset(type: ElementType, presets: Preset[]): Preset | undefined {
  return ['personal', 'work', 'global'].flatMap(scope => presets.filter(item => item.element_type === type && item.scope === scope && item.is_default).sort((a, b) => b.id - a.id))[0];
}
