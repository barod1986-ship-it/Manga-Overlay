import type { components } from '../generated/api';

export type ApiElement = components['schemas']['Element'];
export type ElementType = ApiElement['element_type'];
export type ElementStyle = components['schemas']['ElementStyle'];
export type Geometry = Required<components['schemas']['Geometry']>;

/** Session-only PoC state; deliberately has no persisted id/version/attribution. */
export type OverlayDraft = Geometry & Pick<ApiElement, 'content' | 'element_type' | 'target_lang'> & {
  key: string;
  style: ElementStyle;
};
