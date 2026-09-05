import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { type ElementType, type OverlayElement } from '@mol/poc-renderer';
import { EditorStage } from './EditorStage';
import { createLocalElement, ELEMENT_TYPE_LABELS, INITIAL_ELEMENTS } from './fixtures';
import {
  nudgeElementByUnits,
  rotateElementToDegrees,
  setPercentGeometry,
  unitsToPercent,
  type PercentGeometryField,
} from './transform';

type MobileSheet = 'layers' | 'properties' | null;
type MobileSheetSize = 'compact' | 'expanded';

interface AddTool {
  readonly type: ElementType;
  readonly shortLabel: string;
  readonly glyph: string;
}

interface GeometryField {
  readonly key: PercentGeometryField;
  readonly label: string;
}

const ADD_TOOLS: readonly AddTool[] = [
  { type: 'bubble', shortLabel: 'فقاعة', glyph: '◯' },
  { type: 'narration', shortLabel: 'سرد', glyph: '▭' },
  { type: 'free_text', shortLabel: 'نص', glyph: 'ن' },
  { type: 'sfx', shortLabel: 'مؤثر', glyph: '✦' },
];

const GEOMETRY_FIELDS: readonly GeometryField[] = [
  { key: 'x_unit', label: 'X' },
  { key: 'y_unit', label: 'Y' },
  { key: 'w_unit', label: 'W' },
  { key: 'h_unit', label: 'H' },
];

function copyInitialElements(): OverlayElement[] {
  return INITIAL_ELEMENTS.map((element) => ({
    ...element,
    style: {
      ...element.style,
      ...(element.style.shadow === undefined ? {} : { shadow: { ...element.style.shadow } }),
    },
  }));
}

function FieldIcon({ glyph }: { readonly glyph: string }) {
  return <span className="mol-tool-glyph" aria-hidden="true">{glyph}</span>;
}

export function EditorApp() {
  const [elements, setElements] = useState<OverlayElement[]>(copyInitialElements);
  const [selectedId, setSelectedId] = useState<number | null>(INITIAL_ELEMENTS[0]?.id ?? null);
  const [preview, setPreview] = useState(false);
  const [zoom, setZoom] = useState(1);
  const [mobileSheet, setMobileSheet] = useState<MobileSheet>(null);
  const [mobileSheetSize, setMobileSheetSize] = useState<MobileSheetSize>('compact');
  const nextId = useRef(300);

  const selectedElement = useMemo(
    () => elements.find((element) => element.id === selectedId) ?? null,
    [elements, selectedId],
  );

  useEffect(() => {
    const visualViewport = window.visualViewport;
    const syncVisualHeight = (): void => {
      const height = visualViewport?.height ?? window.innerHeight;
      document.documentElement.style.setProperty('--mol-visual-height', `${Math.round(height)}px`);
      document.documentElement.style.setProperty('--mol-sheet-compact-height', `${Math.round(height * 0.45)}px`);
      document.documentElement.style.setProperty('--mol-sheet-expanded-height', `${Math.round(height * 0.85)}px`);
    };

    syncVisualHeight();
    visualViewport?.addEventListener('resize', syncVisualHeight);
    visualViewport?.addEventListener('scroll', syncVisualHeight);
    window.addEventListener('resize', syncVisualHeight);
    return () => {
      visualViewport?.removeEventListener('resize', syncVisualHeight);
      visualViewport?.removeEventListener('scroll', syncVisualHeight);
      window.removeEventListener('resize', syncVisualHeight);
      document.documentElement.style.removeProperty('--mol-visual-height');
      document.documentElement.style.removeProperty('--mol-sheet-compact-height');
      document.documentElement.style.removeProperty('--mol-sheet-expanded-height');
    };
  }, []);

  const updateElement = useCallback(
    (id: number, update: (element: OverlayElement) => OverlayElement) => {
      setElements((current) => current.map((element) => {
        if (element.id !== id) {
          return element;
        }
        const updated = update(element);
        return updated === element ? element : { ...updated, version: element.version + 1 };
      }));
    },
    [],
  );

  const selectElement = useCallback((id: number) => {
    setSelectedId(id);
  }, []);

  const openMobileSheet = (sheet: Exclude<MobileSheet, null>): void => {
    setMobileSheetSize('compact');
    setMobileSheet(sheet);
  };

  const addElement = (type: ElementType): void => {
    const id = nextId.current;
    nextId.current += 1;
    const created = createLocalElement(type, id);
    setElements((current) => [...current, created]);
    setSelectedId(id);
    setPreview(false);
    openMobileSheet('properties');
  };

  const resetDemo = (): void => {
    setElements(copyInitialElements());
    setSelectedId(INITIAL_ELEMENTS[0]?.id ?? null);
    setPreview(false);
    setZoom(1);
    setMobileSheet(null);
    setMobileSheetSize('compact');
    nextId.current = 300;
  };

  const updateSelected = (update: (element: OverlayElement) => OverlayElement): void => {
    if (selectedElement !== null) {
      updateElement(selectedElement.id, update);
    }
  };

  return (
    <div className={`mol-editor-shell${preview ? ' is-preview' : ''}`}>
      <header className="mol-editor-header">
        <div className="mol-editor-brand">
          <span className="mol-brand-mark" aria-hidden="true">MO</span>
          <div>
            <p>MANGA OVERLAY · T-02</p>
            <h1>محرر الفصل التجريبي</h1>
          </div>
        </div>

        <div className="mol-page-status" aria-label="حالة الصفحة">
          <span>الفصل 01</span>
          <strong>الصفحة 1 / 1</strong>
          <span className="mol-local-status">محلي — بلا REST</span>
        </div>

        <div className="mol-header-actions">
          <button type="button" className="mol-quiet-button" onClick={resetDemo}>إعادة الضبط</button>
          <button
            type="button"
            className="mol-preview-button"
            aria-pressed={preview}
            onClick={() => setPreview((value) => !value)}
          >
            {preview ? 'العودة للتحرير' : 'معاينة'}
          </button>
        </div>
      </header>

      <nav className="mol-desktop-tools" aria-label="إضافة عنصر">
        <span>إضافة</span>
        {ADD_TOOLS.map((tool) => (
          <button key={tool.type} type="button" onClick={() => addElement(tool.type)}>
            <FieldIcon glyph={tool.glyph} />
            {tool.shortLabel}
          </button>
        ))}
      </nav>

      <div className="mol-editor-grid">
        <aside
          className="mol-properties-panel mol-mobile-sheet"
          data-mobile-open={mobileSheet === 'properties'}
          data-mobile-size={mobileSheetSize}
          data-testid="properties-panel"
          aria-label="خصائص العنصر"
        >
          <div className="mol-panel-heading">
            <div>
              <p>الخصائص</p>
              <h2>{selectedElement === null ? 'لا يوجد تحديد' : ELEMENT_TYPE_LABELS[selectedElement.element_type]}</h2>
            </div>
            <div className="mol-sheet-actions">
              <button
                type="button"
                className="mol-sheet-size-toggle"
                aria-expanded={mobileSheetSize === 'expanded'}
                onClick={() => setMobileSheetSize((size) => size === 'compact' ? 'expanded' : 'compact')}
              >{mobileSheetSize === 'compact' ? 'توسيع' : 'تصغير'}</button>
              <button
                type="button"
                className="mol-sheet-close"
                aria-label="إغلاق الخصائص"
                onClick={() => setMobileSheet(null)}
              >×</button>
            </div>
          </div>

          {selectedElement === null ? (
            <p className="mol-panel-empty">اختر عنصرًا من الصفحة أو من قائمة الطبقات.</p>
          ) : (
            <div className="mol-properties-content">
              <label className="mol-field mol-content-field">
                <span>النص العربي</span>
                <textarea
                  data-testid="content-input"
                  lang="ar"
                  dir="rtl"
                  rows={4}
                  value={selectedElement.content}
                  onChange={(event) => updateSelected((element) => ({ ...element, content: event.target.value }))}
                />
              </label>

              <fieldset className="mol-transform-fields">
                <legend>Transform</legend>
                <p>القيم نسبة مئوية من أبعاد الصورة الأصلية.</p>
                <div className="mol-number-grid" dir="ltr">
                  {GEOMETRY_FIELDS.map((field) => (
                    <label key={field.key} className="mol-number-field">
                      <span>{field.label}</span>
                      <input
                        type="number"
                        inputMode="decimal"
                        min="0"
                        max="100"
                        step="0.1"
                        data-transform-field={field.key}
                        value={unitsToPercent(selectedElement[field.key])}
                        onChange={(event) => {
                          const value = Number.parseFloat(event.target.value);
                          updateSelected((element) => setPercentGeometry(element, field.key, value));
                        }}
                      />
                      <small>%</small>
                    </label>
                  ))}
                  <label className="mol-number-field mol-rotation-field">
                    <span>°</span>
                    <input
                      type="number"
                      inputMode="decimal"
                      min="-180"
                      max="180"
                      step="1"
                      data-transform-field="rotation_mdeg"
                      value={selectedElement.rotation_mdeg / 1_000}
                      onChange={(event) => {
                        const value = Number.parseFloat(event.target.value);
                        if (Number.isFinite(value)) {
                          updateSelected((element) => rotateElementToDegrees(element, value));
                        }
                      }}
                    />
                    <small>دوران</small>
                  </label>
                </div>
              </fieldset>

              <div className="mol-alternative-controls" aria-label="أزرار التحويل البديلة">
                <div className="mol-nudge-pad" dir="ltr">
                  <button type="button" aria-label="حرّك لأعلى" onClick={() => updateSelected((element) => nudgeElementByUnits(element, 0, -5_000))}>↑</button>
                  <button type="button" aria-label="حرّك لليسار" onClick={() => updateSelected((element) => nudgeElementByUnits(element, -5_000, 0))}>←</button>
                  <button type="button" aria-label="حرّك لليمين" onClick={() => updateSelected((element) => nudgeElementByUnits(element, 5_000, 0))}>→</button>
                  <button type="button" aria-label="حرّك لأسفل" onClick={() => updateSelected((element) => nudgeElementByUnits(element, 0, 5_000))}>↓</button>
                </div>
                <div className="mol-step-controls">
                  <button type="button" onClick={() => updateSelected((element) => setPercentGeometry(element, 'w_unit', unitsToPercent(element.w_unit) - 1))}>العرض −</button>
                  <button type="button" onClick={() => updateSelected((element) => setPercentGeometry(element, 'w_unit', unitsToPercent(element.w_unit) + 1))}>العرض +</button>
                  <button type="button" onClick={() => updateSelected((element) => rotateElementToDegrees(element, element.rotation_mdeg / 1_000 - 1))}>دوران −</button>
                  <button type="button" onClick={() => updateSelected((element) => rotateElementToDegrees(element, element.rotation_mdeg / 1_000 + 1))}>دوران +</button>
                </div>
              </div>

              <p className="mol-version-note">نسخة العنصر المحلية: {selectedElement.version}</p>
            </div>
          )}
        </aside>

        <main className="mol-editor-main">
          <EditorStage
            elements={elements}
            selectedId={selectedId}
            preview={preview}
            zoom={zoom}
            onSelect={selectElement}
            onCommit={updateElement}
            onZoomChange={setZoom}
          />
        </main>

        <aside
          className="mol-layers-panel mol-mobile-sheet"
          data-mobile-open={mobileSheet === 'layers'}
          data-mobile-size="compact"
          data-testid="layers-panel"
          aria-label="طبقات الصفحة"
        >
          <div className="mol-panel-heading">
            <div>
              <p>الصفحة 1</p>
              <h2>الطبقات <span>{elements.length}</span></h2>
            </div>
            <button
              type="button"
              className="mol-sheet-close"
              aria-label="إغلاق الطبقات"
              onClick={() => setMobileSheet(null)}
            >×</button>
          </div>
          <ol className="mol-layer-list">
            {[...elements].reverse().map((element) => (
              <li key={element.id}>
                <button
                  type="button"
                  aria-pressed={selectedId === element.id}
                  onClick={() => {
                    selectElement(element.id);
                    setMobileSheet(null);
                  }}
                >
                  <FieldIcon glyph={ADD_TOOLS.find((tool) => tool.type === element.element_type)?.glyph ?? 'ن'} />
                  <span>
                    <strong>{ELEMENT_TYPE_LABELS[element.element_type]}</strong>
                    <small>{element.content || 'نص فارغ'}</small>
                  </span>
                  <code>#{element.id}</code>
                </button>
              </li>
            ))}
          </ol>
          <p className="mol-layer-note">البيانات في الذاكرة فقط؛ لا طلبات شبكة أثناء التحريك.</p>
        </aside>
      </div>

      <nav className="mol-mobile-toolbar" aria-label="أدوات المحرر للجوال">
        <button
          type="button"
          aria-pressed={mobileSheet === 'properties'}
          onClick={() => openMobileSheet('properties')}
        >
          <FieldIcon glyph="↖" />
          تحديد
        </button>
        {ADD_TOOLS.map((tool) => (
          <button key={tool.type} type="button" onClick={() => addElement(tool.type)}>
            <FieldIcon glyph={tool.glyph} />
            {tool.shortLabel}
          </button>
        ))}
        <button
          type="button"
          aria-pressed={mobileSheet === 'layers'}
          onClick={() => openMobileSheet('layers')}
        >
          <FieldIcon glyph="☷" />
          الطبقات
        </button>
      </nav>
    </div>
  );
}
