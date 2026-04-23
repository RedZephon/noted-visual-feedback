export function h(
  tag: string,
  attrs?: Record<string, string | boolean | EventListener>,
  ...children: (Node | string | null | undefined)[]
): HTMLElement {
  const el = document.createElement(tag);

  if (attrs) {
    for (const [key, val] of Object.entries(attrs)) {
      if (key.startsWith('on') && typeof val === 'function') {
        el.addEventListener(key.slice(2).toLowerCase(), val as EventListener);
      } else if (typeof val === 'boolean') {
        if (val) el.setAttribute(key, '');
      } else if (typeof val === 'string') {
        el.setAttribute(key, val);
      }
    }
  }

  for (const child of children) {
    if (child == null) continue;
    if (typeof child === 'string') {
      el.appendChild(document.createTextNode(child));
    } else {
      el.appendChild(child);
    }
  }

  return el;
}

export function clearChildren(el: HTMLElement): void {
  while (el.firstChild) el.removeChild(el.firstChild);
}
