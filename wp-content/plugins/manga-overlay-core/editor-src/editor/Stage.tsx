import { useLayoutEffect, useRef, useState } from 'react';
import Moveable from 'react-moveable';
import { fromPixels, toPixels, type ImageSize, type PixelBox } from '../domain/geometry';
import type { Geometry } from '../domain/types';
import { OverlayElement } from '../renderer/OverlayElement';
import type { Page } from './state';
import type { WorkingElement } from './drafts';

interface Props {
  page: Page; elements: WorkingElement[]; selectedKey: string | null; preview: boolean; visible: boolean; zoom: number; canEdit: boolean;
  onSelect: (key: string | null) => void; onEditText: (key: string) => void; onTransform: (key: string, geometry: Geometry) => void;
}
interface Interaction { key: string; node: HTMLElement; box: PixelBox; size: ImageSize; z: number; css: string; changed: boolean }
export function Stage({ page, elements, selectedKey, preview, visible, zoom, canEdit, onSelect, onEditText, onTransform }: Props) {
  const viewport = useRef<HTMLDivElement>(null);
  const stage = useRef<HTMLDivElement>(null);
  const moveable = useRef<Moveable>(null);
  const interaction = useRef<Interaction | null>(null);
  const [target, setTarget] = useState<HTMLElement | null>(null);
  const [available, setAvailable] = useState(1);
  const [imageState, setImageState] = useState<'loading' | 'ready' | 'error'>('loading');
  const [imageAttempt, setImageAttempt] = useState(0);
  const selected = elements.find(element => element.key === selectedKey);
  const width = Math.min(860, available) * (preview ? 1 : zoom);
  const size = { width, height: width * page.natural_height / page.natural_width };
  const enabled = canEdit && !preview && visible && imageState === 'ready';
  useLayoutEffect(() => {
    const node = viewport.current!;
    const measure = () => setAvailable(Math.max(1, node.clientWidth - 32));
    const observer = new ResizeObserver(measure);
    observer.observe(node); measure();
    return () => observer.disconnect();
  }, []);
  function cancel() {
    const active = interaction.current;
    interaction.current = null;
    if (active) active.node.style.cssText = active.css;
    moveable.current?.stopDrag();
  }
  useLayoutEffect(() => {
    // A resize, selection change, overlay toggle or unmount cannot commit stale gesture pixels.
    setTarget(enabled && selectedKey ? stage.current?.querySelector<HTMLElement>(`[data-element-key="${CSS.escape(selectedKey)}"]`) ?? null : null);
    return cancel;
  }, [enabled, selectedKey, width, size.height]);
  useLayoutEffect(() => { if (!interaction.current) moveable.current?.updateRect(); }, [selected, target, width]);
  function begin() {
    if (!enabled || !selected || !target) return null;
    interaction.current = { key: selected.key, node: target, box: toPixels(selected, size), size, z: selected.z_index, css: target.style.cssText, changed: false };
    return interaction.current.box;
  }
  function draw(patch: Partial<PixelBox>) {
    const active = interaction.current;
    if (!active) return;
    active.box = { ...active.box, ...patch }; active.changed = true;
    const box = active.box;
    Object.assign(active.node.style, { left: `${box.x}px`, top: `${box.y}px`, width: `${box.width}px`, height: `${box.height}px`, transform: `rotate(${box.rotation}deg)` });
  }
  function finish(inputEvent?: Event) {
    const active = interaction.current;
    interaction.current = null;
    if (!active) return;
    active.node.style.cssText = active.css;
    if (!enabled || !active.changed || inputEvent?.type.endsWith('cancel')) { moveable.current?.updateRect(); return; }
    const geometry = fromPixels(active.box, active.size, active.z);
    // Restore normalized CSS even when clamping resolves to the same React value.
    Object.assign(active.node.style, { left: `${geometry.x_unit / 10000}%`, top: `${geometry.y_unit / 10000}%`, width: `${geometry.w_unit / 10000}%`, height: `${geometry.h_unit / 10000}%`, transform: `rotate(${geometry.rotation_mdeg / 1000}deg)` });
    onTransform(active.key, geometry);
  }
  return <div className={`mol-editor-viewport${enabled ? ' mol-editor-transforming' : ''}`} ref={viewport} aria-label="مساحة الصفحة">
    <div className="mol-editor-stage-wrap" style={{ width }}>
      <div className="mol-editor-page-caption"><span>الصفحة {page.page_index + 1}</span><span dir="ltr">{page.natural_width} × {page.natural_height}</span></div>
      {imageState === 'error' && <div className="mol-editor-message" role="alert"><p>تعذر تحميل صورة الصفحة.</p><button onClick={() => { setImageState('loading'); setImageAttempt(value => value + 1); }}>إعادة تحميل الصورة</button></div>}
      <div ref={stage} className="mol-editor-stage" data-page-id={page.id} style={{ width, height: size.height }}
        onClick={event => { if (!preview && (event.target === event.currentTarget || event.target instanceof HTMLImageElement)) onSelect(null); }}>
        <img key={imageAttempt} className="mol-page-image" src={page.image.url} srcSet={page.image.srcset ?? undefined} sizes="(max-width: 860px) 100vw, 860px"
          width={page.natural_width} height={page.natural_height} alt={page.image.alt || 'صفحة ' + (page.page_index + 1)} draggable={false} decoding="async"
          onLoad={() => setImageState('ready')} onError={() => setImageState('error')} />
        <div className="mol-overlay-layer" hidden={!visible || imageState !== 'ready'}>
          {elements.map(element => <OverlayElement key={element.key} element={element} size={size}
            selected={!preview && selectedKey === element.key} editable={!preview && visible}
            onSelect={onSelect} onEditText={onEditText} />)}
        </div>
        {enabled && <Moveable ref={moveable} target={target} container={stage.current} origin={false}
          draggable resizable rotatable pinchable={false} snappable={false} keepRatio={false} checkInput
          rotationPosition="top" throttleDrag={0} throttleResize={0} throttleRotate={.1}
          renderDirections={['nw', 'n', 'ne', 'w', 'e', 'sw', 's', 'se']}
          onDragStart={event => { const box = begin(); if (box) event.set([box.x, box.y]); else moveable.current?.stopDrag(); }}
          onDrag={event => draw({ x: event.beforeTranslate[0], y: event.beforeTranslate[1] })}
          onDragEnd={event => finish(event.inputEvent)}
          onResizeStart={event => { const box = begin(); if (box && event.dragStart) event.dragStart.set([box.x, box.y]); else if (!box) moveable.current?.stopDrag(); }}
          onResize={event => draw({ width: event.width, height: event.height, x: event.drag.beforeTranslate[0], y: event.drag.beforeTranslate[1] })}
          onResizeEnd={event => finish(event.inputEvent)}
          onRotateStart={event => { const box = begin(); if (box) event.set(box.rotation); else moveable.current?.stopDrag(); }}
          onRotate={event => draw({ rotation: event.beforeRotate })}
          onRotateEnd={event => finish(event.inputEvent)} />}
      </div>
    </div>
  </div>;
}
