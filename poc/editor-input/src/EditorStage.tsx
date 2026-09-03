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
  moveElementByPixels,
  resizeElementFromPixels,
  rotateElementToDegrees,
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

function pointDistance(first: Point, second: Point): number {
  return Math.hypot(second.x - first.x, second.y - first.y);
}

function clampZoom(value: number): number {
  return Math.min(Math.max(value, 0.65), 2.25);
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
  const [target, setTarget] = useState<HTMLElement | null>(null);
  const [elementGuidelines, setElementGuidelines] = useState<HTMLElement[]>([]);
  const [stageSize, setStageSize] = useState<StageSize>({ width: 0, height: 0 });
  const [snappingEnabled, setSnappingEnabled] = useState(true);
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
    event.currentTarget.setPointerCapture(event.pointerId);
    const points = Array.from(touchPointsRef.current.values());
    if (points.length === 2 && points[0] !== undefined && points[1] !== undefined) {
      pinchStartRef.current = { distance: pointDistance(points[0], points[1]), zoom };
    }
  };

  const handleStagePointerMove = (event: ReactPointerEvent<HTMLDivElement>): void => {
    if (!touchPointsRef.current.has(event.pointerId)) {
      return;
    }
    touchPointsRef.current.set(event.pointerId, { x: event.clientX, y: event.clientY });
    const points = Array.from(touchPointsRef.current.values());
    const pinchStart = pinchStartRef.current;
    if (points.length !== 2 || points[0] === undefined || points[1] === undefined || pinchStart === null) {
      return;
    }
    event.preventDefault();
    const distance = pointDistance(points[0], points[1]);
    if (pinchStart.distance > 0) {
      onZoomChange(clampZoom(pinchStart.zoom * (distance / pinchStart.distance)));
    }
  };

  const handleStagePointerEnd = (event: ReactPointerEvent<HTMLDivElement>): void => {
    touchPointsRef.current.delete(event.pointerId);
    pinchStartRef.current = null;
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
    onZoomChange(clampZoom(availableWidth / frame.offsetWidth));
  };

  return (
    <section className="mol-stage-section" aria-label="مساحة تحرير صفحة المانجا">
      <div className="mol-stage-zoom-controls" aria-label="تكبير مساحة العمل">
        <button type="button" data-zoom-action="out" onClick={() => onZoomChange(clampZoom(zoom - 0.1))} aria-label="تصغير">−</button>
        <output data-testid="stage-zoom">{Math.round(zoom * 100)}%</output>
        <button type="button" data-zoom-action="in" onClick={() => onZoomChange(clampZoom(zoom + 0.1))} aria-label="تكبير">+</button>
        <button type="button" data-zoom-action="reset" onClick={() => onZoomChange(1)}>100%</button>
        <button type="button" data-zoom-action="fit" onClick={fitStageWidth}>ملاءمة</button>
      </div>

      <div
        ref={viewportRef}
        className="mol-stage-viewport"
        data-zoom={zoom.toFixed(2)}
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
                linePadding={8}
                controlPadding={10}
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
