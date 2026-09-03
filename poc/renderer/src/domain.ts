export const MOL_UNIT = 1_000_000 as const;

export type ElementType = 'bubble' | 'narration' | 'free_text' | 'sfx';
export type TextAlignment = 'start' | 'center' | 'end';
export type Shape = 'ellipse' | 'rounded_rect' | 'rect' | 'cloud' | 'none' | 'burst' | 'impact';

export interface Geometry {
  readonly x_unit: number;
  readonly y_unit: number;
  readonly w_unit: number;
  readonly h_unit: number;
  readonly rotation_mdeg: number;
  readonly z_index: number;
}

export interface ShadowStyle {
  readonly xUnit: number;
  readonly yUnit: number;
  readonly blurUnit: number;
  readonly color: string;
  readonly opacity: number;
}

export interface ElementStyle {
  readonly fontId: 'noto-sans-arabic' | 'cairo' | 'tajawal' | 'noto-kufi-arabic' | 'sfx-display-1';
  readonly fontSizeUnit: number;
  readonly fontWeight: 400 | 500 | 600 | 700 | 800 | 900;
  readonly lineHeight: number;
  readonly textAlign: TextAlignment;
  readonly color: string;
  readonly backgroundColor: string;
  readonly backgroundOpacity: number;
  readonly borderColor: string;
  readonly borderWidthUnit: number;
  readonly borderRadiusUnit: number;
  readonly paddingUnit: number;
  readonly shape: Shape;
  readonly strokeColor?: string;
  readonly strokeWidthUnit?: number;
  readonly shadow?: ShadowStyle;
}

export interface OverlayElement extends Geometry {
  readonly id: number;
  readonly page_id: number;
  readonly target_lang: string;
  readonly element_type: ElementType;
  readonly content: string;
  readonly style: ElementStyle;
  readonly version: number;
}
