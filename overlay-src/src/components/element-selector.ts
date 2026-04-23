import type { NotedConfig } from '../types';
import type { NotedState } from '../state';
import { generateStableSelector } from '../utils/selector';

type Store = {
  get(): NotedState;
  set(p: Partial<NotedState>): void;
};

const IGNORE_TAGS = new Set(['HTML', 'BODY', 'SCRIPT', 'STYLE', 'LINK', 'META', 'HEAD', 'NOSCRIPT']);

export interface ElementSelectorHandle {
  update(state: NotedState): void;
  destroy(): void;
}

export function createElementSelector(
  _config: NotedConfig,
  store: Store,
): ElementSelectorHandle {
  let active = false;
  let highlightedEl: Element | null = null;

  // Overlay element shown on top of hovered element
  const overlay = document.createElement('div');
  overlay.id = 'noted-element-highlight';
  overlay.style.cssText =
    'position:absolute;pointer-events:none;z-index:2147483641;' +
    'border:2px solid #F5A623;' +
    'background:rgba(245,166,35,0.06);border-radius:3px;display:none;' +
    'transition:top 80ms ease,left 80ms ease,width 80ms ease,height 80ms ease;';

  // Label showing the element tag/class
  const label = document.createElement('div');
  label.style.cssText =
    'position:absolute;top:-22px;left:-1px;font-size:10px;font-weight:600;' +
    "font-family:'Inter Tight',system-ui,sans-serif;" +
    'background:#F5A623;color:#fff;' +
    'padding:2px 6px;border-radius:3px 3px 0 0;white-space:nowrap;line-height:1.4;';
  overlay.appendChild(label);

  document.body.appendChild(overlay);

  function onMouseMove(e: MouseEvent) {
    if (!active) return;
    const state = store.get();
    if (state.placingPin) return; // popover is open

    const target = e.target as Element;
    if (!target || target === overlay || overlay.contains(target)) return;
    if (target.id === 'noted-host' || target.hasAttribute('data-noted-pin')) return;
    if (target.id === 'noted-click-overlay') return;
    if (IGNORE_TAGS.has(target.tagName)) { hideHighlight(); return; }

    if (target === highlightedEl) return;
    highlightedEl = target;
    showHighlight(target);
  }

  function onClick(e: MouseEvent) {
    if (!active || !highlightedEl) return;
    const state = store.get();
    if (state.placingPin) return;

    // Ignore clicks that originate from the overlay (toolbar, panel, etc.)
    // In closed Shadow DOM, events retarget to #noted-host
    const clickTarget = e.target as Element;
    if (!clickTarget || clickTarget.id === 'noted-host' || clickTarget.id === 'noted-click-overlay' ||
        clickTarget.hasAttribute('data-noted-pin')) {
      return;
    }

    // Verify the highlighted element is still under the cursor
    const elUnder = document.elementFromPoint(e.clientX, e.clientY);
    if (!elUnder || elUnder !== highlightedEl && !highlightedEl.contains(elUnder)) {
      highlightedEl = null;
      return;
    }

    e.preventDefault();
    e.stopPropagation();

    const el = highlightedEl as HTMLElement;
    const rect = el.getBoundingClientRect();
    const selector = generateStableSelector(el);

    // Place pin at the top-right corner of the element
    const pinX = rect.right;
    const pinY = rect.top;

    const position = {
      css_selector: selector,
      selector_offset_x: 0,
      selector_offset_y: 0,
      x_percent: (pinX + window.scrollX) / document.documentElement.scrollWidth * 100,
      y_percent: (pinY + window.scrollY) / document.documentElement.scrollHeight * 100,
      viewport_width: window.innerWidth,
      scroll_y: window.scrollY,
      breakpoint: state.activeBreakpoint,
    };

    store.set({
      placingPin: { x: pinX, y: pinY, position },
      selectedPinId: null,
    });

    hideHighlight();
  }

  function showHighlight(el: Element) {
    const rect = el.getBoundingClientRect();
    const bpMode = document.body.style.position === 'relative';
    let left: number, top: number;

    if (bpMode) {
      const bodyRect = document.body.getBoundingClientRect();
      left = rect.left - bodyRect.left + document.body.scrollLeft;
      top = rect.top - bodyRect.top + document.body.scrollTop;
    } else {
      left = rect.left + window.scrollX;
      top = rect.top + window.scrollY;
    }

    overlay.style.display = 'block';
    overlay.style.left = `${left}px`;
    overlay.style.top = `${top}px`;
    overlay.style.width = `${rect.width}px`;
    overlay.style.height = `${rect.height}px`;

    // Build label: tag + id/class
    const tag = el.tagName.toLowerCase();
    const id = el.id ? `#${el.id}` : '';
    const cls = !id && el.className && typeof el.className === 'string'
      ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.')
      : '';
    label.textContent = `${tag}${id || cls}`;
  }

  function hideHighlight() {
    overlay.style.display = 'none';
    highlightedEl = null;
  }

  let wasPlacingPin = false;
  let prevBp = '';

  function enable() {
    if (active) return;
    active = true;
    document.addEventListener('mousemove', onMouseMove, true);
    document.addEventListener('click', onClick, true);
  }

  function disable() {
    if (!active) return;
    active = false;
    hideHighlight();
    document.removeEventListener('mousemove', onMouseMove, true);
    document.removeEventListener('click', onClick, true);
  }

  function update(state: NotedState) {
    const shouldBeActive = state.guestPhase === 'ready'
      && state.mode === 'annotate'
      && state.activeTool === 'element';

    if (shouldBeActive && !active) enable();
    else if (!shouldBeActive && active) disable();

    // Clear highlight when breakpoint changes (element positions shift)
    if (state.activeBreakpoint !== prevBp) {
      prevBp = state.activeBreakpoint;
      hideHighlight();
    }

    // Hide highlight when popover opens
    if (state.placingPin && active) {
      hideHighlight();
    }

    // Re-activate after popover closes (pin submitted or cancelled)
    if (wasPlacingPin && !state.placingPin && shouldBeActive) {
      if (!active) enable();
      highlightedEl = null;
    }
    wasPlacingPin = !!state.placingPin;
  }

  function destroy() {
    disable();
    overlay.remove();
  }

  return { update, destroy };
}
