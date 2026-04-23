export interface NotedConfig {
  restUrl: string;
  restNonce: string;
  projectId: number;
  pagePath: string;
  pageTitle: string;
  position: string;
  brandColor: string;
  guestToken: string;
  currentUser: NotedUser | null;
  canResolve: boolean;
  canExport: boolean;
  isInternal: boolean;
  walkthroughCompleted: boolean;
  strings: Record<string, string>;
}

export interface NotedUser {
  id: number;
  name: string;
  email: string;
  avatar: string;
}

export interface NotedProject {
  id: number;
  name: string;
  status: string;
  access_token: string | null;
  toolbar_position: string;
  brand_color: string;
}

export interface NotedPage {
  id: number;
  project_id: number;
  url_path: string;
  title: string;
  sort_order: number;
}

export interface NotedPin {
  id: number;
  page_id: number;
  project_id: number;
  x_percent: number;
  y_percent: number;
  css_selector: string | null;
  selector_offset_x: number | null;
  selector_offset_y: number | null;
  viewport_width: number | null;
  scroll_y: number | null;
  breakpoint: NotedBreakpoint;
  pin_number: number;
  status: 'open' | 'resolved';
  author_type: 'user' | 'guest';
  author_user_id: number | null;
  author_guest_id: number | null;
  author_name?: string;
  author_avatar?: string;
  body: string;
  resolved_at: string | null;
  created_at: string;
  updated_at: string;
  comments?: NotedComment[];
}

export interface NotedComment {
  id: number;
  pin_id: number;
  parent_id: number | null;
  author_type: 'user' | 'guest';
  author_user_id: number | null;
  author_guest_id: number | null;
  author_name?: string;
  author_avatar?: string;
  body: string;
  created_at: string;
}

export type NotedTool = 'cursor' | 'pin' | 'element';
export type NotedMode = 'annotate' | 'view';
export type NotedBreakpoint = 'desktop' | 'fixed' | 'tablet' | 'mobile';

export interface PinPosition {
  css_selector: string;
  selector_offset_x: number;
  selector_offset_y: number;
  x_percent: number;
  y_percent: number;
  viewport_width: number;
  scroll_y: number;
  breakpoint: NotedBreakpoint;
}

export interface CalculatedPosition {
  left: number;
  top: number;
  method: 'selector' | 'percentage';
}
