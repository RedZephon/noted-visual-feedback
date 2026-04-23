let strings: Record<string, string> = {};

export function initStrings(s: Record<string, string>) {
  strings = s;
}

export function t(key: string, ...args: (string | number)[]): string {
  let str = strings[key] || key;
  args.forEach(arg => {
    str = str.replace(/%[sd]/, String(arg));
  });
  return str;
}
