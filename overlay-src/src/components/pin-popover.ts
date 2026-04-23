import type { NotedConfig } from '../types';
import type { NotedState } from '../state';
import type { NotedAPI } from '../api';
import { h } from '../utils/dom';
import { getBreakpointLabel } from '../utils/breakpoint';
import { t } from '../utils/i18n';

type Store = {
  get(): NotedState;
  set(p: Partial<NotedState>): void;
  addPin(pin: any): void;
};

const monitorIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>';

export interface PinPopoverHandle {
  update(state: NotedState): void;
}

export function createPinPopover(
  container: HTMLElement,
  config: NotedConfig,
  store: Store,
  api: NotedAPI,
  onPinCreated?: (pinId: number) => void,
): PinPopoverHandle {
  const el = h('div', { class: 'noted-pin-popover hidden' });

  // Breakpoint indicator
  const bpIndicator = h('div', { class: 'noted-popover-breakpoint' });
  el.appendChild(bpIndicator);

  // Textarea
  const textarea = document.createElement('textarea') as HTMLTextAreaElement;
  textarea.className = 'noted-popover-textarea';
  textarea.placeholder = t('leaveFeedback');
  el.appendChild(textarea);

  // Error
  const errorEl = h('div', { class: 'noted-popover-error' });
  errorEl.style.display = 'none';
  el.appendChild(errorEl);

  // Actions
  const actions = h('div', { class: 'noted-popover-actions' });

  const cancelBtn = h('button', {
    class: 'noted-btn noted-btn-ghost',
    onClick: () => {
      store.set({ placingPin: null });
      textarea.value = '';
      errorEl.style.display = 'none';
    },
  }, t('cancel'));

  const submitBtn = h('button', {
    class: 'noted-btn noted-btn-primary',
    disabled: 'true',
    onClick: () => submit(),
  }, t('submit'));

  actions.appendChild(cancelBtn);
  actions.appendChild(submitBtn);
  el.appendChild(actions);

  container.appendChild(el);

  textarea.addEventListener('input', () => {
    if (textarea.value.trim()) {
      submitBtn.removeAttribute('disabled');
    } else {
      submitBtn.setAttribute('disabled', '');
    }
  });

  textarea.addEventListener('keydown', (e: KeyboardEvent) => {
    if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
      e.preventDefault();
      submit();
    }
    if (e.key === 'Escape') {
      store.set({ placingPin: null });
      textarea.value = '';
    }
  });

  async function submit() {
    const state = store.get();
    if (!state.placingPin || !state.currentPage) return;
    const body = textarea.value.trim();
    if (!body) return;

    store.set({ submitting: true });
    submitBtn.setAttribute('disabled', '');
    errorEl.style.display = 'none';

    try {
      const pos = state.placingPin.position;
      const pinData = {
        page_id: state.currentPage.id,
        project_id: config.projectId,
        x_percent: pos.x_percent,
        y_percent: pos.y_percent,
        css_selector: pos.css_selector,
        selector_offset_x: pos.selector_offset_x,
        selector_offset_y: pos.selector_offset_y,
        viewport_width: pos.viewport_width,
        scroll_y: pos.scroll_y,
        breakpoint: pos.breakpoint,
        body,
      };

      const newPin = await api.createPin(pinData);

      // Add author metadata for display
      const guestInfo = store.get().guestInfo;
      const pinWithMeta = {
        ...newPin,
        author_name: config.currentUser?.name || guestInfo?.name || 'You',
        author_avatar: config.currentUser?.avatar || '',
        comments: [],
      };

      store.addPin(pinWithMeta);

      if (onPinCreated) onPinCreated(newPin.id);

      textarea.value = '';
      store.set({ placingPin: null, submitting: false, selectedPinId: newPin.id });
    } catch (err: any) {
      errorEl.textContent = err.message || 'Failed to create pin';
      errorEl.style.display = 'block';
      store.set({ submitting: false });
      submitBtn.removeAttribute('disabled');
    }
  }

  let wasOpen = false;

  function update(state: NotedState) {
    if (!state.placingPin) {
      el.className = 'noted-pin-popover hidden';
      wasOpen = false;
      return;
    }

    const justOpened = !wasOpen;
    wasOpen = true;

    el.className = 'noted-pin-popover';

    // Position near click (only reposition when first opening)
    if (justOpened) {
      const x = state.placingPin.x;
      const y = state.placingPin.y;
      const popoverWidth = 320;
      const popoverHeight = 220;

      let left = x + 16;
      let top = y - 20;

      if (left + popoverWidth > window.innerWidth - (state.panelOpen ? 340 : 0)) {
        left = x - popoverWidth - 16;
      }
      if (top + popoverHeight > window.innerHeight - 36) {
        top = window.innerHeight - 36 - popoverHeight - 8;
      }
      if (top < 52 + 36) {
        top = 52 + 36 + 8;
      }

      el.style.left = `${left}px`;
      el.style.top = `${top}px`;

      // Breakpoint indicator
      const bp = state.placingPin.position?.breakpoint || state.activeBreakpoint;
      bpIndicator.innerHTML = `<span style="display:flex">${monitorIcon}</span>`;
      bpIndicator.querySelector('svg')!.style.cssText = 'width:12px;height:12px;';
      bpIndicator.appendChild(document.createTextNode(' ' + t('commentOn', getBreakpointLabel(bp))));

      // Focus textarea only when popover first opens
      setTimeout(() => textarea.focus(), 50);
    }
  }

  return { update };
}
