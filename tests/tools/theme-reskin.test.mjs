import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { reskin } from '../../tools/theme-reskin.mjs';

const parent = JSON.parse(readFileSync(new URL('./fixtures/parent-theme.json', import.meta.url)));
const brand = {
  palette: { primary:'#144478', accent:'#eca867', accentHover:'#cf9259', accentTint:'#fbf0e4',
    surface:'#ffffff', surfaceElevated:'#f4f7fb', surfaceSunken:'#ecf1f7',
    foreground:'#1e2c39', foregroundMuted:'#5c6b82', border:'#e4eaf2', borderStrong:'#cdd9ec' },
  fonts: { body:{family:'Montserrat'}, heading:{family:'Montserrat'} }, radius:'8px',
};

test('reskin preserves all 11 parent slugs and overwrites brand colors', () => {
  const child = reskin(brand, parent, 'montserrat.woff2');
  const pal = child.settings.color.palette;
  assert.equal(pal.length, 11); // no dropped slugs
  const by = Object.fromEntries(pal.map((p) => [p.slug, p.color]));
  assert.equal(by['primary'], '#144478');
  assert.equal(by['accent'], '#eca867');
  assert.equal(by['accent-tint'], '#fbf0e4');
  assert.equal(by['surface'], '#ffffff');
});

test('reskin wires Montserrat into body+heading, leaves mono', () => {
  const child = reskin(brand, parent, 'montserrat.woff2');
  const fams = Object.fromEntries(child.settings.typography.fontFamilies.map((f) => [f.slug, f]));
  assert.match(fams['heading'].fontFamily, /^Montserrat,/);
  assert.equal(fams['heading'].fontFace[0].src[0], 'file:./assets/fonts/montserrat.woff2');
  assert.equal(fams['heading'].fontFace[0].fontWeight, '400 800');
  assert.equal(fams['mono'].fontFamily, 'ui-monospace, monospace'); // untouched
  assert.equal(child.version, 2);
});
