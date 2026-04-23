import { h } from '../utils/dom';
import type { WalkthroughStep } from './WalkthroughStep';

const TOOLBAR_HEIGHT = 52;

export interface TooltipHandle {
  show(step: WalkthroughStep, stepIndex: number, totalSteps: number, shadowRoot: ShadowRoot): void;
  destroy(): void;
}

export function createWalkthroughTooltip(
  container: HTMLElement,
  onNext: () => void,
  onBack: () => void,
  onSkip: () => void,
  toolbarPosition: 'top' | 'bottom',
): TooltipHandle {
  let backdrop: HTMLElement | null = null;
  let tooltip: HTMLElement | null = null;
  let keyHandler: ((e: KeyboardEvent) => void) | null = null;

  // Safe zone — area not obscured by toolbar
  const safeTop = toolbarPosition === 'top' ? TOOLBAR_HEIGHT : 0;
  const safeBottom = toolbarPosition === 'bottom' ? TOOLBAR_HEIGHT : 0;

  function cleanup() {
    if (keyHandler) {
      document.removeEventListener('keydown', keyHandler);
      keyHandler = null;
    }
    if (backdrop) { backdrop.remove(); backdrop = null; }
    if (tooltip) { tooltip.remove(); tooltip = null; }
  }

  function show(step: WalkthroughStep, stepIndex: number, totalSteps: number, shadowRoot: ShadowRoot) {
    cleanup();

    const isModalStep = !step.targetSelector;
    const targetEl = step.targetSelector
      ? shadowRoot.querySelector(step.targetSelector) as HTMLElement | null
      : null;

    // Create backdrop
    backdrop = h('div', { class: 'noted-wt-backdrop noted-wt-spotlight-backdrop' });

    if (targetEl) {
      // Apply spotlight cutout via clip-path
      const rect = getRelativeRect(targetEl);
      const pad = 6;
      const r = 10;
      backdrop.style.clipPath = buildSpotlightClipPath(
        rect.left - pad, rect.top - pad,
        rect.width + pad * 2, rect.height + pad * 2,
        r,
      );

      // Scroll target into view if needed (accounting for toolbar)
      const domRect = targetEl.getBoundingClientRect();
      if (domRect.top < safeTop || domRect.bottom > window.innerHeight - safeBottom) {
        targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }

    container.appendChild(backdrop);

    // Create tooltip
    if (isModalStep) {
      tooltip = buildCenteredCard(step, stepIndex, totalSteps);
    } else if (targetEl) {
      tooltip = buildTooltip(step, stepIndex, totalSteps, targetEl);
    } else {
      tooltip = buildCenteredCard(step, stepIndex, totalSteps);
    }

    container.appendChild(tooltip);

    // Keyboard
    keyHandler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        onSkip();
      } else if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        onNext();
      } else if (e.key === 'ArrowLeft' && stepIndex > 0) {
        e.preventDefault();
        onBack();
      } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        onNext();
      }
    };
    document.addEventListener('keydown', keyHandler);
  }

  function buildTooltip(
    step: WalkthroughStep,
    stepIndex: number,
    totalSteps: number,
    target: HTMLElement,
  ): HTMLElement {
    const el = h('div', { class: 'noted-wt-tooltip' });
    el.appendChild(buildContent(step, stepIndex, totalSteps));

    // Position after append to measure
    container.appendChild(el);
    positionTooltip(el, target, step.placement);
    el.remove(); // will be re-appended by caller

    return el;
  }

  function buildCenteredCard(
    step: WalkthroughStep,
    stepIndex: number,
    totalSteps: number,
  ): HTMLElement {
    const wrapper = h('div', { class: 'noted-wt-centered' });
    const card = h('div', { class: 'noted-wt-modal noted-wt-modal-step' });
    card.appendChild(buildContent(step, stepIndex, totalSteps));
    wrapper.appendChild(card);
    return wrapper;
  }

  function buildContent(step: WalkthroughStep, stepIndex: number, totalSteps: number): HTMLElement {
    const frag = h('div', {});

    // Step counter
    const counter = h('span', { class: 'noted-wt-counter' }, `Step ${stepIndex + 1} of ${totalSteps}`);
    frag.appendChild(counter);

    // Title
    frag.appendChild(h('h3', { class: 'noted-wt-step-title' }, step.title));

    // Body
    frag.appendChild(h('p', { class: 'noted-wt-step-body' }, step.body));

    // Navigation
    const nav = h('div', { class: 'noted-wt-nav' });

    const leftNav = h('div', { class: 'noted-wt-nav-left' });
    if (stepIndex > 0) {
      leftNav.appendChild(h('button', { class: 'noted-wt-btn-ghost noted-wt-btn-sm', onClick: () => onBack() }, 'Back'));
    }
    nav.appendChild(leftNav);

    const rightNav = h('div', { class: 'noted-wt-nav-right' });
    const isLast = stepIndex === totalSteps - 1;

    rightNav.appendChild(h('button', {
      class: 'noted-wt-skip-link',
      onClick: () => onSkip(),
    }, 'Skip walkthrough'));

    rightNav.appendChild(h('button', {
      class: 'noted-wt-btn-primary noted-wt-btn-sm',
      onClick: () => onNext(),
    }, isLast ? 'Finish' : 'Next'));

    nav.appendChild(rightNav);
    frag.appendChild(nav);

    return frag;
  }

  function positionTooltip(
    el: HTMLElement,
    target: HTMLElement,
    placement: WalkthroughStep['placement'],
  ) {
    const targetRect = getRelativeRect(target);
    const viewW = window.innerWidth;
    const viewH = window.innerHeight;

    // Temporarily add to measure
    el.style.visibility = 'hidden';
    el.style.position = 'fixed';
    container.appendChild(el);
    const tipRect = el.getBoundingClientRect();
    el.remove();
    el.style.visibility = '';

    const tipW = tipRect.width;
    const tipH = tipRect.height;
    const gap = 12;

    // Usable area excluding toolbar
    const minY = safeTop + 8;
    const maxY = viewH - safeBottom - 8;

    let top = 0;
    let left = 0;
    let resolvedPlacement = placement;

    if (resolvedPlacement === 'auto') {
      // Pick best placement based on available space (toolbar-aware)
      const spaceAbove = targetRect.top - minY;
      const spaceBelow = maxY - targetRect.top - targetRect.height;
      const spaceLeft = targetRect.left;
      const spaceRight = viewW - targetRect.left - targetRect.width;

      if (spaceBelow >= tipH + gap) resolvedPlacement = 'bottom';
      else if (spaceAbove >= tipH + gap) resolvedPlacement = 'top';
      else if (spaceRight >= tipW + gap) resolvedPlacement = 'right';
      else if (spaceLeft >= tipW + gap) resolvedPlacement = 'left';
      else resolvedPlacement = 'bottom';
    }

    switch (resolvedPlacement) {
      case 'bottom':
        top = targetRect.top + targetRect.height + gap;
        left = targetRect.left + targetRect.width / 2 - tipW / 2;
        break;
      case 'top':
        top = targetRect.top - tipH - gap;
        left = targetRect.left + targetRect.width / 2 - tipW / 2;
        break;
      case 'right':
        top = targetRect.top + targetRect.height / 2 - tipH / 2;
        left = targetRect.left + targetRect.width + gap;
        break;
      case 'left':
        top = targetRect.top + targetRect.height / 2 - tipH / 2;
        left = targetRect.left - tipW - gap;
        break;
    }

    // Clamp to safe area (respecting toolbar)
    left = Math.max(8, Math.min(left, viewW - tipW - 8));
    top = Math.max(minY, Math.min(top, maxY - tipH));

    el.style.position = 'fixed';
    el.style.top = `${top}px`;
    el.style.left = `${left}px`;
  }

  function getRelativeRect(el: HTMLElement): { top: number; left: number; width: number; height: number } {
    const rect = el.getBoundingClientRect();
    return { top: rect.top, left: rect.left, width: rect.width, height: rect.height };
  }

  function buildSpotlightClipPath(x: number, y: number, w: number, h: number, r: number): string {
    const vw = window.innerWidth;
    const vh = window.innerHeight;

    return `polygon(evenodd, ` +
      `0 0, ${vw}px 0, ${vw}px ${vh}px, 0 ${vh}px, 0 0, ` +
      `${x + r}px ${y}px, ${x + w - r}px ${y}px, ${x + w}px ${y + r}px, ${x + w}px ${y + h - r}px, ` +
      `${x + w - r}px ${y + h}px, ${x + r}px ${y + h}px, ${x}px ${y + h - r}px, ${x}px ${y + r}px, ${x + r}px ${y}px` +
      `)`;
  }

  return {
    show,
    destroy: cleanup,
  };
}
