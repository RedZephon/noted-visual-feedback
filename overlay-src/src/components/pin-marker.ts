import type { NotedConfig, NotedPin } from '../types';
import type { NotedState } from '../state';
import { calculatePinPosition } from '../utils/position';

type Store = { get(): NotedState; set(p: Partial<NotedState>): void };

const checkSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px"><polyline points="20 6 9 17 4 12"/></svg>`;

let styleInjected = false;

function injectPageStyles(_brandColor: string) {
  if (styleInjected) return;
  styleInjected = true;

  const style = document.createElement('style');
  style.id = 'noted-pin-styles';
  style.textContent = `
    @keyframes noted-pin-appear {
      0% { transform: scale(0.3) translate(-50%, -50%); opacity: 0; }
      50% { transform: scale(1.2) translate(-50%, -50%); }
      100% { transform: scale(1) translate(-50%, -50%); opacity: 1; }
    }
    [data-noted-pin] {
      position: absolute;
      width: 22px;
      height: 22px;
      border-radius: 50%;
      color: #fff;
      font-size: 11px;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 2147483640;
      transform: translate(-50%, -50%);
      transition: transform 200ms cubic-bezier(0.4, 0, 0.2, 1), background 200ms cubic-bezier(0.4, 0, 0.2, 1);
      user-select: none;
      pointer-events: auto;
      font-family: 'Inter Tight', system-ui, sans-serif;
      line-height: 1;
      box-sizing: border-box;
    }
    [data-noted-pin]:hover {
      transform: scale(1.05) translate(-50%, -50%);
      background: #D4920A !important;
    }
    @keyframes noted-pin-pulse {
      0%, 100% { box-shadow: 0 0 0 0 rgba(77, 142, 247, 0.5); }
      50% { box-shadow: 0 0 0 8px rgba(77, 142, 247, 0); }
    }
    [data-noted-pin].noted-pin-selected {
      transform: scale(1.15) translate(-50%, -50%);
      background: #4d8ef7 !important;
      animation: noted-pin-pulse 1.8s ease-in-out infinite;
    }
    [data-noted-pin].noted-pin-new {
      animation: noted-pin-appear 600ms ease forwards;
    }
    #noted-pin-preview {
      position: absolute;
      z-index: 2147483641;
      background: #FFFFFF;
      border: 0.5px solid #E3E0D9;
      border-radius: 10px;
      padding: 10px 12px;
      max-width: 240px;
      pointer-events: none;
      font-family: 'Inter Tight', system-ui, sans-serif;
      display: none;
    }
    #noted-pin-preview .npp-author {
      font-size: 12px;
      font-weight: 700;
      color: #111110;
      margin-bottom: 3px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    #noted-pin-preview .npp-body {
      font-size: 12px;
      font-weight: 500;
      color: #111110;
      line-height: 1.4;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    #noted-pin-preview .npp-meta {
      font-size: 10px;
      color: #9E9B97;
      margin-top: 4px;
    }
  `;
  document.head.appendChild(style);
}

export interface PinMarkerHandle {
  renderAll(pins: NotedPin[]): void;
  highlight(pinId: number | null): void;
  destroy(): void;
}

export function createPinMarkerManager(_config: NotedConfig, store: Store): PinMarkerHandle {
  const brandColor = '#F5A623';
  const greenColor = '#3ecf7a';

  injectPageStyles(brandColor);

  const markerMap = new Map<number, HTMLElement>();
  const pinDataMap = new Map<number, NotedPin>();
  const newPinIds = new Set<number>();

  // Hover preview element
  const preview = document.createElement('div');
  preview.id = 'noted-pin-preview';
  document.body.appendChild(preview);
  let hoverTimer: number;

  function showPreview(pin: NotedPin, marker: HTMLElement) {
    const authorName = pin.author_name || (pin.author_type === 'user' ? 'Team member' : 'Guest');
    const bodyText = (pin.body || '').replace(/<[^>]*>/g, '').trim();
    const replyCount = pin.comments?.length || 0;
    const meta = replyCount > 0 ? `${replyCount} ${replyCount === 1 ? 'reply' : 'replies'}` : '';

    preview.innerHTML = `<div class="npp-author">${authorName}</div>`
      + (bodyText ? `<div class="npp-body">${bodyText}</div>` : '')
      + (meta ? `<div class="npp-meta">${meta}</div>` : '');

    // Position to the right of the marker; flip left if near viewport edge
    const rect = marker.getBoundingClientRect();
    const bpMode = document.body.style.position === 'relative';

    preview.style.display = 'block';
    preview.style.left = '0';
    preview.style.top = '0';
    preview.style.transform = 'none';
    const previewWidth = preview.offsetWidth;

    const gap = 8;
    const showOnRight = rect.right + gap + previewWidth + 12 <= window.innerWidth;

    let left: number, top: number;
    if (bpMode) {
      const bodyRect = document.body.getBoundingClientRect();
      const markerLeft = rect.left - bodyRect.left + document.body.scrollLeft;
      top = rect.top - bodyRect.top + document.body.scrollTop + rect.height / 2;
      left = showOnRight
        ? markerLeft + rect.width + gap
        : markerLeft - gap;
    } else {
      top = rect.top + window.scrollY + rect.height / 2;
      left = showOnRight
        ? rect.left + window.scrollX + rect.width + gap
        : rect.left + window.scrollX - gap;
    }

    preview.style.left = `${left}px`;
    preview.style.top = `${top}px`;
    preview.style.transform = showOnRight ? 'translateY(-50%)' : 'translate(-100%, -50%)';
  }

  function hidePreview() {
    preview.style.display = 'none';
  }

  function renderAll(pins: NotedPin[]) {
    const currentIds = new Set(pins.map(p => p.id));

    // Remove markers for pins that no longer exist
    markerMap.forEach((el, id) => {
      if (!currentIds.has(id)) {
        el.remove();
        markerMap.delete(id);
        pinDataMap.delete(id);
      }
    });

    // Create or update markers
    pins.forEach(pin => {
      const pos = calculatePinPosition(pin);
      let marker = markerMap.get(pin.id);

      if (!marker) {
        marker = document.createElement('div');
        marker.setAttribute('data-noted-pin', String(pin.id));
        marker.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          hidePreview();
          const state = store.get();
          const newId = state.selectedPinId === pin.id ? null : pin.id;
          store.set({ selectedPinId: newId, panelOpen: true });
        });

        // Hover preview
        const pinId = pin.id;
        marker.addEventListener('mouseenter', () => {
          clearTimeout(hoverTimer);
          hoverTimer = window.setTimeout(() => {
            const data = pinDataMap.get(pinId);
            const m = markerMap.get(pinId);
            if (data && m) showPreview(data, m);
          }, 300);
        });
        marker.addEventListener('mouseleave', () => {
          clearTimeout(hoverTimer);
          hidePreview();
        });

        if (newPinIds.has(pin.id)) {
          marker.classList.add('noted-pin-new');
          newPinIds.delete(pin.id);
          setTimeout(() => marker!.classList.remove('noted-pin-new'), 700);
        }

        document.body.appendChild(marker);
        markerMap.set(pin.id, marker);
      }

      // Keep pin data in sync for hover preview
      pinDataMap.set(pin.id, pin);

      // Position
      marker.style.left = `${pos.left}px`;
      marker.style.top = `${pos.top}px`;

      // Appearance
      if (pin.status === 'resolved') {
        marker.style.background = greenColor;
        marker.innerHTML = checkSvg;
      } else {
        marker.style.background = brandColor;
        marker.innerHTML = '';
        marker.textContent = String(pin.pin_number);
      }
    });
  }

  function highlight(pinId: number | null) {
    markerMap.forEach((el, id) => {
      if (id === pinId) {
        el.classList.add('noted-pin-selected');
      } else {
        el.classList.remove('noted-pin-selected');
      }
    });
  }

  function markAsNew(pinId: number) {
    newPinIds.add(pinId);
  }

  function destroy() {
    markerMap.forEach(el => el.remove());
    markerMap.clear();
    pinDataMap.clear();
    hidePreview();
    preview.remove();
    const style = document.getElementById('noted-pin-styles');
    style?.remove();
    styleInjected = false;
  }

  return {
    renderAll,
    highlight,
    destroy,
    // Expose markAsNew via the object
    ...({ markAsNew } as { markAsNew: (id: number) => void }),
  };
}
