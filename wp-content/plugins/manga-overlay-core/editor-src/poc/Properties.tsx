import { useEffect, useRef, useState, type RefObject } from 'react';
import type { ElementStyle, Geometry, OverlayDraft } from '../domain/types';
import { BASE_STYLES, ELEMENT_LABELS } from '../domain/baseStyles';
import { normalizeGeometry } from '../domain/geometry';

interface Props {
  element: OverlayDraft | undefined;
  onChange: (patch: Partial<OverlayDraft>) => void;
  onDelete: () => void;
  onDuplicate: () => void;
  onClose: () => void;
  textRef: RefObject<HTMLTextAreaElement | null>;
}
export function Properties({ element, onChange, onDelete, onDuplicate, onClose, textRef }: Props) {
  const sheet = useRef<HTMLElement>(null);
  if (!element) return <aside className="mol-properties mol-empty-properties"><h2>خصائص العنصر</h2><p>حدد عنصرًا على الصفحة أو أضف عنصر ترجمة من شريط الأدوات.</p></aside>;
  const style = element.style;
  const setStyle = (patch: Partial<ElementStyle>) => onChange({ style: { ...style, ...patch } });
  const geometry = (patch: Partial<Geometry>) => onChange(normalizeGeometry({ ...element, ...patch }));
  const shapes: NonNullable<ElementStyle['shape']>[] = element.element_type === 'bubble' ? ['ellipse', 'rounded_rect', 'rect', 'cloud']
    : element.element_type === 'narration' ? ['rect', 'rounded_rect']
    : element.element_type === 'sfx' ? ['none', 'burst', 'impact'] : ['none', 'rect', 'rounded_rect'];
  const shapeNames: Record<NonNullable<ElementStyle['shape']>, string> = { ellipse: 'بيضاوي', rounded_rect: 'مستطيل مستدير', rect: 'مستطيل', cloud: 'سحابة', none: 'بلا شكل', burst: 'انفجار', impact: 'صدمة' };
  return <aside className="mol-properties" ref={sheet} aria-label="خصائص العنصر">
    <div className="mol-panel-heading"><h2>{ELEMENT_LABELS[element.element_type]}</h2><button className="mol-mobile-only" onClick={onClose} aria-label="إغلاق الخصائص">إغلاق</button></div>
    <label className="mol-field">النص العربي
      <textarea ref={textRef} value={element.content} maxLength={10000} rows={4}
        onChange={event => onChange({ content: event.target.value })} placeholder="اكتب الترجمة هنا…"
        onFocus={() => window.setTimeout(() => textRef.current?.scrollIntoView({ block: 'nearest' }), 150)} />
    </label>
    <p className="mol-field-note">النص فوق الصورة؛ تبقى الصفحة الأصلية كما هي.</p>
    <details open><summary>الخط والمحاذاة</summary><div className="mol-details-body">
      <label className="mol-field">الخط<select value={style.fontId} onChange={event => setStyle({ fontId: event.target.value as ElementStyle['fontId'] })}>
        <option value="cairo">القاهرة — Cairo</option><option value="noto-sans-arabic">Noto Sans Arabic</option>
        <option value="tajawal">تجوال — Tajawal</option><option value="noto-kufi-arabic">Noto Kufi Arabic</option>
        <option value="sfx-display-1">خط المؤثر التجريبي</option>
      </select></label>
      <div className="mol-field-pair">
        <NumberField label="حجم الخط (% عرض الصفحة)" value={(style.fontSizeUnit ?? 26000) / 10000} min={.1} max={20} step={.1} onChange={value => setStyle({ fontSizeUnit: Math.round(value * 10000) })} />
        <label className="mol-field">الوزن<select value={style.fontWeight} onChange={event => setStyle({ fontWeight: Number(event.target.value) as ElementStyle['fontWeight'] })}>{[400, 500, 600, 700, 800, 900].map(weight => <option key={weight} value={weight}>{weight}</option>)}</select></label>
      </div>
      <div className="mol-field-pair">
        <NumberField label="ارتفاع السطر" value={style.lineHeight ?? 1.35} min={1} max={2.5} step={.05} onChange={value => setStyle({ lineHeight: value })} />
        <label className="mol-field">المحاذاة<select value={style.textAlign} onChange={event => setStyle({ textAlign: event.target.value as ElementStyle['textAlign'] })}><option value="start">بداية</option><option value="center">وسط</option><option value="end">نهاية</option></select></label>
      </div>
      <label className="mol-check"><input type="checkbox" checked={style.autoFit ?? false} onChange={event => setStyle({ autoFit: event.target.checked })} />ملاءمة النص تلقائيًا</label>
      {style.autoFit && <NumberField label="أقل حجم (% عرض الصفحة)" value={(style.minFontSizeUnit ?? 1000) / 10000} min={.1} max={10} step={.1} onChange={value => setStyle({ minFontSizeUnit: Math.round(value * 10000) })} />}
    </div></details>
    <details><summary>الشكل والألوان</summary><div className="mol-details-body">
      <label className="mol-field">الشكل<select value={style.shape} onChange={event => setStyle({ shape: event.target.value as ElementStyle['shape'] })}>{shapes.map(shape => <option key={shape} value={shape}>{shapeNames[shape]}</option>)}</select></label>
      <div className="mol-field-pair"><ColorField label="لون النص" value={style.color ?? '#111111'} onChange={color => setStyle({ color })} /><ColorField label="الخلفية" value={style.backgroundColor ?? '#FFFFFF'} onChange={backgroundColor => setStyle({ backgroundColor })} /></div>
      <NumberField label="عتامة الخلفية" value={style.backgroundOpacity ?? 0} min={0} max={1} step={.05} onChange={backgroundOpacity => setStyle({ backgroundOpacity })} />
      <div className="mol-field-pair"><ColorField label="لون الحد" value={style.borderColor ?? '#111111'} onChange={borderColor => setStyle({ borderColor })} /><NumberField label="الحد (% عرض الصفحة)" value={(style.borderWidthUnit ?? 0) / 10000} min={0} max={5} step={.1} onChange={value => setStyle({ borderWidthUnit: Math.round(value * 10000) })} /></div>
      <NumberField label="الحشو (% عرض الصفحة)" value={(style.paddingUnit ?? 0) / 10000} min={0} max={10} step={.1} onChange={value => setStyle({ paddingUnit: Math.round(value * 10000) })} />
      {element.element_type === 'bubble' && <label className="mol-check"><input type="checkbox" checked={style.tail?.enabled ?? false} onChange={event => setStyle({ tail: { enabled: event.target.checked, angleMdeg: 90000, lengthUnit: 40000, widthUnit: 25000 } })} />ذيل الفقاعة</label>}
    </div></details>
    <details open><summary>الموضع والحجم</summary><div className="mol-details-body">
      <div className="mol-field-pair"><NumberField label="X (%)" value={element.x_unit / 10000} min={0} max={100} step={.1} onChange={value => geometry({ x_unit: value * 10000 })} /><NumberField label="Y (%)" value={element.y_unit / 10000} min={0} max={100} step={.1} onChange={value => geometry({ y_unit: value * 10000 })} /></div>
      <div className="mol-field-pair"><NumberField label="العرض (%)" value={element.w_unit / 10000} min={.0001} max={100} step={.1} onChange={value => geometry({ w_unit: value * 10000 })} /><NumberField label="الارتفاع (%)" value={element.h_unit / 10000} min={.0001} max={100} step={.1} onChange={value => geometry({ h_unit: value * 10000 })} /></div>
      <div className="mol-field-pair"><NumberField label="الدوران (°)" value={element.rotation_mdeg / 1000} min={-360} max={360} step={1} onChange={value => geometry({ rotation_mdeg: value * 1000 })} /><NumberField label="ترتيب الطبقة" value={element.z_index} min={-1000} max={10000} step={1} onChange={z_index => geometry({ z_index })} /></div>
      <div className="mol-nudge" dir="ltr" aria-label="نقل دقيق"><button onClick={() => geometry({ x_unit: element.x_unit - 1000 })} aria-label="نقل لليسار">←</button><button onClick={() => geometry({ y_unit: element.y_unit - 1000 })} aria-label="نقل للأعلى">↑</button><button onClick={() => geometry({ y_unit: element.y_unit + 1000 })} aria-label="نقل للأسفل">↓</button><button onClick={() => geometry({ x_unit: element.x_unit + 1000 })} aria-label="نقل لليمين">→</button></div>
    </div></details>
    {element.element_type === 'sfx' && <details open><summary>المؤثر الصوتي</summary><div className="mol-details-body"><NumberField label="سماكة النص (% عرض الصفحة)" value={(style.strokeWidthUnit ?? 0) / 10000} min={0} max={5} step={.05} onChange={value => setStyle({ strokeWidthUnit: Math.round(value * 10000) })} /><div className="mol-field-pair"><NumberField label="مقياس X" value={style.scaleX ?? 1} min={.5} max={2} step={.1} onChange={scaleX => setStyle({ scaleX })} /><NumberField label="مقياس Y" value={style.scaleY ?? 1} min={.5} max={2} step={.1} onChange={scaleY => setStyle({ scaleY })} /></div><p className="mol-field-note">عند تداخل حدود الحروف العربية، جرّب سماكة أقل. اعتماد خط المؤثر يتطلب فحص الأجهزة.</p></div></details>}
    <div className="mol-panel-actions"><button onClick={() => onChange({ style: structuredClone(BASE_STYLES[element.element_type]) })}>النمط الأساسي</button><button onClick={onDuplicate}>نسخ العنصر</button><button className="mol-danger" onClick={onDelete}>حذف العنصر</button></div>
  </aside>;
}

function NumberField({ label, value, min, max, step, onChange }: { label: string; value: number; min: number; max: number; step: number; onChange: (value: number) => void }) {
  const [draft, setDraft] = useState(String(Number(value.toFixed(4))));
  useEffect(() => setDraft(String(Number(value.toFixed(4)))), [value]);
  return <label className="mol-field">{label}<input type="number" dir="ltr" value={draft} min={min} max={max} step={step} onChange={event => {
    setDraft(event.currentTarget.value);
    const candidate = event.currentTarget.valueAsNumber;
    if (Number.isFinite(candidate) && candidate >= min && candidate <= max) onChange(candidate);
  }} onBlur={() => {
    const candidate = draft.trim() === '' ? value : Number(draft);
    const resolved = Number.isFinite(candidate) ? Math.max(min, Math.min(max, candidate)) : value;
    onChange(resolved); setDraft(String(Number(resolved.toFixed(4))));
  }} /></label>;
}
function ColorField({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
  return <label className="mol-field">{label}<input type="color" value={value} onChange={event => onChange(event.target.value.toUpperCase())} /></label>;
}
