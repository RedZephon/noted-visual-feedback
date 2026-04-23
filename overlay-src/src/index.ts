import { store } from './state';
import { NotedAPI } from './api';
import { createToolbar } from './components/toolbar';
import { createHintBar } from './components/hint-bar';
import { createStatusBar } from './components/status-bar';
import { createCommentsPanel } from './components/comments-panel';
import { createPinMarkerManager } from './components/pin-marker';
import { createPinPopover } from './components/pin-popover';
import { createGuestModal } from './components/guest-modal';
import { createShareModal } from './components/share-modal';
import { createShortcutsHelp } from './components/shortcuts-help';
import { createElementSelector } from './components/element-selector';
import { createPresence } from './components/presence';
import { createWalkthrough } from './walkthrough/Walkthrough';
import { capturePinPosition } from './utils/position';
import { initStrings } from './utils/i18n';
import OVERLAY_CSS from './styles/overlay.css?inline';
import type { NotedConfig } from './types';

(function () {
  'use strict';

  const config: NotedConfig = (window as any).NotedConfig;
  if (!config) return;

  initStrings(config.strings || {});

  const api = new NotedAPI(config);

  // ── Create Shadow DOM host ──
  const host = document.createElement('div');
  host.id = 'noted-host';
  host.style.cssText = 'position:fixed;top:0;left:0;width:0;height:0;z-index:2147483647;pointer-events:none;';
  document.body.appendChild(host);

  const shadow = host.attachShadow({ mode: 'closed' });

  // Inject styles
  if ('adoptedStyleSheets' in Document.prototype) {
    const sheets: CSSStyleSheet[] = [];
    const sheet = new CSSStyleSheet();
    sheet.replaceSync(OVERLAY_CSS);
    sheets.push(sheet);
    if (config.brandColor && config.brandColor !== '#F5A623') {
      const brandSheet = new CSSStyleSheet();
      brandSheet.replaceSync(`:host { --noted-amber: ${config.brandColor}; --noted-copper: ${config.brandColor}; }`);
      sheets.push(brandSheet);
    }
    shadow.adoptedStyleSheets = sheets;
  } else {
    const style = document.createElement('style');
    let css = OVERLAY_CSS;
    if (config.brandColor && config.brandColor !== '#F5A623') {
      css = `:host { --noted-amber: ${config.brandColor}; --noted-copper: ${config.brandColor}; }\n` + css;
    }
    style.textContent = css;
    shadow.appendChild(style);
  }

  // ── App container ──
  const app = document.createElement('div');
  app.id = 'noted-app';
  shadow.appendChild(app);

  // ── Push page content for toolbar + panel ──
  const originalMarginBottom = document.body.style.marginBottom;
  const originalMarginRight = document.documentElement.style.marginRight;
  let panelPushing = false;

  const toolbarAtTop = config.position === 'top';

  function applyToolbarOffset() {
    if (toolbarAtTop) {
      document.body.style.marginTop = '52px';
    } else {
      document.body.style.marginBottom = '52px';
    }
  }

  function setPanelPush(open: boolean) {
    if (open === panelPushing) return;
    panelPushing = open;
    document.documentElement.style.transition = 'margin-right 250ms cubic-bezier(0.4, 0, 0.2, 1)';
    document.documentElement.style.marginRight = open ? '340px' : originalMarginRight;
  }

  function destroyOverlay() {
    if (toolbarAtTop) {
      document.body.style.marginTop = '';
    } else {
      document.body.style.marginBottom = originalMarginBottom;
    }
    document.documentElement.style.marginRight = originalMarginRight;
    document.documentElement.style.transition = '';
    host.remove();
    if (clickOverlay) clickOverlay.remove();
    markers.destroy();
    elementSelector.destroy();
    presence.destroy();
    const url = new URL(window.location.href);
    url.searchParams.delete('noted');
    history.replaceState(null, '', url.toString());
  }

  // ── Initialize components ──
  // These are always created but may not be visible depending on guestPhase

  // Guest/auth modals (always rendered, visibility controlled by guestPhase)
  const guestModal = createGuestModal(app, config, store, api, (guestId, name, email) => {
    store.set({
      guestPhase: 'ready',
      guestInfo: { id: guestId, name, email },
    });
    applyToolbarOffset();
    loadPageAndPins().then(() => {
      if (!config.walkthroughCompleted) launchWalkthrough();
    });
  });

  // Main overlay components (only active when guestPhase === 'ready')
  const toolbar = createToolbar(app, config, store, destroyOverlay);
  const hintBar = createHintBar(app, store);
  const statusBar = createStatusBar(app, config, store);
  const panel = createCommentsPanel(app, config, store, api);
  const markers = createPinMarkerManager(config, store);
  const popover = createPinPopover(app, config, store, api, (pinId: number) => {
    (markers as any).markAsNew?.(pinId);
  });
  const shareModal = createShareModal(app, config, store, api);
  const elementSelector = createElementSelector(config, store);
  const shortcutsHelp = createShortcutsHelp(app, config, store);
  const presence = createPresence(config, api);
  // Mount presence avatars as the last element in the toolbar
  const toolbarEl = app.querySelector('.noted-toolbar');
  const toolbarRight = toolbarEl?.querySelector('.noted-toolbar-right');
  if (toolbarRight) {
    toolbarRight.appendChild(presence.getElement());
  }

  // ── Click overlay for pin placement ──
  const clickOverlay = document.createElement('div');
  clickOverlay.id = 'noted-click-overlay';
  clickOverlay.style.cssText = toolbarAtTop
    ? 'position:fixed;top:52px;left:0;right:0;bottom:0;z-index:2147483639;cursor:crosshair;display:none;'
    : 'position:fixed;top:0;left:0;right:0;bottom:52px;z-index:2147483639;cursor:crosshair;display:none;';
  document.body.appendChild(clickOverlay);

  clickOverlay.addEventListener('click', (e: MouseEvent) => {
    const state = store.get();
    if (state.mode !== 'annotate' || state.activeTool !== 'pin') return;

    clickOverlay.style.pointerEvents = 'none';
    const target = document.elementFromPoint(e.clientX, e.clientY);
    clickOverlay.style.pointerEvents = 'auto';

    if (!target || target.id === 'noted-host' || target.hasAttribute('data-noted-pin')) return;

    const position = capturePinPosition(e, target);
    position.breakpoint = store.get().activeBreakpoint;
    store.set({
      placingPin: { x: e.clientX, y: e.clientY, position },
      selectedPinId: null,
    });
  });

  // ── Checking spinner ──
  const checkingSpinner = document.createElement('div');
  checkingSpinner.className = 'noted-checking-spinner';
  checkingSpinner.innerHTML = '<div class="noted-spinner"></div>';
  app.appendChild(checkingSpinner);

  // ── State subscriptions ──
  store.subscribe(() => {
    try {
      const state = store.get();
      const isReady = state.guestPhase === 'ready';

      // Checking spinner
      checkingSpinner.style.display = state.guestPhase === 'checking' ? 'flex' : 'none';

      // Guest/auth modals
      guestModal.update(state);

      // Main overlay visibility
      const toolbarEl = app.querySelector('.noted-toolbar') as HTMLElement;
      if (toolbarEl) toolbarEl.style.display = isReady ? 'flex' : 'none';
      const hintEl = app.querySelector('.noted-hint-bar') as HTMLElement;
      if (hintEl) hintEl.style.display = isReady && state.mode === 'annotate' && state.activeTool !== 'cursor' && !state.hintBarDismissed && !state.placingPin ? 'flex' : 'none';
      const statusEl = app.querySelector('.noted-status-bar') as HTMLElement;
      if (statusEl) statusEl.style.display = isReady ? 'flex' : 'none';
      const panelEl = app.querySelector('.noted-panel') as HTMLElement;
      if (panelEl) panelEl.style.display = isReady ? 'flex' : 'none';

      if (isReady) {
        // Push page content when panel is open
        setPanelPush(state.panelOpen);

        // Show/hide click overlay
        const showClick = state.mode === 'annotate' && state.activeTool === 'pin' && !state.placingPin;
        clickOverlay.style.display = showClick ? 'block' : 'none';
        clickOverlay.style.right = state.panelOpen ? '340px' : '0';

        toolbar.update(state);
        hintBar.update(state);
        statusBar.update(state);
        panel.update(state);
        popover.update(state);
        markers.renderAll(state.pins.filter(p => p.breakpoint === state.activeBreakpoint));
        markers.highlight(state.selectedPinId);
        shareModal.update(state);
        elementSelector.update(state);
        shortcutsHelp.update(state);
      } else {
        clickOverlay.style.display = 'none';
        markers.renderAll([]);
      }
    } catch (err) {
      console.error('Noted: subscription error', err);
    }
  });

  // ── Keyboard shortcuts ──
  document.addEventListener('keydown', (e: KeyboardEvent) => {
    const state = store.get();
    if (state.guestPhase !== 'ready') return;

    const target = e.target as HTMLElement;
    if (target instanceof HTMLInputElement ||
        target instanceof HTMLTextAreaElement ||
        target?.isContentEditable) return;
    if (target.closest?.('#noted-host')) return;

    // Close shortcuts help on any key
    if (state.shortcutsHelpOpen) {
      store.set({ shortcutsHelpOpen: false });
      return;
    }

    switch (e.key.toLowerCase()) {
      case 'c':
        if (state.mode === 'annotate' && !state.placingPin) {
          store.set({ activeTool: 'pin' });
        }
        break;
      case 'v':
        if (state.mode === 'annotate' && !state.placingPin) {
          store.set({ activeTool: 'cursor' });
        }
        break;
      case 'e':
        if (state.mode === 'annotate' && !state.placingPin) {
          store.set({ activeTool: 'element' });
        }
        break;
      case 'n': {
        const filtered = store.getFilteredPins();
        if (filtered.length === 0) break;
        const currentIdx = filtered.findIndex(p => p.id === state.selectedPinId);
        const nextIdx = currentIdx < filtered.length - 1 ? currentIdx + 1 : 0;
        store.set({ selectedPinId: filtered[nextIdx].id, panelOpen: true });
        break;
      }
      case 'p': {
        const filtered = store.getFilteredPins();
        if (filtered.length === 0) break;
        const currentIdx = filtered.findIndex(p => p.id === state.selectedPinId);
        const prevIdx = currentIdx > 0 ? currentIdx - 1 : filtered.length - 1;
        store.set({ selectedPinId: filtered[prevIdx].id, panelOpen: true });
        break;
      }
      case ']': {
        const pages = (state as any).projectPages || [];
        if (pages.length <= 1) break;
        const curIdx = pages.findIndex((pg: any) => pg.url_path === config.pagePath);
        const nxtIdx = curIdx < pages.length - 1 ? curIdx + 1 : 0;
        const nxtPage = pages[nxtIdx];
        const url = new URL(nxtPage.url_path, window.location.origin);
        const notedParam = new URLSearchParams(window.location.search).get('noted');
        url.searchParams.set('noted', notedParam ?? '');
        window.location.href = url.toString();
        break;
      }
      case '[': {
        const pages = (state as any).projectPages || [];
        if (pages.length <= 1) break;
        const curIdx = pages.findIndex((pg: any) => pg.url_path === config.pagePath);
        const prvIdx = curIdx > 0 ? curIdx - 1 : pages.length - 1;
        const prvPage = pages[prvIdx];
        const url = new URL(prvPage.url_path, window.location.origin);
        const notedParam = new URLSearchParams(window.location.search).get('noted');
        url.searchParams.set('noted', notedParam ?? '');
        window.location.href = url.toString();
        break;
      }
      case '.':
        store.set({ panelOpen: !state.panelOpen });
        break;
      case '/':
        store.set({ mode: state.mode === 'annotate' ? 'view' : 'annotate' });
        break;
      case '?':
        store.set({ shortcutsHelpOpen: true });
        break;
      case 'escape':
        if (state.shareModalOpen) {
          store.set({ shareModalOpen: false });
        } else if (state.placingPin) {
          store.set({ placingPin: null });
        } else if (state.selectedPinId) {
          store.set({ selectedPinId: null });
        }
        break;
    }
  });

  // ── Reposition pins on resize/scroll ──
  let repositionTimer: number;
  function scheduleReposition() {
    clearTimeout(repositionTimer);
    repositionTimer = window.setTimeout(() => {
      if (store.get().guestPhase === 'ready') {
        const state = store.get();
        markers.renderAll(state.pins.filter(p => p.breakpoint === state.activeBreakpoint));
      }
    }, 60);
  }
  window.addEventListener('resize', scheduleReposition);
  // Body scroll (breakpoint mode) — reposition pins as user scrolls within the device frame
  document.body.addEventListener('scroll', scheduleReposition);

  // ── Load page and pins ──
  async function loadPageAndPins() {
    try {
      const page = await api.ensurePage(config.projectId, config.pagePath, config.pageTitle);
      store.set({ currentPage: page });

      // Load project pages for page selector.
      try {
        const project = await api.getProject(config.projectId);
        store.set({ projectPages: (project as any).pages || [] });
      } catch { /* ignore */ }

      const pins = await api.listPins(page.id);
      const pinsWithComments = await Promise.all(
        pins.map(async (pin) => {
          try {
            const comments = await api.listComments(pin.id);
            return { ...pin, comments };
          } catch {
            return { ...pin, comments: [] };
          }
        }),
      );

      store.set({ pins: pinsWithComments, loading: false });

      // Reposition pins after layout settles (margin offsets may not have reflowed yet)
      requestAnimationFrame(() => scheduleReposition());

      // Start presence tracking
      presence.start(page.id);
    } catch (err) {
      console.error('Noted: Failed to load data', err);
      store.set({ loading: false });
    }
  }

  // ── Walkthrough launcher ──
  function launchWalkthrough() {
    const tbPos = config.position === 'top' ? 'top' : 'bottom';
    const walkthrough = createWalkthrough(app, shadow, api, tbPos as 'top' | 'bottom', () => {});
    walkthrough.start();
  }

  // ── Init ──
  async function init() {
    const isGuest = !!config.guestToken && !config.currentUser;

    if (isGuest) {
      // ── Guest flow ──
      const storageKey = `noted_guest_token_${config.projectId}`;
      const existingToken = localStorage.getItem(storageKey);

      if (existingToken) {
        // Returning guest — validate session
        store.set({ guestPhase: 'checking' });
        const validation = await api.validateGuest(existingToken);

        if (validation.valid) {
          api.setGuestToken(existingToken);
          store.set({
            guestPhase: 'ready',
            guestInfo: {
              id: validation.guest_id,
              name: validation.name || localStorage.getItem(`noted_guest_name_${config.projectId}`) || 'Guest',
              email: validation.email || localStorage.getItem(`noted_guest_email_${config.projectId}`) || '',
            },
          });
          applyToolbarOffset();
          await loadPageAndPins();
          if (!config.walkthroughCompleted) launchWalkthrough();
          return;
        } else {
          localStorage.removeItem(storageKey);
        }
      }

      // New guest — check access
      store.set({ guestPhase: 'checking' });
      try {
        await api.checkAccess(config.guestToken);
        store.set({ guestPhase: 'register' });
      } catch (err: any) {
        store.set({ guestPhase: 'invalid' });
      }
    } else {
      // ── Logged-in user flow ──
      store.set({ guestPhase: 'ready' });
      applyToolbarOffset();
      await loadPageAndPins();

      // Launch walkthrough for first-time users
      if (!config.walkthroughCompleted) {
        launchWalkthrough();
      }
    }
  }

  init();
})();
