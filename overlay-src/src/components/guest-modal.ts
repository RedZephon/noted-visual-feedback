import type { NotedConfig } from '../types';
import type { NotedState } from '../state';
import type { NotedAPI } from '../api';
import { h } from '../utils/dom';
import { t } from '../utils/i18n';

type Store = { get(): NotedState; set(p: Partial<NotedState>): void };

export interface GuestModalHandle {
  update(state: NotedState): void;
  destroy(): void;
}

export function createGuestModal(
  container: HTMLElement,
  config: NotedConfig,
  _store: Store,
  api: NotedAPI,
  onRegistered: (guestId: number, name: string, email: string) => void,
): GuestModalHandle {
  const backdrop = h('div', { class: 'noted-modal-backdrop' });
  const modal = h('div', { class: 'noted-modal' });

  const title = h('h2', { class: 'noted-modal-title' }, t('welcomeTitle'));
  const subtitle = h('p', { class: 'noted-modal-subtitle' }, t('welcomeSubtitle'));

  const form = h('div', { class: 'noted-modal-form' });

  // Name field
  const nameLabel = h('label', { class: 'noted-modal-label' }, t('yourName') + ' *');
  const nameInput = document.createElement('input') as HTMLInputElement;
  nameInput.type = 'text';
  nameInput.className = 'noted-modal-input';
  nameInput.placeholder = t('yourName');
  nameInput.required = true;

  // Pre-fill from localStorage
  const cachedName = localStorage.getItem(`noted_guest_name_${config.projectId}`);
  if (cachedName) nameInput.value = cachedName;

  // Email field
  const emailLabel = h('label', { class: 'noted-modal-label' }, t('yourEmail'));
  const emailInput = document.createElement('input') as HTMLInputElement;
  emailInput.type = 'email';
  emailInput.className = 'noted-modal-input';
  emailInput.placeholder = 'your@email.com';

  const cachedEmail = localStorage.getItem(`noted_guest_email_${config.projectId}`);
  if (cachedEmail) emailInput.value = cachedEmail;

  const hint = h('p', { class: 'noted-modal-hint' }, 'Your feedback will be attributed to this name.');

  // Error
  const errorEl = h('div', { class: 'noted-modal-error' });
  errorEl.style.display = 'none';

  // Submit
  const submitBtn = h('button', {
    class: 'noted-btn noted-btn-primary noted-modal-submit',
    onClick: () => submit(),
  }, t('continue'));

  // Footer
  const footer = h('p', { class: 'noted-modal-footer' });
  if (t('poweredByNoted')) {
    const footerLink = document.createElement('a');
    footerLink.href = 'https://wpnoted.com';
    footerLink.target = '_blank';
    footerLink.rel = 'noopener';
    footerLink.textContent = 'Noted';
    footer.appendChild(document.createTextNode(t('poweredByNoted').replace('Noted', '')));
    footer.appendChild(footerLink);
  }

  form.append(nameLabel, nameInput, emailLabel, emailInput, hint, errorEl, submitBtn);
  modal.append(title, subtitle, form, footer);
  backdrop.appendChild(modal);

  container.appendChild(backdrop);

  // Disable submit when name is empty
  function updateSubmitState() {
    if (nameInput.value.trim()) {
      submitBtn.removeAttribute('disabled');
    } else {
      submitBtn.setAttribute('disabled', '');
    }
  }
  nameInput.addEventListener('input', updateSubmitState);
  updateSubmitState();

  nameInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') submit();
  });
  emailInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') submit();
  });

  async function submit() {
    const name = nameInput.value.trim();
    if (!name) return;
    const email = emailInput.value.trim();

    submitBtn.setAttribute('disabled', '');
    submitBtn.textContent = t('connecting');
    errorEl.style.display = 'none';

    try {
      const result = await api.registerGuest(config.guestToken, name, email);

      // Store session in localStorage
      localStorage.setItem(`noted_guest_token_${config.projectId}`, result.token);
      localStorage.setItem(`noted_guest_name_${config.projectId}`, name);
      if (email) localStorage.setItem(`noted_guest_email_${config.projectId}`, email);

      onRegistered(result.guest_id, name, email);
    } catch (err: any) {
      errorEl.textContent = err.message || 'Registration failed. Please try again.';
      errorEl.style.display = 'block';
      submitBtn.removeAttribute('disabled');
      submitBtn.textContent = t('continue');
    }
  }

  function update(state: NotedState) {
    backdrop.style.display = state.guestPhase === 'register' ? 'flex' : 'none';
    if (state.guestPhase === 'register') {
      setTimeout(() => nameInput.focus(), 100);
    }
  }

  return {
    update,
    destroy() { backdrop.remove(); },
  };
}
