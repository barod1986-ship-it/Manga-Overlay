import type {
  BurstStyle,
  ElementStyle,
  ElementType,
  FontId,
  OverlayElement,
  ShadowStyle,
  Shape,
  TailStyle,
  TextAlignment,
} from './domain';
import { toCssGeometry, unitToPixels } from './geometry';
import { createShapeLayer } from './shapes';

const FONT_FAMILIES: Readonly<Record<FontId, string>> = {
  'noto-sans-arabic': '"Noto Sans Arabic", "Segoe UI", sans-serif',
  cairo: 'Cairo, "Noto Sans Arabic", "Segoe UI", sans-serif',
  tajawal: 'Tajawal, "Noto Sans Arabic", "Segoe UI", sans-serif',
  'noto-kufi-arabic': '"Noto Kufi Arabic", "Segoe UI", sans-serif',
  'sfx-display-1': 'Tajawal, "Noto Sans Arabic", "Segoe UI", sans-serif',
};

const FONT_WEIGHTS = [400, 500, 600, 700, 800, 900] as const;
const TEXT_ALIGNMENTS = ['start', 'center', 'end'] as const;
const BURST_POINTS = [8, 12, 16, 24] as const;

const BASE_STYLES: Readonly<Record<ElementType, ElementStyle>> = {
  bubble: {
    fontId: 'cairo', fontSizeUnit: 26_000, fontWeight: 700, lineHeight: 1.35,
    textAlign: 'center', color: '#111111', backgroundColor: '#FFFFFF',
    backgroundOpacity: 0.96, borderColor: '#111111', borderWidthUnit: 1_800,
    borderRadiusUnit: 50_000, paddingUnit: 9_000, shape: 'ellipse',
    tail: { enabled: true, angleMdeg: 25_000, lengthUnit: 80_000, widthUnit: 55_000 },
    autoFit: true, minFontSizeUnit: 16_000,
  },
  narration: {
    fontId: 'noto-sans-arabic', fontSizeUnit: 24_000, fontWeight: 600, lineHeight: 1.4,
    textAlign: 'center', color: '#111111', backgroundColor: '#FFFFFF',
    backgroundOpacity: 0.94, borderColor: '#111111', borderWidthUnit: 1_500,
    borderRadiusUnit: 18_000, paddingUnit: 10_000, shape: 'rounded_rect',
    autoFit: true, minFontSizeUnit: 15_000,
  },
  free_text: {
    fontId: 'cairo', fontSizeUnit: 26_000, fontWeight: 700, lineHeight: 1.3,
    textAlign: 'center', color: '#111111', backgroundColor: '#FFFFFF',
    backgroundOpacity: 0, borderColor: '#111111', borderWidthUnit: 0,
    borderRadiusUnit: 0, paddingUnit: 0, shape: 'none', autoFit: false,
  },
  sfx: {
    fontId: 'sfx-display-1', fontSizeUnit: 52_000, fontWeight: 900, lineHeight: 1.1,
    textAlign: 'center', color: '#FFFFFF', backgroundColor: '#B5231C',
    backgroundOpacity: 0, borderColor: '#111111', borderWidthUnit: 0,
    borderRadiusUnit: 0, paddingUnit: 0, shape: 'none', strokeColor: '#111111',
    strokeWidthUnit: 3_500, scaleX: 1, scaleY: 1, autoFit: false,
  },
};

const SHAPES: Readonly<Record<ElementType, readonly Shape[]>> = {
  bubble: ['ellipse', 'rounded_rect', 'rect', 'cloud'],
  narration: ['rect', 'rounded_rect'],
  free_text: ['none', 'rect', 'rounded_rect'],
  sfx: ['none', 'burst', 'impact'],
};

function record(value: unknown): Readonly<Record<string, unknown>> {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
    ? value as Readonly<Record<string, unknown>>
    : {};
}

function clamp(value: number, minimum: number, maximum: number): number {
  return Math.min(Math.max(value, minimum), maximum);
}

function finite(value: unknown, fallback: number, minimum: number, maximum: number): number {
  return typeof value === 'number' && Number.isFinite(value)
    ? clamp(value, minimum, maximum)
    : fallback;
}

function integer(value: unknown, fallback: number, minimum: number, maximum: number): number {
  return Number.isInteger(value) ? clamp(value as number, minimum, maximum) : fallback;
}

function color(value: unknown, fallback: string): string {
  return typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value) ? value : fallback;
}

function enumValue<T extends string>(value: unknown, allowed: readonly T[], fallback: T): T {
  return typeof value === 'string' && allowed.includes(value as T) ? value as T : fallback;
}

function normalizeShadow(candidate: unknown): ShadowStyle | null {
  if (candidate === null) return null;
  const value = record(candidate);
  if (Object.keys(value).length === 0) return null;
  return {
    xUnit: integer(value.xUnit, 0, -50_000, 50_000),
    yUnit: integer(value.yUnit, 0, -50_000, 50_000),
    blurUnit: integer(value.blurUnit, 0, 0, 50_000),
    color: color(value.color, '#000000'),
    opacity: finite(value.opacity, 0.75, 0, 1),
  };
}

function normalizeTail(candidate: unknown, fallback: TailStyle | null): TailStyle | null {
  if (candidate === null) return null;
  const value = record(candidate);
  if (Object.keys(value).length === 0) return fallback;
  return {
    enabled: typeof value.enabled === 'boolean' ? value.enabled : fallback?.enabled ?? true,
    angleMdeg: integer(value.angleMdeg, fallback?.angleMdeg ?? 25_000, -360_000, 360_000),
    lengthUnit: integer(value.lengthUnit, fallback?.lengthUnit ?? 80_000, 0, 300_000),
    widthUnit: integer(value.widthUnit, fallback?.widthUnit ?? 55_000, 0, 200_000),
  };
}

function normalizeBurst(candidate: unknown): BurstStyle | null {
  if (candidate === null) return null;
  const value = record(candidate);
  if (Object.keys(value).length === 0) return null;
  const points = integer(value.points, 12, 8, 24);
  return {
    points: (BURST_POINTS.includes(points as BurstStyle['points']) ? points : 12) as BurstStyle['points'],
    depth: finite(value.depth, 0.35, 0, 1),
  };
}

export function normalizeElementStyle(elementType: ElementType, candidate: unknown): ElementStyle {
  const base = BASE_STYLES[elementType];
  const value = record(candidate);
  const fontId = enumValue(value.fontId, Object.keys(FONT_FAMILIES) as FontId[], base.fontId);
  const fontWeight = integer(value.fontWeight, base.fontWeight, 400, 900);
  const shape = enumValue(value.shape, SHAPES[elementType], base.shape);

  return {
    fontId,
    fontSizeUnit: integer(value.fontSizeUnit, base.fontSizeUnit, 1_000, 200_000),
    fontWeight: (FONT_WEIGHTS.includes(fontWeight as ElementStyle['fontWeight'])
      ? fontWeight : base.fontWeight) as ElementStyle['fontWeight'],
    lineHeight: finite(value.lineHeight, base.lineHeight, 1, 2.5),
    textAlign: enumValue(value.textAlign, TEXT_ALIGNMENTS, base.textAlign) as TextAlignment,
    color: color(value.color, base.color),
    backgroundColor: color(value.backgroundColor, base.backgroundColor),
    backgroundOpacity: finite(value.backgroundOpacity, base.backgroundOpacity, 0, 1),
    borderColor: color(value.borderColor, base.borderColor),
    borderWidthUnit: integer(value.borderWidthUnit, base.borderWidthUnit, 0, 50_000),
    borderRadiusUnit: integer(value.borderRadiusUnit, base.borderRadiusUnit, 0, 500_000),
    paddingUnit: integer(value.paddingUnit, base.paddingUnit, 0, 100_000),
    shape,
    ...(elementType === 'sfx' ? {
      strokeColor: color(value.strokeColor, base.strokeColor ?? '#111111'),
      strokeWidthUnit: integer(value.strokeWidthUnit, base.strokeWidthUnit ?? 0, 0, 50_000),
      scaleX: finite(value.scaleX, base.scaleX ?? 1, 0.5, 2),
      scaleY: finite(value.scaleY, base.scaleY ?? 1, 0.5, 2),
      burst: normalizeBurst(value.burst),
    } : {}),
    shadow: normalizeShadow(value.shadow),
    ...(elementType === 'bubble' ? { tail: normalizeTail(value.tail, base.tail ?? null) } : {}),
    autoFit: typeof value.autoFit === 'boolean' ? value.autoFit : base.autoFit ?? false,
    ...(value.minFontSizeUnit !== undefined || base.minFontSizeUnit !== undefined
      ? { minFontSizeUnit: integer(value.minFontSizeUnit, base.minFontSizeUnit ?? 1_000, 1_000, 100_000) }
      : {}),
  };
}

function rgba(hex: string, opacity: number): string {
  const normalized = color(hex, '#000000').slice(1);
  const red = Number.parseInt(normalized.slice(0, 2), 16);
  const green = Number.parseInt(normalized.slice(2, 4), 16);
  const blue = Number.parseInt(normalized.slice(4, 6), 16);
  return `rgba(${red}, ${green}, ${blue}, ${clamp(opacity, 0, 1)})`;
}

function createTextNode(element: OverlayElement, style: ElementStyle, imageWidth: number): HTMLParagraphElement {
  const text = document.createElement('p');
  text.className = 'mol-element-text';
  text.textContent = element.content;
  text.lang = element.target_lang;
  text.dir = element.target_lang.toLowerCase().startsWith('ar') ? 'rtl' : 'auto';
  text.style.fontFamily = FONT_FAMILIES[style.fontId];
  text.style.fontSize = `${unitToPixels(style.fontSizeUnit, imageWidth)}px`;
  text.style.fontWeight = String(style.fontWeight);
  text.style.lineHeight = String(style.lineHeight);
  text.style.textAlign = style.textAlign;
  text.style.color = style.color;
  text.style.padding = `${unitToPixels(style.paddingUnit, imageWidth)}px`;

  if (style.strokeColor !== undefined && (style.strokeWidthUnit ?? 0) > 0) {
    text.style.webkitTextStrokeColor = style.strokeColor;
    text.style.webkitTextStrokeWidth = `${unitToPixels(style.strokeWidthUnit ?? 0, imageWidth)}px`;
    text.style.paintOrder = 'stroke fill';
  }
  if (style.shadow !== undefined && style.shadow !== null) {
    text.style.textShadow = `${unitToPixels(style.shadow.xUnit, imageWidth)}px ${unitToPixels(style.shadow.yUnit, imageWidth)}px ${unitToPixels(style.shadow.blurUnit, imageWidth)}px ${rgba(style.shadow.color, style.shadow.opacity)}`;
  }
  return text;
}

export function createOverlayNode(element: OverlayElement, imageWidth: number, selected = false): HTMLElement {
  const geometry = toCssGeometry(element);
  const style = normalizeElementStyle(element.element_type, element.style);
  const node = document.createElement('article');
  node.className = `mol-overlay-element mol-overlay-element--${element.element_type}`;
  node.dataset.elementId = String(element.id);
  node.dataset.elementType = element.element_type;
  node.dataset.selected = String(selected);
  node.dataset.testid = `stage-element-${element.id}`;
  node.setAttribute('aria-label', `عنصر ${element.element_type}`);
  node.style.left = geometry.left;
  node.style.top = geometry.top;
  node.style.width = geometry.width;
  node.style.height = geometry.height;
  node.style.transform = `rotate(${geometry.rotation}) scale(${style.scaleX ?? 1}, ${style.scaleY ?? 1})`;
  node.style.zIndex = geometry.zIndex;

  const shape = createShapeLayer(element, style, imageWidth);
  if (shape !== null) node.append(shape);
  node.append(createTextNode(element, style, imageWidth));
  return node;
}

function updateOverlayNode(current: HTMLElement, next: HTMLElement): void {
  const nextAttributeNames = new Set(next.getAttributeNames());
  for (const name of current.getAttributeNames()) {
    if (!nextAttributeNames.has(name)) current.removeAttribute(name);
  }
  for (const name of nextAttributeNames) {
    const value = next.getAttribute(name);
    if (value !== null) current.setAttribute(name, value);
  }
  current.replaceChildren(...next.childNodes);
}

export class OverlayRenderer {
  readonly #layer: HTMLElement;
  readonly #frame: HTMLElement;
  readonly #image: HTMLImageElement;
  readonly #resizeObserver: ResizeObserver;
  #elements: readonly OverlayElement[];
  #selectedId: number | null;

  constructor(layer: HTMLElement, frame: HTMLElement, image: HTMLImageElement, elements: readonly OverlayElement[], selectedId: number | null = null) {
    this.#layer = layer;
    this.#frame = frame;
    this.#image = image;
    this.#elements = elements;
    this.#selectedId = selectedId;
    this.#resizeObserver = new ResizeObserver(() => this.render());
  }

  mount(): void {
    this.#image.addEventListener('load', this.render);
    this.#resizeObserver.observe(this.#frame);
    this.render();
  }

  destroy(): void {
    this.#image.removeEventListener('load', this.render);
    this.#resizeObserver.disconnect();
    this.#layer.replaceChildren();
  }

  setElements(elements: readonly OverlayElement[], selectedId: number | null = this.#selectedId): void {
    this.#elements = elements;
    this.#selectedId = selectedId;
    this.render();
  }

  setSelectedId(selectedId: number | null): void {
    this.#selectedId = selectedId;
    this.render();
  }

  setTranslationVisible(visible: boolean): void {
    this.#layer.hidden = !visible;
  }

  readonly render = (): void => {
    const imageWidth = this.#image.clientWidth || this.#frame.clientWidth;
    if (imageWidth <= 0) return;

    // Moveable keeps a live reference to the selected outer element. Preserve
    // that node while refreshing its safe DOM/SVG contents so a selection does
    // not become detached whenever its data or selected state changes.
    const existingById = new Map<string, HTMLElement>();
    for (const child of Array.from(this.#layer.children)) {
      if (child instanceof HTMLElement && child.dataset.elementId !== undefined) {
        existingById.set(child.dataset.elementId, child);
      }
    }

    const retained = new Set<HTMLElement>();
    this.#elements.forEach((element, index) => {
      const next = createOverlayNode(element, imageWidth, element.id === this.#selectedId);
      const current = existingById.get(String(element.id));
      const node = current ?? next;
      if (current !== undefined) updateOverlayNode(current, next);

      const nodeAtIndex = this.#layer.children.item(index);
      if (nodeAtIndex !== node) this.#layer.insertBefore(node, nodeAtIndex);
      retained.add(node);
    });

    for (const child of Array.from(this.#layer.children)) {
      if (child instanceof HTMLElement && !retained.has(child)) child.remove();
    }
  };
}
