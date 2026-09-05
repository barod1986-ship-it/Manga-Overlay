import {
  useCallback,
  useEffect,
  useLayoutEffect,
  useRef,
  useState,
  type PointerEvent as ReactPointerEvent,
} from 'react';
import Moveable, {
  type OnDrag,
  type OnDragStart,
  type OnResize,
  type OnResizeStart,
  type OnRotate,
  type OnRotateStart,
} from 'react-moveable';
import { OverlayRenderer, type OverlayElement } from '@mol/poc-renderer';
import {
  clampStageZoom,
  moveElementByPixels,
  panStageScroll,
  resizeElementFromPixels,
  rotateElementToDegrees,
  scaleStageZoom,
  type ResizeDraft,
  type StageSize,
} from './transform';

interface EditorStageProps {
  readonly elements: readonly OverlayElement[];
  readonly selectedId: number | null;
  readonly preview: boolean;
  readonly zoom: number;
  readonly onSelect: (id: number) => void;
  readonly onCommit: (id: number, update: (element: OverlayElement) => OverlayElement) => void;
  readonly onZoomChange: (zoom: number) => void;
}

interface DragDraft {
  readonly id: number;
  readonly translateX: number;
  readonly translateY: number;
}

interface RotateDraft {
  readonly id: number;
  readonly degrees: number;
}

interface ActiveResizeDraft extends ResizeDraft {
  readonly id: number;
}

interface Point {
  readonly x: number;
  readonly y: number;
}

interface PanDraft {
  readonly pointerId: number;
  readonly point: Point;
  readonly scrollLeft: number;
  readonly scrollTop: number;
}

function pointDistance(first: Point, second: Point): number {
  return Math.hypot(second.x - first.x, second.y - first.y);
}

export function EditorStage({
  elements,
  selectedId,
  preview,
  zoom,
  onSelect,
  onCommit,
  onZoomChange,
}: EditorStageProps) {
  const frameRef = useRef<HTMLElement>(null);
  const imageRef = useRef<HTMLImageElement>(null);
  const layerRef = useRef<HTMLDivElement>(null);
  const viewportRef = useRef<HTMLDivElement>(null);
  const rendererRef = useRef<OverlayRenderer | null>(null);
  const dragDraftRef = useRef<DragDraft | null>(null);
  const resizeDraftRef = useRef<ActiveResizeDraft | null>(null);
  const rotateDraftRef = useRef<RotateDraft | null>(null);
  const touchPointsRef = useRef(new Map<number, Point>());
  const pinchStartRef = useRef<{ distance: number; zoom: number } | null>(null);
  const panStartRef = useRef<PanDraft | null>(null);
  const [target, setTarget] = useState<HTMLElement | null>(null);
  const [elementGuidelines, setElementGuidelines] = useState<HTMLElement[]>([]);
  const [stageSize, setStageSize] = useState<StageSize>({ width: 0, height: 0 });
  const [snappingEnabled, setSnappingEnabled] = useState(true);
  const [isPanning, setIsPanning] = useState(false);
  const selectedElement = elements.find((element) => element.id === selectedId) ?? null;

  const refreshMoveableTargets = useCallback(() => {
    const layer = layerRef.current;
    if (layer === null) {
      setTarget(null);
      setElementGuidelines([]);
      return;
    }

    const nextTarget = selectedId === null
      ? null
      : layer.querySelector<HTMLElement>(`[data-element-id="${selectedId}"]`);
    const guidelines = Array.from(layer.querySelectorAll<HTMLElement>('.mol-overlay-element'))
      .filter((element) => element !== nextTarget);

    setTarget(nextTarget);
    setElementGuidelines(guidelines);
    setStageSize({ width: layer.clientWidth, height: layer.clientHeight });
  }, [selectedId]);

  useLayoutEffect(() => {
    const layer = layerRef.current;
    const frame = frameRef.current;
    const image = imageRef.current;
    if (layer === null || frame === null || image === null) {
      return undefined;
    }

    const renderer = new OverlayRenderer(layer, frame, image, elements);
    rendererRef.current = renderer;
    renderer.mount();

    return () => {
      renderer.destroy();
      rendererRef.current = null;
    };
  }, []);

  useLayoutEffect(() => {
    rendererRef.current?.setElements(elements);
    refreshMoveableTargets();
  }, [elements, refreshMoveableTargets]);

  useEffect(() => {
    const layer = layerRef.current;
    if (layer === null) {
      return undefined;
    }

    const observer = new MutationObserver(refreshMoveableTargets);
    observer.observe(layer, { childList: true });
    return () => observer.disconnect();
  }, [refreshMoveableTargets]);

  useEffect(() => {
    const layer = layerRef.current;
    if (layer === null) {
      return undefined;
    }

    const handlePointerDown = (event: PointerEvent) => {
      const origin = event.target;
      if (!(origin instanceof Element)) {
        return;
      }
      const element = origin.closest<HTMLElement>('[data-element-id]');
      const id = Number(element?.dataset.elementId);
      if (Number.isInteger(id)) {
        onSelect(id);
      }
    };

    layer.addEventListener('pointerdown', handlePointerDown);
    return () => layer.removeEventListener('pointerdown', handlePointerDown);
  }, [onSelect]);

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent): void => {
      if (event.key === 'Alt') {
        setSnappingEnabled(false);
      }
    };
    const restoreSnapping = (): void => setSnappingEnabled(true);

    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('keyup', restoreSnapping);
    window.addEventListener('blur', restoreSnapping);
    return () => {
      window.removeEventListener('keydown', handleKeyDown);
      window.removeEventListener('keyup', restoreSnapping);
      window.removeEventListener('blur', restoreSnapping);
    };
  }, []);

  const currentStageSize = (): StageSize => {
    const layer = layerRef.current;
    return { width: layer?.clientWidth ?? 0, height: layer?.clientHeight ?? 0 };
  };

  const handleDragStart = (event: OnDragStart): void => {
    if (selectedElement === null) {
      return;
    }
    event.set([0, 0]);
    event.setTransform(`rotate(${selectedElement.rotation_mdeg / 1_000}deg)`);
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
    if (draft === null) {
      return;
    }
    const size = currentStageSize();
    if (size.width <= 0 || size.height <= 0) {
      return;
    }
    onCommit(draft.id, (element) => moveElementByPixels(element, draft.translateX, draft.translateY, size));
  };

  const handleResizeStart = (event: OnResizeStart): void => {
    if (selectedElement === null) {
      return;
    }
    event.setOrigin(['50%', '50%']);
    if (event.dragStart !== false) {
      event.dragStart.set([0, 0]);
    }
    resizeDraftRef.current = {
      id: selectedElement.id,
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
    if (draft === null) {
      return;
    }
    const size = currentStageSize();
    if (size.width <= 0 || size.height <= 0) {
      return;
    }
    onCommit(draft.id, (element) => resizeElementFromPixels(element, draft, size));
  };

  const handleRotateStart = (event: OnRotateStart): void => {
    if (selectedElement === null) {
      return;
    }
    const degrees = selectedElement.rotation_mdeg / 1_000;
    event.set(degrees);
    rotateDraftRef.current = { id: selectedElement.id, degrees };
  };

  const handleRotate = (event: OnRotate): void => {
    event.target.style.transform = `rotate(${event.rotation}deg)`;
    if (selectedElement !== null) {
      rotateDraftRef.current = { id: selectedElement.id, degrees: event.rotation };
    }
  };

  const handleRotateEnd = (): void => {
    const draft = rotateDraftRef.current;
    rotateDraftRef.current = null;
    if (draft !== null) {
      onCommit(draft.id, (element) => rotateElementToDegrees(element, draft.degrees));
    }
  };

  const handleStagePointerDown = (event: ReactPointerEvent<HTMLDivElement>): void => {
    if (event.pointerType !== 'touch') {
      return;
    }
    const origin = event.target;
    if (origin instanceof Element && origin.closest('.mol-overlay-element, .moveable-control-box') !== null) {
      return;
    }

    touchPointsRef.current.set(event.pointerId, { x: event.clientX, y: event.clientY });
    try {
      event.currentTarget.setPointerCapture(event.pointerId);
    } catch {
      // Synthetic pointers and a few older engines can reject capture; the
      // gesture still works while the pointer remains inside the viewport.
    }
    const points = Array.from(touchPointsRef.current.values());
    if (points.length === 2 && points[0] !== undefined && points[1] !== undefined) {
      pinchStartRef.current = { distance: pointDistance(points[0], points[1]), zoom };
      panStartRef.current = null;
      setIsPanning(false);
    } else if (points.length === 1 && zoom > 1) {
      panStartRef.current = {
        pointerId: event.pointerId,
        point: points[0] ?? { x: event.clientX, y: event.clientY },
        scrollLeft: event.currentTarget.scrollLeft,
        scrollTop: event.currentTarget.scrollTop,
      };
    }
  };

  const handleStagePointerMove = (event: ReactPointerEvent<HTMLDivElement>): void => {
    if (!touchPointsRef.current.has(event.pointerId)) {
      return;
    }
    touchPointsRef.current.set(event.pointerId, { x: event.clientX, y: event.clientY });
    const points = Array.from(touchPointsRef.current.values());
    const pinchStart = pinchStartRef.current;
    if (points.length === 2 && points[0] !== undefined && points[1] !== undefined && pinchStart !== null) {
      event.preventDefault();
      onZoomChange(scaleStageZoom(
        pinchStart.zoom,
        pinchStart.distance,
        pointDistance(points[0], points[1]),
      ));
      return;
    }

    const panStart = panStartRef.current;
    const viewport = viewportRef.current;
    if (points.length === 1 && panStart !== null && viewport !== null && panStart.pointerId === event.pointerId) {
      event.preventDefault();
      const scroll = panStageScroll(
        panStart.scrollLeft,
        panStart.scrollTop,
        event.clientX - panStart.point.x,
        event.clientY - panStart.point.y,
      );
      viewport.scrollLeft = scroll.left;
      viewport.scrollTop = scroll.top;
      setIsPanning(true);
    }
  };

  const handleStagePointerEnd = (event: ReactPointerEvent<HTMLDivElement>): void => {
    touchPointsRef.current.delete(event.pointerId);
    pinchStartRef.current = null;
    const remaining = Array.from(touchPointsRef.current.entries());
    const nextPointer = remaining[0];
    const viewport = viewportRef.current;
    if (remaining.length === 1 && nextPointer !== undefined && viewport !== null && zoom > 1) {
      panStartRef.current = {
        pointerId: nextPointer[0],
        point: nextPointer[1],
        scrollLeft: viewport.scrollLeft,
        scrollTop: viewport.scrollTop,
      };
    } else {
      panStartRef.current = null;
      setIsPanning(false);
    }
    if (event.currentTarget.hasPointerCapture(event.pointerId)) {
      event.currentTarget.releasePointerCapture(event.pointerId);
    }
  };

  const fitStageWidth = (): void => {
    const viewport = viewportRef.current;
    const frame = frameRef.current;
    if (viewport === null || frame === null || frame.offsetWidth <= 0) {
      return;
    }

    const style = window.getComputedStyle(viewport);
    const horizontalPadding = Number.parseFloat(style.paddingInlineStart)
      + Number.parseFloat(style.paddingInlineEnd);
    const availableWidth = Math.max(viewport.clientWidth - horizontalPadding, 1);
    onZoomChange(clampStageZoom(availableWidth / frame.offsetWidth));
  };

  return (
    <section className="mol-stage-section" aria-label="مساحة تحرير صفحة المانجا">
      <div className="mol-stage-zoom-controls" aria-label="تكبير مساحة العمل">
        <button type="button" data-zoom-action="out" onClick={() => onZoomChange(clampStageZoom(zoom - 0.1))} aria-label="تصغير">−</button>
        <output data-testid="stage-zoom">{Math.round(zoom * 100)}%</output>
        <button type="button" data-zoom-action="in" onClick={() => onZoomChange(clampStageZoom(zoom + 0.1))} aria-label="تكبير">+</button>
        <button type="button" data-zoom-action="reset" onClick={() => onZoomChange(1)}>100%</button>
        <button type="button" data-zoom-action="fit" onClick={fitStageWidth}>ملاءمة</button>
      </div>

      <div
        ref={viewportRef}
        className="mol-stage-viewport"
        data-zoom={zoom.toFixed(2)}
        data-panning={isPanning}
        onPointerDown={handleStagePointerDown}
        onPointerMove={handleStagePointerMove}
        onPointerUp={handleStagePointerEnd}
        onPointerCancel={handleStagePointerEnd}
      >
        <div className="mol-stage-canvas" style={{ transform: `scale(${zoom})` }}>
          <figure ref={frameRef} className="mol-editor-page-frame">
            <img
              ref={imageRef}
              src="./sample-page.svg"
              width="1200"
              height="1800"
              alt="صفحة مانجا تجريبية أصلية"
            />
            <div ref={layerRef} className={`mol-overlay-layer mol-editor-overlay${preview ? ' is-preview' : ''}`} />
            {!preview && target !== null ? (
              <Moveable
                target={target}
                container={frameRef.current}
                draggable
                resizable
                rotatable
                pinchable
                snappable={snappingEnabled}
                snapContainer={frameRef.current}
                snapDirections={{ left: true, top: true, right: true, bottom: true, center: true, middle: true }}
                elementSnapDirections={{ left: true, top: true, right: true, bottom: true, center: true, middle: true }}
                elementGuidelines={elementGuidelines}
                verticalGuidelines={[0, stageSize.width / 2, stageSize.width]}
                horizontalGuidelines={[0, stageSize.height / 2, stageSize.height]}
                snapThreshold={Math.max(3, 6 / zoom)}
                isDisplaySnapDigit={false}
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
          </figure>
        </div>
      </div>
    </section>
  );
}
