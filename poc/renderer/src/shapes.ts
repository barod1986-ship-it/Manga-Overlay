import type { ElementStyle, OverlayElement, Shape } from './domain';
import { unitToPixels } from './geometry';

const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

function createSvgNode<K extends keyof SVGElementTagNameMap>(name: K): SVGElementTagNameMap[K] {
  return document.createElementNS(SVG_NAMESPACE, name);
}

function clamp(value: number, minimum: number, maximum: number): number {
  return Math.min(Math.max(value, minimum), maximum);
}

function createBurstPoints(pointCount: number, depth: number): string {
  const center = 500;
  const innerRadius = 480 - clamp(depth, 0, 1) * 260;
  const points: string[] = [];
  for (let index = 0; index < pointCount * 2; index += 1) {
    const angle = (Math.PI * 2 * index) / (pointCount * 2) - Math.PI / 2;
    const radius = index % 2 === 0 ? 480 : innerRadius;
    points.push(`${center + Math.cos(angle) * radius},${center + Math.sin(angle) * radius}`);
  }
  return points.join(' ');
}

function appendPrimaryShape(
  svg: SVGSVGElement,
  shape: Shape,
  style: ElementStyle,
  element: OverlayElement,
): void {
  switch (shape) {
    case 'ellipse': {
      const ellipse = createSvgNode('ellipse');
      ellipse.setAttribute('cx', '500');
      ellipse.setAttribute('cy', '475');
      ellipse.setAttribute('rx', '475');
      ellipse.setAttribute('ry', '445');
      svg.append(ellipse);
      break;
    }
    case 'rounded_rect':
    case 'rect': {
      const rectangle = createSvgNode('rect');
      rectangle.setAttribute('x', '20');
      rectangle.setAttribute('y', '20');
      rectangle.setAttribute('width', '960');
      rectangle.setAttribute('height', '960');
      const relativeRadius = element.w_unit > 0
        ? clamp((style.borderRadiusUnit / element.w_unit) * 1_000, 0, 500)
        : 0;
      rectangle.setAttribute('rx', shape === 'rounded_rect' ? String(relativeRadius) : '0');
      svg.append(rectangle);
      break;
    }
    case 'cloud': {
      const cloud = createSvgNode('path');
      cloud.setAttribute(
        'd',
        'M95 580 C20 430 130 300 265 315 C265 150 460 100 545 225 C650 105 855 185 840 350 C980 360 1010 555 890 625 C920 790 735 890 625 790 C520 930 295 860 285 720 C165 745 70 685 95 580 Z',
      );
      svg.append(cloud);
      break;
    }
    case 'burst':
    case 'impact': {
      const burst = createSvgNode('polygon');
      const pointCount = style.burst?.points ?? (shape === 'impact' ? 16 : 12);
      burst.setAttribute('points', createBurstPoints(pointCount, style.burst?.depth ?? 0.35));
      svg.append(burst);
      break;
    }
    case 'none':
      break;
  }
}

function appendTail(svg: SVGSVGElement, style: ElementStyle, element: OverlayElement): void {
  if (element.element_type !== 'bubble' || style.tail === undefined || style.tail === null || !style.tail.enabled) {
    return;
  }
  const length = element.h_unit > 0
    ? clamp((style.tail.lengthUnit / element.h_unit) * 1_000, 0, 700)
    : 0;
  const width = element.w_unit > 0
    ? clamp((style.tail.widthUnit / element.w_unit) * 1_000, 0, 480)
    : 0;
  if (length <= 0 || width <= 0) return;

  const tail = createSvgNode('path');
  tail.dataset.molTail = 'true';
  tail.setAttribute(
    'd',
    `M ${500 - width / 2} 825 Q 500 ${880 + length * 0.35} 500 ${880 + length} Q 500 ${880 + length * 0.45} ${500 + width / 2} 825 Z`,
  );
  tail.setAttribute('transform', `rotate(${style.tail.angleMdeg / 1_000} 500 500)`);
  svg.append(tail);
}

export function createShapeLayer(
  element: OverlayElement,
  style: ElementStyle,
  imageWidth: number,
): SVGSVGElement | null {
  if (style.shape === 'none') return null;

  const svg = createSvgNode('svg');
  svg.classList.add('mol-element-shape');
  svg.setAttribute('viewBox', '0 0 1000 1000');
  svg.setAttribute('preserveAspectRatio', 'none');
  svg.setAttribute('aria-hidden', 'true');
  appendPrimaryShape(svg, style.shape, style, element);
  appendTail(svg, style, element);

  const shapeNodes = Array.from(svg.querySelectorAll<SVGGeometryElement>('ellipse, rect, path, polygon'));
  const strokeWidth = unitToPixels(style.borderWidthUnit, imageWidth);
  for (const node of shapeNodes) {
    node.setAttribute('fill', style.backgroundColor);
    node.setAttribute('fill-opacity', String(style.backgroundOpacity));
    node.setAttribute('stroke', style.borderColor);
    node.setAttribute('stroke-width', String(strokeWidth));
    node.setAttribute('vector-effect', 'non-scaling-stroke');
    node.setAttribute('stroke-linejoin', 'round');
  }
  return svg;
}
