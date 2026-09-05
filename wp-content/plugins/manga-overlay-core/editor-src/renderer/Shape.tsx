import type { ElementStyle, ElementType } from '../domain/types';
import { stylePixels } from '../domain/geometry';

interface Props { style: ElementStyle; type: ElementType; width: number; height: number; imageWidth: number }

/** All SVG is generated from allowlisted parameters. There are no user paths or markup. */
export function Shape({ style, type, width, height, imageWidth }: Props) {
  const border = Math.min(stylePixels(style.borderWidthUnit, imageWidth), width / 2, height / 2);
  const inset = border / 2;
  const rx = Math.max(.001, width / 2 - inset);
  const ry = Math.max(.001, height / 2 - inset);
  const shape = style.shape ?? 'none';
  const common = {
    fill: style.backgroundColor ?? '#FFFFFF', fillOpacity: style.backgroundOpacity ?? 0,
    stroke: style.borderColor ?? '#111111', strokeWidth: border, strokeLinejoin: 'round' as const,
  };
  const radial = (count: number, depth: number) => Array.from({ length: count * 2 }, (_, i) => {
    const angle = Math.PI * 2 * i / (count * 2) - Math.PI / 2;
    const r = i % 2 ? 1 - depth * .7 : 1;
    return `${width / 2 + Math.cos(angle) * rx * r},${height / 2 + Math.sin(angle) * ry * r}`;
  }).join(' ');
  const tail = type === 'bubble' && style.tail?.enabled ? style.tail : null;
  const angle = ((tail?.angleMdeg ?? 90_000) / 1_000) * Math.PI / 180;
  const cx = width / 2, cy = height / 2;
  const tx = cx + Math.cos(angle) * rx * .8, ty = cy + Math.sin(angle) * ry * .8;
  const tailWidth = stylePixels(tail?.widthUnit ?? 30_000, imageWidth) / 2;
  const length = stylePixels(tail?.lengthUnit ?? 50_000, imageWidth);
  const tailPoints = `${tx - Math.sin(angle) * tailWidth},${ty + Math.cos(angle) * tailWidth} ${cx + Math.cos(angle) * (rx + length)},${cy + Math.sin(angle) * (ry + length)} ${tx + Math.sin(angle) * tailWidth},${ty - Math.cos(angle) * tailWidth}`;
  return <svg className="mol-shape" viewBox={`0 0 ${width} ${height}`} aria-hidden="true">
    {tail && <polygon points={tailPoints} {...common} />}
    {shape === 'ellipse' && <ellipse cx={cx} cy={cy} rx={rx} ry={ry} {...common} />}
    {(shape === 'rect' || shape === 'rounded_rect') && <rect x={inset} y={inset} width={Math.max(0, width - border)} height={Math.max(0, height - border)} rx={shape === 'rounded_rect' ? Math.min(stylePixels(style.borderRadiusUnit, imageWidth), rx, ry) : 0} {...common} />}
    {shape === 'cloud' && <path d={cloudPath(width, height, inset)} {...common} />}
    {(shape === 'burst' || shape === 'impact') && <polygon points={radial(style.burst?.points ?? (shape === 'burst' ? 16 : 8), style.burst?.depth ?? .35)} {...common} />}
    {shape === 'none' && (style.backgroundOpacity ?? 0) > 0 && <rect x={inset} y={inset} width={Math.max(0, width - border)} height={Math.max(0, height - border)} {...common} />}
  </svg>;
}

function cloudPath(width: number, height: number, inset: number): string {
  const cx = width / 2, cy = height / 2;
  const rx = Math.max(.001, cx - inset), ry = Math.max(.001, cy - inset);
  const at = (angle: number, r: number) => `${cx + rx * r * Math.cos(angle)},${cy + ry * r * Math.sin(angle)}`;
  let path = `M ${at(0, .82)}`;
  for (let i = 0; i < 12; i += 1) {
    const angle = i * Math.PI / 6;
    path += ` Q ${at(angle + Math.PI / 12, 1.14)} ${at(angle + Math.PI / 6, .82)}`;
  }
  return path + ' Z';
}
