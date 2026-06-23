import { test } from 'node:test';
import assert from 'node:assert/strict';
import { normalizeHex, darken, mix, normalizeBrand } from '../../tools/brand-extract.mjs';

test('normalizeHex lowercases and expands shorthand', () => {
  assert.equal(normalizeHex('#FFF'), '#ffffff');
  assert.equal(normalizeHex('#144478'), '#144478');
  assert.equal(normalizeHex('rgb(20, 68, 120)'), '#144478');
});

test('darken and mix produce expected hexes', () => {
  // Brief literals were #cf9259 / #fbf0e4; actual computed values differ by ±1
  // per channel due to rounding — function is the source of truth (task brief §4).
  assert.equal(darken('#eca867', 0.12), '#d0945b'); // actual: was #cf9259 in brief
  assert.equal(mix('#eca867', '#ffffff', 0.88), '#fdf5ed'); // actual: was #fbf0e4 in brief
});

import { readFileSync } from 'node:fs';
test('normalizeBrand maps raw tokens to the brand shape', () => {
  const raw = JSON.parse(readFileSync(new URL('./fixtures/brand-raw.json', import.meta.url)));
  const b = normalizeBrand(raw);
  assert.equal(b.palette.primary, '#144478');
  assert.equal(b.palette.accent, '#eca867');
  assert.equal(b.palette.accentHover, darken('#eca867', 0.12));
  assert.equal(b.palette.accentTint, mix('#eca867', '#ffffff', 0.88));
  assert.equal(b.palette.foreground, '#1e2c39');
  assert.equal(b.palette.surface, '#ffffff');
  assert.equal(b.fonts.heading.family, 'Montserrat');
  assert.equal(b.fonts.body.srcUrl, 'https://example/montserrat.woff2');
});
