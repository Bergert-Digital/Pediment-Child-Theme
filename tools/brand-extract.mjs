#!/usr/bin/env node
/** Pure brand normalizer for /port-site. Capture layer lives in the skill. */

export function normalizeHex(input) {
  if (!input) return null;
  let s = String(input).trim();
  const rgb = s.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
  if (rgb) {
    return '#' + [rgb[1], rgb[2], rgb[3]]
      .map((n) => Number(n).toString(16).padStart(2, '0')).join('');
  }
  s = s.replace('#', '').toLowerCase();
  if (s.length === 3) s = s.split('').map((c) => c + c).join('');
  if (!/^[0-9a-f]{6}$/.test(s)) return null;
  return '#' + s;
}

const toRgb = (h) => {
  const x = normalizeHex(h).slice(1);
  return [0, 2, 4].map((i) => parseInt(x.slice(i, i + 2), 16));
};
const toHex = (rgb) =>
  '#' + rgb.map((n) => Math.max(0, Math.min(255, Math.round(n)))
    .toString(16).padStart(2, '0')).join('');

export function darken(hex, amount) {
  return toHex(toRgb(hex).map((c) => c * (1 - amount)));
}
export function mix(hex, withHex, ratio) {
  const a = toRgb(hex), b = toRgb(withHex);
  return toHex(a.map((c, i) => c * (1 - ratio) + b[i] * ratio));
}

export function normalizeBrand(raw) {
  const accent = normalizeHex(raw.buttonBg);
  const primary = normalizeHex(raw.bandBg) || normalizeHex(raw.headingColor);
  const font = (raw.fontFamilies && raw.fontFamilies[0]) || { name: 'system-ui', weights: ['400', '700'] };
  const fam = { family: font.name, weights: font.weights || ['400', '700'], srcUrl: font.srcUrl || null };
  return {
    palette: {
      primary,
      accent,
      accentHover: darken(accent, 0.12),
      accentTint: mix(accent, '#ffffff', 0.88),
      surface: '#ffffff',
      surfaceElevated: mix(primary, '#ffffff', 0.95),
      surfaceSunken: mix(primary, '#ffffff', 0.92),
      foreground: normalizeHex(raw.bodyColor) || normalizeHex(raw.headingColor),
      foregroundMuted: mix(normalizeHex(raw.bodyColor) || primary, '#ffffff', 0.45),
      border: mix(primary, '#ffffff', 0.9),
      borderStrong: mix(primary, '#ffffff', 0.8),
    },
    fonts: { body: fam, heading: fam },
    radius: (raw.radii && raw.radii[0]) || '8px',
  };
}
