import type { NotedConfig } from '../types';
import type { NotedAPI } from '../api';
import { h, clearChildren } from '../utils/dom';

export interface PresenceHandle {
  start(pageId: number): void;
  destroy(): void;
  getElement(): HTMLElement;
}

export function createPresence(
  config: NotedConfig,
  api: NotedAPI,
): PresenceHandle {
  const el = h('div', { class: 'noted-presence' });

  let heartbeatInterval: number;
  let pollInterval: number;
  let currentPageId = 0;

  function start(pageId: number) {
    currentPageId = pageId;

    // Send initial heartbeat
    sendHeartbeat();

    // Heartbeat every 30s
    heartbeatInterval = window.setInterval(sendHeartbeat, 30000);

    // Poll presence every 15s
    pollPresence();
    pollInterval = window.setInterval(pollPresence, 15000);
  }

  async function sendHeartbeat() {
    if (!currentPageId) return;
    const name = config.currentUser?.name || 'Guest';
    try { await api.heartbeat(currentPageId, name); } catch {}
  }

  async function pollPresence() {
    if (!currentPageId) return;
    try {
      const viewers = await api.getPresence(currentPageId);
      renderViewers(viewers);
    } catch {}
  }

  function renderViewers(viewers: { type: string; id: number; name: string; avatar: string }[]) {
    clearChildren(el);
    if (viewers.length === 0) return;

    viewers.forEach(viewer => {
      const avatar = h('div', {
        class: 'noted-presence-avatar',
        'data-tip': `${viewer.name} is viewing this page`,
      });

      if (viewer.avatar) {
        const img = document.createElement('img');
        img.src = viewer.avatar;
        img.alt = '';
        avatar.appendChild(img);
      } else {
        avatar.textContent = (viewer.name || 'G').charAt(0).toUpperCase();
      }

      el.appendChild(avatar);
    });
  }

  function destroy() {
    clearInterval(heartbeatInterval);
    clearInterval(pollInterval);
    el.remove();
  }

  return { start, destroy, getElement: () => el };
}
