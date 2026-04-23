import type { NotedConfig } from '../types';
import type { NotedState } from '../state';
import { h, clearChildren } from '../utils/dom';
import { getBreakpointLabel, getBreakpointWidth } from '../utils/breakpoint';
import { t } from '../utils/i18n';

type Store = { get(): NotedState; getPinCounts(): { open: number; resolved: number } };

export interface StatusBarHandle {
  update(state: NotedState): void;
}

const monitorIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>';
const cursorIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3l14 8-6 2-2 6z"/></svg>';

export function createStatusBar(_container: HTMLElement, _config: NotedConfig, store: Store): StatusBarHandle {
  const el = h('div', { class: 'noted-status-bar' });

  const left = h('div', { class: 'noted-status-left' });
  const center = h('div', { class: 'noted-status-center' });
  const right = h('div', { class: 'noted-status-right' });

  el.appendChild(left);
  el.appendChild(center);
  el.appendChild(right);
  _container.appendChild(el);

  function update(state: NotedState) {
    el.className = `noted-status-bar ${state.panelOpen ? 'panel-open' : ''}`;

    // Left: breakpoint
    clearChildren(left);
    const iconSpan = document.createElement('span');
    iconSpan.innerHTML = monitorIcon;
    iconSpan.style.display = 'flex';
    left.appendChild(iconSpan);
    left.appendChild(document.createTextNode(
      `${getBreakpointLabel(state.activeBreakpoint)} ${getBreakpointWidth(state.activeBreakpoint)}px`
    ));

    // Center: keyboard hint
    clearChildren(center);
    if (state.mode === 'annotate') {
      if (state.activeTool === 'pin') {
        const iconEl = document.createElement('span');
        iconEl.innerHTML = cursorIcon;
        iconEl.style.display = 'flex';
        center.appendChild(iconEl);
        center.appendChild(document.createTextNode('Switch to '));
        center.appendChild(h('kbd', {}, 'V'));
        center.appendChild(document.createTextNode(' Cursor to scroll & interact'));
      } else if (state.activeTool === 'cursor') {
        const iconEl = document.createElement('span');
        iconEl.innerHTML = cursorIcon;
        iconEl.style.display = 'flex';
        center.appendChild(iconEl);
        center.appendChild(document.createTextNode('Switch to '));
        center.appendChild(h('kbd', {}, 'C'));
        center.appendChild(document.createTextNode(' Pin to leave feedback'));
      }
    } else {
      center.appendChild(document.createTextNode(t('viewMode')));
    }

    // Right: pin counts + remaining
    clearChildren(right);
    const counts = store.getPinCounts();
    const openDot = h('span', { class: 'noted-status-dot open' });
    const resolvedDot = h('span', { class: 'noted-status-dot resolved' });
    right.appendChild(openDot);
    right.appendChild(document.createTextNode(t('openCount', counts.open)));
    right.appendChild(document.createTextNode(' · '));
    right.appendChild(resolvedDot);
    right.appendChild(document.createTextNode(t('resolvedCount', counts.resolved)));
  }

  return { update };
}
