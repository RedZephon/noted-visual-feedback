import type { NotedPin, NotedComment, NotedPage, NotedTool, NotedMode, NotedBreakpoint } from './types';

export type GuestPhase = 'checking' | 'register' | 'ready' | 'invalid';

export interface GuestInfo {
  id: number;
  name: string;
  email: string;
}

export interface NotedState {
  currentPage: NotedPage | null;
  pins: NotedPin[];

  mode: NotedMode;
  activeTool: NotedTool;
  activeBreakpoint: NotedBreakpoint;
  selectedPinId: number | null;
  panelOpen: boolean;
  panelTab: 'comments';
  hintBarDismissed: boolean;

  statusFilter: 'all' | 'open' | 'resolved';
  breakpointFilter: NotedBreakpoint | 'all';

  placingPin: { x: number; y: number; position: any } | null;

  loading: boolean;
  submitting: boolean;

  // Guest state
  guestPhase: GuestPhase;
  guestInfo: GuestInfo | null;

  // Share modal
  shareModalOpen: boolean;

  // Page selector
  projectPages: NotedPage[];

  shortcutsHelpOpen: boolean;
}

type Listener = () => void;

class Store {
  private state: NotedState;
  private listeners: Set<Listener> = new Set();

  constructor() {
    this.state = {
      currentPage: null,
      pins: [],
      mode: 'annotate',
      activeTool: 'element',
      activeBreakpoint: 'desktop',
      selectedPinId: null,
      panelOpen: true,
      panelTab: 'comments',
      hintBarDismissed: false,
      statusFilter: 'all',
      breakpointFilter: 'all',
      placingPin: null,
      loading: true,
      submitting: false,
      guestPhase: 'checking',
      guestInfo: null,
      shareModalOpen: false,
      projectPages: [],
      shortcutsHelpOpen: false,
    };
  }

  get(): NotedState {
    return this.state;
  }

  set(partial: Partial<NotedState>): void {
    this.state = { ...this.state, ...partial };
    this.notify();
  }

  subscribe(listener: Listener): () => void {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener);
  }

  private notify(): void {
    this.listeners.forEach(fn => fn());
  }

  addPin(pin: NotedPin): void {
    this.set({ pins: [...this.state.pins, pin] });
  }

  updatePin(id: number, updates: Partial<NotedPin>): void {
    this.set({
      pins: this.state.pins.map(p => p.id === id ? { ...p, ...updates } : p),
    });
  }

  removePin(id: number): void {
    this.set({
      pins: this.state.pins.filter(p => p.id !== id),
      selectedPinId: this.state.selectedPinId === id ? null : this.state.selectedPinId,
    });
  }

  addComment(pinId: number, comment: NotedComment): void {
    this.set({
      pins: this.state.pins.map(p => {
        if (p.id !== pinId) return p;
        return { ...p, comments: [...(p.comments || []), comment] };
      }),
    });
  }

  getFilteredPins(): NotedPin[] {
    let pins = this.state.pins;

    if (this.state.statusFilter !== 'all') {
      pins = pins.filter(p => p.status === this.state.statusFilter);
    }
    if (this.state.breakpointFilter !== 'all') {
      pins = pins.filter(p => p.breakpoint === this.state.breakpointFilter);
    }

    return pins.sort((a, b) => a.pin_number - b.pin_number);
  }

  /** All pins filtered by status only (not breakpoint) — for the sidebar. */
  getAllVisiblePins(): NotedPin[] {
    let pins = this.state.pins;

    if (this.state.statusFilter !== 'all') {
      pins = pins.filter(p => p.status === this.state.statusFilter);
    }

    return pins.sort((a, b) => a.pin_number - b.pin_number);
  }

  getPinCounts(): { total: number; open: number; resolved: number } {
    const pins = this.state.pins;
    return {
      total: pins.length,
      open: pins.filter(p => p.status === 'open').length,
      resolved: pins.filter(p => p.status === 'resolved').length,
    };
  }
}

export const store = new Store();
