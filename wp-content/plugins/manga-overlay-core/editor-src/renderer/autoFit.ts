/** Largest fitting font in pixels; all measurements stay inside this one element. */
export function fitFont(requested: number, minimum: number, fits: (size: number) => boolean): number {
  let low = Math.min(minimum, requested);
  let high = requested;
  if (fits(requested)) return requested;
  if (!fits(low)) return low;
  for (let index = 0; index < 14; index += 1) {
    const mid = (low + high) / 2;
    if (fits(mid)) low = mid;
    else high = mid;
  }
  return low;
}
