import type { NotedConfig } from '../types';
import type { NotedState } from '../state';
import { h } from '../utils/dom';
import { t } from '../utils/i18n';

type Store = { get(): NotedState; set(p: Partial<NotedState>): void };

export interface ShortcutsHelpHandle {
  update(state: NotedState): void;
}

export function createShortcutsHelp(
  container: HTMLElement,
  _config: NotedConfig,
  store: Store,
): ShortcutsHelpHandle {
  const backdrop = h('div', { class: 'noted-modal-backdrop', onClick: (e: Event) => {
    if (e.target === backdrop) store.set({ shortcutsHelpOpen: false });
  }});
  const modal = h('div', { class: 'noted-modal noted-shortcuts-modal' });

  function buildContent() {
    modal.innerHTML = '';

    const title = h('h2', { class: 'noted-modal-title', style: 'text-align:left;margin:0 0 16px;' });
    title.textContent = t('keyboardShortcuts');
    modal.appendChild(title);

    const sections: { heading: string; shortcuts: [string, string][] }[] = [
      {
        heading: t('tools'),
        shortcuts: [
          ['C', t('pinTool')],
          ['E', t('elementTool')],
          ['V', t('cursorTool')],
        ],
      },
      {
        heading: t('navigation'),
        shortcuts: [
          ['N', t('nextPin')],
          ['P', t('previousPin')],
          [']', t('nextPage')],
          ['[', t('previousPage')],
        ],
      },
      {
        heading: t('general'),
        shortcuts: [
          ['.', t('togglePanel')],
          ['/', t('toggleMode')],
          ['Esc', t('cancelClose')],
          ['?', t('showHelp')],
        ],
      },
    ];

    sections.forEach(section => {
      const sectionEl = h('div', { style: 'margin-bottom:16px;' });
      const heading = h('div', {
        style: 'font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--noted-text-tertiary);margin-bottom:8px;',
      }, section.heading);
      sectionEl.appendChild(heading);

      section.shortcuts.forEach(([key, desc]) => {
        const row = h('div', {
          style: 'display:flex;align-items:center;gap:12px;padding:4px 0;',
        });
        const kbd = h('kbd', {
          style: 'display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:24px;padding:0 6px;background:var(--noted-bg-surface);border:1px solid var(--noted-border);border-radius:4px;font-family:monospace;font-size:12px;font-weight:600;color:var(--noted-text);',
        }, key);
        const label = h('span', { style: 'font-size:13px;color:var(--noted-text-secondary);' }, desc);
        row.appendChild(kbd);
        row.appendChild(label);
        sectionEl.appendChild(row);
      });

      modal.appendChild(sectionEl);
    });

    const hint = h('div', {
      style: 'font-size:11px;color:var(--noted-text-tertiary);text-align:center;margin-top:8px;',
    }, t('pressAnyKey'));
    modal.appendChild(hint);
  }

  backdrop.appendChild(modal);
  container.appendChild(backdrop);

  function update(state: NotedState) {
    backdrop.style.display = state.shortcutsHelpOpen ? 'flex' : 'none';
    if (state.shortcutsHelpOpen) {
      buildContent();
    }
  }

  return { update };
}
