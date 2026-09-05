import React, { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import Moveable, {
  type OnDrag,
  type OnDragStart,
  type OnResize,
  type OnResizeStart,
  type OnRotate,
  type OnRotateStart,
} from 'react-moveable';
import { OverlayRenderer } from '@mol/poc-renderer';
import { resolvedStyle } from './elementModel';
import {
  moveElementByPixels,
  resizeElementFromPixels,
  rotateElementToDegrees,
  type ResizeDraft,
  type StageSize,
} from './transform';
import type { EditorElement, EditorPageData } from './types';

interface EditorStageProps {
  readonly page: EditorPageData;
  readonly elements: readonly EditorElement[];
  readonly zoom: number;
  readonly selectedId: number | null;
  readonly preview: boolean;
  readonly readOnlySelected: boolean;
  readonly onSelect: (id: number) => void;
  readonly onDeselect: () => void;
  readonly onEditText: (id: number) => void;
  readonly onCommit: (id: number, update: (element: EditorElement) => EditorElement) => void;
}

interface DragDraft {
  readonly id: number;
  readonly translateX: number;
  readonly translateY: number;
}

interface RotateDraft {
  readonly id: number;
  readonly initialDegrees: number;
  readonly degrees: number;
}

interface ActiveResizeDraft extends ResizeDraft {
  readonly id: number;
  readonly initialWidth: number;
  readonly initialHeight: number;
}

const TRANSFORM_EPSILON = 0.01;

function isEffectivelyZero(value: number): boolean {
  return Math.abs(value) <= TRANSFORM_EPSILON;
}

export function EditorStage({
  page,
  elements,
  zoom,
  selectedId,
  preview,
  readOnlySelected,
  onSelect,
  onDeselect,
  onEditText,
  onCommit,
}: EditorStageProps) {
  const stageRef = useRef<HTMLDivElement>(null);
  const imageRef = useRef<HTMLImageElement>(null);
  const layerRef = useRef<HTMLDivElement>(null);
  const rendererRef = useRef<OverlayRenderer | null>(null);
  const dragDraftRef = useRef<DragDraft | null>(null);
  const resizeDraftRef = useRef<ActiveResizeDraft | null>(null);
  const rotateDraftRef = useRef<RotateDraft | null>(null);
  const [target, setTarget] = useState<HTMLElement | null>(null);
  const selectedElement = elements.find((element) => element.id === selectedId) ?? null;

  const refreshTarget = useCallback((): void => {
    const layer = layerRef.current;
    if (layer === null || selectedId === null || preview || readOnlySelected) {
      setTarget(null);
      return;
    }
    setTarget(layer.querySelector<HTMLElement>(`[data-element-id="${selectedId}"]`));
  }, [preview, readOnlySelected, selectedId]);

  useLayoutEffect(() => {
    const layer = layerRef.current;
    const stage = stageRef.current;
    const image = imageRef.current;
    if (layer === null || stage === null || image === null) return undefined;
    const renderer = new OverlayRenderer(layer, stage, image, elements, selectedId);
    rendererRef.current = renderer;
    renderer.mount();
    refreshTarget();
    return () => {
      renderer.destroy();
      rendererRef.current = null;
    };
  }, [page.id]);

  useLayoutEffect(() => {
    rendererRef.current?.setElements(elements, selectedId);
    refreshTarget();
  }, [elements, selectedId, refreshTarget]);

  useEffect(() => {
    const layer = layerRef.current;
    if (layer === null) return undefined;
    const observer = new MutationObserver(refreshTarget);
    observer.observe(layer, { childList: true });
    return () => observer.disconnect();
  }, [refreshTarget]);

  useEffect(() => {
    const layer = layerRef.current;
    if (layer === null) return undefined;
    const selectFromEvent = (event: Event): number | null => {
      const origin = event.target;
      if (!(origin instanceof Element)) return null;
      const id = Number(origin.closest<HTMLElement>('[data-element-id]')?.dataset.elementId);
      return Number.isInteger(id) ? id : null;
    };
    const handlePointerDown = (event: PointerEvent): void => {
      const id = selectFromEvent(event);
      if (id !== null) onSelect(id);
    };
    const handleDoubleClick = (event: MouseEvent): void => {
      const id = selectFromEvent(event);
      if (id !== null && !(readOnlySelected && id === selectedId)) onEditText(id);
    };
    layer.addEventListener('pointerdown', handlePointerDown);
    layer.addEventListener('dblclick', handleDoubleClick);
    return () => {
      layer.removeEventListener('pointerdown', handlePointerDown);
      layer.removeEventListener('dblclick', handleDoubleClick);
    };
  }, [onEditText, onSelect, readOnlySelected, selectedId]);

  const currentStageSize = (): StageSize => ({
    width: stageRef.current?.clientWidth ?? 0,
    height: stageRef.current?.clientHeight ?? 0,
  });

  const selectedTransform = (): string => {
    if (selectedElement === null) return '';
    const style = resolvedStyle(selectedElement);
    return `rotate(${selectedElement.rotation_mdeg / 1_000}deg) scale(${style.scaleX ?? 1}, ${style.scaleY ?? 1})`;
  };

  const handleDragStart = (event: OnDragStart): void => {
    if (selectedElement === null) return;
    event.set([0, 0]);
    event.setTransform(selectedTransform());
    dragDraftRef.current = { id: selectedElement.id, translateX: 0, translateY: 0 };
  };

  const handleDrag = (event: OnDrag): void => {
    const [translateX = 0, translateY = 0] = event.beforeTranslate;
    event.target.style.transform = event.transform;
    if (selectedElement !== null) {
      dragDraftRef.current = { id: selectedElement.id, translateX, translateY };
    }
  };

  const handleDragEnd = (): void => {
    const draft = dragDraftRef.current;
    dragDraftRef.current = null;
    const size = currentStageSize();
    if (draft === null || size.width <= 0 || size.height <= 0) return;
    if (isEffectivelyZero(draft.translateX) && isEffectivelyZero(draft.translateY)) return;
    onCommit(draft.id, (element) => moveElementByPixels(element, draft.translateX, draft.translateY, size));
  };

  const handleResizeStart = (event: OnResizeStart): void => {
    if (selectedElement === null) return;
    event.setOrigin(['50%', '50%']);
    if (event.dragStart !== false) {
      event.dragStart.set([0, 0]);
      event.dragStart.setTransform(selectedTransform());
    }
    resizeDraftRef.current = {
      id: selectedElement.id,
      initialWidth: event.target.clientWidth,
      initialHeight: event.target.clientHeight,
      translateX: 0,
      translateY: 0,
      width: event.target.clientWidth,
      height: event.target.clientHeight,
    };
  };

  const handleResize = (event: OnResize): void => {
    const [translateX = 0, translateY = 0] = event.drag.beforeTranslate;
    event.target.style.width = `${event.width}px`;
    event.target.style.height = `${event.height}px`;
    event.target.style.transform = event.drag.transform;
    if (selectedElement !== null) {
      resizeDraftRef.current = {
        id: selectedElement.id,
        initialWidth: resizeDraftRef.current?.initialWidth ?? event.target.clientWidth,
        initialHeight: resizeDraftRef.current?.initialHeight ?? event.target.clientHeight,
        translateX,
        translateY,
        width: event.width,
        height: event.height,
      };
    }
  };

  const handleResizeEnd = (): void => {
    const draft = resizeDraftRef.current;
    resizeDraftRef.current = null;
    const size = currentStageSize();
    if (draft === null || size.width <= 0 || size.height <= 0) return;
    if (
      isEffectivelyZero(draft.translateX)
      && isEffectivelyZero(draft.translateY)
      && isEffectivelyZero(draft.width - draft.initialWidth)
      && isEffectivelyZero(draft.height - draft.initialHeight)
    ) return;
    onCommit(draft.id, (element) => resizeElementFromPixels(element, draft, size));
  };

  const handleRotateStart = (event: OnRotateStart): void => {
    if (selectedElement === null) return;
    const degrees = selectedElement.rotation_mdeg / 1_000;
    event.set(degrees);
    rotateDraftRef.current = { id: selectedElement.id, initialDegrees: degrees, degrees };
  };

  const handleRotate = (event: OnRotate): void => {
    if (selectedElement === null) return;
    const style = resolvedStyle(selectedElement);
    event.target.style.transform = `rotate(${event.rotation}deg) scale(${style.scaleX ?? 1}, ${style.scaleY ?? 1})`;
    rotateDraftRef.current = {
      id: selectedElement.id,
      initialDegrees: rotateDraftRef.current?.initialDegrees ?? selectedElement.rotation_mdeg / 1_000,
      degrees: event.rotation,
    };
  };

  const handleRotateEnd = (): void => {
    const draft = rotateDraftRef.current;
    rotateDraftRef.current = null;
    if (draft !== null && !isEffectivelyZero(draft.degrees - draft.initialDegrees)) {
      onCommit(draft.id, (element) => rotateElementToDegrees(element, draft.degrees));
    }
  };

  const ratio = page.natural_width > 0 && page.natural_height > 0
    ? `${page.natural_width} / ${page.natural_height}`
    : '2 / 3';

  return (
    <div className="mol-editor-stage-scroll" data-testid="stage-scroll">
      <div
        ref={stageRef}
        className="mol-editor-stage"
        data-testid="editor-stage"
        style={{
          aspectRatio: ratio,
          width: `${zoom * 100}%`,
          maxWidth: `${Math.max(page.natural_width, 1) * zoom}px`,
        }}
        onPointerDown={(event) => {
          if (event.target === event.currentTarget || event.target === imageRef.current) onDeselect();
        }}
      >
        {page.image.url === '' ? (
          <div className="mol-editor-image-missing" role="img" aria-label="صورة الصفحة غير متاحة">
            صورة الصفحة غير متاحة
          </div>
        ) : (
          <img
            ref={imageRef}
            src={page.image.url}
            srcSet={page.image.srcset ?? undefined}
            sizes={page.image.sizes ?? undefined}
            width={page.image.width}
            height={page.image.height}
            alt={page.image.alt ?? `صفحة ${page.page_index + 1}`}
            draggable={false}
          />
        )}
        <div
          ref={layerRef}
          className={`mol-overlay-layer mol-editor-overlay${preview ? ' is-preview' : ''}`}
          data-testid="overlay-layer"
        />
        {!preview && !readOnlySelected && target !== null ? (
          <Moveable
            target={target}
            container={stageRef.current}
            draggable
            resizable
            rotatable
            snappable={false}
            renderDirections={['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w']}
            rotationPosition="top"
            origin={false}
            keepRatio={false}
            useResizeObserver
            linePadding={12}
            controlPadding={14}
            onDragStart={handleDragStart}
            onDrag={handleDrag}
            onDragEnd={handleDragEnd}
            onResizeStart={handleResizeStart}
            onResize={handleResize}
            onResizeEnd={handleResizeEnd}
            onRotateStart={handleRotateStart}
            onRotate={handleRotate}
            onRotateEnd={handleRotateEnd}
          />
        ) : null}
      </div>
    </div>
  );
}
