import type { OverlayElement } from './domain';
import { toCssGeometry, unitToPixels } from './geometry';
import { createShapeLayer } from './shapes';

function fontFamily(fontId: OverlayElement['style']['fontId']): string {
  const families: Record<OverlayElement['style']['fontId'], string> = {
    'noto-sans-arabic': '"Noto Sans Arabic", "Segoe UI", sans-serif',
    cairo: 'Cairo, "Noto Sans Arabic", "Segoe UI", sans-serif',
    tajawal: 'Tajawal, "Noto Sans Arabic", "Segoe UI", sans-serif',
    'noto-kufi-arabic': '"Noto Kufi Arabic", "Segoe UI", sans-serif',
    'sfx-display-1': 'Tajawal, "Noto Sans Arabic", "Segoe UI", sans-serif',
  };

  return families[fontId];
}

function createTextNode(element: OverlayElement, imageWidth: number): HTMLParagraphElement {
  const style = element.style;
  const text = document.createElement('p');
  text.className = 'mol-element-text';
  text.textContent = element.content;
  text.lang = element.target_lang;
  text.dir = 'rtl';
  text.style.fontFamily = fontFamily(style.fontId);
  text.style.fontSize = `${unitToPixels(style.fontSizeUnit, imageWidth)}px`;
  text.style.fontWeight = String(style.fontWeight);
  text.style.lineHeight = String(style.lineHeight);
  text.style.textAlign = style.textAlign;
  text.style.color = style.color;
  text.style.padding = `${unitToPixels(style.paddingUnit, imageWidth)}px`;

  if (style.strokeColor !== undefined && style.strokeWidthUnit !== undefined) {
    text.style.webkitTextStrokeColor = style.strokeColor;
    text.style.webkitTextStrokeWidth = `${unitToPixels(style.strokeWidthUnit, imageWidth)}px`;
    text.style.paintOrder = 'stroke fill';
  }

  if (style.shadow !== undefined) {
    const shadow = style.shadow;
    text.style.textShadow = [
      unitToPixels(shadow.xUnit, imageWidth),
      unitToPixels(shadow.yUnit, imageWidth),
      unitToPixels(shadow.blurUnit, imageWidth),
      `${shadow.color}${Math.round(shadow.opacity * 255).toString(16).padStart(2, '0')}`,
    ].map((value, index) => (index < 3 ? `${value}px` : value)).join(' ');
  }

  return text;
}

export function createOverlayNode(element: OverlayElement, imageWidth: number): HTMLElement {
  const geometry = toCssGeometry(element);
  const node = document.createElement('article');
  node.className = `mol-overlay-element mol-overlay-element--${element.element_type}`;
  node.dataset.elementId = String(element.id);
  node.dataset.elementType = element.element_type;
  node.style.left = geometry.left;
  node.style.top = geometry.top;
  node.style.width = geometry.width;
  node.style.height = geometry.height;
  node.style.transform = `rotate(${geometry.rotation})`;
  node.style.zIndex = geometry.zIndex;

  const shape = createShapeLayer(element.element_type, element.style, imageWidth);
  if (shape !== null) {
    node.append(shape);
  }
  node.append(createTextNode(element, imageWidth));

  return node;
}

export class OverlayRenderer {
  readonly #layer: HTMLElement;
  readonly #frame: HTMLElement;
  readonly #image: HTMLImageElement;
  readonly #resizeObserver: ResizeObserver;
  #elements: readonly OverlayElement[];

  constructor(
    layer: HTMLElement,
    frame: HTMLElement,
    image: HTMLImageElement,
    elements: readonly OverlayElement[],
  ) {
    this.#layer = layer;
    this.#frame = frame;
    this.#image = image;
    this.#elements = elements;
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

  setElements(elements: readonly OverlayElement[]): void {
    this.#elements = elements;
    this.render();
  }

  setTranslationVisible(visible: boolean): void {
    this.#layer.hidden = !visible;
  }

  readonly render = (): void => {
    const imageWidth = this.#image.clientWidth || this.#frame.clientWidth;
    if (imageWidth <= 0) {
      return;
    }

    const fragment = document.createDocumentFragment();
    for (const element of this.#elements) {
      fragment.append(createOverlayNode(element, imageWidth));
    }
    this.#layer.replaceChildren(fragment);
  };
}
