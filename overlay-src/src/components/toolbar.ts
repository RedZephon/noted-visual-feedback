import type { NotedConfig, NotedMode, NotedTool, NotedBreakpoint } from '../types';
import type { NotedState } from '../state';
import { h, clearChildren } from '../utils/dom';
import { t } from '../utils/i18n';
import { getBreakpointWidth, isFixedBreakpoint } from '../utils/breakpoint';

type Store = { get(): NotedState; set(p: Partial<NotedState>): void; getPinCounts(): { open: number } };

const icons = {
  wordpress: '<svg viewBox="0 0 122.52 122.523" fill="currentColor"><path d="m8.708 61.26c0 20.802 12.089 38.779 29.619 47.298l-25.069-68.686c-2.916 6.536-4.55 13.769-4.55 21.388z"/><path d="m96.74 58.608c0-6.495-2.333-10.993-4.334-14.494-2.664-4.329-5.161-7.995-5.161-12.324 0-4.831 3.664-9.328 8.825-9.328.233 0 .454.029.681.042-9.35-8.566-21.807-13.796-35.489-13.796-18.36 0-34.513 9.42-43.91 23.688 1.233.037 2.395.063 3.382.063 5.497 0 14.006-.667 14.006-.667 2.833-.167 3.167 3.994.337 4.329 0 0-2.847.335-6.015.501l19.138 56.925 11.501-34.493-8.188-22.434c-2.83-.166-5.511-.501-5.511-.501-2.832-.166-2.5-4.496.332-4.329 0 0 8.679.667 13.843.667 5.496 0 14.006-.667 14.006-.667 2.835-.167 3.168 3.994.337 4.329 0 0-2.853.335-6.015.501l18.992 56.494 5.242-17.517c2.272-7.269 4.001-12.49 4.001-16.989z"/><path d="m62.184 65.857-15.768 45.819c4.708 1.384 9.687 2.141 14.846 2.141 6.12 0 11.989-1.058 17.452-2.979-.141-.225-.269-.464-.374-.724z"/><path d="m107.376 36.046c.226 1.674.354 3.471.354 5.404 0 5.333-.996 11.328-3.996 18.824l-16.053 46.413c15.624-9.111 26.133-26.038 26.133-45.426.001-9.137-2.333-17.729-6.438-25.215z"/><path d="m61.262 0c-33.779 0-61.262 27.481-61.262 61.26 0 33.783 27.483 61.263 61.262 61.263 33.778 0 61.265-27.48 61.265-61.263-.001-33.779-27.487-61.26-61.265-61.26zm0 119.715c-32.23 0-58.453-26.223-58.453-58.455 0-32.23 26.222-58.451 58.453-58.451 32.229 0 58.45 26.221 58.45 58.451 0 32.232-26.221 58.455-58.45 58.455z"/></svg>',
  pencil: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>',
  eye: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>',
  cursor: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3l14 8-6 2-2 6z"/></svg>',
  pin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
  chat: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
  close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
  monitor: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
  tablet: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>',
  mobile: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>',
  lock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
  fixed: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/><line x1="7" y1="7" x2="7" y2="13"/><line x1="17" y1="7" x2="17" y2="13"/></svg>',
  element: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
  chevron: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px;flex-shrink:0"><polyline points="6 9 12 15 18 9"/></svg>',
};

function svgEl(svg: string): HTMLElement {
  const span = document.createElement('span');
  span.innerHTML = svg;
  span.style.display = 'flex';
  span.style.alignItems = 'center';
  return span;
}

function divider(): HTMLElement {
  return h('div', { class: 'noted-toolbar-divider' });
}

export interface ToolbarHandle {
  update(state: NotedState): void;
  destroy(): void;
}

export function createToolbar(
  container: HTMLElement,
  config: NotedConfig,
  store: Store,
  onClose: () => void,
): ToolbarHandle {

  // ── Hide WP admin bar ──
  const adminBarStyle = document.createElement('style');
  adminBarStyle.id = 'noted-hide-adminbar';
  adminBarStyle.textContent = '#wpadminbar{display:none!important}html{margin-top:0!important}';
  document.head.appendChild(adminBarStyle);

  // ── Helpers ──
  function cleanPageTitle(title: string, path: string): string {
    let clean = title || '';
    if (clean.includes('–')) clean = clean.split('–')[0].trim();
    else if (clean.includes('|')) clean = clean.split('|')[0].trim();
    if (!clean || clean === title) {
      if (path === '/' || path === '') return 'Homepage';
    }
    return clean || path || '/';
  }
  const displayTitle = cleanPageTitle(config.pageTitle || '', config.pagePath);

  // ══════════════════════════════════════════════════════
  // SINGLE BOTTOM TOOLBAR
  // Left: logo, page, wp, close
  // Center: mode toggle, breakpoints
  // Right: tools, colors, panel, share
  // ══════════════════════════════════════════════════════
  const isTop = config.position === 'top';
  const el = h('div', { class: `noted-toolbar${isTop ? ' noted-toolbar--top' : ''}` });
  if (isTop) {
    el.style.bottom = 'auto';
    el.style.top = '0';
    el.style.borderTop = 'none';
    el.style.borderBottom = '0.5px solid var(--noted-border)';
  }

  // ── LEFT GROUP ──
  const leftGroup = h('div', { class: 'noted-toolbar-left' });

  // Wordmark
  const wordmark = document.createElement('div');
  wordmark.className = 'noted-widget-wordmark';
  wordmark.innerHTML = '<span class="noted-wm-wrap"><span class="noted-wm-hl"></span><span class="noted-wm-text"><span class="noted-wm-noted">noted</span></span></span>';
  leftGroup.appendChild(wordmark);

  // Page selector
  const pageSelector = h('button', {
    class: 'noted-widget-page',
    'data-tip': 'Switch page',
    onClick: () => togglePageDropdown(),
  });
  pageSelector.innerHTML = `<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${displayTitle}</span>${icons.chevron}`;
  leftGroup.appendChild(pageSelector);

  // WP Dashboard (hidden for guests)
  if (config.currentUser) {
    const wpBtn = h('button', { class: 'noted-widget-btn', 'data-tip': 'Dashboard', onClick: () => { window.location.href = '/wp-admin/'; } });
    wpBtn.innerHTML = icons.wordpress;
    leftGroup.appendChild(wpBtn);
  }

  // Close
  const closeBtn = h('button', { class: 'noted-widget-btn', 'data-tip': 'Close', onClick: () => onClose() });
  closeBtn.innerHTML = icons.close;
  leftGroup.appendChild(closeBtn);

  el.appendChild(leftGroup);

  // Page dropdown (above toolbar when bottom, below when top)
  const pageDropdown = h('div', { class: 'noted-page-dropdown' });
  pageDropdown.style.display = 'none';
  if (isTop) {
    pageDropdown.style.bottom = 'auto';
    pageDropdown.style.top = '58px';
  }
  container.appendChild(pageDropdown);

  let dropdownOpen = false;
  let justToggled = false;

  function togglePageDropdown() {
    dropdownOpen = !dropdownOpen;
    pageDropdown.style.display = dropdownOpen ? 'block' : 'none';
    if (dropdownOpen) { renderPageDropdown(); justToggled = true; requestAnimationFrame(() => { justToggled = false; }); }
  }

  function renderPageDropdown() {
    clearChildren(pageDropdown);
    const state = store.get();
    const pages: any[] = (state as any).projectPages || [];
    const hasCurrentPage = pages.some((p: any) => p.url_path === config.pagePath);
    const allPages = hasCurrentPage ? pages : [{ url_path: config.pagePath, title: displayTitle, pin_count: 0 }, ...pages];

    if (allPages.length <= 1 && pages.length === 0) {
      pageDropdown.appendChild(h('div', { class: 'noted-page-dropdown-item current' }, displayTitle));
      pageDropdown.appendChild(h('div', { style: 'padding:8px 16px;font-size:11px;color:var(--noted-slate);border-top:0.5px solid var(--noted-border);' }, 'Visit other pages with ?noted to add them here'));
      return;
    }
    allPages.forEach((page: any) => {
      const isCurrent = page.url_path === config.pagePath;
      const item = h('div', { class: `noted-page-dropdown-item ${isCurrent ? 'current' : ''}`, onClick: () => { if (!isCurrent) navigateToPage(page); } });
      item.appendChild(h('span', {}, cleanPageTitle(page.title || '', page.url_path)));
      if (page.pin_count > 0) item.appendChild(h('span', { class: 'noted-page-dropdown-count' }, String(page.pin_count)));
      pageDropdown.appendChild(item);
    });
  }

  function navigateToPage(page: any) {
    const notedParam = new URLSearchParams(window.location.search).get('noted');
    const url = new URL(page.url_path, window.location.origin);
    url.searchParams.set('noted', notedParam ?? '');
    window.location.href = url.toString();
  }

  container.addEventListener('click', (e: Event) => {
    if (!dropdownOpen || justToggled) return;
    const tgt = e.target as HTMLElement;
    if (pageSelector.contains(tgt) || pageDropdown.contains(tgt)) return;
    dropdownOpen = false; pageDropdown.style.display = 'none';
  }, true);
  document.addEventListener('click', () => { if (!dropdownOpen || justToggled) return; dropdownOpen = false; pageDropdown.style.display = 'none'; });

  // ── CENTER GROUP ──
  const centerGroup = h('div', { class: 'noted-toolbar-center' });

  // Mode toggle
  const modeToggle = h('div', { class: 'noted-mode-toggle' });
  const annotateBtn = h('button', { class: 'noted-mode-btn active', 'data-tip': 'Annotate', onClick: () => setMode('annotate') });
  annotateBtn.appendChild(svgEl(icons.pencil));
  annotateBtn.appendChild(document.createTextNode(t('annotate')));
  const viewBtn = h('button', { class: 'noted-mode-btn', 'data-tip': 'View', onClick: () => setMode('view') });
  viewBtn.appendChild(svgEl(icons.eye));
  viewBtn.appendChild(document.createTextNode(t('view')));
  modeToggle.appendChild(annotateBtn);
  modeToggle.appendChild(viewBtn);
  centerGroup.appendChild(modeToggle);

  function setMode(mode: NotedMode) { store.set({ mode }); }

  // Breakpoint tabs
  const bpTabs = h('div', { class: 'noted-breakpoint-tabs' });
  const bpItems: { id: NotedBreakpoint; label: string; icon: string }[] = [
    { id: 'desktop', label: 'Desktop — element selector only', icon: icons.monitor },
    { id: 'fixed', label: 'Fixed 1440px — all tools', icon: icons.fixed },
    { id: 'tablet', label: 'Tablet 768px', icon: icons.tablet },
    { id: 'mobile', label: 'Mobile 375px', icon: icons.mobile },
  ];
  const bpButtons: HTMLElement[] = [];
  bpItems.forEach(bp => {
    const btn = h('button', { class: `noted-bp-tab ${bp.id === 'desktop' ? 'active' : ''}`, 'data-tip': bp.label, onClick: () => setBreakpoint(bp.id) });
    btn.appendChild(svgEl(bp.icon));
    bpTabs.appendChild(btn);
    bpButtons.push(btn);
  });
  centerGroup.appendChild(bpTabs);
  el.appendChild(centerGroup);

  // Breakpoint logic
  const DEVICE_HEIGHTS: Record<NotedBreakpoint, number> = { desktop: 0, fixed: 0, tablet: 1024, mobile: 812 };
  let activeBreakpoint: NotedBreakpoint = 'desktop';
  const htmlEl = document.documentElement;
  const bodyEl = document.body;
  const savedHtml = { overflow: htmlEl.style.overflow, display: htmlEl.style.display, justifyContent: htmlEl.style.justifyContent, alignItems: htmlEl.style.alignItems, background: htmlEl.style.background, minHeight: htmlEl.style.minHeight };
  const savedBody = { maxWidth: bodyEl.style.maxWidth, maxHeight: bodyEl.style.maxHeight, width: bodyEl.style.width, overflow: bodyEl.style.overflow, position: bodyEl.style.position, margin: bodyEl.style.marginLeft, transition: bodyEl.style.transition, boxShadow: bodyEl.style.boxShadow, borderRadius: bodyEl.style.borderRadius };

  function applyBreakpointStyles(bp: NotedBreakpoint) {
    activeBreakpoint = bp;
    if (bp === 'desktop') {
      htmlEl.style.overflow = savedHtml.overflow; htmlEl.style.display = savedHtml.display; htmlEl.style.justifyContent = savedHtml.justifyContent; htmlEl.style.alignItems = savedHtml.alignItems; htmlEl.style.background = savedHtml.background; htmlEl.style.minHeight = savedHtml.minHeight;
      bodyEl.style.maxWidth = savedBody.maxWidth; bodyEl.style.maxHeight = savedBody.maxHeight; bodyEl.style.width = savedBody.width; bodyEl.style.overflow = savedBody.overflow; bodyEl.style.position = savedBody.position; bodyEl.style.marginLeft = savedBody.margin; bodyEl.style.marginRight = savedBody.margin; bodyEl.style.boxShadow = savedBody.boxShadow; bodyEl.style.transition = ''; bodyEl.style.borderRadius = savedBody.borderRadius;
    } else {
      const width = getBreakpointWidth(bp);
      const height = DEVICE_HEIGHTS[bp];
      const availableHeight = window.innerHeight - 52;
      const frameHeight = Math.min(height || availableHeight, availableHeight);
      htmlEl.style.overflow = 'hidden'; htmlEl.style.display = 'flex'; htmlEl.style.justifyContent = 'center'; htmlEl.style.alignItems = 'flex-start'; htmlEl.style.background = '#E3E0D9'; htmlEl.style.minHeight = '100vh';
      bodyEl.style.position = 'relative'; bodyEl.style.width = `${width}px`; bodyEl.style.maxWidth = `${width}px`; bodyEl.style.maxHeight = `${frameHeight}px`; bodyEl.style.overflow = 'auto'; bodyEl.style.marginLeft = 'auto'; bodyEl.style.marginRight = 'auto'; bodyEl.style.boxShadow = '0 0 0 0.5px #E3E0D9'; bodyEl.style.transition = 'max-width 300ms ease, max-height 300ms ease'; bodyEl.style.borderRadius = '0';
    }
  }

  function setBreakpoint(bp: NotedBreakpoint) {
    if (bp === activeBreakpoint) return;
    applyBreakpointStyles(bp);
    store.set({ activeBreakpoint: bp, breakpointFilter: bp });
  }

  // ── RIGHT GROUP ──
  const rightGroup = h('div', { class: 'noted-toolbar-right' });

  const tools: { id: NotedTool; icon: string; enabled: boolean; label: string }[] = [
    { id: 'cursor', icon: icons.cursor, enabled: true, label: 'Cursor (V)' },
    { id: 'pin', icon: icons.pin, enabled: true, label: 'Pin (C)' },
    { id: 'element', icon: icons.element, enabled: true, label: 'Element (E)' },
  ];

  const toolButtons = new Map<NotedTool, HTMLElement>();
  tools.forEach(tool => {
    const btn = h('button', { class: `noted-tool-btn ${tool.enabled ? '' : 'disabled'}`, 'data-tip': tool.label, onClick: () => { if (tool.enabled) store.set({ activeTool: tool.id }); } });
    btn.innerHTML = tool.icon;
    if (!tool.enabled) { const ls = document.createElement('span'); ls.className = 'noted-tool-lock'; ls.innerHTML = icons.lock; btn.appendChild(ls); }
    toolButtons.set(tool.id, btn);
    rightGroup.appendChild(btn);
  });

  rightGroup.appendChild(divider());

  // Panel toggle
  const panelToggle = h('button', { class: 'noted-tool-btn noted-panel-toggle', 'data-tip': 'Comments (.)', onClick: () => store.set({ panelOpen: !store.get().panelOpen }) });
  panelToggle.innerHTML = icons.chat;
  const panelBadge = h('span', { class: 'noted-panel-badge' }, '0');
  panelToggle.appendChild(panelBadge);
  rightGroup.appendChild(panelToggle);

  // Share button
  if (config.isInternal) {
    const shareBtn = h('button', { class: 'noted-share-btn', 'data-tip': 'Share link', onClick: () => store.set({ shareModalOpen: true }) }, t('shareReviewLink'));
    rightGroup.appendChild(shareBtn);
  }

  el.appendChild(rightGroup);
  container.appendChild(el);

  // ── UPDATE ──
  // Tools available on responsive desktop (no fixed canvas)
  const DESKTOP_TOOLS = new Set<NotedTool>(['cursor', 'element']);
  // Tools available on fixed-width breakpoints (stable canvas)
  const FIXED_TOOLS = new Set<NotedTool>(['cursor', 'pin', 'element']);

  function update(state: NotedState) {
    // Sync body styles if breakpoint changed externally (e.g., clicking a comment from another bp)
    if (state.activeBreakpoint !== activeBreakpoint) {
      applyBreakpointStyles(state.activeBreakpoint);
    }

    annotateBtn.className = `noted-mode-btn ${state.mode === 'annotate' ? 'active' : ''}`;
    viewBtn.className = `noted-mode-btn ${state.mode === 'view' ? 'active' : ''}`;
    bpButtons.forEach((btn, i) => { btn.className = `noted-bp-tab ${bpItems[i].id === state.activeBreakpoint ? 'active' : ''}`; });

    const fixed = isFixedBreakpoint(state.activeBreakpoint);
    const allowedTools = fixed ? FIXED_TOOLS : DESKTOP_TOOLS;

    // If active tool isn't allowed at this breakpoint, switch to element
    if (state.mode === 'annotate' && !allowedTools.has(state.activeTool)) {
      store.set({ activeTool: 'element' });
      return; // will re-run via subscription
    }

    toolButtons.forEach((btn, id) => {
      const tool = tools.find(t2 => t2.id === id)!;
      if (state.mode === 'view') {
        btn.style.display = 'none';
      } else if (!allowedTools.has(id)) {
        btn.style.display = 'none';
      } else {
        btn.style.display = 'flex';
        if (tool.enabled) btn.className = `noted-tool-btn ${state.activeTool === id ? 'active' : ''}`;
      }
    });

    const openCount = state.pins.filter(p => p.status === 'open').length;
    panelBadge.textContent = String(openCount);
    panelBadge.style.display = openCount > 0 ? 'flex' : 'none';
    panelToggle.className = `noted-tool-btn noted-panel-toggle ${state.panelOpen ? 'active' : ''}`;
  }

  return {
    update,
    destroy() {
      htmlEl.style.overflow = savedHtml.overflow; htmlEl.style.display = savedHtml.display; htmlEl.style.justifyContent = savedHtml.justifyContent; htmlEl.style.alignItems = savedHtml.alignItems; htmlEl.style.background = savedHtml.background; htmlEl.style.minHeight = savedHtml.minHeight;
      bodyEl.style.maxWidth = savedBody.maxWidth; bodyEl.style.maxHeight = savedBody.maxHeight; bodyEl.style.width = savedBody.width; bodyEl.style.overflow = savedBody.overflow; bodyEl.style.position = savedBody.position; bodyEl.style.marginLeft = savedBody.margin; bodyEl.style.marginRight = savedBody.margin; bodyEl.style.boxShadow = savedBody.boxShadow; bodyEl.style.transition = ''; bodyEl.style.borderRadius = savedBody.borderRadius;
      adminBarStyle.remove(); el.remove(); pageDropdown.remove();
    },
  };
}
