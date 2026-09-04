import React, { useCallback, useEffect, useMemo, useReducer } from 'react';
import {
  clampPagePosition,
  editorReducer,
  initialEditorState,
  pagePositionFromSearch,
  physicalGeometryStyle,
  searchForPage,
} from './state';
import type { EditorBootstrap, EditorElement, EditorPageData } from './types';

const ELEMENT_LABELS: Readonly<Record<EditorElement['element_type'], string>> = {
  bubble: 'فقاعة',
  narration: 'سرد',
  free_text: 'نص حر',
  sfx: 'مؤثر صوتي',
};

const TOOL_LABELS = ['تحديد', 'فقاعة', 'سرد', 'نص', 'مؤثر'] as const;

function chapterTitle(data: EditorBootstrap): string {
  const title = data.chapter.title?.trim();
  return title === undefined || title === ''
    ? `الفصل ${data.chapter.chapter_label}`
    : title;
}

function elementName(element: EditorElement, position: number): string {
  const preview = element.content.trim().replace(/\s+/g, ' ').slice(0, 28);
  return preview === ''
    ? `${ELEMENT_LABELS[element.element_type]} ${position + 1}`
    : preview;
}

function PageStage({
  page,
  zoom,
  selectedId,
  preview,
  onSelect,
}: {
  readonly page: EditorPageData;
  readonly zoom: number;
  readonly selectedId: number | null;
  readonly preview: boolean;
  readonly onSelect: (id: number) => void;
}) {
  const ratio = page.natural_width > 0 && page.natural_height > 0
    ? `${page.natural_width} / ${page.natural_height}`
    : '2 / 3';
  const width = `${zoom * 100}%`;
  const maximumWidth = `${Math.max(page.natural_width, 1) * zoom}px`;

  return (
    <div className="mol-editor-stage-scroll" data-testid="stage-scroll">
      <div
        className="mol-editor-stage"
        data-testid="editor-stage"
        style={{ aspectRatio: ratio, width, maxWidth: maximumWidth }}
      >
        {page.image.url === '' ? (
          <div className="mol-editor-image-missing" role="img" aria-label="صورة الصفحة غير متاحة">
            صورة الصفحة غير متاحة
          </div>
        ) : (
          <img
            src={page.image.url}
            srcSet={page.image.srcset ?? undefined}
            sizes={page.image.sizes ?? undefined}
            width={page.image.width}
            height={page.image.height}
            alt={page.image.alt ?? `صفحة ${page.page_index + 1}`}
            draggable={false}
          />
        )}
        {!preview && page.elements.map((element) => (
          <button
            key={element.id}
            type="button"
            className="mol-editor-element-outline"
            data-testid={`stage-element-${element.id}`}
            data-selected={selectedId === element.id ? 'true' : 'false'}
            aria-label={`تحديد ${ELEMENT_LABELS[element.element_type]}`}
            style={physicalGeometryStyle(element)}
            onClick={() => onSelect(element.id)}
          >
            <span>{ELEMENT_LABELS[element.element_type]}</span>
          </button>
        ))}
      </div>
    </div>
  );
}

function LayersPanel({
  page,
  selectedId,
  collapsed,
  onSelect,
  onToggle,
}: {
  readonly page: EditorPageData;
  readonly selectedId: number | null;
  readonly collapsed: boolean;
  readonly onSelect: (id: number) => void;
  readonly onToggle: () => void;
}) {
  return (
    <aside className="mol-editor-layers" data-collapsed={collapsed ? 'true' : 'false'} aria-label="الطبقات">
      <div className="mol-editor-panel-heading">
        {!collapsed && <h2>الطبقات</h2>}
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
      {!collapsed && (
        page.elements.length === 0 ? (
          <p className="mol-editor-empty-panel">لا توجد عناصر ترجمة في هذه الصفحة.</p>
        ) : (
          <ol className="mol-editor-layer-list">
            {[...page.elements].sort((a, b) => b.z_index - a.z_index || b.id - a.id).map((element, index) => (
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
                    <small>{ELEMENT_LABELS[element.element_type]} · z {element.z_index}</small>
                  </span>
                </button>
              </li>
            ))}
          </ol>
        )
      )}
    </aside>
  );
}

function PropertiesPanel({ element }: { readonly element: EditorElement | null }) {
  return (
    <aside className="mol-editor-properties" aria-label="الخصائص" data-testid="properties-panel">
      <div className="mol-editor-panel-heading">
        <h2>الخصائص</h2>
        <span>للقراءة فقط</span>
      </div>
      {element === null ? (
        <p className="mol-editor-empty-panel">اختر عنصرًا من الصفحة أو من قائمة الطبقات.</p>
      ) : (
        <div className="mol-editor-property-content">
          <div className="mol-editor-property-title">
            <span>{ELEMENT_LABELS[element.element_type]}</span>
            <strong>#{element.id}</strong>
          </div>
          <label>
            <span>النص العربي</span>
            <textarea data-testid="property-content" value={element.content} rows={5} readOnly />
          </label>
          <dl className="mol-editor-geometry" dir="ltr">
            <div><dt>X</dt><dd>{(element.x_unit / 10_000).toFixed(2)}%</dd></div>
            <div><dt>Y</dt><dd>{(element.y_unit / 10_000).toFixed(2)}%</dd></div>
            <div><dt>W</dt><dd>{(element.w_unit / 10_000).toFixed(2)}%</dd></div>
            <div><dt>H</dt><dd>{(element.h_unit / 10_000).toFixed(2)}%</dd></div>
          </dl>
          <p className="mol-editor-readonly-note">التحرير والحفظ يُفعّلان في T‑11 وT‑12.</p>
        </div>
      )}
    </aside>
  );
}

export function EditorApp({ data }: { readonly data: EditorBootstrap }) {
  const [state, dispatch] = useReducer(
    editorReducer,
    initialEditorState(window.location.search, data.pages.length),
  );
  const page = data.pages[state.pagePosition] ?? null;
  const selectedElement = useMemo(
    () => page?.elements.find((element) => element.id === state.selectedElementId) ?? null,
    [page, state.selectedElementId],
  );

  const goToPage = useCallback((requestedPosition: number, updateHistory = true): void => {
    const position = clampPagePosition(requestedPosition, data.pages.length);
    if (updateHistory) {
      const search = searchForPage(window.location.search, position);
      window.history.pushState({ molPage: position }, '', `${window.location.pathname}${search}${window.location.hash}`);
    }
    dispatch({ type: 'go-page', position, pageCount: data.pages.length });
  }, [data.pages.length]);

  useEffect(() => {
    const restoreRoute = (): void => {
      goToPage(pagePositionFromSearch(window.location.search, data.pages.length), false);
    };
    window.addEventListener('popstate', restoreRoute);
    return () => window.removeEventListener('popstate', restoreRoute);
  }, [data.pages.length, goToPage]);

  useEffect(() => {
    const exitPreview = (event: KeyboardEvent): void => {
      if (event.key === 'Escape' && state.preview) {
        dispatch({ type: 'toggle-preview' });
      }
    };
    window.addEventListener('keydown', exitPreview);
    return () => window.removeEventListener('keydown', exitPreview);
  }, [state.preview]);

  return (
    <div className={`mol-editor-shell${state.preview ? ' is-preview' : ''}`} data-testid="editor-shell">
      <header className="mol-editor-header">
        <a className="mol-editor-back" href={data.links.work} aria-label="العودة إلى صفحة العمل">→</a>
        <div className="mol-editor-identity">
          <span dir="auto">{data.work.title}</span>
          <strong dir="auto">{chapterTitle(data)}</strong>
        </div>
        <div className="mol-editor-page-state" aria-live="polite">
          <span>{page === null ? 'لا صفحات' : `صفحة ${state.pagePosition + 1} من ${data.pages.length}`}</span>
          <span className="mol-editor-save-state">عرض فقط · الحفظ غير مفعّل بعد</span>
        </div>
        <button
          type="button"
          className="mol-editor-preview-button"
          aria-pressed={state.preview}
          onClick={() => dispatch({ type: 'toggle-preview' })}
        >
          {state.preview ? 'العودة إلى المحرر' : 'معاينة'}
        </button>
      </header>

      {!state.preview && (
        <nav className="mol-editor-toolbar" aria-label="أدوات المحرر">
          {TOOL_LABELS.map((label, index) => (
            <button key={label} type="button" disabled={index > 0} title={index > 0 ? 'يتاح في T‑11' : undefined}>
              <span aria-hidden="true">{index === 0 ? '↖' : index === 4 ? '✦' : '＋'}</span>
              {label}
            </button>
          ))}
          <span className="mol-editor-toolbar-note">هيكل T‑10 · لا تغييرات تُحفظ</span>
        </nav>
      )}

      {page === null ? (
        <main className="mol-editor-no-pages">
          <h1>لا توجد صفحات في هذا الفصل</h1>
          <p>أضف صفحات من إدارة المحتوى أولًا، ثم عُد إلى المحرر.</p>
          <a href={data.links.work}>العودة إلى العمل</a>
        </main>
      ) : (
        <div className={`mol-editor-layout${state.layersCollapsed ? ' has-collapsed-layers' : ''}`}>
          {!state.preview && (
            <LayersPanel
              page={page}
              selectedId={state.selectedElementId}
              collapsed={state.layersCollapsed}
              onSelect={(id) => dispatch({ type: 'select-element', id })}
              onToggle={() => dispatch({ type: 'toggle-layers' })}
            />
          )}

          <main className="mol-editor-main">
            <PageStage
              page={page}
              zoom={state.zoom}
              selectedId={state.selectedElementId}
              preview={state.preview}
              onSelect={(id) => dispatch({ type: 'select-element', id })}
            />
            <div className="mol-editor-stage-controls">
              <div role="group" aria-label="التنقل بين الصفحات">
                <button
                  type="button"
                  data-testid="previous-page"
                  disabled={state.pagePosition <= 0}
                  onClick={() => goToPage(state.pagePosition - 1)}
                >السابق</button>
                <span>{state.pagePosition + 1} / {data.pages.length}</span>
                <button
                  type="button"
                  data-testid="next-page"
                  disabled={state.pagePosition >= data.pages.length - 1}
                  onClick={() => goToPage(state.pagePosition + 1)}
                >التالي</button>
              </div>
              {!state.preview && (
                <div role="group" aria-label="تكبير مساحة العمل">
                  <button type="button" aria-label="تصغير" onClick={() => dispatch({ type: 'set-zoom', zoom: state.zoom - 0.25 })}>−</button>
                  <output>{Math.round(state.zoom * 100)}%</output>
                  <button type="button" aria-label="تكبير" onClick={() => dispatch({ type: 'set-zoom', zoom: state.zoom + 0.25 })}>＋</button>
                  <button type="button" onClick={() => dispatch({ type: 'set-zoom', zoom: 1 })}>ملاءمة</button>
                </div>
              )}
            </div>
          </main>

          {!state.preview && <PropertiesPanel element={selectedElement} />}
        </div>
      )}
      <span className="mol-editor-release" aria-hidden="true">Core {data.release.core}</span>
    </div>
  );
}
