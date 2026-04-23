import type { NotedConfig } from '../types';
import type { NotedState } from '../state';
import type { NotedAPI } from '../api';
import { h, clearChildren } from '../utils/dom';
import { t } from '../utils/i18n';

type Store = { get(): NotedState; set(p: Partial<NotedState>): void };

const closeIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

export interface ShareModalHandle {
  update(state: NotedState): void;
}

export function createShareModal(
  container: HTMLElement,
  config: NotedConfig,
  store: Store,
  api: NotedAPI,
): ShareModalHandle {
  const backdrop = h('div', { class: 'noted-modal-backdrop', onClick: (e: Event) => {
    if (e.target === backdrop) store.set({ shareModalOpen: false });
  }});
  const modal = h('div', { class: 'noted-modal noted-share-modal' });

  // Header
  const header = h('div', { class: 'noted-share-header' });
  const title = h('h2', { class: 'noted-modal-title', style: 'text-align:left;margin:0;' }, t('shareReviewLink'));
  const closeBtn = h('button', { class: 'noted-close-btn', onClick: () => store.set({ shareModalOpen: false }) });
  closeBtn.innerHTML = closeIcon;
  header.append(title, closeBtn);
  modal.appendChild(header);

  // Content area
  const content = h('div', { class: 'noted-share-content' });
  modal.appendChild(content);

  backdrop.appendChild(modal);
  container.appendChild(backdrop);

  let projectData: any = null;

  async function loadProjectData() {
    try {
      projectData = await api.getProject(config.projectId);
      renderContent();
    } catch (err) {
      content.textContent = 'Failed to load project data.';
    }
  }

  function renderContent() {
    clearChildren(content);

    if (!projectData || !projectData.access_token) {
      // No share link yet
      const msg = h('p', { class: 'noted-modal-subtitle', style: 'text-align:left;margin-bottom:16px;' },
        'Generate a shareable link so reviewers can leave feedback without logging in.');
      const genBtn = h('button', {
        class: 'noted-btn noted-btn-primary noted-modal-submit',
        onClick: async () => {
          genBtn.setAttribute('disabled', '');
          genBtn.textContent = 'Generating...';
          try {
            const result = await api.generateShareLink(config.projectId);
            projectData = { ...projectData, access_token: result.token };
            renderContent();
          } catch {
            genBtn.removeAttribute('disabled');
            genBtn.textContent = 'Generate Link';
          }
        },
      }, t('generateLink'));
      content.append(msg, genBtn);
      return;
    }

    // Share link exists
    const shareUrl = `${window.location.origin}/?noted=${projectData.access_token}`;

    // Link field
    const linkRow = h('div', { class: 'noted-share-link-row' });
    const linkInput = document.createElement('input') as HTMLInputElement;
    linkInput.type = 'text';
    linkInput.className = 'noted-modal-input';
    linkInput.readOnly = true;
    linkInput.value = shareUrl;
    linkInput.style.flex = '1';

    const copyBtn = h('button', {
      class: 'noted-btn noted-btn-primary',
      style: 'flex-shrink:0;',
      onClick: async () => {
        try {
          await navigator.clipboard.writeText(shareUrl);
        } catch {
          const ta = document.createElement('textarea');
          ta.value = shareUrl;
          ta.style.cssText = 'position:fixed;opacity:0;left:-9999px;';
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          ta.remove();
        }
        copyBtn.textContent = t('copied');
        setTimeout(() => { copyBtn.textContent = t('copyLink'); }, 2000);
      },
    }, t('copyLink'));
    linkRow.append(linkInput, copyBtn);
    content.appendChild(linkRow);

    // Revoke
    const revokeBtn = h('button', {
      class: 'noted-btn noted-btn-ghost',
      style: 'color:var(--noted-red);margin-top:16px;',
      onClick: async () => {
        if (!confirm('This will invalidate all existing share links. Continue?')) return;
        revokeBtn.setAttribute('disabled', '');
        await api.updateProject(config.projectId, { access_token: '' });
        projectData.access_token = null;
        renderContent();
      },
    }, 'Revoke Link');
    content.appendChild(revokeBtn);
  }

  function update(state: NotedState) {
    backdrop.style.display = state.shareModalOpen ? 'flex' : 'none';
    if (state.shareModalOpen && !projectData) {
      loadProjectData();
    }
  }

  return { update };
}
