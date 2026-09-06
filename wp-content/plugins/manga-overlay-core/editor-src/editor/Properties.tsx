import { useEffect, useId, useState, type ReactNode, type RefObject } from 'react';
import { ELEMENT_LABELS } from '../domain/baseStyles';
import type { ElementStyle, Geometry } from '../domain/types';
import type { ElementChange, WorkingElement } from './drafts';

interface Props {
  element?: WorkingElement; editable: boolean; canDelete: boolean; textRef: RefObject<HTMLTextAreaElement | null>;
  onChange: (patch: ElementChange) => void; onDuplicate: () => void; onDelete: () => void; onClose: () => void;
}
export function Properties({ element, editable, canDelete, textRef, onChange, onDuplicate, onDelete, onClose }: Props) {
  const [expanded, setExpanded] = useState(false);
  const style = element?.style;
  const setStyle = (patch: Partial<ElementStyle>) => onChange({ style: { ...style, ...patch } });
  const shapeNames = { ellipse: 'بيضاوي', rounded_rect: 'مستطيل مستدير', rect: 'مستطيل', cloud: 'سحابة', none: 'بلا شكل', burst: 'انفجار', impact: 'صدمة' };
  const shapes = element?.element_type === 'bubble' ? ['ellipse', 'rounded_rect', 'rect', 'cloud'] as const
    : element?.element_type === 'narration' ? ['rect', 'rounded_rect'] as const
      : element?.element_type === 'sfx' ? ['none', 'burst', 'impact'] as const : ['none', 'rect', 'rounded_rect'] as const;
  const geometry = (patch: Partial<Geometry>) => onChange(patch);
  return <aside className={`mol-editor-properties${expanded ? ' mol-editor-sheet-expanded' : ''}`} aria-label="خصائص العنصر">
    <div className="mol-editor-panel-title"><h2>خصائص العنصر</h2><button className="mol-editor-mobile" onClick={() => setExpanded(value => !value)} aria-expanded={expanded}>{expanded ? 'تصغير الخصائص' : 'توسيع الخصائص'}</button><button className="mol-editor-mobile" onClick={onClose}>إغلاق الخصائص</button></div>
    {!element || !style ? <p className="mol-editor-muted">حدد عنصرًا على الصفحة أو من قائمة الطبقات، أو أضف عنصر ترجمة.</p> : <>
      <p className="mol-editor-type">{ELEMENT_LABELS[element.element_type]}{element.source ? '' : ' · جديد في هذه الجلسة'}</p>
      <fieldset disabled={!editable}>
        <label htmlFor="mol-editor-content">النص العربي</label><textarea ref={textRef} id="mol-editor-content" value={element.content} readOnly={!editable} rows={4} maxLength={10000} dir="rtl"
          onChange={event => onChange({ content: event.target.value })} onFocus={() => { setExpanded(true); }} />
        <p className="mol-editor-muted">النص فوق الصورة الأصلية. لا تُرسل هذه التغييرات بعد.</p>
        <details open><summary>الخط والمحاذاة</summary><div className="mol-editor-fields">
          <Choice label="الخط" value={style.fontId ?? 'cairo'} onChange={value => setStyle({ fontId: value as ElementStyle['fontId'] })}>{[['cairo', 'القاهرة — Cairo'], ['noto-sans-arabic', 'Noto Sans Arabic'], ['tajawal', 'تجوال — Tajawal'], ['noto-kufi-arabic', 'Noto Kufi Arabic'], ['sfx-display-1', 'خط المؤثر التجريبي']].map(([value, text]) => <option key={value} value={value}>{text}</option>)}</Choice>
          <NumberField label="حجم الخط (% عرض الصفحة)" value={(style.fontSizeUnit ?? 26000) / 10000} min={.1} max={20} step={.1} onChange={value => setStyle({ fontSizeUnit: Math.round(value * 10000) })} />
          <Choice label="وزن الخط" value={String(style.fontWeight ?? 700)} onChange={value => setStyle({ fontWeight: Number(value) as ElementStyle['fontWeight'] })}>{[400, 500, 600, 700, 800, 900].map(value => <option key={value}>{value}</option>)}</Choice>
          <NumberField label="ارتفاع السطر" value={style.lineHeight ?? 1.35} min={1} max={2.5} step={.05} onChange={lineHeight => setStyle({ lineHeight })} />
          <Choice label="المحاذاة" value={style.textAlign ?? 'center'} onChange={value => setStyle({ textAlign: value as ElementStyle['textAlign'] })}><option value="start">بداية</option><option value="center">وسط</option><option value="end">نهاية</option></Choice>
          <Color label="لون النص" value={style.color ?? '#111111'} onChange={color => setStyle({ color })} />
          <Check label="ملاءمة النص تلقائيًا" checked={style.autoFit ?? false} onChange={autoFit => setStyle({ autoFit })} />
          {style.autoFit && <NumberField label="أقل حجم (% عرض الصفحة)" value={(style.minFontSizeUnit ?? 1000) / 10000} min={.1} max={10} step={.1} onChange={value => setStyle({ minFontSizeUnit: Math.round(value * 10000) })} />}
        </div></details>
        <details><summary>الشكل والخلفية</summary><div className="mol-editor-fields">
          <Choice label="الشكل" value={style.shape ?? shapes[0]} onChange={value => setStyle({ shape: value as ElementStyle['shape'] })}>{shapes.map(value => <option key={value} value={value}>{shapeNames[value]}</option>)}</Choice>
          <Color label="لون الخلفية" value={style.backgroundColor ?? '#FFFFFF'} onChange={backgroundColor => setStyle({ backgroundColor })} />
          <NumberField label="عتامة الخلفية" value={style.backgroundOpacity ?? 0} min={0} max={1} step={.05} onChange={backgroundOpacity => setStyle({ backgroundOpacity })} />
          <Color label="لون الحد" value={style.borderColor ?? '#111111'} onChange={borderColor => setStyle({ borderColor })} />
          <NumberField label="الحد (% عرض الصفحة)" value={(style.borderWidthUnit ?? 0) / 10000} min={0} max={5} step={.05} onChange={value => setStyle({ borderWidthUnit: Math.round(value * 10000) })} />
          <NumberField label="استدارة الزوايا (% عرض الصفحة)" value={(style.borderRadiusUnit ?? 0) / 10000} min={0} max={50} step={.1} onChange={value => setStyle({ borderRadiusUnit: Math.round(value * 10000) })} />
          <NumberField label="الحشو (% عرض الصفحة)" value={(style.paddingUnit ?? 0) / 10000} min={0} max={10} step={.1} onChange={value => setStyle({ paddingUnit: Math.round(value * 10000) })} />
          {element.element_type === 'bubble' && <>
            <Check label="ذيل الفقاعة" checked={style.tail?.enabled ?? false} onChange={enabled => setStyle({ tail: { ...style.tail, enabled } })} />
            {style.tail?.enabled && <>
              <NumberField label="زاوية الذيل (°)" value={(style.tail.angleMdeg ?? 90000) / 1000} min={-360} max={360} step={1} onChange={value => setStyle({ tail: { ...style.tail, angleMdeg: Math.round(value * 1000) } })} />
              <NumberField label="طول الذيل (% عرض الصفحة)" value={(style.tail.lengthUnit ?? 50000) / 10000} min={0} max={30} step={.1} onChange={value => setStyle({ tail: { ...style.tail, lengthUnit: Math.round(value * 10000) } })} />
              <NumberField label="عرض الذيل (% عرض الصفحة)" value={(style.tail.widthUnit ?? 30000) / 10000} min={0} max={20} step={.1} onChange={value => setStyle({ tail: { ...style.tail, widthUnit: Math.round(value * 10000) } })} />
            </>}
          </>}
        </div></details>
        <details open><summary>الموضع والحجم</summary><div className="mol-editor-fields mol-editor-transform-fields">
          <NumberField label="X (%)" value={element.x_unit / 10000} min={0} max={100} step={.1} onChange={value => geometry({ x_unit: value * 10000 })} />
          <NumberField label="Y (%)" value={element.y_unit / 10000} min={0} max={100} step={.1} onChange={value => geometry({ y_unit: value * 10000 })} />
          <NumberField label="العرض (%)" value={element.w_unit / 10000} min={.0001} max={100} step={.1} onChange={value => geometry({ w_unit: value * 10000 })} />
          <NumberField label="الارتفاع (%)" value={element.h_unit / 10000} min={.0001} max={100} step={.1} onChange={value => geometry({ h_unit: value * 10000 })} />
          <NumberField label="الدوران (°)" value={element.rotation_mdeg / 1000} min={-360} max={360} step={1} onChange={value => geometry({ rotation_mdeg: value * 1000 })} />
          <NumberField label="ترتيب الطبقة" value={element.z_index} min={-1000} max={10000} step={1} onChange={z_index => geometry({ z_index })} />
          <div className="mol-editor-steps" dir="ltr">
            <button type="button" aria-label="نقل لليسار" onClick={() => geometry({ x_unit: element.x_unit - 1000 })}>←</button><button type="button" aria-label="نقل للأعلى" onClick={() => geometry({ y_unit: element.y_unit - 1000 })}>↑</button><button type="button" aria-label="نقل للأسفل" onClick={() => geometry({ y_unit: element.y_unit + 1000 })}>↓</button><button type="button" aria-label="نقل لليمين" onClick={() => geometry({ x_unit: element.x_unit + 1000 })}>→</button>
          </div>
          <div className="mol-editor-steps">
            <button type="button" onClick={() => geometry({ w_unit: element.w_unit + 1000 })}>زيادة العرض</button><button type="button" onClick={() => geometry({ w_unit: element.w_unit - 1000 })}>تقليل العرض</button>
            <button type="button" onClick={() => geometry({ h_unit: element.h_unit + 1000 })}>زيادة الارتفاع</button><button type="button" onClick={() => geometry({ h_unit: element.h_unit - 1000 })}>تقليل الارتفاع</button>
            <button type="button" onClick={() => geometry({ rotation_mdeg: element.rotation_mdeg + 1000 })}>دوران +1°</button><button type="button" onClick={() => geometry({ rotation_mdeg: element.rotation_mdeg - 1000 })}>دوران −1°</button>
          </div>
        </div></details>
        <details><summary>حدود النص والظل</summary><div className="mol-editor-fields">
          <Color label="لون حدود النص" value={style.strokeColor ?? '#111111'} onChange={strokeColor => setStyle({ strokeColor })} />
          <NumberField label="سماكة النص (% عرض الصفحة)" value={(style.strokeWidthUnit ?? 0) / 10000} min={0} max={5} step={.05} onChange={value => setStyle({ strokeWidthUnit: Math.round(value * 10000) })} />
          <Check label="ظل النص" checked={!!style.shadow} onChange={enabled => setStyle({ shadow: enabled ? { xUnit: 1000, yUnit: 1000, blurUnit: 2000, color: '#111111', opacity: .5 } : null })} />
          {style.shadow && <>
            <Color label="لون الظل" value={style.shadow.color ?? '#111111'} onChange={color => setStyle({ shadow: { ...style.shadow, color } })} />
            <NumberField label="الظل X (% عرض الصفحة)" value={(style.shadow.xUnit ?? 0) / 10000} min={-5} max={5} step={.1} onChange={value => setStyle({ shadow: { ...style.shadow, xUnit: Math.round(value * 10000) } })} />
            <NumberField label="الظل Y (% عرض الصفحة)" value={(style.shadow.yUnit ?? 0) / 10000} min={-5} max={5} step={.1} onChange={value => setStyle({ shadow: { ...style.shadow, yUnit: Math.round(value * 10000) } })} />
            <NumberField label="تمويه الظل (% عرض الصفحة)" value={(style.shadow.blurUnit ?? 0) / 10000} min={0} max={5} step={.1} onChange={value => setStyle({ shadow: { ...style.shadow, blurUnit: Math.round(value * 10000) } })} />
            <NumberField label="عتامة الظل" value={style.shadow.opacity ?? 1} min={0} max={1} step={.05} onChange={opacity => setStyle({ shadow: { ...style.shadow, opacity } })} />
          </>}
        </div></details>
        {element.element_type === 'sfx' && <details open><summary>المؤثر الصوتي</summary><div className="mol-editor-fields">
          <NumberField label="مقياس X" value={style.scaleX ?? 1} min={.5} max={2} step={.1} onChange={scaleX => setStyle({ scaleX })} />
          <NumberField label="مقياس Y" value={style.scaleY ?? 1} min={.5} max={2} step={.1} onChange={scaleY => setStyle({ scaleY })} />
          <Choice label="رؤوس الانفجار" value={String(style.burst?.points ?? (style.shape === 'impact' ? 8 : 16))} onChange={value => setStyle({ burst: { ...style.burst, points: Number(value) as 8 | 12 | 16 | 24 } })}>{[8, 12, 16, 24].map(value => <option key={value}>{value}</option>)}</Choice>
          <NumberField label="عمق الانفجار" value={style.burst?.depth ?? .35} min={0} max={1} step={.05} onChange={depth => setStyle({ burst: { ...style.burst, depth } })} />
        </div></details>}
        <div className="mol-editor-actions"><button type="button" onClick={onDuplicate}>نسخ العنصر</button><button type="button" disabled={!canDelete} onClick={onDelete}>حذف العنصر</button></div>
      </fieldset>
    </>}
  </aside>;
}
function NumberField({ label, value, min, max, step, onChange }: { label: string; value: number; min: number; max: number; step: number; onChange: (value: number) => void }) {
  const id = useId();
  const display = (number: number) => String(Number(number.toFixed(4)));
  const [draft, setDraft] = useState(display(value));
  useEffect(() => setDraft(display(value)), [value]);
  return <div className="mol-editor-field"><label htmlFor={id}>{label}</label><input id={id} type="number" dir="ltr" value={draft} min={min} max={max} step={step}
    onChange={event => { setDraft(event.currentTarget.value); const candidate = event.currentTarget.valueAsNumber; if (Number.isFinite(candidate) && candidate >= min && candidate <= max) onChange(candidate); }}
    onBlur={() => { const candidate = draft.trim() === '' ? value : Number(draft); const resolved = Number.isFinite(candidate) ? Math.max(min, Math.min(max, candidate)) : value; if (resolved !== value) onChange(resolved); setDraft(display(resolved)); }} /></div>;
}
function Choice({ label, value, onChange, children }: { label: string; value: string; onChange: (value: string) => void; children: ReactNode }) {
  const id = useId(); return <div className="mol-editor-field"><label htmlFor={id}>{label}</label><select id={id} value={value} onChange={event => onChange(event.target.value)}>{children}</select></div>;
}
function Color({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
  const id = useId(); return <div className="mol-editor-field"><label htmlFor={id}>{label}</label><input id={id} type="color" value={value} onChange={event => onChange(event.target.value.toUpperCase())} /></div>;
}
function Check({ label, checked, onChange }: { label: string; checked: boolean; onChange: (value: boolean) => void }) {
  const id = useId(); return <div className="mol-editor-check"><input id={id} type="checkbox" checked={checked} onChange={event => onChange(event.target.checked)} /><label htmlFor={id}>{label}</label></div>;
}
