import { useLayoutEffect, useRef, type CSSProperties } from 'react';
import type { OverlayDraft } from '../domain/types';
import { FONT_FAMILIES } from '../domain/baseStyles';
import { stylePixels, toPixels, type ImageSize } from '../domain/geometry';
import { Shape } from './Shape';
import { fitFont } from './autoFit';

interface Props {
  element: OverlayDraft;
  size: ImageSize;
  selected?: boolean;
  editable?: boolean;
  onSelect?: (key: string) => void;
  onEditText?: (key: string) => void;
}
export function OverlayElement({ element, size, selected = false, editable = false, onSelect, onEditText }: Props) {
  const textRef = useRef<HTMLDivElement>(null);
  const box = toPixels(element, size);
  const style = element.style;
  const fontSize = stylePixels(style.fontSizeUnit ?? 26000, size.width);
  const padding = stylePixels(style.paddingUnit, size.width);
  const shadow = style.shadow;
  const fontFamily = FONT_FAMILIES[style.fontId ?? 'cairo'];

  useLayoutEffect(() => {
    let active = true;
    const fit = () => {
      const node = textRef.current;
      if (!active || !node) return;
      node.style.fontSize = `${fontSize}px`;
      if (style.autoFit) {
        const fits = (candidate: number) => {
          node.style.fontSize = `${candidate}px`;
          return node.scrollHeight <= node.clientHeight + .5 && node.scrollWidth <= node.clientWidth + .5;
        };
        node.style.fontSize = `${fitFont(fontSize, stylePixels(style.minFontSizeUnit ?? 1000, size.width), fits)}px`;
      }
      node.dataset.fittedFontUnit = String(parseFloat(node.style.fontSize) / size.width * 1000000);
      node.dataset.overflow = String(node.scrollHeight > node.clientHeight + .5 || node.scrollWidth > node.clientWidth + .5);
    };
    fit();
    void document.fonts.ready.then(fit);
    return () => { active = false; };
  }, [element.content, box.width, box.height, fontSize, padding, fontFamily, style.fontWeight, style.lineHeight, style.autoFit, style.minFontSizeUnit, size.width]);

  const outer: CSSProperties = {
    left: `${element.x_unit / 10000}%`, top: `${element.y_unit / 10000}%`,
    width: `${element.w_unit / 10000}%`, height: `${element.h_unit / 10000}%`,
    transform: `rotate(${box.rotation}deg)`, zIndex: element.z_index,
  };
  const textStyle: CSSProperties = {
    fontFamily, fontSize, fontWeight: style.fontWeight ?? 700,
    lineHeight: style.lineHeight ?? 1.35, color: style.color ?? '#111111',
    textAlign: style.textAlign ?? 'center', padding,
    WebkitTextStroke: `${stylePixels(style.strokeWidthUnit, size.width)}px ${style.strokeColor ?? '#111111'}`,
    paintOrder: 'stroke fill',
    textShadow: shadow ? `${stylePixels(shadow.xUnit, size.width)}px ${stylePixels(shadow.yUnit, size.width)}px ${stylePixels(shadow.blurUnit, size.width)}px ${rgba(shadow.color ?? '#111111', shadow.opacity ?? 1)}` : undefined,
    transform: element.element_type === 'sfx' ? `scale(${style.scaleX ?? 1}, ${style.scaleY ?? 1})` : undefined,
  };
  return <div
    className={`mol-element${selected ? ' mol-element-selected' : ''}${editable ? ' mol-element-editable' : ''}`}
    style={outer} data-element-key={element.key} data-element-type={element.element_type}
    role={editable ? 'button' : undefined} tabIndex={editable ? 0 : undefined}
    aria-label={editable ? `تحديد: ${element.content || 'عنصر فارغ'}` : undefined}
    aria-pressed={editable ? selected : undefined}
    onClick={editable ? () => onSelect?.(element.key) : undefined}
    onDoubleClick={editable ? () => onEditText?.(element.key) : undefined}
    onKeyDown={editable ? (event) => {
      if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); onSelect?.(element.key); }
    } : undefined}
  >
    <Shape style={style} type={element.element_type} width={box.width} height={box.height} imageWidth={size.width} />
    <div className="mol-element-text" dir="rtl" lang="ar" style={textStyle} ref={textRef}><span>{element.content}</span></div>
  </div>;
}

function rgba(color: string, opacity: number): string {
  const hex = color.slice(1);
  return `rgba(${parseInt(hex.slice(0, 2), 16)},${parseInt(hex.slice(2, 4), 16)},${parseInt(hex.slice(4, 6), 16)},${opacity})`;
}
