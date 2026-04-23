import type { NotedAPI } from '../api';
import { walkthroughSteps } from './WalkthroughStep';
import { createWalkthroughModal } from './WalkthroughModal';
import { createWalkthroughTooltip } from './WalkthroughTooltip';

export interface WalkthroughHandle {
  start(): void;
  destroy(): void;
}

export function createWalkthrough(
  container: HTMLElement,
  shadowRoot: ShadowRoot,
  api: NotedAPI,
  toolbarPosition: 'top' | 'bottom',
  onComplete: () => void,
): WalkthroughHandle {
  let currentStep = 0;
  let activeSteps = walkthroughSteps;
  let modal: ReturnType<typeof createWalkthroughModal> | null = null;
  let tooltip: ReturnType<typeof createWalkthroughTooltip> | null = null;

  function completeWalkthrough() {
    if (tooltip) { tooltip.destroy(); tooltip = null; }
    if (modal) { modal.destroy(); modal = null; }
    api.setWalkthroughState(true).catch(() => {});
    onComplete();
  }

  function filterAvailableSteps() {
    activeSteps = walkthroughSteps.filter(step => {
      if (!step.targetSelector) return true; // Modal steps always available
      const el = shadowRoot.querySelector(step.targetSelector);
      if (!el) {
        return false;
      }
      return true;
    });
  }

  function showStep(index: number) {
    if (index < 0 || index >= activeSteps.length) {
      completeWalkthrough();
      return;
    }

    currentStep = index;
    const step = activeSteps[currentStep];

    if (!tooltip) {
      tooltip = createWalkthroughTooltip(
        container,
        () => showStep(currentStep + 1),
        () => showStep(currentStep - 1),
        () => completeWalkthrough(),
        toolbarPosition,
      );
    }

    tooltip.show(step, currentStep, activeSteps.length, shadowRoot);
  }

  function startWalkthrough() {
    filterAvailableSteps();
    if (activeSteps.length === 0) {
      completeWalkthrough();
      return;
    }
    showStep(0);
  }

  function showWelcome() {
    modal = createWalkthroughModal(
      container,
      () => {
        modal = null;
        startWalkthrough();
      },
      () => {
        modal = null;
        completeWalkthrough();
      },
    );
    modal.show();
  }

  return {
    start() {
      showWelcome();
    },
    destroy() {
      if (tooltip) { tooltip.destroy(); tooltip = null; }
      if (modal) { modal.destroy(); modal = null; }
    },
  };
}
