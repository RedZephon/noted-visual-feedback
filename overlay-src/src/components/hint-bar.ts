import type { NotedState } from '../state';
import { h } from '../utils/dom';
import { t } from '../utils/i18n';

type Store = { get(): NotedState; set(p: Partial<NotedState>): void };

export interface HintBarHandle {
  update(state: NotedState): void;
}

const hintKeys: Record<string, string> = {
  pin: 'pinHint',
  element: 'elementHint',
};

const pinIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
const closeIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

export function createHintBar(container: HTMLElement, store: Store): HintBarHandle {
  const el = h('div', { class: 'noted-hint-bar' });

  const textEl = h('span', { class: 'noted-hint-text' });
  el.appendChild(textEl);

  const closeBtn = h('button', {
    class: 'noted-hint-close',
    onClick: () => store.set({ hintBarDismissed: true }),
  });
  closeBtn.innerHTML = closeIcon;
  el.appendChild(closeBtn);

  container.appendChild(el);

  let prevTool = '';

  function update(state: NotedState) {
    const shouldShow = state.mode === 'annotate'
      && state.activeTool !== 'cursor'
      && !state.hintBarDismissed
      && !state.placingPin;

    el.className = `noted-hint-bar ${state.panelOpen ? 'panel-open' : ''} ${shouldShow ? '' : 'hidden'}`;

    if (state.activeTool !== prevTool) {
      prevTool = state.activeTool;
      store.set({ hintBarDismissed: false });
      const text = t(hintKeys[state.activeTool] || '');
      textEl.innerHTML = '';
      const iconSpan = document.createElement('span');
      iconSpan.innerHTML = pinIcon;
      iconSpan.style.display = 'flex';
      textEl.appendChild(iconSpan);
      textEl.appendChild(document.createTextNode(text));
    }
  }

  return { update };
}
