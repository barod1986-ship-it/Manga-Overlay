export interface Point { x: number; y: number }
export interface ViewBox { left: number; top: number; width: number; height: number }
export const clampZoom = (zoom: number) => Number.isFinite(zoom) ? Math.max(.5, Math.min(3, zoom)) : 1;
export const midpoint = (a: Point, b: Point): Point => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });
export const distance = (a: Point, b: Point) => Math.hypot(a.x - b.x, a.y - b.y);
export function imageAnchor(point: Point, box: ViewBox): Point {
  return { x: (point.x - box.left) / Math.max(1, box.width), y: (point.y - box.top) / Math.max(1, box.height) };
}
// Scroll by this delta after layout to keep the same image point below the fingers.
// The browser clamps only at the actual scroll boundary; stored overlay units never change.
export function anchorScroll(anchor: Point, point: Point, box: ViewBox): Point {
  return { x: box.left + anchor.x * box.width - point.x, y: box.top + anchor.y * box.height - point.y };
}
