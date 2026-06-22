#!/usr/bin/env node
/** Pure theme.json re-skinner for /port-site. Forks parent subtrees wholesale. */

const SLUG = {
  primary: 'primary', accent: 'accent', accentHover: 'accent-hover',
  accentTint: 'accent-tint', surface: 'surface', surfaceElevated: 'surface-elevated',
  surfaceSunken: 'surface-sunken', foreground: 'foreground',
  foregroundMuted: 'foreground-muted', border: 'border', borderStrong: 'border-strong',
};
const STACK = ', system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';

export function reskin(brand, parent, fontFile) {
  const bySlug = Object.fromEntries(Object.entries(SLUG).map(([k, s]) => [s, brand.palette[k]]));
  const palette = parent.settings.color.palette.map((p) =>
    bySlug[p.slug] ? { ...p, color: bySlug[p.slug] } : { ...p });

  const face = (fam) => ([{
    fontFamily: fam, fontWeight: '400 800', fontStyle: 'normal',
    fontDisplay: 'swap', src: ['file:./assets/fonts/' + fontFile],
  }]);
  const fontFamilies = parent.settings.typography.fontFamilies.map((f) => {
    if (f.slug === 'body' || f.slug === 'heading') {
      const fam = brand.fonts[f.slug].family;
      return { ...f, fontFamily: fam + STACK, fontFace: face(fam) };
    }
    return { ...f };
  });

  return {
    $schema: parent.$schema || 'https://schemas.wp.org/trunk/theme.json',
    version: parent.version || 2,
    settings: { color: { palette }, typography: { fontFamilies } },
  };
}
