import React, {
  useCallback,
  useEffect,
  useMemo,
  useReducer,
  useRef,
  useState,
} from 'react';
import { EditorStage } from './EditorStage';
import {
  ELEMENT_LABELS,
  SHAPE_OPTIONS,
  createLocalElement,
  duplicateLocalElement,
  elementName,
  highestZIndex,
  moveElementLayer,
  resolvedStyle,
  updateElementStyle,
} from './elementModel';
import {
  clampPagePosition,
  editorReducer,
  initialEditorState,
  pagePositionFromSearch,
  searchForPage,
} from './state';
import {
  nudgeElementByUnits,
  rotateElementToDegrees,
  setPercentGeometry,
  unitsToPercent,
  type PercentGeometryField,
} from './transform';
import type {
  BurstStyle,
  EditorBootstrap,
  EditorElement,
  EditorPageData,
  ElementShape,
  ElementStyle,
  ElementType,
  FontId,
  ShadowStyle,
  TailStyle,
  TextAlignment,
} from './types';

const ADD_TOOLS: readonly { type: ElementType; label: string; shortLabel: string; glyph: string }[] = [
  { type: 'bubble', label: 'إضافة فقاعة', shortLabel: 'فقاعة', glyph: '◯' },
  { type: 'narration', label: 'إضافة سرد', shortLabel: 'سرد', glyph: '▭' },
  { type: 'free_text', label: 'إضافة نص حر', shortLabel: 'نص', glyph: 'ن' },
  { type: 'sfx', label: 'إضافة مؤثر صوتي', shortLabel: 'مؤثر', glyph: '✦' },
];

const GEOMETRY_FIELDS: readonly { key: PercentGeometryField; label: string }[] = [
  { key: 'x_unit', label: 'X' },
  { key: 'y_unit', label: 'Y' },
  { key: 'w_unit', label: 'W' },
  { key: 'h_unit', label: 'H' },
];

const FONT_OPTIONS: readonly { value: FontId; label: string }[] = [
  { value: 'cairo', label: 'Cairo' },
  { value: 'noto-sans-arabic', label: 'Noto Sans Arabic' },
  { value: 'tajawal', label: 'Tajawal' },
  { value: 'noto-kufi-arabic', label: 'Noto Kufi Arabic' },
  { value: 'sfx-display-1', label: 'SFX Display' },
];

type ElementsByPage = Readonly<Record<number, readonly EditorElement[]>>;

function initialElements(data: EditorBootstrap): ElementsByPage {
  const result: Record<number, EditorElement[]> = {};
  for (const page of data.pages) {
    result[page.id] = page.elements.map((element) => ({ ...element, style: { ...element.style } }));
  }
  return result;
}

function chapterTitle(data: EditorBootstrap): string {
  const title = data.chapter.title?.trim();
  return title === undefined || title === '' ? `الفصل ${data.chapter.chapter_label}` : title;
}

function isTypingTarget(target: EventTarget | null): boolean {
  return target instanceof HTMLInputElement
    || target instanceof HTMLTextAreaElement
    || target instanceof HTMLSelectElement
    || (target instanceof HTMLElement && target.isContentEditable);
}

function LayersPanel({
  elements,
  selectedId,
  collapsed,
  onSelect,
  onToggle,
}: {
  readonly elements: readonly EditorElement[];
  readonly selectedId: number | null;
  readonly collapsed: boolean;
  readonly onSelect: (id: number) => void;
  readonly onToggle: () => void;
}) {
  const ordered = [...elements].sort((left, right) => right.z_index - left.z_index || right.id - left.id);
  return (
    <aside className="mol-editor-layers" data-collapsed={collapsed ? 'true' : 'false'} aria-label="الطبقات">
      <div className="mol-editor-panel-heading">
        {!collapsed && <h2>الطبقات <small>{elements.length}</small></h2>}
        <button
          type="button"
          className="mol-editor-icon-button"
          aria-label={collapsed ? 'فتح لوحة الطبقات' : 'طي لوحة الطبقات'}
          aria-expanded={!collapsed}
          onClick={onToggle}
        >
          {collapsed ? '»' : '«'}
        </button>
      </div>
      {!collapsed && (elements.length === 0 ? (
        <p className="mol-editor-empty-panel">لا توجد عناصر ترجمة في هذه الصفحة.</p>
      ) : (
        <ol className="mol-editor-layer-list">
          {ordered.map((element, index) => (
            <li key={element.id}>
              <button
                type="button"
                data-testid={`layer-${element.id}`}
                aria-pressed={selectedId === element.id}
                onClick={() => onSelect(element.id)}
              >
                <span className={`mol-editor-layer-kind is-${element.element_type}`} aria-hidden="true">
                  {element.element_type === 'sfx' ? '✦' : 'ن'}
                </span>
                <span>
                  <strong>{elementName(element, index)}</strong>
                  <small>{ELEMENT_LABELS[element.element_type]} · z {element.z_index}{element.version === 0 ? ' · جديد' : ''}</small>
                </span>
              </button>
            </li>
          ))}
        </ol>
      ))}
    </aside>
  );
}

function PercentInput({
  label,
  value,
  minimum = 0,
  maximum = 100,
  onChange,
  testId,
}: {
  readonly label: string;
  readonly value: number;
  readonly minimum?: number;
  readonly maximum?: number;
  readonly onChange: (value: number) => void;
  readonly testId?: string;
}) {
  return (
    <label className="mol-editor-number-field">
      <span>{label}</span>
      <input
        type="number"
        inputMode="decimal"
        min={minimum}
        max={maximum}
        step="0.1"
        value={value}
        data-testid={testId}
        onChange={(event) => onChange(Number.parseFloat(event.target.value))}
      />
      <small>%</small>
    </label>
  );
}

function StyleFields({
  element,
  onUpdate,
}: {
  readonly element: EditorElement;
  readonly onUpdate: (update: (element: EditorElement) => EditorElement) => void;
}) {
  const style = resolvedStyle(element);
  const patchStyle = (patch: Partial<ElementStyle>): void => onUpdate((current) => updateElementStyle(current, patch));
  const tail = style.tail ?? { enabled: false, angleMdeg: 25_000, lengthUnit: 80_000, widthUnit: 55_000 } satisfies TailStyle;
  const burst = style.burst ?? { points: 12, depth: 0.35 } satisfies BurstStyle;
  const shadow = style.shadow ?? { xUnit: 2_000, yUnit: 2_000, blurUnit: 4_000, color: '#000000', opacity: 0.75 } satisfies ShadowStyle;

  return (
    <>
      <fieldset className="mol-editor-fieldset">
        <legend>النص</legend>
        <div className="mol-editor-form-grid">
          <label className="is-wide">
            <span>الخط</span>
            <select
              value={style.fontId}
              data-testid="font-select"
              onChange={(event) => patchStyle({ fontId: event.target.value as FontId })}
            >
              {FONT_OPTIONS.map((font) => <option key={font.value} value={font.value}>{font.label}</option>)}
            </select>
          </label>
          <PercentInput label="الحجم" value={unitsToPercent(style.fontSizeUnit)} maximum={20} onChange={(value) => patchStyle({ fontSizeUnit: Math.round(value * 10_000) })} />
          <label>
            <span>السماكة</span>
            <select value={style.fontWeight} onChange={(event) => patchStyle({ fontWeight: Number(event.target.value) as ElementStyle['fontWeight'] })}>
              {[400, 500, 600, 700, 800, 900].map((weight) => <option key={weight} value={weight}>{weight}</option>)}
            </select>
          </label>
          <label>
            <span>ارتفاع السطر</span>
            <input type="number" min="1" max="2.5" step="0.05" value={style.lineHeight} onChange={(event) => patchStyle({ lineHeight: Number(event.target.value) })} />
          </label>
          <label>
            <span>المحاذاة</span>
            <select value={style.textAlign} onChange={(event) => patchStyle({ textAlign: event.target.value as TextAlignment })}>
              <option value="start">بداية</option><option value="center">وسط</option><option value="end">نهاية</option>
            </select>
          </label>
          <label>
            <span>لون النص</span>
            <input type="color" value={style.color} data-testid="text-color" onChange={(event) => patchStyle({ color: event.target.value })} />
          </label>
        </div>
      </fieldset>

      <fieldset className="mol-editor-fieldset">
        <legend>الشكل والخلفية</legend>
        <div className="mol-editor-form-grid">
          <label className="is-wide">
            <span>الشكل</span>
            <select value={style.shape} data-testid="shape-select" onChange={(event) => patchStyle({ shape: event.target.value as ElementShape })}>
              {SHAPE_OPTIONS[element.element_type].map((shape) => <option key={shape.value} value={shape.value}>{shape.label}</option>)}
            </select>
          </label>
          <label><span>الخلفية</span><input type="color" value={style.backgroundColor} onChange={(event) => patchStyle({ backgroundColor: event.target.value })} /></label>
          <label><span>الشفافية</span><input type="number" min="0" max="1" step="0.05" value={style.backgroundOpacity} onChange={(event) => patchStyle({ backgroundOpacity: Number(event.target.value) })} /></label>
          <label><span>لون الحد</span><input type="color" value={style.borderColor} onChange={(event) => patchStyle({ borderColor: event.target.value })} /></label>
          <PercentInput label="سُمك الحد" value={unitsToPercent(style.borderWidthUnit)} maximum={5} onChange={(value) => patchStyle({ borderWidthUnit: Math.round(value * 10_000) })} />
          <PercentInput label="الحشو" value={unitsToPercent(style.paddingUnit)} maximum={10} onChange={(value) => patchStyle({ paddingUnit: Math.round(value * 10_000) })} />
        </div>
      </fieldset>

      {element.element_type === 'bubble' && (
        <fieldset className="mol-editor-fieldset" data-testid="tail-fields">
          <legend>ذيل الفقاعة</legend>
          <label className="mol-editor-checkbox"><input type="checkbox" checked={tail.enabled} onChange={(event) => patchStyle({ tail: { ...tail, enabled: event.target.checked } })} /><span>إظهار الذيل</span></label>
          <div className="mol-editor-form-grid">
            <label><span>الزاوية</span><input type="number" min="-360" max="360" step="1" value={tail.angleMdeg / 1_000} onChange={(event) => patchStyle({ tail: { ...tail, angleMdeg: Math.round(Number(event.target.value) * 1_000) } })} /></label>
            <PercentInput label="الطول" value={unitsToPercent(tail.lengthUnit)} maximum={30} onChange={(value) => patchStyle({ tail: { ...tail, lengthUnit: Math.round(value * 10_000) } })} />
          </div>
        </fieldset>
      )}

      {element.element_type === 'sfx' && (
        <fieldset className="mol-editor-fieldset" data-testid="sfx-fields">
          <legend>المؤثر الصوتي</legend>
          <div className="mol-editor-form-grid">
            <label><span>لون Stroke</span><input type="color" value={style.strokeColor ?? '#111111'} data-testid="stroke-color" onChange={(event) => patchStyle({ strokeColor: event.target.value })} /></label>
            <PercentInput label="Stroke" value={unitsToPercent(style.strokeWidthUnit ?? 0)} maximum={5} onChange={(value) => patchStyle({ strokeWidthUnit: Math.round(value * 10_000) })} />
            <label><span>Scale X</span><input type="number" min="0.5" max="2" step="0.05" value={style.scaleX ?? 1} onChange={(event) => patchStyle({ scaleX: Number(event.target.value) })} /></label>
            <label><span>Scale Y</span><input type="number" min="0.5" max="2" step="0.05" value={style.scaleY ?? 1} onChange={(event) => patchStyle({ scaleY: Number(event.target.value) })} /></label>
            {style.shape !== 'none' && <>
              <label><span>نقاط الانفجار</span><select value={burst.points} onChange={(event) => patchStyle({ burst: { ...burst, points: Number(event.target.value) as BurstStyle['points'] } })}>{[8, 12, 16, 24].map((points) => <option key={points} value={points}>{points}</option>)}</select></label>
              <label><span>عمق الانفجار</span><input type="number" min="0" max="1" step="0.05" value={burst.depth} onChange={(event) => patchStyle({ burst: { ...burst, depth: Number(event.target.value) } })} /></label>
            </>}
          </div>
          <label className="mol-editor-checkbox"><input type="checkbox" checked={style.shadow !== null && style.shadow !== undefined} onChange={(event) => patchStyle({ shadow: event.target.checked ? shadow : null })} /><span>ظل آمن</span></label>
        </fieldset>
      )}
    </>
  );
}

function PropertiesPanel({
  element,
  onUpdate,
  onDuplicate,
  onDelete,
  onLayer,
}: {
  readonly element: EditorElement | null;
  readonly onUpdate: (update: (element: EditorElement) => EditorElement) => void;
  readonly onDuplicate: () => void;
  readonly onDelete: () => void;
  readonly onLayer: (direction: 'up' | 'down') => void;
}) {
  return (
    <aside className="mol-editor-properties" aria-label="الخصائص" data-testid="properties-panel">
      <div className="mol-editor-panel-heading"><h2>الخصائص</h2><span>تحرير محلي</span></div>
      {element === null ? (
        <p className="mol-editor-empty-panel">اختر عنصرًا من الصفحة أو من قائمة الطبقات.</p>
      ) : (
        <div className="mol-editor-property-content">
          <div className="mol-editor-property-title"><span>{ELEMENT_LABELS[element.element_type]}</span><strong>{element.id > 0 ? `#${element.id}` : 'جديد'}</strong></div>
          <label>
            <span>النص العربي</span>
            <textarea
              id={`mol-element-content-${element.id}`}
              data-testid="property-content"
              value={element.content}
              rows={5}
              lang="ar"
              dir="rtl"
              onChange={(event) => onUpdate((current) => ({ ...current, content: event.target.value }))}
            />
          </label>

          <fieldset className="mol-editor-fieldset">
            <legend>الموضع والتحويل</legend>
            <div className="mol-editor-number-grid" dir="ltr">
              {GEOMETRY_FIELDS.map((field) => (
                <PercentInput
                  key={field.key}
                  label={field.label}
                  value={unitsToPercent(element[field.key])}
                  testId={`geometry-${field.key}`}
                  onChange={(value) => onUpdate((current) => setPercentGeometry(current, field.key, value))}
                />
              ))}
              <label className="mol-editor-number-field"><span>°</span><input type="number" min="-180" max="180" step="1" value={element.rotation_mdeg / 1_000} data-testid="geometry-rotation" onChange={(event) => onUpdate((current) => rotateElementToDegrees(current, Number(event.target.value)))} /><small>دوران</small></label>
              <label className="mol-editor-number-field"><span>Z</span><input type="number" min="-1000" max="10000" step="1" value={element.z_index} onChange={(event) => onUpdate((current) => ({ ...current, z_index: Math.min(10_000, Math.max(-1_000, Math.round(Number(event.target.value)))) }))} /><small>طبقة</small></label>
            </div>
            <div className="mol-editor-nudge" aria-label="بدائل التحريك">
              <button type="button" aria-label="حرّك لأعلى" onClick={() => onUpdate((current) => nudgeElementByUnits(current, 0, -5_000))}>↑</button>
              <button type="button" aria-label="حرّك لليسار" onClick={() => onUpdate((current) => nudgeElementByUnits(current, -5_000, 0))}>←</button>
              <button type="button" aria-label="حرّك لليمين" onClick={() => onUpdate((current) => nudgeElementByUnits(current, 5_000, 0))}>→</button>
              <button type="button" aria-label="حرّك لأسفل" onClick={() => onUpdate((current) => nudgeElementByUnits(current, 0, 5_000))}>↓</button>
              <button type="button" onClick={() => onLayer('up')}>طبقة لأعلى</button>
              <button type="button" onClick={() => onLayer('down')}>طبقة لأسفل</button>
            </div>
          </fieldset>

          <StyleFields element={element} onUpdate={onUpdate} />
          <div className="mol-editor-element-actions">
            <button type="button" onClick={onDuplicate}>نسخ العنصر</button>
            <button type="button" className="is-danger" onClick={onDelete}>حذف العنصر</button>
          </div>
          <p className="mol-editor-readonly-note">التغييرات محفوظة في ذاكرة هذه الجلسة فقط. يضيف T‑12 الحفظ الشبكي وAutosave.</p>
        </div>
      )}
    </aside>
  );
}

export function EditorApp({ data }: { readonly data: EditorBootstrap }) {
  const [state, dispatch] = useReducer(editorReducer, initialEditorState(window.location.search, data.pages.length));
  const [elementsByPage, setElementsByPage] = useState<ElementsByPage>(() => initialElements(data));
  const [dirtyPages, setDirtyPages] = useState<ReadonlySet<number>>(() => new Set());
  const nextLocalId = useRef(-1);
  const page = data.pages[state.pagePosition] ?? null;
  const elements = page === null ? [] : elementsByPage[page.id] ?? [];
  const selectedElement = useMemo(
    () => elements.find((element) => element.id === state.selectedElementId) ?? null,
    [elements, state.selectedElementId],
  );

  const mutatePage = useCallback((pageId: number, mutation: (elements: readonly EditorElement[]) => readonly EditorElement[]): void => {
    setElementsByPage((current) => ({ ...current, [pageId]: mutation(current[pageId] ?? []) }));
    setDirtyPages((current) => new Set(current).add(pageId));
  }, []);

  const updateElement = useCallback((id: number, update: (element: EditorElement) => EditorElement): void => {
    if (page === null) return;
    mutatePage(page.id, (current) => current.map((element) => element.id === id ? update(element) : element));
  }, [mutatePage, page]);

  const selectElement = useCallback((id: number): void => dispatch({ type: 'select-element', id }), []);
  const focusElementText = useCallback((id: number): void => {
    selectElement(id);
    window.requestAnimationFrame(() => document.getElementById(`mol-element-content-${id}`)?.focus());
  }, [selectElement]);

  const addElement = useCallback((type: ElementType): void => {
    if (page === null) return;
    const id = nextLocalId.current;
    nextLocalId.current -= 1;
    const created = createLocalElement(page.id, data.targetLanguage, type, id, highestZIndex(elements) + 1);
    mutatePage(page.id, (current) => [...current, created]);
    selectElement(id);
  }, [data.targetLanguage, elements, mutatePage, page, selectElement]);

  const duplicateSelected = useCallback((): void => {
    if (page === null || selectedElement === null) return;
    const id = nextLocalId.current;
    nextLocalId.current -= 1;
    const duplicate = duplicateLocalElement(selectedElement, id, highestZIndex(elements) + 1);
    mutatePage(page.id, (current) => [...current, duplicate]);
    selectElement(id);
  }, [elements, mutatePage, page, selectElement, selectedElement]);

  const deleteSelected = useCallback((): void => {
    if (page === null || selectedElement === null) return;
    mutatePage(page.id, (current) => current.filter((element) => element.id !== selectedElement.id));
    dispatch({ type: 'select-element', id: null });
  }, [mutatePage, page, selectedElement]);

  const moveSelectedLayer = useCallback((direction: 'up' | 'down'): void => {
    if (page === null || selectedElement === null) return;
    mutatePage(page.id, (current) => moveElementLayer(current, selectedElement.id, direction));
  }, [mutatePage, page, selectedElement]);

  const goToPage = useCallback((requestedPosition: number, updateHistory = true): void => {
    const position = clampPagePosition(requestedPosition, data.pages.length);
    if (updateHistory) {
      const search = searchForPage(window.location.search, position);
      window.history.pushState({ molPage: position }, '', `${window.location.pathname}${search}${window.location.hash}`);
    }
    dispatch({ type: 'go-page', position, pageCount: data.pages.length });
  }, [data.pages.length]);

  useEffect(() => {
    const restoreRoute = (): void => goToPage(pagePositionFromSearch(window.location.search, data.pages.length), false);
    window.addEventListener('popstate', restoreRoute);
    return () => window.removeEventListener('popstate', restoreRoute);
  }, [data.pages.length, goToPage]);

  useEffect(() => {
    const handleKeyboard = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        if (state.preview) dispatch({ type: 'toggle-preview' });
        else dispatch({ type: 'select-element', id: null });
        return;
      }
      if (state.preview || selectedElement === null || isTypingTarget(event.target)) return;
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'd') {
        event.preventDefault();
        duplicateSelected();
        return;
      }
      if (event.key === 'Delete' || event.key === 'Backspace') {
        event.preventDefault();
        deleteSelected();
        return;
      }
      const step = event.shiftKey ? 10_000 : 1_000;
      const delta = event.key === 'ArrowLeft' ? [-step, 0]
        : event.key === 'ArrowRight' ? [step, 0]
          : event.key === 'ArrowUp' ? [0, -step]
            : event.key === 'ArrowDown' ? [0, step]
              : null;
      if (delta !== null) {
        event.preventDefault();
        updateElement(selectedElement.id, (element) => nudgeElementByUnits(element, delta[0], delta[1]));
      }
    };
    window.addEventListener('keydown', handleKeyboard);
    return () => window.removeEventListener('keydown', handleKeyboard);
  }, [deleteSelected, duplicateSelected, selectedElement, state.preview, updateElement]);

  const status = dirtyPages.size > 0 ? 'تغييرات محلية · الحفظ في T‑12' : 'جاهز للتحرير محليًا · الحفظ في T‑12';

  return (
    <div className={`mol-editor-shell${state.preview ? ' is-preview' : ''}`} data-testid="editor-shell">
      <header className="mol-editor-header">
        <a className="mol-editor-back" href={data.links.work} aria-label="العودة إلى صفحة العمل">→</a>
        <div className="mol-editor-identity"><span dir="auto">{data.work.title}</span><strong dir="auto">{chapterTitle(data)}</strong></div>
        <div className="mol-editor-page-state" aria-live="polite"><span>{page === null ? 'لا صفحات' : `صفحة ${state.pagePosition + 1} من ${data.pages.length}`}</span><span className="mol-editor-save-state">{status}</span></div>
        <button type="button" className="mol-editor-preview-button" aria-pressed={state.preview} onClick={() => dispatch({ type: 'toggle-preview' })}>{state.preview ? 'العودة إلى المحرر' : 'معاينة'}</button>
      </header>

      {!state.preview && (
        <nav className="mol-editor-toolbar" aria-label="أدوات المحرر">
          <button type="button" aria-pressed={state.selectedElementId === null} onClick={() => dispatch({ type: 'select-element', id: null })}><span aria-hidden="true">↖</span>تحديد</button>
          {ADD_TOOLS.map((tool) => <button key={tool.type} type="button" data-testid={`add-${tool.type}`} aria-label={tool.label} onClick={() => addElement(tool.type)}><span aria-hidden="true">{tool.glyph}</span>{tool.shortLabel}</button>)}
          <span className="mol-editor-toolbar-note">T‑11 · تحرير محلي بلا طلبات شبكة</span>
        </nav>
      )}

      {page === null ? (
        <main className="mol-editor-no-pages"><h1>لا توجد صفحات في هذا الفصل</h1><p>أضف صفحات من إدارة المحتوى أولًا، ثم عُد إلى المحرر.</p><a href={data.links.work}>العودة إلى العمل</a></main>
      ) : (
        <div className={`mol-editor-layout${state.layersCollapsed ? ' has-collapsed-layers' : ''}`}>
          {!state.preview && <LayersPanel elements={elements} selectedId={state.selectedElementId} collapsed={state.layersCollapsed} onSelect={selectElement} onToggle={() => dispatch({ type: 'toggle-layers' })} />}
          <main className="mol-editor-main">
            <EditorStage page={page as EditorPageData} elements={elements} zoom={state.zoom} selectedId={state.selectedElementId} preview={state.preview} onSelect={selectElement} onDeselect={() => dispatch({ type: 'select-element', id: null })} onEditText={focusElementText} onCommit={updateElement} />
            <div className="mol-editor-stage-controls">
              <div role="group" aria-label="التنقل بين الصفحات"><button type="button" data-testid="previous-page" disabled={state.pagePosition <= 0} onClick={() => goToPage(state.pagePosition - 1)}>السابق</button><span>{state.pagePosition + 1} / {data.pages.length}</span><button type="button" data-testid="next-page" disabled={state.pagePosition >= data.pages.length - 1} onClick={() => goToPage(state.pagePosition + 1)}>التالي</button></div>
              {!state.preview && <div role="group" aria-label="تكبير مساحة العمل"><button type="button" aria-label="تصغير" onClick={() => dispatch({ type: 'set-zoom', zoom: state.zoom - 0.25 })}>−</button><output>{Math.round(state.zoom * 100)}%</output><button type="button" aria-label="تكبير" onClick={() => dispatch({ type: 'set-zoom', zoom: state.zoom + 0.25 })}>＋</button><button type="button" onClick={() => dispatch({ type: 'set-zoom', zoom: 1 })}>ملاءمة</button></div>}
            </div>
          </main>
          {!state.preview && <PropertiesPanel element={selectedElement} onUpdate={(update) => selectedElement !== null && updateElement(selectedElement.id, update)} onDuplicate={duplicateSelected} onDelete={deleteSelected} onLayer={moveSelectedLayer} />}
        </div>
      )}
      <span className="mol-editor-release" aria-hidden="true">Core {data.release.core}</span>
    </div>
  );
}
