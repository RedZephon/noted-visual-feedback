import type { NotedBreakpoint } from '../types';

export function detectBreakpoint(): NotedBreakpoint {
  const width = window.innerWidth;
  if (width <= 480) return 'mobile';
  if (width <= 1024) return 'tablet';
  return 'desktop';
}

export function getBreakpointLabel(bp: NotedBreakpoint): string {
  switch (bp) {
    case 'desktop': return 'Desktop';
    case 'fixed': return 'Fixed';
    case 'tablet': return 'Tablet';
    case 'mobile': return 'Mobile';
  }
}

export function getBreakpointWidth(bp: NotedBreakpoint): number {
  switch (bp) {
    case 'desktop': return 0; // responsive, no constraint
    case 'fixed': return 1440;
    case 'tablet': return 768;
    case 'mobile': return 375;
  }
}

/** Whether this breakpoint uses a fixed-width canvas (coordinate tools are reliable) */
export function isFixedBreakpoint(bp: NotedBreakpoint): boolean {
  return bp !== 'desktop';
}
