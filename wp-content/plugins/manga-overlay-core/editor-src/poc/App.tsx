import { useEffect, useRef, useState } from 'react';
import type { ElementType, OverlayDraft } from '../domain/types';
import { ELEMENT_LABELS } from '../domain/baseStyles';
import { clamp, normalizeGeometry } from '../domain/geometry';
import { Stage } from './Stage';
import { Properties } from './Properties';
import { createDraft, referenceElements } from './fixtures';

const types: ElementType[] = ['bubble', 'narration', 'free_text', 'sfx'];
const symbols: Record<ElementType, string> = { bubble: '◯', narration: '▭', free_text: 'ن', sfx: '!' };

export function App() {
  const [elements, setElements] = useState<OverlayDraft[]>(referenceElements);
  const [selectedKey, setSelectedKey] = useState<string | null>('bubble-reference');
  const [preview, setPreview] = useState(false);
  const [visible, setVisible] = useState(true);
  const [zoom, setZoom] = useState(1);
  const [snapping, setSnapping] = useState(true);
  const [panel, setPanel] = useState<'properties' | 'layers' | null>(null);
  const [changed, setChanged] = useState(false);
  const [notice, setNotice] = useState('');
  const [deleted, setDeleted] = useState<OverlayDraft | null>(null);
  const [keyboardInset, setKeyboardInset] = useState(0);
  const textRef = useRef<HTMLTextAreaElement>(null);
  const propertiesButton = useRef<HTMLButtonElement>(null);
  const selected = elements.find(element => element.key === selectedKey);

  function change(key: string, patch: Partial<OverlayDraft>) {
    setElements(previous => previous.map(element => element.key === key ? { ...element, ...patch } : element));
    setChanged(true);
  }
  function add(type: ElementType) {
    const draft = createDraft(type);
    draft.z_index = Math.min(10000, Math.max(0, ...elements.map(element => element.z_index)) + 1);
    setElements(previous => [...previous, draft]);
    setSelectedKey(draft.key); setPanel('properties'); setChanged(true); setVisible(true);
    window.setTimeout(() => textRef.current?.focus(), 0);
  }
  function duplicate() {
    if (!selected) return;
    const copy = structuredClone(selected);
    copy.key = crypto.randomUUID();
    Object.assign(copy, normalizeGeometry({ ...copy, x_unit: copy.x_unit + 15000, y_unit: copy.y_unit + 15000, z_index: copy.z_index + 1 }));
    setElements(previous => [...previous, copy]); setSelectedKey(copy.key); setChanged(true);
  }
  function remove() {
    if (!selected) return;
    setDeleted(selected); setElements(previous => previous.filter(element => element.key !== selected.key));
    setSelectedKey(null); setPanel(null); setChanged(true); setNotice('حُذف العنصر من التجربة. يمكنك التراجع.');
  }
  function editText(key: string) {
    setSelectedKey(key); setPanel('properties');
    window.setTimeout(() => textRef.current?.focus(), 0);
  }
  useEffect(() => {
    if (!deleted) return;
    const timer = window.setTimeout(() => { setDeleted(null); setNotice(''); }, 7000);
    return () => window.clearTimeout(timer);
  }, [deleted]);
  useEffect(() => {
    const keyboard = () => setKeyboardInset(window.visualViewport ? Math.max(0, window.innerHeight - window.visualViewport.height - window.visualViewport.offsetTop) : 0);
    window.visualViewport?.addEventListener('resize', keyboard);
    window.visualViewport?.addEventListener('scroll', keyboard);
    return () => { window.visualViewport?.removeEventListener('resize', keyboard); window.visualViewport?.removeEventListener('scroll', keyboard); };
  }, []);
  useEffect(() => {
    const leave = (event: BeforeUnloadEvent) => { if (changed) { event.preventDefault(); event.returnValue = ''; } };
    window.addEventListener('beforeunload', leave);
    return () => window.removeEventListener('beforeunload', leave);
  }, [changed]);
  useEffect(() => {
    const keydown = (event: KeyboardEvent) => {
      if (event.target instanceof HTMLElement && event.target.closest('input, textarea, select, button, [contenteditable="true"]')) return;
      if (preview || !visible) return;
      if (event.key === 'Escape') { setSelectedKey(null); setPanel(null); return; }
      if (!selected) return;
      if (event.key === 'Delete') { event.preventDefault(); remove(); }
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'd') { event.preventDefault(); duplicate(); }
      const step = event.shiftKey ? 10000 : 1000;
      const delta: Record<string, [number, number]> = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
      if (delta[event.key]) {
        event.preventDefault(); const [x, y] = delta[event.key];
        change(selected.key, normalizeGeometry({ ...selected, x_unit: selected.x_unit + x, y_unit: selected.y_unit + y }));
      }
    };
    window.addEventListener('keydown', keydown);
    return () => window.removeEventListener('keydown', keydown);
  });

  return <div className={`mol-app${preview ? ' mol-preview' : ''}`} style={{ '--mol-keyboard-inset': `${keyboardInset}px` } as React.CSSProperties}>
    <header className="mol-header">
      <div className="mol-brand"><strong dir="ltr">MOL<span> / </span></strong><div><h1>محرر الترجمة</h1><p>تجربة العرض والتحكم</p></div></div>
      <p className="mol-session-state" role="status"><span className={changed ? 'mol-dot mol-dot-changed' : 'mol-dot'} />{changed ? 'تغييرات في هذه الجلسة فقط' : 'نسخة تجريبية — دون حفظ على الخادم'}</p>
      <button className="mol-preview-button" aria-pressed={preview} onClick={() => { setPreview(!preview); setPanel(null); }}>{preview ? 'العودة للتحرير' : 'معاينة القراءة'}</button>
    </header>
    <div className="mol-controls">
      <div className="mol-control-group">
        <button aria-pressed={visible} onClick={() => setVisible(!visible)}>{visible ? 'إخفاء الترجمة' : 'إظهار الترجمة'}</button>
        {!preview && <label className="mol-check mol-snap"><input type="checkbox" checked={snapping} onChange={event => setSnapping(event.target.checked)} />محاذاة تلقائية</label>}
      </div>
      <div className="mol-zoom" dir="ltr"><button aria-label="تصغير الصفحة" onClick={() => setZoom(value => clamp(value - .25, .5, 3))}>−</button><button className="mol-zoom-value" onClick={() => setZoom(1)} aria-label="ملاءمة عرض الصفحة">{Math.round(zoom * 100)}%</button><button aria-label="تكبير الصفحة" onClick={() => setZoom(value => clamp(value + .25, .5, 3))}>+</button></div>
    </div>
    <div className={`mol-workspace${panel ? ` mol-open-${panel}` : ''}`}>
      {!preview && <Properties element={selected} onChange={patch => { if (selected) change(selected.key, patch); }} onDelete={remove} onDuplicate={duplicate}
        onClose={() => { setPanel(null); propertiesButton.current?.focus(); }} textRef={textRef} />}
      <Stage elements={elements} selectedKey={selectedKey} onSelect={key => setSelectedKey(key)} onEditText={editText}
        onTransform={change} onInteractionEnd={() => setChanged(true)} preview={preview} overlayVisible={visible}
        zoom={zoom} onZoom={setZoom} snapping={snapping} />
      {!preview && <aside className="mol-layers" aria-label="طبقات الصفحة">
        <div className="mol-panel-heading"><h2>الطبقات <span>{elements.length}</span></h2><button className="mol-mobile-only" onClick={() => setPanel(null)}>إغلاق</button></div>
        <p className="mol-field-note">العنصر الأعلى يظهر في المقدمة.</p>
        <ol>{[...elements].sort((a, b) => b.z_index - a.z_index).map(element => <li key={element.key}><button className={element.key === selectedKey ? 'mol-active-layer' : ''} aria-pressed={element.key === selectedKey} onClick={() => { setSelectedKey(element.key); setPanel('properties'); }}><span className="mol-layer-symbol" aria-hidden="true">{symbols[element.element_type]}</span><span><strong>{element.content || 'عنصر فارغ'}</strong><small>{ELEMENT_LABELS[element.element_type]}</small></span></button></li>)}</ol>
        <div className="mol-layer-help"><strong>أدوات دقيقة فوق الصورة</strong><p>حدد العنصر لتحريكه. انقر مرتين لتعديل النص. استخدم حقول الموضع والحجم للتحكم دون سحب.</p></div>
      </aside>}
    </div>
    {!preview && <nav className="mol-tools" aria-label="أدوات الترجمة">
      {types.map(type => <button key={type} onClick={() => add(type)} aria-label={`إضافة ${ELEMENT_LABELS[type]}`}><span aria-hidden="true">{symbols[type]}</span>{ELEMENT_LABELS[type]}</button>)}
      <span className="mol-tool-divider" />
      <button ref={propertiesButton} onClick={() => setPanel(panel === 'properties' ? null : 'properties')} disabled={!selected}><span aria-hidden="true">☷</span>الخصائص</button>
      <button className="mol-mobile-only" onClick={() => setPanel(panel === 'layers' ? null : 'layers')}><span aria-hidden="true">▤</span>الطبقات</button>
      <p className="mol-desktop-only">ملف اختبار • التغييرات مؤقتة وتُفقد عند إغلاق الصفحة</p>
    </nav>}
    {notice && <div className="mol-toast" role="status">{notice}{deleted && <button onClick={() => { setElements(previous => [...previous, deleted]); setSelectedKey(deleted.key); setDeleted(null); setNotice(''); }}>تراجع</button>}</div>}
  </div>;
}
