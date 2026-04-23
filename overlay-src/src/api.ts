import type { NotedConfig, NotedPage, NotedPin, NotedComment, NotedProject } from './types';

export class NotedAPIError extends Error {
  status: number;
  upgradeUrl: string | null;
  constructor(status: number, message: string, upgradeUrl?: string) {
    super(message);
    this.status = status;
    this.upgradeUrl = upgradeUrl || null;
    this.name = 'NotedAPIError';
  }
}

export class NotedAPI {
  private baseUrl: string;
  private nonce: string;
  private guestToken: string | null;

  constructor(config: NotedConfig) {
    this.baseUrl = config.restUrl;
    this.nonce = config.restNonce;
    this.guestToken = config.guestToken || null;
  }

  setGuestToken(token: string): void {
    this.guestToken = token;
  }

  private async request<T>(method: string, path: string, body?: Record<string, unknown>): Promise<T> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
    };

    if (this.nonce) {
      headers['X-WP-Nonce'] = this.nonce;
    }
    if (this.guestToken) {
      headers['X-Noted-Guest-Token'] = this.guestToken;
    }

    const res = await fetch(`${this.baseUrl}${path}`, {
      method,
      headers,
      credentials: 'same-origin',
      body: body ? JSON.stringify(body) : undefined,
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new NotedAPIError(
        res.status,
        err.message || 'Request failed',
        err.data?.upgrade_url || undefined,
      );
    }

    if (res.status === 204) return undefined as T;
    return res.json();
  }

  ensurePage(projectId: number, path: string, title: string): Promise<NotedPage> {
    return this.request('POST', 'pages/ensure', {
      project_id: projectId,
      url_path: path,
      title,
    });
  }

  listPins(pageId: number, filters?: Record<string, string>): Promise<NotedPin[]> {
    const params = new URLSearchParams({ page_id: String(pageId), ...filters });
    return this.request('GET', `pins?${params}`);
  }

  createPin(data: Record<string, unknown>): Promise<NotedPin> {
    return this.request('POST', 'pins', data);
  }

  updatePin(id: number, data: Partial<NotedPin>): Promise<NotedPin> {
    return this.request('PATCH', `pins/${id}`, data as Record<string, unknown>);
  }

  deletePin(id: number): Promise<void> {
    return this.request('DELETE', `pins/${id}`);
  }

  listComments(pinId: number): Promise<NotedComment[]> {
    return this.request('GET', `pins/${pinId}/comments`);
  }

  createComment(pinId: number, body: string, parentId?: number): Promise<NotedComment> {
    const data: Record<string, unknown> = { body };
    if (parentId) data.parent_id = parentId;
    return this.request('POST', `pins/${pinId}/comments`, data);
  }

  // Guest methods
  checkAccess(projectToken: string): Promise<{ page_title: string; url_path: string }> {
    return this.request('POST', 'guests/check-access', { project_token: projectToken });
  }

  async registerGuest(projectToken: string, name: string, email: string): Promise<{
    guest_id: number;
    token: string;
    project: { id: number; name: string };
  }> {
    const data: Record<string, unknown> = { project_token: projectToken, name, email };
    const res = await this.request<{ guest_id: number; token: string; project: { id: number; name: string } }>('POST', 'guests/register', data);
    this.guestToken = res.token;
    return res;
  }

  async validateGuest(token: string): Promise<{ valid: boolean; guest_id: number; name: string; email: string }> {
    try {
      const res = await this.request<{ guest_id: number; name: string; email: string }>('POST', 'guests/validate', { token });
      return { valid: true, guest_id: res.guest_id, name: res.name, email: res.email };
    } catch {
      return { valid: false, guest_id: 0, name: '', email: '' };
    }
  }

  // Share / project methods
  generateShareLink(projectId: number): Promise<{ token: string; share_url: string }> {
    return this.request('POST', `projects/${projectId}/share`);
  }

  updateProject(projectId: number, data: Record<string, unknown>): Promise<NotedProject> {
    return this.request('PATCH', `projects/${projectId}`, data);
  }

  getProject(projectId: number): Promise<NotedProject & { access_token?: string }> {
    return this.request('GET', `projects/${projectId}`);
  }

  // Export
  listDestinations(): Promise<{ id: string; label: string; available: boolean; connected: boolean; plan: string }[]> {
    return this.request('GET', 'export/destinations');
  }

  exportPins(pinIds: number[], destination: string, options?: Record<string, unknown>): Promise<{
    destination: string;
    results: { pin_id: number | string; success: boolean; ref: string | null; url: string | null; error: string | null }[];
  }> {
    return this.request('POST', 'export', { pin_ids: pinIds, destination, options });
  }

  // Walkthrough
  getWalkthroughState(): Promise<{ completed: boolean }> {
    return this.request('GET', 'walkthrough');
  }

  setWalkthroughState(completed: boolean): Promise<{ success: boolean; completed: boolean }> {
    return this.request('POST', 'walkthrough', { completed });
  }

  // Presence
  heartbeat(pageId: number, name?: string): Promise<{ ok: boolean }> {
    return this.request('POST', 'presence', { page_id: pageId, name });
  }

  getPresence(pageId: number): Promise<{ type: string; id: number; name: string; avatar: string }[]> {
    return this.request('GET', `presence?page_id=${pageId}`);
  }
}
