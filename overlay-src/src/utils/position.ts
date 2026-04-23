import type { PinPosition, CalculatedPosition, NotedPin } from '../types';
import { generateStableSelector } from './selector';
import { detectBreakpoint } from './breakpoint';

// Check if we're in breakpoint mode (body is the scroll container).
function isBreakpointMode(): boolean {
  const ov = document.body.style.overflow;
  return ov === 'auto' || ov === 'scroll';
}

// Get scroll offsets for coordinate capture.
// In breakpoint mode: body.scrollTop (body is the scroll container).
// In desktop mode: window.scrollY (normal page scroll).
function getScrollOffsets(): { scrollX: number; scrollY: number } {
  if (isBreakpointMode()) {
    return { scrollX: document.body.scrollLeft, scrollY: document.body.scrollTop };
  }
  return { scrollX: window.scrollX, scrollY: window.scrollY };
}

// Full document dimensions — used for percentage-based pin positioning.
// Must be consistent between capture and calculation.
function getDocDimensions(): { width: number; height: number } {
  return {
    width: Math.max(document.documentElement.scrollWidth, document.body.scrollWidth),
    height: Math.max(document.documentElement.scrollHeight, document.body.scrollHeight),
  };
}

export function capturePinPosition(event: MouseEvent, target: Element): PinPosition {
  const rect = target.getBoundingClientRect();
  const scroll = getScrollOffsets();
  const doc = getDocDimensions();

  return {
    css_selector: generateStableSelector(target),
    selector_offset_x: event.clientX - rect.left,
    selector_offset_y: event.clientY - rect.top,
    x_percent: ((event.clientX + scroll.scrollX) / doc.width) * 100,
    y_percent: ((event.clientY + scroll.scrollY) / doc.height) * 100,
    viewport_width: window.innerWidth,
    scroll_y: scroll.scrollY,
    breakpoint: detectBreakpoint(),
  };
}

export function calculatePinPosition(pin: NotedPin): CalculatedPosition {
  const bpMode = isBreakpointMode();

  if (pin.css_selector) {
    try {
      const el = document.querySelector(pin.css_selector);
      if (el) {
        const rect = el.getBoundingClientRect();

        if (bpMode) {
          // Body has position:relative — convert to body-content coords
          const bodyRect = document.body.getBoundingClientRect();
          return {
            left: rect.left - bodyRect.left + (pin.selector_offset_x ?? 0) + document.body.scrollLeft,
            top: rect.top - bodyRect.top + (pin.selector_offset_y ?? 0) + document.body.scrollTop,
            method: 'selector',
          };
        }

        // Desktop: absolute relative to document
        return {
          left: rect.left + (pin.selector_offset_x ?? 0) + window.scrollX,
          top: rect.top + (pin.selector_offset_y ?? 0) + window.scrollY,
          method: 'selector',
        };
      }
    } catch {
      // Invalid selector — fall through to percentage
    }
  }

  // Percentage fallback.
  // Use the viewport_width the pin was captured at to reconstruct
  // the original absolute position, then scale to current doc width.
  const doc = getDocDimensions();
  const capturedWidth = pin.viewport_width || doc.width;
  const capturedHeight = doc.height; // height doesn't change as dramatically

  // Reconstruct the original absolute position
  const origX = (pin.x_percent / 100) * capturedWidth;
  const origY = (pin.y_percent / 100) * capturedHeight;

  // Scale horizontally if viewport changed
  const scaleX = doc.width / capturedWidth;
  return {
    left: origX * scaleX,
    top: origY,
    method: 'percentage',
  };
}
