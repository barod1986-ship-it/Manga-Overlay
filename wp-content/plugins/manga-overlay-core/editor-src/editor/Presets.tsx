import { useEffect, useId, useState } from 'react';
import type { ElementChange, WorkingElement } from './drafts';
import { EditorApi } from './api';
import type { Bootstrap } from './state';
import { presetStyle, SCOPE_LABELS, type Preset } from './presets';

interface Props {
  api: EditorApi; boot: Bootstrap; workId: number; element: WorkingElement; editable: boolean;
  presets: Preset[]; loading: boolean; loadError?: string; reload: () => void; onChange: (patch: ElementChange) => void;
}
export function Presets({ api, boot, workId, element, editable, presets, loading, loadError, reload, onChange }: Props) {
  const fieldId = useId();
  const [id, setId] = useState(0);
  const [name, setName] = useState('');
  const [scope, setScope] = useState<Preset['scope']>('personal');
  const [isDefault, setDefault] = useState(false);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');
  const [failed, setFailed] = useState(false);
  const selected = presets.find(item => item.id === id && item.element_type === element.element_type);
  const allowed = selected && (selected.scope === 'personal' ? selected.owner_user_id === boot.userId : selected.scope === 'work' ? boot.canManageWorkPresets : boot.canManageGlobalPresets);
  useEffect(() => { setName(selected?.name ?? ''); setDefault(selected?.is_default ?? false); }, [selected?.id, selected?.name, selected?.is_default]);
  async function mutate(action: 'create' | 'update' | 'delete') {
    if (busy || loading || loadError || action !== 'create' && !allowed) return;
    if (action === 'delete' && !window.confirm('حذف النمط المحفوظ؟ تبقى أنماط العناصر المطبّقة كما هي.')) return;
    setBusy(true); setMessage(''); setFailed(false);
    try {
      if (action === 'create') {
        const created = await api.createPreset({ name: name.trim(), scope, ...(scope === 'work' ? { work_id: workId } : {}), element_type: element.element_type, style: structuredClone(element.style), is_default: isDefault });
        setId(created.id); setMessage('حُفظ النمط.');
      } else if (action === 'update') {
        await api.updatePreset(id, { name: name.trim(), style: structuredClone(element.style), is_default: isDefault }); setMessage('حُدّث النمط من خصائص العنصر الحالية.');
      } else { await api.deletePreset(id); setId(0); setMessage('حُذف النمط المحفوظ.'); }
      reload();
    } catch (error) {
      setFailed(true); setMessage(error instanceof Error ? error.message : 'تعذر تأكيد العملية.');
    } finally { setBusy(false); }
  }
  return <section className="mol-editor-presets" aria-label="الأنماط المحفوظة">
    <h3>الأنماط المحفوظة</h3>
    {loadError ? <p role="alert">{loadError}</p> : loading ? <p role="status">جارٍ تحميل الأنماط…</p> : null}
    <button type="button" disabled={busy || loading} onClick={() => { setFailed(false); setMessage(''); reload(); }}>تحديث قائمة الأنماط</button>
    <fieldset disabled={busy || loading || !!loadError}>
      <label htmlFor={fieldId + '-preset'}>النمط</label><select id={fieldId + '-preset'} value={selected?.id ?? 0} onChange={event => { setId(Number(event.target.value)); setMessage(''); }}>
        <option value={0}>النمط الأساسي المدمج</option>
        {presets.filter(item => item.element_type === element.element_type).map(item => <option key={item.id} value={item.id}>{item.name} · {SCOPE_LABELS[item.scope]}{item.is_default ? ' · افتراضي' : ''}</option>)}
      </select>
      <button type="button" disabled={!editable} onClick={() => onChange({ style: presetStyle(element.element_type, selected) })}>تطبيق النمط</button>
      <p className="mol-editor-muted">يغيّر النمط المظهر فقط، ويُحفظ ضمن تغييرات العنصر المعتادة.</p>
      <details><summary>حفظ وإدارة الأنماط</summary>
        <label htmlFor={fieldId + '-name'}>اسم النمط</label><input id={fieldId + '-name'} maxLength={100} value={name} onChange={event => setName(event.target.value)} />
        <label htmlFor={fieldId + '-scope'}>نطاق النمط الجديد</label><select id={fieldId + '-scope'} value={scope} onChange={event => setScope(event.target.value as Preset['scope'])}>
          <option value="personal">شخصي</option>{boot.canManageWorkPresets && <option value="work">لهذا العمل</option>}{boot.canManageGlobalPresets && <option value="global">عام</option>}
        </select>
        <label className="mol-editor-check"><input type="checkbox" checked={isDefault} onChange={event => setDefault(event.target.checked)} />افتراضي لهذا النوع والنطاق</label>
        <button type="button" disabled={!name.trim() || failed} onClick={() => void mutate('create')}>حفظ كنمط جديد</button>
        {allowed && <><button type="button" disabled={!name.trim() || failed} onClick={() => void mutate('update')}>تحديث النمط المحدد من العنصر</button><button type="button" disabled={failed} onClick={() => void mutate('delete')}>حذف النمط المحدد</button></>}
      </details>
    </fieldset>
    {message && <p role={failed ? 'alert' : 'status'}>{message}{failed ? ' حدّث القائمة وراجع النتيجة قبل إعادة العملية.' : ''}</p>}
  </section>;
}
