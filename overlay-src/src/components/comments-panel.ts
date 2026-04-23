import type { NotedConfig, NotedPin, NotedComment } from '../types';
import type { NotedState } from '../state';
import type { NotedAPI } from '../api';
import { h, clearChildren } from '../utils/dom';
import { relativeTime } from '../utils/time';
import { calculatePinPosition } from '../utils/position';
import { t } from '../utils/i18n';

type Store = {
  get(): NotedState;
  set(p: Partial<NotedState>): void;
  getFilteredPins(): NotedPin[];
  getAllVisiblePins(): NotedPin[];
  getPinCounts(): { total: number; open: number; resolved: number };
  addComment(pinId: number, comment: NotedComment): void;
  updatePin(id: number, updates: Partial<NotedPin>): void;
};

const chatIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
const checkIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
const undoIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6.69 3L3 13"/></svg>';
const emptyIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h.01"/><path d="M12 10h.01"/><path d="M16 10h.01"/></svg>';

export interface CommentsPanelHandle {
  update(state: NotedState): void;
}

export function createCommentsPanel(
  container: HTMLElement,
  config: NotedConfig,
  store: Store,
  api: NotedAPI,
): CommentsPanelHandle {
  const el = h('div', { class: 'noted-panel' });
  if (config.position === 'top') {
    el.style.top = '52px';
  }

  // ── Tabs ──
  const tabsRow = h('div', { class: 'noted-panel-tabs' });
  const commentsTab = h('button', {
    class: 'noted-panel-tab active',
    onClick: () => store.set({ panelTab: 'comments' }),
  });
  commentsTab.innerHTML = `<span style="display:flex">${chatIcon}</span>`;
  commentsTab.appendChild(document.createTextNode(' ' + t('comments')));
  const commentsBadge = h('span', { class: 'noted-tab-badge' }, '0');
  commentsTab.appendChild(commentsBadge);

  tabsRow.appendChild(commentsTab);

  el.appendChild(tabsRow);

  // ── Filter row ──
  const filterRow = h('div', { class: 'noted-filter-row' });
  const filterAll = h('button', { class: 'noted-filter-btn active', onClick: () => store.set({ statusFilter: 'all' }) }, t('all') + ' (0)');
  const filterOpen = h('button', { class: 'noted-filter-btn', onClick: () => store.set({ statusFilter: 'open' }) }, t('open') + ' (0)');
  const filterDone = h('button', { class: 'noted-filter-btn', onClick: () => store.set({ statusFilter: 'resolved' }) }, t('done') + ' (0)');
  filterRow.appendChild(filterAll);
  filterRow.appendChild(filterOpen);
  filterRow.appendChild(filterDone);
  el.appendChild(filterRow);

  // ── Pin list ──
  const pinList = h('div', { class: 'noted-pin-list' });
  el.appendChild(pinList);

  container.appendChild(el);

  let prevSelectedId: number | null = null;
  let prevPinCount = -1;
  let prevCommentCount = -1;
  let prevStatusSnapshot = '';
  let prevBreakpoint = '';

  function update(state: NotedState) {
    el.className = `noted-panel ${state.panelOpen ? '' : 'closed'}`;

    // Tab state
    commentsTab.className = `noted-panel-tab ${state.panelTab === 'comments' ? 'active' : ''}`;

    // Update tab badge counts
    const totalPins = state.pins.length;
    commentsBadge.textContent = String(totalPins);
    commentsBadge.style.display = totalPins > 0 ? 'inline-flex' : 'none';

    // ── Comments view ──
    filterRow.style.display = 'flex';

    // Filter counts
    const counts = store.getPinCounts();
    filterAll.textContent = `${t('all')} (${counts.total})`;
    filterOpen.textContent = `${t('open')} (${counts.open})`;
    filterDone.textContent = `${t('done')} (${counts.resolved})`;
    filterAll.className = `noted-filter-btn ${state.statusFilter === 'all' ? 'active' : ''}`;
    filterOpen.className = `noted-filter-btn ${state.statusFilter === 'open' ? 'active' : ''}`;
    filterDone.className = `noted-filter-btn ${state.statusFilter === 'resolved' ? 'active' : ''}`;

    // Rebuild pin list — show ALL pins (not filtered by breakpoint) in the sidebar
    const filtered = store.getAllVisiblePins();
    const selectedPin = state.selectedPinId ? filtered.find(p => p.id === state.selectedPinId) : null;
    const commentCount = selectedPin?.comments?.length ?? -1;
    const statusSnapshot = filtered.map(p => `${p.id}:${p.status}`).join(',');

    const needsRebuild = state.selectedPinId !== prevSelectedId
      || filtered.length !== prevPinCount
      || commentCount !== prevCommentCount
      || statusSnapshot !== prevStatusSnapshot
      || state.activeBreakpoint !== prevBreakpoint;

    prevSelectedId = state.selectedPinId;
    prevPinCount = filtered.length;
    prevCommentCount = commentCount;
    prevStatusSnapshot = statusSnapshot;
    prevBreakpoint = state.activeBreakpoint;

    if (!needsRebuild) return;

    clearChildren(pinList);

    if (state.loading) {
      const loading = h('div', { class: 'noted-loading' });
      const spinner = h('div', { class: 'noted-spinner' });
      loading.appendChild(spinner);
      loading.appendChild(document.createTextNode('Loading...'));
      pinList.appendChild(loading);
      return;
    }

    if (filtered.length === 0) {
      renderEmptyState();
      return;
    }

    filtered.forEach(pin => {
      const isSelected = state.selectedPinId === pin.id;
      const item = renderPinItem(pin, isSelected, state.activeBreakpoint);
      pinList.appendChild(item);

      if (isSelected) {
        const thread = renderThread(pin);
        pinList.appendChild(thread);
      }
    });

    if (state.selectedPinId) {
      const selectedEl = pinList.querySelector('.noted-pin-item.selected');
      selectedEl?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }

  function renderEmptyState() {
    const empty = h('div', { class: 'noted-empty-state' });
    const icon = h('div', { class: 'noted-empty-icon' });
    icon.innerHTML = emptyIcon;
    empty.appendChild(icon);
    empty.appendChild(h('div', { class: 'noted-empty-title' }, t('noFeedback')));
    empty.appendChild(h('div', { class: 'noted-empty-desc' }, t('noFeedbackDesc')));
    pinList.appendChild(empty);
  }

  // SVG icons for breakpoint pills (no emojis)
  const bpPillIcons: Record<string, string> = {
    desktop: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
    fixed: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/><line x1="7" y1="7" x2="7" y2="13"/><line x1="17" y1="7" x2="17" y2="13"/></svg>',
    tablet: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>',
    mobile: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:10px;height:10px;flex-shrink:0"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>',
  };
  const bpPillLabels: Record<string, string> = { desktop: 'Desktop', fixed: 'Fixed', tablet: 'Tablet', mobile: 'Mobile' };

  function renderPinItem(pin: NotedPin, isSelected: boolean, activeBp: string): HTMLElement {
    const isOtherBreakpoint = pin.breakpoint !== activeBp;
    const classes = [
      'noted-pin-item',
      isSelected ? 'selected' : '',
      pin.status === 'resolved' ? 'resolved' : '',
      isOtherBreakpoint ? 'noted-pin-other-bp' : '',
    ].filter(Boolean).join(' ');

    const item = h('div', {
      class: classes,
      onClick: () => {
        const updates: Partial<NotedState> = {
          selectedPinId: isSelected ? null : pin.id,
          panelOpen: true,
        };
        // Auto-switch breakpoint if the pin belongs to a different one
        if (!isSelected && pin.breakpoint && pin.breakpoint !== store.get().activeBreakpoint) {
          updates.activeBreakpoint = pin.breakpoint as any;
          updates.breakpointFilter = pin.breakpoint as any;
        }
        store.set(updates);
        if (!isSelected) scrollToPin(pin);
      },
    });

    // Row 1: number + author + pill + time
    const header = h('div', { class: 'noted-pin-header' });
    const numberEl = h('span', { class: `noted-pin-number ${pin.status === 'resolved' ? 'resolved' : ''}` });
    if (pin.status === 'resolved') {
      numberEl.innerHTML = checkIcon;
      numberEl.querySelector('svg')!.style.cssText = 'width:11px;height:11px;';
    } else {
      numberEl.textContent = String(pin.pin_number);
    }
    header.appendChild(numberEl);

    const authorName = pin.author_name || (pin.author_type === 'user' ? 'Team member' : 'Guest');
    header.appendChild(h('span', { class: 'noted-pin-author' }, authorName));

    // Breakpoint pill with SVG icon
    const bpLabel = bpPillLabels[pin.breakpoint] || pin.breakpoint;
    const pillSvg = bpPillIcons[pin.breakpoint] || bpPillIcons.desktop;
    const pill = h('span', { class: `noted-bp-pill ${isOtherBreakpoint ? 'noted-bp-pill-other' : ''}` });
    pill.innerHTML = pillSvg;
    pill.appendChild(document.createTextNode(' ' + bpLabel));
    header.appendChild(pill);

    header.appendChild(h('span', { class: 'noted-pin-time' }, relativeTime(pin.created_at)));
    item.appendChild(header);

    // Row 2: body preview (only when NOT expanded)
    if (!isSelected) {
      const bodyText = pin.body.replace(/<[^>]*>/g, '').trim();
      if (bodyText) {
        item.appendChild(h('div', { class: 'noted-pin-body' }, bodyText));
      }
    }

    return item;
  }

  function renderThread(pin: NotedPin): HTMLElement {
    const thread = h('div', { class: 'noted-thread' });

    // Full body (shown only in expanded thread — pin item hides preview)
    const body = h('div', { class: 'noted-thread-body' });
    body.innerHTML = pin.body;
    thread.appendChild(body);

    // Comments
    if (pin.comments && pin.comments.length > 0) {
      const commentsContainer = h('div', { class: 'noted-thread-comments' });
      pin.comments.forEach(comment => {
        commentsContainer.appendChild(renderComment(comment));
      });
      thread.appendChild(commentsContainer);
    }

    // Status actions (hidden for guests)
    const isGuestUser = !config.currentUser && store.get().guestInfo;
    if (config.canResolve && !isGuestUser) {
      const isResolved = pin.status === 'resolved';
      const statusBar = h('div', { class: 'noted-thread-actions' });

      if (isResolved) {
        const reopenBtn = h('button', {
          class: 'noted-action-btn noted-action-reopen',
          onClick: async (e: Event) => {
            e.stopPropagation();
            store.updatePin(pin.id, { status: 'open', resolved_at: null });
            try { await api.updatePin(pin.id, { status: 'open' }); }
            catch { store.updatePin(pin.id, { status: pin.status }); }
          },
        });
        reopenBtn.innerHTML = `<span style="display:flex">${undoIcon}</span> Reopen`;
        statusBar.appendChild(reopenBtn);
      } else {
        // Resolve
        const resolveBtn = h('button', {
          class: 'noted-action-btn noted-action-resolve',
          onClick: async (e: Event) => {
            e.stopPropagation();
            store.updatePin(pin.id, { status: 'resolved', resolved_at: new Date().toISOString() });
            try { await api.updatePin(pin.id, { status: 'resolved' }); }
            catch { store.updatePin(pin.id, { status: pin.status }); }
          },
        });
        resolveBtn.innerHTML = `<span style="display:flex;width:12px;height:12px">${checkIcon}</span> Resolve`;
        statusBar.appendChild(resolveBtn);
      }

      thread.appendChild(statusBar);
    }

    // Reply form
    const form = h('div', { class: 'noted-reply-form' });
    const textarea = document.createElement('textarea') as HTMLTextAreaElement;
    textarea.className = 'noted-reply-input';
    textarea.placeholder = 'Write a reply...';
    textarea.rows = 2;
    form.appendChild(textarea);

    const actions = h('div', { class: 'noted-reply-actions' });
    const submitBtn = h('button', {
      class: 'noted-btn noted-btn-primary noted-btn-small',
      disabled: 'true',
      onClick: async () => {
        const body = textarea.value.trim();
        if (!body) return;
        submitBtn.setAttribute('disabled', '');
        try {
          const comment = await api.createComment(pin.id, body);
          const guestInfo = store.get().guestInfo;
          const commentWithMeta = {
            ...comment,
            author_name: config.currentUser?.name || guestInfo?.name || 'You',
            author_avatar: config.currentUser?.avatar || '',
          };
          store.addComment(pin.id, commentWithMeta);
        } catch (err) {
          console.error('Failed to post comment', err);
          submitBtn.removeAttribute('disabled');
        }
      },
    }, 'Reply');

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
        submitBtn.click();
      }
    });

    actions.appendChild(submitBtn);
    form.appendChild(actions);
    thread.appendChild(form);

    return thread;
  }

  function renderComment(comment: NotedComment): HTMLElement {
    const el = h('div', { class: 'noted-comment' });

    // Avatar
    const avatar = h('div', { class: 'noted-comment-avatar' });
    if (comment.author_avatar) {
      const img = document.createElement('img');
      img.src = comment.author_avatar;
      img.alt = '';
      avatar.appendChild(img);
    } else {
      const name = comment.author_name || 'G';
      avatar.textContent = name.charAt(0).toUpperCase();
    }
    el.appendChild(avatar);

    // Content
    const content = h('div', { class: 'noted-comment-content' });
    const meta = h('div', { class: 'noted-comment-meta' });
    meta.appendChild(h('span', { class: 'noted-comment-name' }, comment.author_name || 'Guest'));
    meta.appendChild(h('span', { class: 'noted-comment-time' }, relativeTime(comment.created_at)));
    content.appendChild(meta);

    const body = h('div', { class: 'noted-comment-body' });
    body.textContent = comment.body.replace(/<[^>]*>/g, '');
    content.appendChild(body);

    el.appendChild(content);
    return el;
  }

  function scrollToPin(pin: NotedPin) {
    const pos = calculatePinPosition(pin);
    window.scrollTo({
      top: Math.max(0, pos.top - window.innerHeight / 3),
      behavior: 'smooth',
    });
  }

  return { update };
}
