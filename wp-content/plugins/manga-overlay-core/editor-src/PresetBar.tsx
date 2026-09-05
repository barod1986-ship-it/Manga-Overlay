import React, { useEffect, useMemo, useState } from 'react';
import { ElementApi, ElementApiError } from './elementApi';
import { resolvedStyle, updateElementStyle } from './elementModel';
import type {
  EditorBootstrap,
  EditorElement,
  ElementStyle,
  PresetScope,
  StylePreset,
} from './types';

const SCOPE_LABELS: Readonly<Record<PresetScope, string>> = {
  personal: 'شخصي',
  work: 'هذا العمل',
  global: 'عام',
};

export function applyPresetStyle(element: EditorElement, preset: StylePreset): EditorElement {
  if (element.element_type !== preset.element_type) return element;
  return updateElementStyle(element, preset.style as Partial<ElementStyle>);
}

export function PresetBar({
  data,
  element,
  disabled,
  onUpdate,
}: {
  readonly data: EditorBootstrap;
  readonly element: EditorElement;
  readonly disabled: boolean;
  readonly onUpdate: (update: (element: EditorElement) => EditorElement) => void;
}) {
  const api = useMemo(() => new ElementApi(data.api), [data.api]);
  const [presets, setPresets] = useState<readonly StylePreset[]>([]);
  const [selectedId, setSelectedId] = useState('');
  const [showSave, setShowSave] = useState(false);
  const [name, setName] = useState('');
  const [scope, setScope] = useState<PresetScope>('personal');
  const [isDefault, setIsDefault] = useState(false);
  const [status, setStatus] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let active = true;
    setStatus('جارٍ تحميل الأنماط…');
    void api.listPresets(data.work.id, element.element_type).then((loaded) => {
      if (!active) return;
      setPresets(loaded);
      setSelectedId((current) => loaded.some((preset) => String(preset.id) === current)
        ? current
        : String(loaded.find((preset) => preset.is_default)?.id ?? loaded[0]?.id ?? ''));
      setStatus('');
    }).catch((error: unknown) => {
      if (active) setStatus(error instanceof ElementApiError ? error.message : 'تعذر تحميل الأنماط المحفوظة.');
    });
    return () => { active = false; };
  }, [api, data.work.id, element.element_type]);

  const selected = presets.find((preset) => String(preset.id) === selectedId) ?? null;
  const allowedScopes: readonly PresetScope[] = [
    'personal',
    ...(data.permissions.manageWorkPresets ? ['work' as const] : []),
    ...(data.permissions.manageGlobalPresets ? ['global' as const] : []),
  ];

  const save = async (): Promise<void> => {
    if (name.trim() === '' || busy) return;
    setBusy(true);
    setStatus('جارٍ حفظ النمط…');
    try {
      const created = await api.createPreset({
        scope,
        work_id: scope === 'work' ? data.work.id : null,
        name: name.trim(),
        element_type: element.element_type,
        style: { ...resolvedStyle(element) },
        is_default: isDefault,
      });
      setPresets((current) => [
        ...current.map((preset) => isDefault && preset.scope === created.scope
          ? { ...preset, is_default: false }
          : preset),
        created,
      ]);
      setSelectedId(String(created.id));
      setName('');
      setIsDefault(false);
      setShowSave(false);
      setStatus('حُفظ النمط.');
    } catch (error) {
      setStatus(error instanceof ElementApiError ? error.message : 'تعذر حفظ النمط.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="mol-editor-preset-bar" aria-label="الأنماط المحفوظة" data-testid="preset-bar">
      <div className="mol-editor-preset-row">
        <label>
          <span>النمط</span>
          <select value={selectedId} disabled={disabled || presets.length === 0} data-testid="preset-select" onChange={(event) => setSelectedId(event.target.value)}>
            {presets.length === 0 && <option value="">Base Style</option>}
            {presets.map((preset) => (
              <option key={preset.id} value={preset.id}>
                {preset.name} · {SCOPE_LABELS[preset.scope]}{preset.is_default ? ' · افتراضي' : ''}
              </option>
            ))}
          </select>
        </label>
        <button type="button" disabled={disabled || selected === null} data-testid="apply-preset" onClick={() => selected !== null && onUpdate((current) => applyPresetStyle(current, selected))}>تطبيق</button>
        <button type="button" disabled={disabled} data-testid="save-preset-toggle" aria-expanded={showSave} onClick={() => setShowSave((visible) => !visible)}>حفظ كنمط</button>
      </div>
      {showSave && (
        <div className="mol-editor-preset-save" data-testid="preset-save-form">
          <label><span>الاسم</span><input value={name} maxLength={100} data-testid="preset-name" onChange={(event) => setName(event.target.value)} /></label>
          <label><span>النطاق</span><select value={scope} data-testid="preset-scope" onChange={(event) => setScope(event.target.value as PresetScope)}>{allowedScopes.map((value) => <option key={value} value={value}>{SCOPE_LABELS[value]}</option>)}</select></label>
          <label className="mol-editor-checkbox"><input type="checkbox" checked={isDefault} onChange={(event) => setIsDefault(event.target.checked)} /><span>اجعله الافتراضي</span></label>
          <button type="button" className="is-primary" disabled={busy || name.trim() === ''} data-testid="save-preset" onClick={() => void save()}>حفظ</button>
        </div>
      )}
      <small className="mol-editor-preset-status" role="status">{status}</small>
    </section>
  );
}
