import { useLayoutEffect, type RefObject } from 'react';

export function useMobileViewport(root: RefObject<HTMLDivElement | null>, preview: boolean) {
  useLayoutEffect(() => {
    const node = root.current!;
    const viewport = window.visualViewport;
    const mobile = window.matchMedia('(max-width:800px)');
    let frame = 0;
    const reveal = () => {
      const input = document.activeElement;
      if (!(input instanceof HTMLElement) || !input.matches('input, textarea, select')) return;
      const sheet = input.closest<HTMLElement>('.mol-editor-properties, .mol-editor-layers');
      if (!sheet || !sheet.getClientRects().length) return;
      const bounds = sheet.getBoundingClientRect(), field = input.getBoundingClientRect();
      const header = sheet.querySelector('.mol-editor-panel-title')?.getBoundingClientRect();
      const top = Math.max(bounds.top + 12, (header?.bottom ?? bounds.top) + 8), bottom = bounds.bottom - 12;
      if (field.bottom > bottom) sheet.scrollTop += field.bottom - bottom;
      else if (field.top < top) sheet.scrollTop -= top - field.top;
    };
    const update = () => {
      cancelAnimationFrame(frame);
      if (!mobile.matches) return;
      node.style.setProperty('--mol-visual-height', `${viewport?.height ?? window.innerHeight}px`);
      node.style.setProperty('--mol-visual-top', `${viewport?.offsetTop ?? 0}px`);
      const toolbar = node.querySelector('.mol-editor-bottom');
      node.style.setProperty('--mol-toolbar-height', `${toolbar?.getBoundingClientRect().height ?? 0}px`);
      frame = requestAnimationFrame(reveal);
    };
    const observer = new ResizeObserver(update);
    observer.observe(node);
    const toolbar = node.querySelector('.mol-editor-bottom');
    if (toolbar) observer.observe(toolbar);
    window.addEventListener('resize', update); viewport?.addEventListener('resize', update); viewport?.addEventListener('scroll', update);
    mobile.addEventListener('change', update); node.addEventListener('focusin', update); update();
    return () => {
      cancelAnimationFrame(frame); observer.disconnect(); window.removeEventListener('resize', update);
      viewport?.removeEventListener('resize', update); viewport?.removeEventListener('scroll', update);
      mobile.removeEventListener('change', update); node.removeEventListener('focusin', update);
    };
  }, [root, preview]);
}
