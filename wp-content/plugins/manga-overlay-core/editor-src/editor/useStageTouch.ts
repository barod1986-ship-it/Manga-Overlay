import { useEffect, useLayoutEffect, useRef, type RefObject } from 'react';
import { anchorScroll, clampZoom, distance, imageAnchor, midpoint, type Point } from './viewportGeometry';

interface Options {
  viewport: RefObject<HTMLDivElement | null>; stage: RefObject<HTMLDivElement | null>;
  zoom: number; width: number; preview: boolean; onZoom: (zoom: number) => void; cancel: () => void;
}
interface Pinch { ids: [number, number]; distance: number; zoom: number; anchor: Point }
const point = (touch: Touch): Point => ({ x: touch.clientX, y: touch.clientY });
export function useStageTouch(options: Options) {
  const latest = useRef(options); latest.current = options;
  const ownsGesture = useRef(false);
  const pending = useRef<{ anchor: Point; point: Point } | null>(null);
  const applyAnchor = () => {
    const { viewport, stage } = latest.current;
    if (!pending.current || !viewport.current || !stage.current) return;
    const delta = anchorScroll(pending.current.anchor, pending.current.point, stage.current.getBoundingClientRect());
    viewport.current.scrollLeft += delta.x; viewport.current.scrollTop += delta.y;
    pending.current = null;
  };
  useLayoutEffect(applyAnchor, [options.zoom, options.width]);
  useEffect(() => {
    const node = options.viewport.current!;
    let active = false, pinch: Pinch | null = null, pan: { start: Point; left: number; top: number } | null = null;
    let suppressClickUntil = 0;
    const stop = (event: TouchEvent) => { if (event.cancelable) event.preventDefault(); event.stopPropagation(); };
    const claim = () => { ownsGesture.current = true; latest.current.cancel(); };
    const start = (event: TouchEvent) => {
      if (latest.current.preview) return;
      // Ignore fingers outside this viewport, including the independently scrolling sheet.
      if (!active) {
        if (!event.touches.length) return;
        active = true;
        const editable = event.target instanceof Element && event.target.closest('.mol-element-selected, .moveable-control, .moveable-line');
        pan = editable || event.touches.length !== 1 ? null : { start: point(event.touches[0]), left: node.scrollLeft, top: node.scrollTop };
      }
      if (event.touches.length >= 2) {
        const touches = Array.from(event.touches);
        if (!touches.every(touch => touch.target instanceof Node && node.contains(touch.target))) return;
        if (!pinch && latest.current.stage.current) {
          const [a, b] = touches;
          pinch = { ids: [a.identifier, b.identifier], distance: Math.max(1, distance(point(a), point(b))), zoom: latest.current.zoom,
            anchor: imageAnchor(midpoint(point(a), point(b)), latest.current.stage.current.getBoundingClientRect()) };
          pan = null; claim();
        }
      }
      if (ownsGesture.current) stop(event);
    };
    const move = (event: TouchEvent) => {
      if (!active) return;
      if (pinch) {
        const a = Array.from(event.touches).find(touch => touch.identifier === pinch!.ids[0]);
        const b = Array.from(event.touches).find(touch => touch.identifier === pinch!.ids[1]);
        if (a && b) {
          const zoom = clampZoom(pinch.zoom * distance(point(a), point(b)) / pinch.distance);
          pending.current = { anchor: pinch.anchor, point: midpoint(point(a), point(b)) };
          if (Math.abs(zoom - latest.current.zoom) < .00001) applyAnchor();
          else latest.current.onZoom(zoom);
        }
      } else if (pan && event.touches.length === 1) {
        const current = point(event.touches[0]);
        if (!ownsGesture.current && distance(current, pan.start) > 6) claim();
        if (ownsGesture.current) { node.scrollLeft = pan.left + pan.start.x - current.x; node.scrollTop = pan.top + pan.start.y - current.y; }
      }
      if (ownsGesture.current) stop(event);
    };
    const end = (event: TouchEvent) => {
      if (!active) return;
      if (ownsGesture.current) { stop(event); suppressClickUntil = Date.now() + 500; }
      // After pinch, a remaining finger cannot become a new element drag or tap.
      if (!event.touches.length || event.type === 'touchcancel') {
        active = false; pinch = null; pan = null; ownsGesture.current = false;
        if (event.type === 'touchcancel') { pending.current = null; latest.current.cancel(); }
      }
    };
    const click = (event: MouseEvent) => {
      if (Date.now() < suppressClickUntil) { event.preventDefault(); event.stopPropagation(); }
    };
    const reset = () => { active = false; pinch = null; pan = null; pending.current = null; ownsGesture.current = false; latest.current.cancel(); };
    node.addEventListener('touchstart', start, { capture: true, passive: false });
    // Capture before Moveable's document listeners, including a second finger on its handles.
    window.addEventListener('touchmove', move, { capture: true, passive: false });
    window.addEventListener('touchend', end, { capture: true, passive: false });
    window.addEventListener('touchcancel', end, { capture: true, passive: false });
    node.addEventListener('click', click, true); window.addEventListener('blur', reset);
    return () => {
      node.removeEventListener('touchstart', start, true); window.removeEventListener('touchmove', move, true);
      window.removeEventListener('touchend', end, true); window.removeEventListener('touchcancel', end, true);
      node.removeEventListener('click', click, true); window.removeEventListener('blur', reset); reset();
    };
  }, [options.viewport, options.preview]);
  return ownsGesture;
}
