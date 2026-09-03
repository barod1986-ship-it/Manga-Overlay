import './styles.css';
import { SAMPLE_ELEMENTS } from './fixtures';
import { OverlayRenderer } from './renderer';

function requireElement<T extends Element>(selector: string): T {
  const element = document.querySelector<T>(selector);
  if (element === null) {
    throw new Error(`Required element is missing: ${selector}`);
  }
  return element;
}

const layer = requireElement<HTMLElement>('#overlay-layer');
const frame = requireElement<HTMLElement>('#page-frame');
const image = requireElement<HTMLImageElement>('#page-image');
const toggle = requireElement<HTMLButtonElement>('#translation-toggle');
const viewportReadout = requireElement<HTMLElement>('#viewport-readout');
const renderer = new OverlayRenderer(layer, frame, image, SAMPLE_ELEMENTS);
let translationVisible = true;

function updateReadout(): void {
  viewportReadout.textContent = `عرض الصورة الآن: ${Math.round(image.clientWidth)}px`;
}

toggle.addEventListener('click', () => {
  translationVisible = !translationVisible;
  renderer.setTranslationVisible(translationVisible);
  toggle.setAttribute('aria-pressed', String(translationVisible));
  toggle.textContent = translationVisible ? 'الترجمة ظاهرة' : 'الترجمة مخفية';
});

const readoutObserver = new ResizeObserver(updateReadout);
readoutObserver.observe(frame);
renderer.mount();
updateReadout();

window.addEventListener('pagehide', () => {
  readoutObserver.disconnect();
  renderer.destroy();
});
