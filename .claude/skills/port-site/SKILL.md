---
name: port-site
description: Capture a client's brand from their live homepage (colors, fonts, radii), re-skin the child theme.json, and verify the brand renders correctly. Run once per client before using /port-page. Produces assets/fonts/<font>.woff2 and a re-skinned theme.json in the repo.
---

# Port a site's brand to Pediment (phase 1)

Re-skin the child theme to match a client's visual brand. Extract colors, fonts,
and radii from their live homepage; normalize them through `tools/brand-extract.mjs`;
download the webfont; patch `theme.json` via `tools/theme-reskin.mjs`; verify the
result with the shared fidelity critic. Phase 2 (header/footer template parts) is a
later step — this skill only establishes the brand token layer.

**Argument:** the client's homepage URL (the public, rendered URL — not a staging
URL that requires auth).

All per-run scratch files go under `.context/port-site/` (gitignored).

---

## Preconditions (check first, stop if unmet)

1. **wp-env is running.** Run `npm run env:mode`. If the output is not `running`,
   tell the user to run `npm run env:start` and stop.
2. **Browser automation is available.** If any browser step below fails to open a
   tab, stop and report.
3. **The homepage URL is publicly accessible** (no auth, not a localhost URL).
   Load the URL in the browser; if it returns an error or redirect loop, stop.

---

## Pipeline — execute in order

### Step 1: Resolve the theme slug

The child-theme slug is the basename of the current working directory — never
hard-code it. Resolve it once and use it throughout:

```bash
THEME_SLUG=$(basename "$PWD")
```

The child-theme lives in the wp-env container at:
`wp-content/themes/$THEME_SLUG/`

The parent theme.json lives at:
`wp-content/themes/pediment/theme.json`

---

### Step 2: Capture brand from the source homepage

Open the homepage URL in a browser tab. Wait for the page to fully load (network
idle). Then run JavaScript in the tab to capture computed styles from representative
elements. Collect all of the following into a `raw` object:

| Field | Source element & property |
|---|---|
| `buttonBg` | First `<a>` or `<button>` that looks like a primary CTA button → `background-color` (computed) |
| `buttonText` | Same button → `color` (computed) |
| `headingColor` | First `<h1>` (or `<h2>` if no `<h1>`) → `color` (computed) |
| `bodyColor` | `<body>` or first `<p>` → `color` (computed) |
| `linkColor` | First `<a>` in body text (not nav, not button) → `color` (computed) |
| `bandBg` | The hero/nav band — try the `<header>` or outermost hero wrapper → `background-color` (computed); if transparent/white, try the first full-bleed colored section |
| `fontFamilies` | Array of `{name, weights, srcUrl}` — see below |
| `radii` | Array of `border-radius` values from buttons and card-like containers (collect 2–3 distinct values, deduplicated) |

**Collecting `fontFamilies`:**
1. Query `document.fonts` (the FontFaceSet). Filter to loaded faces whose `family`
   is not a generic system font (skip `system-ui`, `-apple-system`, `Arial`,
   `Helvetica`, `Georgia`, `Times`, `Roboto`, `sans-serif`, `serif`, `monospace`).
2. For each distinct family, record:
   - `name`: the font-family name string
   - `weights`: the weights available (from loaded FontFace objects)
   - `srcUrl`: the `src` URL of the first FontFace entry for that family —
     inspect `<link rel="stylesheet">` tags or `@font-face` rules in
     `document.styleSheets` to find the CSS/woff2 URL. Prefer a `.woff2` URL.
     If only a Google Fonts CSS URL is found (fonts.googleapis.com), record that
     as `srcUrl` — the download step handles it.
3. If `document.fonts` yields nothing useful, fall back to reading
   `getComputedStyle(document.body).fontFamily` and the first heading; extract
   the first non-generic family name. Record `srcUrl: null` and note that the
   font must be found manually.

Save the raw JS object as JSON to `.context/port-site/raw-brand.json`.

---

### Step 3: Normalize the brand

Pass `raw-brand.json` through `normalizeBrand` from `tools/brand-extract.mjs`.
Call it via an inline Node invocation:

```bash
node --input-type=module <<'EOF'
import { readFileSync, writeFileSync } from 'fs';
import { normalizeBrand } from './tools/brand-extract.mjs';

const raw = JSON.parse(readFileSync('.context/port-site/raw-brand.json', 'utf8'));
const brand = normalizeBrand(raw);
writeFileSync('.context/port-site/brand.json', JSON.stringify(brand, null, 2));
console.log(JSON.stringify(brand, null, 2));
EOF
```

Review the output. Sanity-check:
- `palette.accent` should be the CTA button color — if it is `null` (because
  `buttonBg` was not captured), stop and recapture with a more specific selector.
- `palette.primary` should be the hero/nav band color — if it is `null`, try
  re-running with `bandBg` set to the heading color as a fallback.
- `fonts.body.family` and `fonts.heading.family` should not be a generic
  (`system-ui`, `sans-serif`). If they are, revisit the font collection step.

Save the normalized brand to `.context/port-site/brand.json`.

---

### Step 4: Download the webfont

Use the `srcUrl` from `brand.json` → `fonts.body.srcUrl` (body and heading share
the same family via `normalizeBrand`).

**Case A — direct `.woff2` URL:**
Download the file directly:
```bash
mkdir -p assets/fonts
curl -L -o "assets/fonts/<FamilyName>.woff2" "<srcUrl>"
```
Name the file `<FamilyName>.woff2` (family name, spaces replaced with hyphens,
lowercased, e.g. `plus-jakarta-sans.woff2`).

**Case B — Google Fonts CSS URL (`fonts.googleapis.com`):**
1. Fetch the CSS with a modern `User-Agent` so it returns `.woff2` format:
   ```bash
   curl -A "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36" \
        -L "<srcUrl>" -o .context/port-site/gfonts.css
   ```
2. Extract the first `.woff2` URL from the CSS:
   ```bash
   grep -oP 'url\(\K[^)]+\.woff2' .context/port-site/gfonts.css | head -1
   ```
3. Download that `.woff2`:
   ```bash
   curl -L -o "assets/fonts/<FamilyName>.woff2" "<extracted-woff2-url>"
   ```

**Case C — no `srcUrl` (null):** Stop and ask the user to provide the font file
or Google Fonts URL. Do not proceed to reskin without a font file.

**Variable font consolidation:** if the font CSS exposes multiple weight files
(e.g. 400, 500, 700 as separate files), prefer a variable font file
(`wdth 100`, axis ranges in filename) — download only the variable font as a
single file. `reskin` registers it as `fontWeight: '400 800'`.

Record the final filename (e.g. `plus-jakarta-sans.woff2`) as `FONT_FILE`.
Verify the file exists and is > 1 KB before continuing.

---

### Step 5: Re-skin theme.json

**5a. Read the parent theme.json from the container:**
```bash
wp-env run cli cat wp-content/themes/pediment/theme.json \
  > .context/port-site/parent-theme.json
```

**5b. Call `reskin` with the brand and font file:**
```bash
node --input-type=module <<'EOF'
import { readFileSync, writeFileSync } from 'fs';
import { reskin } from './tools/theme-reskin.mjs';

const brand   = JSON.parse(readFileSync('.context/port-site/brand.json', 'utf8'));
const parent  = JSON.parse(readFileSync('.context/port-site/parent-theme.json', 'utf8'));
const FONT_FILE = process.env.FONT_FILE;

const child = reskin(brand, parent, FONT_FILE);
writeFileSync('theme.json', JSON.stringify(child, null, 2) + '\n');
console.log('theme.json written — palette slots:', Object.keys(child.settings.color.palette).length || child.settings.color.palette.length);
EOF
```
(Pass `FONT_FILE` in the environment, e.g. `FONT_FILE=plus-jakarta-sans.woff2 node ...`)

**What `reskin` does (do not duplicate this logic):**
- Forks the full parent `settings.color.palette` array — preserving every slot,
  overwriting only the named brand slots by slug.
- Forks the full parent `settings.typography.fontFamilies` array — overwriting
  the `body` and `heading` entries with the new family + `@font-face` pointing
  to `file:./assets/fonts/<FONT_FILE>`.
- Emits a minimal child `theme.json` (only `settings.color.palette` and
  `settings.typography.fontFamilies`) so WordPress merges it with the parent
  for all other settings. Do NOT write a full copy of every parent setting.

**5c. Clear WordPress transients** so WP re-parses the updated theme.json:
```bash
wp-env run cli wp transient delete --all
```

Verify the command exits 0. If it fails with "Error: unknown command", the WP CLI
container may need `--env-cwd` — try:
```bash
wp-env run --env-cwd=/var/www/html cli wp transient delete --all
```

---

### Step 6: Verify the brand renders

**6a. Open the wp-env local homepage** in the browser:
`http://localhost:8900/`

Let the page fully load. **Wait at least 1.5 s after each section enters the
viewport before capturing** — Pediment fades sections in on scroll and an early
screenshot will appear blank or grey.

Capture three screenshots:
- `full-page.png` — full page scroll composite
- `hero.png` — the hero / above-the-fold section
- `button.png` — a close-up of a CTA button

Save to `.context/port-site/screenshots/`.

**6b. Dispatch the fidelity critic** using the template in
`.claude/skills/shared/fidelity-critic-prompt.md`. Scope it to brand fidelity:

Fill placeholders:
- `{{BUILT_PAGE_URL}}`: `http://localhost:8900/`
- `{{SOURCE_URL}}`: the original homepage URL
- `{{SECTION_LIST}}`:
  ```
  hero: above-the-fold section — check accent color on CTA button, heading font, band background
  body-text: a paragraph or content section — check body font family and foreground color
  ```

The critic returns a JSON verdict. Parse it:
- If `overallPass: true` → proceed to hand-off.
- If `overallPass: false` → surface each failing section's `issues` array.
  For brand-only failures (wrong color token, font not applied), the usual fix
  is to re-check the raw capture (was the correct button selected?) and re-run
  from Step 3. For structural issues, note them as out-of-scope for this skill.

---

### Step 7: Commit the brand assets

Stage and commit the two brand artifacts:

```bash
git add theme.json "assets/fonts/$FONT_FILE"
git commit -m "feat(brand): apply <ClientName> brand tokens and webfont

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

Replace `<ClientName>` with the client's domain or name. Do not commit anything
under `.context/` (gitignored).

---

## Hand-off

Tell the user:

> Brand established. The child theme.json carries the client's color palette
> and `<FamilyName>` webfont. You can now run `/port-page <url>` for each page.
>
> **Not yet done:** header, footer, and nav template parts are a later phase
> and will be tackled separately — the site will render with the default
> Pediment header/footer until those are ported.

---

## Constraint reminders

- **Fork, do not copy-paste.** `reskin` forks the full parent palette and
  fontFamilies arrays. Never hand-write color values into theme.json — always
  go through `normalizeBrand` + `reskin`.
- **Clear transients after every theme.json write.** `wp transient delete --all`
  is mandatory; skipping it means WP serves stale cached tokens.
- **Theme slug is dynamic.** Always derive it from `basename "$PWD"`. Never
  hard-code `bozeman` or any other client name.
- **Entrance animations.** Always wait ≥ 1.5 s after a Pediment section enters
  the viewport before screenshotting. Capturing early will produce a blank/grey
  frame and fool the fidelity critic into reporting missing content.
- **One font file.** `reskin` wires a single `file:` `@font-face` entry.
  Consolidate to a variable font where possible; for non-variable fonts, use the
  regular weight (400) as the single file and accept that bolder weights will
  synthesize until a multi-weight setup is added manually.
