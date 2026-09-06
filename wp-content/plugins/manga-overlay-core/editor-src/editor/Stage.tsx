import { useLayoutEffect, useRef, useState } from 'react';
import { OverlayElement } from '../renderer/OverlayElement';
import { overlayView, type Page, type Element } from './state';

interface Props { page: Page; elements: Element[]; selectedId: number | null; preview: boolean; visible: boolean; zoom: number; onSelect: (id: number | null) => void }
export function Stage({ page, elements, selectedId, preview, visible, zoom, onSelect }: Props) {
  const viewport = useRef<HTMLDivElement>(null);
  const [available, setAvailable] = useState(1);
  const [imageState, setImageState] = useState<'loading' | 'ready' | 'error'>('loading');
  const [imageAttempt, setImageAttempt] = useState(0);
  useLayoutEffect(() => {
    const node = viewport.current!;
    const measure = () => setAvailable(Math.max(1, node.clientWidth - 32));
    const observer = new ResizeObserver(measure);
    observer.observe(node); measure();
    return () => observer.disconnect();
  }, []);
  const width = Math.min(860, available) * (preview ? 1 : zoom);
  const size = { width, height: width * page.natural_height / page.natural_width };
  return <div className="mol-editor-viewport" ref={viewport} aria-label="مساحة الصفحة">
    <div className="mol-editor-stage-wrap" style={{ width }}>
      <div className="mol-editor-page-caption"><span>الصفحة {page.page_index + 1}</span><span dir="ltr">{page.natural_width} × {page.natural_height}</span></div>
      {imageState === 'error' && <div className="mol-editor-message" role="alert"><p>تعذر تحميل صورة الصفحة.</p><button onClick={() => { setImageState('loading'); setImageAttempt(value => value + 1); }}>إعادة تحميل الصورة</button></div>}
      <div className="mol-editor-stage" data-page-id={page.id} style={{ width, height: size.height }}
        onClick={event => { if (event.target === event.currentTarget || event.target instanceof HTMLImageElement) onSelect(null); }}>
        <img key={imageAttempt} className="mol-page-image" src={page.image.url} srcSet={page.image.srcset ?? undefined} sizes="(max-width: 860px) 100vw, 860px"
          width={page.natural_width} height={page.natural_height} alt={page.image.alt || 'صفحة ' + (page.page_index + 1)} draggable={false} decoding="async"
          onLoad={() => setImageState('ready')} onError={() => setImageState('error')} />
        <div className="mol-overlay-layer" hidden={!visible || imageState !== 'ready'}>
          {elements.map(element => <OverlayElement key={element.id} element={overlayView(element)} size={size}
            selected={!preview && selectedId === element.id} editable={!preview && visible}
            onSelect={key => onSelect(Number(key))} />)}
        </div>
      </div>
    </div>
  </div>;
}
