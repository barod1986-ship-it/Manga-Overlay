import { ELEMENT_LABELS } from '../domain/baseStyles';
import type { Element } from './state';

const percent = (unit: number) => String(Number((unit / 10000).toFixed(4))) + '%';
export function Properties({ element, onClose }: { element?: Element; onClose: () => void }) {
  const style = element?.style;
  return <aside className="mol-editor-properties" aria-label="خصائص العنصر">
    <div className="mol-editor-panel-title"><h2>خصائص العنصر</h2><button className="mol-editor-mobile" onClick={onClose}>إغلاق الخصائص</button></div>
    {!element || !style ? <p className="mol-editor-muted">حدد عنصرًا على الصفحة أو من قائمة الطبقات لفحص النص وخصائصه.</p> : <>
      <p className="mol-editor-type">{ELEMENT_LABELS[element.element_type]}</p>
      <label htmlFor="mol-editor-content">النص العربي</label><textarea id="mol-editor-content" value={element.content} readOnly rows={5} dir="rtl" />
      <details open><summary>الخط والمظهر</summary><dl>
        <dt>الخط</dt><dd dir="auto">{style.fontId ?? 'cairo'}</dd>
        <dt>حجم الخط</dt><dd>{percent(style.fontSizeUnit ?? 26000)}</dd>
        <dt>وزن الخط</dt><dd>{style.fontWeight ?? 700}</dd>
        <dt>لون النص</dt><dd dir="ltr">{style.color ?? '#111111'}</dd>
        <dt>ملاءمة النص</dt><dd>{style.autoFit ? 'تلقائية' : 'حجم ثابت'}</dd>
      </dl></details>
      <details open><summary>الموضع والحجم</summary><dl dir="ltr">
        <dt>X</dt><dd>{percent(element.x_unit)}</dd><dt>Y</dt><dd>{percent(element.y_unit)}</dd>
        <dt>W</dt><dd>{percent(element.w_unit)}</dd><dt>H</dt><dd>{percent(element.h_unit)}</dd>
        <dt>الدوران</dt><dd>{(element.rotation_mdeg ?? 0) / 1000}°</dd>
        <dt>ترتيب الطبقة</dt><dd>{element.z_index ?? 0}</dd>
      </dl></details>
    </>}
  </aside>;
}
