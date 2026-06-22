# Fidelity-First Elementor → Pediment Migration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the port tool around fidelity-to-the-original: a `/port-site` phase that extracts the client's brand and re-skins the child `theme.json`, and a rewritten `/port-page` phase that builds each page live in wp-env and loops on an independent fidelity-critic subagent until each section matches the source.

**Architecture:** Two pure Node helpers (`brand-extract`, `theme-reskin`) with `node --test` unit tests do the deterministic transforms; two skills (`/port-site`, rewritten `/port-page`) carry the pipelines; a shared fidelity-critic rubric + dispatch template provide the independent visual gate. The existing `tools/blocks-catalog.mjs` generator and `docs/PEDIMENT-BLOCKS.md` are unchanged.

**Tech Stack:** Node 24 (ESM, built-in `node --test`), `@wordpress/env` (`wp-env run cli`), WordPress 6.9 block markup, Claude Code skills (Markdown + YAML frontmatter), browser automation for capture, subagent dispatch for the critic.

## Global Constraints

- PHP 8.1+, WordPress 6.9+; build via `@wordpress/scripts` (`npm run build` → `build/blocks/`). (AGENTS.md)
- **`theme.json` merges per top-level subtree, not per slug:** a declared `color.palette` / `typography.fontFamilies` array REPLACES the parent's wholesale — fork the full parent array and edit leaves; never emit a partial one. (README/AGENTS.md)
- **No color literals in custom block CSS** — use `var(--wp--preset--…)` tokens. (AGENTS.md)
- **Don't modify the installed parent theme** at `wp-content/themes/pediment/` (read-only). (AGENTS.md)
- Front-end Pediment wrapper classes are `starter-<block>`; `is-style-band-navy` only lightens `stat`/`pull-quote`/`social-links` (text bands must be `surface`/`elevated`). (verified this session)
- Pediment fades sections in on scroll — **wait ≥1.5s after a section enters view before capturing** (builder and critic). (verified this session)
- Child theme mounts under the workspace-basename slug (e.g. `bozeman`); resolve dynamically, never hard-code. (`tools/setup-env.mjs` precedent)
- wp-env for this workspace is at `http://localhost:8900` (gitignored `.wp-env.override.json` remap). Never start a second wp-env.
- Pure helpers are ESM `.mjs`, no TypeScript, no new dependencies (Node built-ins only).
- Conventional commits, imperative, ≤60-char summary, stage files BY NAME (never `git add -A`), include trailer `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`. `git push`/`gh` need explicit user go-ahead.

## File Structure

- Create `tools/brand-extract.mjs` — pure normalizer: raw captured tokens → brand JSON. One job.
- Create `tools/theme-reskin.mjs` — pure transform: brand JSON + parent `theme.json` → child `theme.json`. One job.
- Create `tests/tools/brand-extract.test.mjs`, `tests/tools/theme-reskin.test.mjs` + `tests/tools/fixtures/` additions.
- Create `.claude/skills/shared/visual-qa.md` (move from `port-page/`), `.claude/skills/shared/fidelity-critic-prompt.md`.
- Create `.claude/skills/port-site/SKILL.md`.
- Rewrite `.claude/skills/port-page/SKILL.md`.
- Modify `package.json` (`test:tools` already globs `tests/tools/*.test.mjs` — no change needed; verify).
- Unchanged: `tools/blocks-catalog.mjs`, `docs/PEDIMENT-BLOCKS.md`.

---

### Task 1: Brand-extract pure normalizer + tests

**Files:**
- Create: `tools/brand-extract.mjs`
- Create: `tests/tools/brand-extract.test.mjs`
- Create: `tests/tools/fixtures/brand-raw.json`

**Interfaces:**
- Produces: `normalizeBrand(raw) -> brand` — pure. `raw` is `{buttonBg, buttonText, headingColor, bodyColor, linkColor, bandBg, fontFamilies: [{name, weights:[...], srcUrl?}], radii:[...]}` captured from the source. Returns the normalized brand:
  `{ palette: {primary, accent, accentHover, accentTint, surface, surfaceElevated, surfaceSunken, foreground, foregroundMuted, border, borderStrong}, fonts: {body:{family,weights,srcUrl}, heading:{...}}, radius: string }`.
  `accentHover` = `darken(accent, 0.12)`, `accentTint` = `mix(accent, '#ffffff', 0.88)`. Hex normalized to lowercase 6-digit.
- Consumes: nothing from other tasks.

- [ ] **Step 1: Write failing test for hex normalization + accent derivation**

Create `tests/tools/brand-extract.test.mjs`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { normalizeHex, darken, mix, normalizeBrand } from '../../tools/brand-extract.mjs';

test('normalizeHex lowercases and expands shorthand', () => {
  assert.equal(normalizeHex('#FFF'), '#ffffff');
  assert.equal(normalizeHex('#144478'), '#144478');
  assert.equal(normalizeHex('rgb(20, 68, 120)'), '#144478');
});

test('darken and mix produce expected hexes', () => {
  assert.equal(darken('#eca867', 0.12), '#cf9259'); // 12% toward black
  assert.equal(mix('#eca867', '#ffffff', 0.88), '#fbf0e4');
});
```

- [ ] **Step 2: Run, verify it fails**

Run: `node --test tests/tools/brand-extract.test.mjs`
Expected: FAIL — module not found.

- [ ] **Step 3: Implement the color utilities**

Create `tools/brand-extract.mjs`:

```js
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
```

- [ ] **Step 4: Run, verify color tests pass**

Run: `node --test tests/tools/brand-extract.test.mjs`
Expected: PASS (normalizeHex, darken, mix).
Note: if `darken`/`mix` rounding differs by ±1 from the literals in Step 1, update the test's expected value to the actual computed output (record it) — the function is the source of truth; do not fudge the math.

- [ ] **Step 5: Write failing test for `normalizeBrand`**

Create `tests/tools/fixtures/brand-raw.json`:

```json
{
  "buttonBg": "rgb(236, 168, 103)",
  "buttonText": "#ffffff",
  "headingColor": "#144478",
  "bodyColor": "#1E2C39",
  "linkColor": "#144478",
  "bandBg": "#144478",
  "fontFamilies": [
    { "name": "Montserrat", "weights": ["400","600","700"], "srcUrl": "https://example/montserrat.woff2" }
  ],
  "radii": ["8px", "999px"]
}
```

Append to `tests/tools/brand-extract.test.mjs`:

```js
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
```

- [ ] **Step 6: Run, verify it fails**

Run: `node --test tests/tools/brand-extract.test.mjs`
Expected: FAIL — `normalizeBrand is not a function`.

- [ ] **Step 7: Implement `normalizeBrand`**

Append to `tools/brand-extract.mjs`:

```js
export function normalizeBrand(raw) {
  const accent = normalizeHex(raw.buttonBg);
  const primary = normalizeHex(raw.bandBg) || normalizeHex(raw.headingColor);
  const font = (raw.fontFamilies && raw.fontFamilies[0]) || { name: 'system-ui', weights: ['400','700'] };
  const fam = { family: font.name, weights: font.weights || ['400','700'], srcUrl: font.srcUrl || null };
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
```

- [ ] **Step 8: Run, verify all pass**

Run: `node --test tests/tools/brand-extract.test.mjs`
Expected: PASS (all).

- [ ] **Step 9: Commit**

```bash
git add tools/brand-extract.mjs tests/tools/brand-extract.test.mjs tests/tools/fixtures/brand-raw.json
git commit -m "feat(tools): add brand-extract normalizer + tests

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Theme-reskin pure transform + tests

**Files:**
- Create: `tools/theme-reskin.mjs`
- Create: `tests/tools/theme-reskin.test.mjs`
- Create: `tests/tools/fixtures/parent-theme.json`

**Interfaces:**
- Consumes: the `brand` object shape from Task 1 (`{palette:{primary,accent,…}, fonts:{body,heading}, radius}`).
- Produces: `reskin(brand, parentThemeJson, fontFile) -> childThemeJson` — pure. Returns a child `theme.json` object whose `settings.color.palette` is the parent's palette array **with every slug preserved** and the brand slugs (`primary`, `accent`, `accent-hover`, `accent-tint`, `surface`, `surface-elevated`, `surface-sunken`, `foreground`, `foreground-muted`, `border`, `border-strong`) overwritten by colour; and whose `settings.typography.fontFamilies` is the parent's array with `body`+`heading` families' `fontFamily` set to `"<Brand>, system-ui, -apple-system, \"Segoe UI\", Roboto, sans-serif"` and a single variable `fontFace` `{fontFamily, fontWeight:"400 800", fontStyle:"normal", fontDisplay:"swap", src:["file:./assets/fonts/" + fontFile]}`. `mono` family untouched. `$schema` and `version:2` preserved.

- [ ] **Step 1: Create the parent fixture**

Create `tests/tools/fixtures/parent-theme.json` (trimmed but structurally real):

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 2,
  "settings": {
    "color": { "palette": [
      { "slug": "primary", "color": "#0A1B33", "name": "Primary" },
      { "slug": "accent", "color": "#0E7490", "name": "Accent" },
      { "slug": "accent-hover", "color": "#155E75", "name": "Accent hover" },
      { "slug": "accent-tint", "color": "#E1F1F6", "name": "Accent tint" },
      { "slug": "surface", "color": "#FFFFFF", "name": "Surface" },
      { "slug": "surface-elevated", "color": "#F5F8FC", "name": "Surface elevated" },
      { "slug": "surface-sunken", "color": "#EEF3F8", "name": "Surface sunken" },
      { "slug": "foreground", "color": "#0B1B33", "name": "Foreground" },
      { "slug": "foreground-muted", "color": "#5C6B82", "name": "Foreground muted" },
      { "slug": "border", "color": "#E4EAF2", "name": "Border" },
      { "slug": "border-strong", "color": "#CDD9EC", "name": "Border strong" }
    ] },
    "typography": { "fontFamilies": [
      { "slug": "body", "name": "Body", "fontFamily": "\"Plus Jakarta Sans\", sans-serif", "fontFace": [] },
      { "slug": "heading", "name": "Heading", "fontFamily": "\"Plus Jakarta Sans\", sans-serif", "fontFace": [] },
      { "slug": "mono", "name": "Mono", "fontFamily": "ui-monospace, monospace" }
    ] }
  }
}
```

- [ ] **Step 2: Write the failing test**

Create `tests/tools/theme-reskin.test.mjs`:

```js
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
```

- [ ] **Step 3: Run, verify it fails**

Run: `node --test tests/tools/theme-reskin.test.mjs`
Expected: FAIL — module not found.

- [ ] **Step 4: Implement `reskin`**

Create `tools/theme-reskin.mjs`:

```js
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
```

- [ ] **Step 5: Run, verify all pass**

Run: `node --test tests/tools/theme-reskin.test.mjs`
Expected: PASS (both tests).

- [ ] **Step 6: Run the whole tool suite (no regressions in catalog/brand tests)**

Run: `npm run test:tools`
Expected: PASS — all `tests/tools/*.test.mjs`.

- [ ] **Step 7: Commit**

```bash
git add tools/theme-reskin.mjs tests/tools/theme-reskin.test.mjs tests/tools/fixtures/parent-theme.json
git commit -m "feat(tools): add theme.json re-skin transform + tests

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Shared fidelity-critic rubric + dispatch template

**Files:**
- Create: `.claude/skills/shared/visual-qa.md` (move + adapt the existing `.claude/skills/port-page/visual-qa.md`)
- Create: `.claude/skills/shared/fidelity-critic-prompt.md`
- Delete: `.claude/skills/port-page/visual-qa.md` (after moving)

**Interfaces:**
- Consumes: nothing executable — these are prompt/doc artifacts read by the skills in Tasks 4–5.
- Produces: the rubric (`shared/visual-qa.md`) and the critic dispatch template (`shared/fidelity-critic-prompt.md`) that `/port-page` and `/port-site` reference.

- [ ] **Step 1: Move the rubric to shared and reframe it to fidelity-vs-original**

Move `.claude/skills/port-page/visual-qa.md` → `.claude/skills/shared/visual-qa.md`. Keep its categories (spacing, image sizing, block-usage, contrast, alignment, content fidelity, polish) and ADD/elevate **fidelity-to-original** as the primary category: each rendered section is judged against the *matching source section*, not a generic Pediment reference. Pass bar: every section ≥ 4/5 per category AND zero high-severity diffs AND holistic "matches the original closely enough" = true. Keep the "wait out entrance animations before capture" note.

- [ ] **Step 2: Write the critic dispatch template**

Create `.claude/skills/shared/fidelity-critic-prompt.md` — a fenced dispatch template for an independent vision subagent. It must specify:
- **Inputs given in the dispatch:** built-page URL (e.g. `http://localhost:8900/<slug>/`), source URL, the ordered section list (labels + brief descriptions from `inventory.md`), and the rubric path `.claude/skills/shared/visual-qa.md`.
- **What the critic does:** create its own browser tab; for each section, capture the rendered section and the matching source section (waiting out entrance animations); compare against the rubric.
- **Required structured output (StructuredOutput / JSON):**
  ```
  { "sections": [ { "label": str, "scores": {"spacing":1-5,"imageSizing":1-5,"blockUsage":1-5,"contrast":1-5,"alignment":1-5,"contentFidelity":1-5,"fidelityToOriginal":1-5}, "pass": bool, "issues": [ {"category": str, "severity": "high|med|low", "observation": str, "fix": str} ] } ], "overallPass": bool }
  ```
- **Independence rule:** the critic must NOT be told what the builder intended or be asked to confirm a section is fine — it evaluates blind against the source. No "don't flag X" instructions.

- [ ] **Step 3: Verify the move and references**

Run: `test -f .claude/skills/shared/visual-qa.md && test ! -f .claude/skills/port-page/visual-qa.md && echo ok`
Expected: `ok`.
Run: `head -5 .claude/skills/shared/fidelity-critic-prompt.md`
Expected: shows the template intro.

- [ ] **Step 4: Commit**

```bash
git add .claude/skills/shared/visual-qa.md .claude/skills/shared/fidelity-critic-prompt.md
git rm .claude/skills/port-page/visual-qa.md
git commit -m "feat(skills): add shared fidelity-critic rubric + dispatch

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: `/port-site` skill (brand → theme re-skin)

**Files:**
- Create: `.claude/skills/port-site/SKILL.md`

**Interfaces:**
- Consumes: `tools/brand-extract.mjs` (`normalizeBrand`), `tools/theme-reskin.mjs` (`reskin`), browser automation, wp-env, the shared fidelity critic (Task 3).
- Produces: the `/port-site <homepage-url>` command; its output is a re-skinned `theme.json` + `assets/fonts/<font>.woff2` in the repo.

- [ ] **Step 1: Write the skill**

Create `.claude/skills/port-site/SKILL.md` with valid frontmatter (`name: port-site`, a `description`) and the phase-1 pipeline:

1. **Preconditions:** wp-env running (else stop: `npm run env:start`); browser available.
2. **Capture brand** from the rendered source homepage: read computed styles of representative elements — primary button background → `buttonBg`; h1/headings → `headingColor` + font family; body text → `bodyColor`; links → `linkColor`; the hero/nav band → `bandBg`; collect font families + weights + the webfont URL(s); a couple of border-radii. Assemble the `raw` object (shape per Task 1) and pass through `normalizeBrand` (call the module via a small `node -e` or inline node script).
3. **Download webfonts** into `assets/fonts/` (consolidate a variable font to one file; note the filename).
4. **Re-skin** `theme.json`: read the parent `theme.json` from the container (`wp-content/themes/pediment/theme.json`), call `reskin(brand, parent, fontFile)`, write the result to repo `theme.json`. Then `wp-env run cli wp transient delete --all` so WP re-parses.
5. **Verify:** render a representative page; dispatch the shared **fidelity critic** scoped to brand (accent shows on buttons, fonts applied, band colors) against the source. Report.
6. **Hand-off:** tell the user the brand is established; `/port-page <url>` can now run. Note header/footer are a later phase.

Include the constraint reminders: fork the full parent subtree (the helper does this), clear transients after writing, resolve theme slug dynamically.

- [ ] **Step 2: Validate frontmatter + referenced commands exist**

Run: `head -5 .claude/skills/port-site/SKILL.md`
Expected: valid frontmatter with `name: port-site`.
Run: `node -e "import('./tools/brand-extract.mjs').then(m=>console.log(typeof m.normalizeBrand)); import('./tools/theme-reskin.mjs').then(m=>console.log(typeof m.reskin))"`
Expected: prints `function` twice (the skill's helpers resolve).

- [ ] **Step 3: Commit**

```bash
git add .claude/skills/port-site/SKILL.md
git commit -m "feat(skills): add /port-site brand + theme re-skin

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: Rewrite `/port-page` (build-in-wp-env + fidelity loop)

**Files:**
- Modify: `.claude/skills/port-page/SKILL.md` (full rewrite)

**Interfaces:**
- Consumes: `npm run blocks:catalog` + `docs/PEDIMENT-BLOCKS.md` (existing), the shared fidelity critic (Task 3), the re-skinned theme from `/port-site` (Task 4), wp-env, browser automation, the `promo-banner` block convention for new blocks.
- Produces: the rewritten `/port-page <url>` command; output is a live page in wp-env + `final.html` export under `.context/port/<slug>/`.

- [ ] **Step 1: Rewrite the skill**

Replace `.claude/skills/port-page/SKILL.md` body with the phase-2 pipeline (keep valid frontmatter `name: port-page`, updated description):

1. **Preconditions:** wp-env running; **child theme already branded** — if repo `theme.json` has no `settings`, STOP and tell the user to run `/port-site` first; browser available.
2. **Extract** → `.context/port/<slug>/`: `source.html`, full + per-section screenshots; `inventory.md` with each section's purpose, literal copy, image refs, **and visual treatment**.
3. **Catalog refresh** → `npm run blocks:catalog`; stop on drift; read `docs/PEDIMENT-BLOCKS.md`.
4. **Map (fidelity ladder)** → `mapping.md`: each section → existing block | existing + variant/CSS | `NEW BLOCK`, with the visual-treatment rationale.
5. **New-block gate** (only mandatory stop): if any `NEW BLOCK`, present all at once (name, why steps 1–2 can't match the original, attributes/slots); on approval scaffold per `src/blocks/promo-banner/` (`var(--wp--preset--…)` only), `npm run build`, re-catalog; declined → coverage TODO, no fabricated markup.
6. **Build in wp-env:** import each referenced image (`wp media import --porcelain` → attachment ID); compose page markup from adapted blocks referencing real IDs + brand bands (respect the `is-style-band-navy` text rule); `wp post create/update` the page; capture its URL.
7. **Fidelity gate loop:** dispatch the shared fidelity critic (Task 3) with built URL + source URL + section list; apply fixes to failing sections; re-render; re-dispatch until `overallPass`. Record rounds in `.context/port/<slug>/fidelity-qa.md`.
8. **Export + hand-off:** write `final.html`; tell the user the page is live at its URL and the export path; list any declined-section TODOs.

Include the verified rules inline: wait out entrance animations before capture; `is-style-band-navy` only lightens stat/pull-quote/social-links; reference media by attachment ID (never hotlink); resolve theme slug dynamically.

- [ ] **Step 2: Validate frontmatter + parse-safety reference**

Run: `head -5 .claude/skills/port-page/SKILL.md`
Expected: valid frontmatter `name: port-page`, new description mentioning fidelity/build-in-wp-env.
Run: `grep -c "is-style-band-navy" .claude/skills/port-page/SKILL.md`
Expected: ≥ 1 (the contrast rule is documented).

- [ ] **Step 3: Commit**

```bash
git add .claude/skills/port-page/SKILL.md
git commit -m "feat(skills): rewrite /port-page for fidelity-first build

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: Manual end-to-end validation

**Files:** none (validation only).

**Interfaces:** consumes the full Task 1–5 toolchain.

- [ ] **Step 1: Run `/port-site` against berlinerteam.de**

Invoke `/port-site https://berlinerteam.de`. Confirm `theme.json` gains a forked palette (navy/orange) + Montserrat with `@font-face`, the font lands in `assets/fonts/`, and a rendered page shows orange buttons + Montserrat (critic brand check passes).

- [ ] **Step 2: Run `/port-page` against the homepage**

Invoke `/port-page https://berlinerteam.de`. Approve/decline new-block proposals at the gate. Let the fidelity loop run.

- [ ] **Step 3: Inspect results**

Confirm `.context/port/<slug>/` has `inventory.md`, `mapping.md`, `fidelity-qa.md` (with per-section passing verdicts), `final.html`. Open the live page; confirm each section matches the source materially better than the pre-redesign run (brand applied, hero with image+padding, constrained images, correct block usage). The pass criterion is the critic's `overallPass` + developer review.

No automated test drives the live browser+model+subagent pipeline — consistent with the existing live-AI e2e being manual-only.

---

## Self-Review

**Spec coverage:**
- Brand extraction → normalized brand → Task 1 (`brand-extract`). ✓
- `theme.json` re-skin forking full parent subtree → Task 2 (`theme-reskin`) + test asserting 11 slugs preserved. ✓
- Independent fidelity-critic + rubric → Task 3 (shared rubric + dispatch template, structured output, independence rule). ✓
- `/port-site` phase (brand/theme, once; header/footer deferred) → Task 4. ✓
- `/port-page` rewrite (build-in-wp-env, media-by-ID, new-block gate, fidelity loop) → Task 5. ✓
- Catalog generator unchanged → noted; not a task. ✓
- Two-phase precondition (port-page requires branded theme) → Task 5 step 1. ✓
- Manual e2e validation → Task 6. ✓
- Demo-artifact revert already done pre-plan (theme.json empty, font removed) → no task needed. ✓

**Placeholder scan:** No TBD/TODO as gaps; the `fidelity-qa.md`/coverage TODOs are runtime artifacts. Color-math test values carry a Step-4 note to reconcile to the function's actual output (the function is source of truth) rather than leaving a guess.

**Type consistency:** `normalizeBrand(raw)→brand`, `reskin(brand, parent, fontFile)→childThemeJson`, and the `brand` shape (`palette.{primary,accent,accentHover,accentTint,…}`, `fonts.{body,heading}.family`) are used identically across Tasks 1, 2, and 4. The critic's JSON schema (Task 3) is the same one consumed by the loop in Task 5. `SLUG` map keys (camelCase brand) → kebab palette slugs are consistent with the parent fixture slugs.
