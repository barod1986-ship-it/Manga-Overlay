import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import Moveable from 'react-moveable';
import type { Geometry, OverlayDraft } from '../domain/types';
import { fromPixels, toPixels, type ImageSize, type PixelBox } from '../domain/geometry';
import { OverlayElement } from '../renderer/OverlayElement';

interface Props {
  elements: OverlayDraft[];
  selectedKey: string | null;
  onSelect: (key: string | null) => void;
  onEditText: (key: string) => void;
  onTransform: (key: string, geometry: Geometry) => void;
  onInteractionEnd: () => void;
  preview: boolean;
  overlayVisible: boolean;
  zoom: number;
  onZoom: (zoom: number) => void;
  snapping: boolean;
}
export function Stage({ elements, selectedKey, onSelect, onEditText, onTransform, onInteractionEnd, preview, overlayVisible, zoom, onZoom, snapping }: Props) {
  const viewport = useRef<HTMLDivElement>(null);
  const stage = useRef<HTMLDivElement>(null);
  const moveable = useRef<Moveable>(null);
  const [availableWidth, setAvailableWidth] = useState(600);
  const [target, setTarget] = useState<HTMLElement | null>(null);
  const interaction = useRef<PixelBox | null>(null);
  const interactionChanged = useRef(false);
  const pointers = useRef(new Map<number, { x: number; y: number }>());
  const gesture = useRef({ distance: 0, zoom: 1, x: 0, y: 0, left: 0, top: 0 });
  const selected = elements.find(element => element.key === selectedKey);
  const width = Math.max(1, Math.min(720, availableWidth) * zoom);
  const size: ImageSize = { width, height: width * 1.5 };

  useLayoutEffect(() => {
    const node = viewport.current;
    if (!node) return;
    const measure = () => setAvailableWidth(Math.max(1, node.clientWidth - 40));
    const observer = new ResizeObserver(measure);
    observer.observe(node);
    measure();
    return () => observer.disconnect();
  }, []);
  useLayoutEffect(() => {
    setTarget(preview || !overlayVisible ? null : stage.current?.querySelector<HTMLElement>(`[data-element-key="${selectedKey}"]`) ?? null);
  }, [selectedKey, preview, overlayVisible, elements.length]);
  useEffect(() => { if (!interaction.current) moveable.current?.updateRect(); }, [size.width, selected, target]);

  const begin = () => {
    interactionChanged.current = false;
    interaction.current = selected ? toPixels(selected, size) : null;
    return interaction.current;
  };
  // Moveable owns transient pixels during a gesture. React receives normalized geometry once at end.
  const draw = (values: Partial<PixelBox>) => {
    if (!interaction.current || !target) return;
    const box = { ...interaction.current, ...values };
    interaction.current = box; interactionChanged.current = true;
    Object.assign(target.style, { left: `${box.x}px`, top: `${box.y}px`, width: `${box.width}px`, height: `${box.height}px`, transform: `rotate(${box.rotation}deg)` });
  };
  const finish = () => {
    if (selected && interaction.current && interactionChanged.current) {
      onTransform(selected.key, fromPixels(interaction.current, size, selected.z_index));
      onInteractionEnd();
    }
    interaction.current = null; interactionChanged.current = false;
  };
  const distance = () => { const [a, b] = [...pointers.current.values()]; return a && b ? Math.hypot(a.x - b.x, a.y - b.y) : 0; };
  const endPointer = (id: number) => {
    pointers.current.delete(id);
    gesture.current.distance = 0;
    const first = [...pointers.current.values()][0];
    if (first && viewport.current) Object.assign(gesture.current, { x: first.x, y: first.y, left: viewport.current.scrollLeft, top: viewport.current.scrollTop });
  };
  return <main className="mol-canvas-viewport" ref={viewport} aria-label="مساحة الصفحة"
    onPointerDown={event => {
      if (event.pointerType !== 'touch' || (event.target as HTMLElement).closest('.mol-element, .moveable-control, .moveable-line')) return;
      pointers.current.set(event.pointerId, { x: event.clientX, y: event.clientY });
      event.currentTarget.setPointerCapture(event.pointerId);
      if (pointers.current.size === 2) Object.assign(gesture.current, { distance: distance(), zoom });
      else Object.assign(gesture.current, { x: event.clientX, y: event.clientY, left: event.currentTarget.scrollLeft, top: event.currentTarget.scrollTop });
    }}
    onPointerMove={event => {
      if (!pointers.current.has(event.pointerId)) return;
      pointers.current.set(event.pointerId, { x: event.clientX, y: event.clientY });
      if (pointers.current.size === 2 && gesture.current.distance > 0) onZoom(Math.min(3, Math.max(.5, gesture.current.zoom * distance() / gesture.current.distance)));
      else if (pointers.current.size === 1) { event.currentTarget.scrollLeft = gesture.current.left + gesture.current.x - event.clientX; event.currentTarget.scrollTop = gesture.current.top + gesture.current.y - event.clientY; }
    }}
    onPointerUp={event => endPointer(event.pointerId)} onPointerCancel={event => endPointer(event.pointerId)}>
    <div className="mol-stage-wrap" style={{ width: size.width }}>
      <div className="mol-page-caption"><span>صفحة اختبار • 01</span><span dir="ltr">1200 × 1800</span></div>
      <div className="mol-stage" ref={stage} style={{ width: size.width, height: size.height }}
        onClick={event => { if (event.target === stage.current || event.target instanceof HTMLImageElement) onSelect(null); }}>
        <img className="mol-page-image" src="./reference-page.svg" width="1200" height="1800" alt="صفحة مرجعية مرسومة خصيصًا لاختبار مواضع الترجمة، تتضمن مباني ومسارًا وأشكال حوار" draggable="false" />
        {overlayVisible && <div className="mol-overlay-layer">
          {elements.map(element => <OverlayElement key={element.key} element={element} size={size}
            selected={!preview && selectedKey === element.key} editable={!preview}
            onSelect={onSelect} onEditText={onEditText} />)}
        </div>}
        {!preview && overlayVisible && <Moveable ref={moveable} target={target} container={stage.current}
          origin={false} draggable resizable rotatable snappable={snapping} pinchable={false}
          rotationPosition="top" keepRatio={false} throttleDrag={0} throttleResize={0} throttleRotate={.1}
          edge={false} renderDirections={['nw', 'n', 'ne', 'w', 'e', 'sw', 's', 'se']}
          verticalGuidelines={[0, width / 2, width]} horizontalGuidelines={[0, size.height / 2, size.height]}
          elementGuidelines={elements.filter(element => element.key !== selectedKey).map(element => `[data-element-key="${element.key}"]`)}
          snapDirections={{ top: true, left: true, bottom: true, right: true, center: true, middle: true }}
          elementSnapDirections={{ top: true, left: true, bottom: true, right: true, center: true, middle: true }}
          snapThreshold={5} isDisplaySnapDigit={false}
          onDragStart={event => { const box = begin(); if (box) event.set([box.x, box.y]); }}
          onDrag={event => draw({ x: event.beforeTranslate[0], y: event.beforeTranslate[1] })}
          onDragEnd={finish}
          onResizeStart={event => { const box = begin(); if (box && event.dragStart) event.dragStart.set([box.x, box.y]); }}
          onResize={event => draw({ width: event.width, height: event.height, x: event.drag.beforeTranslate[0], y: event.drag.beforeTranslate[1] })}
          onResizeEnd={finish}
          onRotateStart={event => { const box = begin(); if (box) event.set(box.rotation); }}
          onRotate={event => draw({ rotation: event.beforeRotate })}
          onRotateEnd={finish}
        />}
      </div>
    </div>
  </main>;
}
