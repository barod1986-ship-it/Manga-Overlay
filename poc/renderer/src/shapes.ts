import type { ElementStyle, ElementType, Shape } from './domain';
import { unitToPixels } from './geometry';

const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

function createSvgNode<K extends keyof SVGElementTagNameMap>(name: K): SVGElementTagNameMap[K] {
  return document.createElementNS(SVG_NAMESPACE, name);
}

function createBurstPoints(pointCount = 24): string {
  const center = 500;
  const points: string[] = [];

  for (let index = 0; index < pointCount; index += 1) {
    const angle = (Math.PI * 2 * index) / pointCount - Math.PI / 2;
    const radius = index % 2 === 0 ? 480 : 350;
    points.push(`${center + Math.cos(angle) * radius},${center + Math.sin(angle) * radius}`);
  }

  return points.join(' ');
}

function appendShape(svg: SVGSVGElement, shape: Shape): SVGGeometryElement | null {
  switch (shape) {
    case 'ellipse': {
      const ellipse = createSvgNode('ellipse');
      ellipse.setAttribute('cx', '500');
      ellipse.setAttribute('cy', '460');
      ellipse.setAttribute('rx', '475');
      ellipse.setAttribute('ry', '430');
      svg.append(ellipse);

      const tail = createSvgNode('path');
      tail.setAttribute('d', 'M 690 805 Q 785 955 885 990 Q 805 850 820 735 Z');
      svg.append(tail);
      return ellipse;
    }
    case 'rounded_rect':
    case 'rect': {
      const rectangle = createSvgNode('rect');
      rectangle.setAttribute('x', '20');
      rectangle.setAttribute('y', '20');
      rectangle.setAttribute('width', '960');
      rectangle.setAttribute('height', '960');
      rectangle.setAttribute('rx', shape === 'rounded_rect' ? '110' : '0');
      svg.append(rectangle);
      return rectangle;
    }
    case 'cloud': {
      const cloud = createSvgNode('path');
      cloud.setAttribute(
        'd',
        'M95 580 C20 430 130 300 265 315 C265 150 460 100 545 225 C650 105 855 185 840 350 C980 360 1010 555 890 625 C920 790 735 890 625 790 C520 930 295 860 285 720 C165 745 70 685 95 580 Z',
      );
      svg.append(cloud);
      return cloud;
    }
    case 'burst':
    case 'impact': {
      const burst = createSvgNode('polygon');
      burst.setAttribute('points', createBurstPoints(shape === 'impact' ? 32 : 24));
      svg.append(burst);
      return burst;
    }
    case 'none':
      return null;
  }
}

export function createShapeLayer(
  elementType: ElementType,
  style: ElementStyle,
  imageWidth: number,
): SVGSVGElement | null {
  if (style.shape === 'none') {
    return null;
  }

  const svg = createSvgNode('svg');
  svg.classList.add('mol-element-shape');
  svg.setAttribute('viewBox', '0 0 1000 1000');
  svg.setAttribute('preserveAspectRatio', 'none');
  svg.setAttribute('aria-hidden', 'true');

  const primaryShape = appendShape(svg, style.shape);
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

  if (elementType === 'bubble' && primaryShape !== null) {
    primaryShape.dataset.molPrimaryShape = 'true';
  }

  return svg;
}
