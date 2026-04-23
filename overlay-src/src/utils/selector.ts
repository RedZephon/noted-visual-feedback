export function generateStableSelector(el: Element): string {
  if (el.id) return `#${CSS.escape(el.id)}`;

  const path: string[] = [];
  let current: Element | null = el;

  while (current && current !== document.body && current !== document.documentElement) {
    let segment = current.tagName.toLowerCase();

    if (current.id) {
      path.unshift(`#${CSS.escape(current.id)}`);
      break;
    }

    const dataId = current.getAttribute('data-id')
      || current.getAttribute('data-testid')
      || current.getAttribute('data-section');
    if (dataId) {
      path.unshift(`[data-id="${CSS.escape(dataId)}"]`);
      break;
    }

    const parent = current.parentElement;
    if (parent) {
      const siblings = Array.from(parent.children)
        .filter(s => s.tagName === current!.tagName);
      if (siblings.length > 1) {
        segment += `:nth-of-type(${siblings.indexOf(current) + 1})`;
      }
    }

    path.unshift(segment);
    current = current.parentElement;
  }

  if (!path.length) return 'body';
  return path.join(' > ');
}
