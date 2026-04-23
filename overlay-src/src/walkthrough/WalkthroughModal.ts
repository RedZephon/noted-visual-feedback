import { h } from '../utils/dom';

export interface WalkthroughModalHandle {
  show(): void;
  destroy(): void;
}

export function createWalkthroughModal(
  container: HTMLElement,
  onStart: () => void,
  onSkip: () => void,
): WalkthroughModalHandle {

  const backdrop = h('div', { class: 'noted-wt-backdrop' });
  const modal = h('div', { class: 'noted-wt-modal' });

  // Wordmark
  const wordmark = h('div', { class: 'noted-wt-wordmark' });
  wordmark.innerHTML = '<span class="noted-wt-wm-noted">noted</span>';
  modal.appendChild(wordmark);

  // Headline
  modal.appendChild(h('h2', { class: 'noted-wt-title' }, 'Welcome to Noted Visual Feedback'));

  // Subtext
  modal.appendChild(h('p', { class: 'noted-wt-subtitle' }, 'Want a quick walkthrough of how everything works? It only takes a minute.'));

  // Buttons
  const actions = h('div', { class: 'noted-wt-actions' });

  const startBtn = h('button', {
    class: 'noted-wt-btn-primary',
    onClick: () => {
      cleanup();
      onStart();
    },
  }, 'Show me around');

  const skipBtn = h('button', {
    class: 'noted-wt-btn-ghost',
    onClick: () => {
      cleanup();
      onSkip();
    },
  }, "Skip, I'll explore on my own");

  actions.appendChild(startBtn);
  actions.appendChild(skipBtn);
  modal.appendChild(actions);

  backdrop.appendChild(modal);

  function cleanup() {
    document.removeEventListener('keydown', onKey);
    backdrop.remove();
  }

  function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape') {
      cleanup();
      onSkip();
    } else if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      cleanup();
      onStart();
    }
  }

  return {
    show() {
      container.appendChild(backdrop);
      document.addEventListener('keydown', onKey);
      startBtn.focus();
    },
    destroy: cleanup,
  };
}
