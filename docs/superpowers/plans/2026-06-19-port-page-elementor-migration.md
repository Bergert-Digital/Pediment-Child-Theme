# `/port-page` Elementor → Pediment Migration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a `/port-page <url>` Claude Code skill in this child theme that rebuilds a single Elementor page natively in Pediment blocks, backed by an auto-refreshed block catalog, emitting a reviewable serialized-markup file plus downloaded media.

**Architecture:** Two committed code artifacts plus the skill. (1) A pure-Node generator `tools/blocks-catalog.mjs` reads the installed parent blocks (via `wp-env run`) and this child's `src/blocks`, and emits a human-readable `docs/PEDIMENT-BLOCKS.md`, preserving hand-written "use when" guidance. (2) `.claude/skills/port-page/SKILL.md` drives an ordered extract → catalog-refresh → map → new-block-gate → build → verify pipeline, writing all per-run artifacts to a gitignored `.context/port/<slug>/`.

**Tech Stack:** Node 24 (ESM, built-in `node --test`), `@wordpress/env` (`wp-env run cli`), `@wordpress/scripts` build, WordPress 6.9 block markup, Claude Code skills (Markdown + YAML frontmatter), browser automation for source capture.

## Global Constraints

- PHP 8.1+, WordPress 6.9+; build via `@wordpress/scripts` (`npm run build` → `build/blocks/`). (verbatim from AGENTS.md)
- **No color literals in custom block CSS** — use `var(--wp--preset--…)` tokens declared in `theme.json`. (AGENTS.md)
- **Don't modify the installed parent theme** at `wp-content/themes/pediment/` — read-only. (AGENTS.md)
- Front-end Pediment wrapper classes are `starter-<block>` (e.g. `starter-hero`, `starter-cta`, `starter-faq`, `starter-prose`, `starter-pull-quote`, `starter-stat`, `starter-contact-form`, `starter-blog-index`) — NOT `wp-block-pediment-*`. (from the AI-page-gen plan's pinned facts)
- Child block convention is `promo-banner`: `block.json` + `render.php` + `edit.tsx` + `index.tsx` + `style.scss`, `name: "pediment-child/<x>"`, `textdomain: "pediment-child"`. (existing `src/blocks/promo-banner/`)
- Commits: conventional, imperative, ≤60-char summary, stage files by name (never `git add -A`), include `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>` trailer. `git push` / `gh` need explicit user go-ahead. (AGENTS.md + user policy)
- The canonical dev env is the child-theme wp-env at `http://localhost:8890`; never start a second wp-env. (AGENTS.md)

---

## File Structure

- Create `tools/blocks-catalog.mjs` — block-catalog generator. One job: turn parsed `block.json` (+ optional `render.php`) records into the catalog Markdown, and provide a CLI that gathers those records and writes `docs/PEDIMENT-BLOCKS.md`.
- Create `tests/tools/blocks-catalog.test.mjs` — `node --test` unit tests for the generator's pure core.
- Create `tests/tools/fixtures/parent-blocks/hero/block.json`, `.../hero/render.php`, `tests/tools/fixtures/child-blocks/promo-banner/block.json` — generator test fixtures.
- Create `docs/PEDIMENT-BLOCKS.md` — committed, generated catalog (regenerated from the live install; "use when" notes hand-curated).
- Create `.claude/skills/port-page/SKILL.md` — the `/port-page` skill (the pipeline).
- Modify `package.json` — add `blocks:catalog` and `test:tools` scripts.
- Modify `.gitignore` — ignore the per-run working area `/.context/`.
- Modify `.distignore` — exclude `.claude` from the shipped theme zip.

---

### Task 1: Block-catalog generator (pure core + CLI) with tests

**Files:**
- Create: `tools/blocks-catalog.mjs`
- Create: `tests/tools/blocks-catalog.test.mjs`
- Create: `tests/tools/fixtures/parent-blocks/hero/block.json`
- Create: `tests/tools/fixtures/parent-blocks/hero/render.php`
- Create: `tests/tools/fixtures/child-blocks/promo-banner/block.json`
- Modify: `package.json` (scripts)

**Interfaces:**
- Produces:
  - `buildCatalog(records, existingDoc) -> string` — pure. `records` is `Array<{ source: 'parent'|'child', blockJson: object, renderPhp?: string }>`. `existingDoc` is the current `docs/PEDIMENT-BLOCKS.md` contents (or `''`). Returns the full Markdown document.
  - `extractWrapperClass(renderPhp) -> string|null` — pure. Returns the first `starter-…` class literal found in a render.php string, else `null`.
  - `parsePreservedNotes(existingDoc) -> Record<blockName,string>` — pure. Maps block name → the human "Use when" note text already in the doc.
- Consumes: nothing from other tasks.

- [ ] **Step 1: Write the failing test for `extractWrapperClass`**

Create `tests/tools/blocks-catalog.test.mjs`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { extractWrapperClass, parsePreservedNotes, buildCatalog } from '../../tools/blocks-catalog.mjs';

test('extractWrapperClass finds the starter- wrapper class', () => {
  const php = `$w = get_block_wrapper_attributes( array( 'class' => 'starter-hero' ) );`;
  assert.equal(extractWrapperClass(php), 'starter-hero');
});

test('extractWrapperClass returns null when absent', () => {
  assert.equal(extractWrapperClass('<?php echo "nope";'), null);
});
```

- [ ] **Step 2: Run the test, verify it fails**

Run: `node --test tests/tools/blocks-catalog.test.mjs`
Expected: FAIL — `Cannot find module '../../tools/blocks-catalog.mjs'`.

- [ ] **Step 3: Create the module with `extractWrapperClass`**

Create `tools/blocks-catalog.mjs`:

```js
#!/usr/bin/env node
/**
 * Block-catalog generator.
 *
 * Pure core (buildCatalog/extractWrapperClass/parsePreservedNotes) is unit
 * tested. The CLI gathers parent block.json from the running wp-env (read-only)
 * and child block.json from src/blocks, then writes docs/PEDIMENT-BLOCKS.md.
 *
 * Run: npm run blocks:catalog   (requires wp-env running)
 */
import { readFile, writeFile, readdir } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const NOTE_MARKER = '_(add guidance)_';

export function extractWrapperClass(renderPhp) {
  if (!renderPhp) return null;
  const m = renderPhp.match(/starter-[a-z0-9-]+/);
  return m ? m[0] : null;
}
```

- [ ] **Step 4: Run the test, verify `extractWrapperClass` passes**

Run: `node --test tests/tools/blocks-catalog.test.mjs`
Expected: PASS for the two `extractWrapperClass` tests.

- [ ] **Step 5: Write the failing test for `parsePreservedNotes`**

Append to `tests/tools/blocks-catalog.test.mjs`:

```js
test('parsePreservedNotes recovers human notes keyed by block name', () => {
  const doc = [
    '## pediment/hero',
    '',
    '**Use when:** the section is a page-leading headline with a primary CTA.',
    '',
    '## pediment/cta',
    '',
    '**Use when:** _(add guidance)_',
    '',
  ].join('\n');
  const notes = parsePreservedNotes(doc);
  assert.equal(notes['pediment/hero'], 'the section is a page-leading headline with a primary CTA.');
  assert.equal(notes['pediment/cta'], '_(add guidance)_');
});
```

- [ ] **Step 6: Run the test, verify it fails**

Run: `node --test tests/tools/blocks-catalog.test.mjs`
Expected: FAIL — `parsePreservedNotes is not a function`.

- [ ] **Step 7: Implement `parsePreservedNotes`**

Append to `tools/blocks-catalog.mjs`:

```js
export function parsePreservedNotes(existingDoc) {
  const notes = {};
  if (!existingDoc) return notes;
  const lines = existingDoc.split('\n');
  let current = null;
  for (const line of lines) {
    const h = line.match(/^## (\S+)/);
    if (h) { current = h[1]; continue; }
    const u = line.match(/^\*\*Use when:\*\*\s+(.*)$/);
    if (u && current) { notes[current] = u[1].trim(); current = null; }
  }
  return notes;
}
```

- [ ] **Step 8: Run the test, verify `parsePreservedNotes` passes**

Run: `node --test tests/tools/blocks-catalog.test.mjs`
Expected: PASS.

- [ ] **Step 9: Create generator fixtures**

Create `tests/tools/fixtures/parent-blocks/hero/block.json`:

```json
{
  "name": "pediment/hero",
  "title": "Hero",
  "description": "Page-leading headline section.",
  "attributes": {
    "heading": { "type": "string", "default": "" },
    "subheading": { "type": "string", "default": "" },
    "buttonText": { "type": "string", "default": "" }
  },
  "supports": { "align": ["wide", "full"] }
}
```

Create `tests/tools/fixtures/parent-blocks/hero/render.php`:

```php
<?php
$wrapper = get_block_wrapper_attributes( array( 'class' => 'starter-hero' ) );
echo '<section ' . $wrapper . '></section>';
```

Create `tests/tools/fixtures/child-blocks/promo-banner/block.json`:

```json
{
  "name": "pediment-child/promo-banner",
  "title": "Promo Banner",
  "description": "Example child block.",
  "attributes": {
    "headline": { "type": "string", "default": "" },
    "linkUrl": { "type": "string", "default": "" }
  },
  "supports": { "align": ["wide", "full"] }
}
```

- [ ] **Step 10: Write the failing test for `buildCatalog`**

Append to `tests/tools/blocks-catalog.test.mjs`:

```js
test('buildCatalog emits a section per block with attrs, class, source, preserved notes', () => {
  const records = [
    {
      source: 'parent',
      blockJson: {
        name: 'pediment/hero', title: 'Hero', description: 'Page-leading headline.',
        attributes: { heading: { type: 'string', default: '' } },
        supports: { align: ['wide', 'full'] },
      },
      renderPhp: `array( 'class' => 'starter-hero' )`,
    },
    {
      source: 'child',
      blockJson: {
        name: 'pediment-child/promo-banner', title: 'Promo Banner', description: 'Example.',
        attributes: { headline: { type: 'string', default: '' } },
      },
    },
  ];
  const existing = '## pediment/hero\n\n**Use when:** leading headline with CTA.\n';
  const md = buildCatalog(records, existing);

  assert.match(md, /^# Pediment block catalog/m);
  assert.match(md, /## pediment\/hero/);
  assert.match(md, /\*\*Source:\*\* parent/);
  assert.match(md, /\*\*Wrapper class:\*\* `starter-hero`/);
  assert.match(md, /`heading` \(string\)/);
  // preserved human note survives regeneration:
  assert.match(md, /\*\*Use when:\*\* leading headline with CTA\./);
  // new block with no prior note gets the editable marker:
  assert.match(md, /## pediment-child\/promo-banner[\s\S]*\*\*Use when:\*\* _\(add guidance\)_/);
});
```

- [ ] **Step 11: Run the test, verify it fails**

Run: `node --test tests/tools/blocks-catalog.test.mjs`
Expected: FAIL — `buildCatalog is not a function`.

- [ ] **Step 12: Implement `buildCatalog`**

Append to `tools/blocks-catalog.mjs`:

```js
function attrLine(name, def) {
  const type = def && def.type ? def.type : 'unknown';
  return `  - \`${name}\` (${type})`;
}

export function buildCatalog(records, existingDoc) {
  const notes = parsePreservedNotes(existingDoc);
  const sorted = [...records].sort((a, b) =>
    a.blockJson.name.localeCompare(b.blockJson.name));

  const out = [];
  out.push('# Pediment block catalog');
  out.push('');
  out.push('> Generated by `npm run blocks:catalog`. Edit only the **Use when** notes —');
  out.push('> everything else is overwritten on regeneration. Do not hand-edit other fields.');
  out.push('');

  for (const { source, blockJson, renderPhp } of sorted) {
    const cls = extractWrapperClass(renderPhp);
    const note = notes[blockJson.name] || NOTE_MARKER;
    out.push(`## ${blockJson.name}`);
    out.push('');
    out.push(`**${blockJson.title || ''}** — ${blockJson.description || ''}`.trim());
    out.push('');
    out.push(`**Source:** ${source}`);
    if (cls) out.push(`**Wrapper class:** \`${cls}\``);
    out.push(`**Use when:** ${note}`);
    out.push('');
    const attrs = blockJson.attributes || {};
    if (Object.keys(attrs).length) {
      out.push('**Attributes:**');
      for (const [name, def] of Object.entries(attrs)) out.push(attrLine(name, def));
      out.push('');
    }
    const align = blockJson.supports && blockJson.supports.align;
    if (align) { out.push(`**Align:** ${align.join(', ')}`); out.push(''); }
  }
  return out.join('\n');
}
```

- [ ] **Step 13: Run the test, verify all pass**

Run: `node --test tests/tools/blocks-catalog.test.mjs`
Expected: PASS (all tests).

- [ ] **Step 14: Add the CLI gather + write `main()`**

Append to `tools/blocks-catalog.mjs`:

```js
const PARENT_GLOB = 'wp-content/themes/pediment/build/blocks';
const DELIM_FILE = '@@@FILE:';
const DELIM_RENDER = '@@@RENDER:';

// Read parent block.json + render.php out of the running wp-env (read-only).
function gatherParentRecords() {
  const script =
    `for d in ${PARENT_GLOB}/*/; do ` +
    `echo "${DELIM_FILE}$d"; cat "$d/block.json" 2>/dev/null; ` +
    `echo; echo "${DELIM_RENDER}$d"; cat "$d/render.php" 2>/dev/null; echo; done`;
  let raw;
  try {
    raw = execFileSync('npx', ['wp-env', 'run', 'cli', 'sh', '-c', script],
      { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
  } catch (e) {
    throw new Error('Could not read parent blocks from wp-env. Is it running? ' +
      'Run `npm run env:start` first.\n' + (e.stderr || e.message));
  }
  const records = [];
  // Split on the FILE delimiter; ignore wp-env banner text before the first one.
  const chunks = raw.split(DELIM_FILE).slice(1);
  for (const chunk of chunks) {
    const [, jsonAndRest = ''] = chunk.match(/^.*?\n([\s\S]*)$/) || [];
    const [jsonPart = '', renderPart = ''] = jsonAndRest.split(DELIM_RENDER);
    const renderPhp = renderPart.replace(/^.*?\n/, '');
    try {
      const blockJson = JSON.parse(jsonPart.trim());
      records.push({ source: 'parent', blockJson, renderPhp });
    } catch { /* skip non-JSON noise */ }
  }
  return records;
}

async function gatherChildRecords(repoRoot) {
  const base = join(repoRoot, 'src', 'blocks');
  const records = [];
  let dirs = [];
  try { dirs = await readdir(base, { withFileTypes: true }); } catch { return records; }
  for (const d of dirs) {
    if (!d.isDirectory()) continue;
    try {
      const blockJson = JSON.parse(await readFile(join(base, d.name, 'block.json'), 'utf8'));
      let renderPhp = '';
      try { renderPhp = await readFile(join(base, d.name, 'render.php'), 'utf8'); } catch {}
      records.push({ source: 'child', blockJson, renderPhp });
    } catch { /* dir without a valid block.json */ }
  }
  return records;
}

async function main() {
  const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..');
  const docPath = join(repoRoot, 'docs', 'PEDIMENT-BLOCKS.md');
  let existing = '';
  try { existing = await readFile(docPath, 'utf8'); } catch {}
  const records = [...gatherParentRecords(), ...await gatherChildRecords(repoRoot)];
  if (!records.length) throw new Error('No blocks found — aborting (would clobber the catalog).');
  await writeFile(docPath, buildCatalog(records, existing) + '\n', 'utf8');
  console.log(`Wrote ${records.length} blocks to docs/PEDIMENT-BLOCKS.md`);
}

if (import.meta.main) {
  main().catch((e) => { console.error(e.message); process.exit(1); });
}
```

- [ ] **Step 15: Add npm scripts**

In `package.json` `scripts`, add (after `"check:wpenv-deps"`):

```json
    "blocks:catalog": "node tools/blocks-catalog.mjs",
    "test:tools": "node --test tests/tools/"
```

(Add a comma to the prior line as needed so the JSON stays valid.)

- [ ] **Step 16: Run the full tool test suite via the script**

Run: `npm run test:tools`
Expected: PASS — all tests in `tests/tools/`.

- [ ] **Step 17: Commit**

```bash
git add tools/blocks-catalog.mjs tests/tools/ package.json
git commit -m "feat(tools): add Pediment block-catalog generator

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Generate and commit the live block catalog

**Files:**
- Create: `docs/PEDIMENT-BLOCKS.md` (generated, then notes curated)

**Interfaces:**
- Consumes: `npm run blocks:catalog` from Task 1.
- Produces: `docs/PEDIMENT-BLOCKS.md` — the committed catalog the skill reads and diff-checks at run start.

- [ ] **Step 1: Ensure wp-env is running**

Run: `npm run env:mode` (and `npm run env:start` if not already up).
Expected: wp-env responds; parent theme present at `wp-content/themes/pediment/`.

- [ ] **Step 2: Generate the catalog**

Run: `npm run blocks:catalog`
Expected: prints `Wrote N blocks to docs/PEDIMENT-BLOCKS.md` (N ≥ the count of parent `build/blocks/*` plus `promo-banner`).

- [ ] **Step 3: Verify the catalog content**

Run: `grep -c '^## ' docs/PEDIMENT-BLOCKS.md`
Expected: a count matching the number of registered blocks. Spot-check that `## pediment/hero` exists with `**Wrapper class:** \`starter-hero\``.

- [ ] **Step 4: Curate the "Use when" notes**

Edit `docs/PEDIMENT-BLOCKS.md`: replace each `**Use when:** _(add guidance)_` with a one-line guidance string describing the source-section shape that maps to that block (e.g. for `pediment/hero`: "the page-leading headline + primary CTA section"). Leave all other generated fields untouched.

- [ ] **Step 5: Re-run the generator to confirm notes are preserved**

Run: `npm run blocks:catalog`
Expected: `git diff docs/PEDIMENT-BLOCKS.md` shows **no change** to your curated `**Use when:**` lines (preservation works).

- [ ] **Step 6: Commit**

```bash
git add docs/PEDIMENT-BLOCKS.md
git commit -m "docs: generate Pediment block catalog

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: The `/port-page` skill and distribution wiring

**Files:**
- Create: `.claude/skills/port-page/SKILL.md`
- Modify: `.gitignore`
- Modify: `.distignore`

**Interfaces:**
- Consumes: `npm run blocks:catalog` (Task 1), `docs/PEDIMENT-BLOCKS.md` (Task 2), the `promo-banner` block convention, `npm run build`, browser automation, wp-env at `:8890`.
- Produces: the invocable `/port-page <url>` command and its per-run artifacts under `.context/port/<slug>/`.

- [ ] **Step 1: Ignore the per-run working area**

Add to `.gitignore` (new line):

```
/.context/
```

- [ ] **Step 2: Keep the skill out of the shipped theme zip**

Add to `.distignore` (new line, alongside `.github`):

```
.claude
```

- [ ] **Step 3: Write the skill**

Create `.claude/skills/port-page/SKILL.md`:

````markdown
---
name: port-page
description: Rebuild an existing (Elementor) web page natively in Pediment blocks. Use when migrating a client page onto the Pediment theme from a public URL. Produces a reviewable serialized-block-markup file plus downloaded media.
---

# Port a page to Pediment

Rebuild ONE existing public web page as native Pediment blocks. Content and
information architecture are preserved faithfully; the visual look intentionally
becomes Pediment's design system. This is a migration tool, not a visual clone.

**Argument:** the source page URL. Derive `<slug>` from its path (e.g.
`/about-us/` → `about-us`; homepage → `home`).

**Preconditions (check first, stop if unmet):**
- wp-env is running (`npm run env:mode`); if not, tell the user to run
  `npm run env:start` and stop.
- Browser automation is available to load the URL and screenshot it.

All per-run files go under `.context/port/<slug>/` (gitignored).

## Pipeline — do these in order

### 1. Extract
- Load the URL in the browser. Save rendered HTML to
  `.context/port/<slug>/source.html`.
- Save a full-page screenshot to `.context/port/<slug>/screenshots/full.png`
  and a per-section screenshot `screenshots/sec-NN.png` for each distinct band.
- Write `.context/port/<slug>/inventory.md`: an **ordered** list of every
  distinct section. For each: a purpose label (hero / feature-grid /
  testimonials / pricing / CTA / FAQ / contact / …), the literal copy, and the
  image URLs it references. **This inventory is the contract** checked in step 6.

### 2. Refresh the block catalog
- Run `npm run blocks:catalog`.
- If `git status --porcelain docs/PEDIMENT-BLOCKS.md` shows changes, the
  committed catalog was stale: STOP and tell the user to review and commit the
  regenerated `docs/PEDIMENT-BLOCKS.md` before continuing. Do not map against a
  stale catalog.
- Read `docs/PEDIMENT-BLOCKS.md` — this is the authoritative block inventory
  (names, attributes, wrapper classes, "use when" guidance). Do not invent
  block names or attributes that aren't in it.

### 3. Map (strict ladder)
For each `inventory.md` section, choose in this priority order:
1. an existing Pediment block whose purpose/attributes fit (per the catalog);
2. else a composition of existing Pediment + core blocks (e.g. a Pediment
   section wrapping core columns);
3. else propose a **new child block**.
Write `.context/port/<slug>/mapping.md`: a table of section → decision (block
name | composition | `NEW BLOCK: <name>`) with a one-line rationale each.

### 4. New-block gate (only mandatory stop)
If `mapping.md` has any `NEW BLOCK` rows, STOP and present each to the user:
name, why ladder steps 1–2 don't fit, proposed attributes/slots. Wait for
approval per block.
- **Approved:** scaffold `src/blocks/<name>/` following `src/blocks/promo-banner/`
  (`block.json` + `render.php` + `edit.tsx` + `index.tsx` + `style.scss`),
  `name: "pediment-child/<name>"`, `textdomain: "pediment-child"`, CSS using
  `var(--wp--preset--…)` only (no color literals). Run `npm run build`, then
  re-run `npm run blocks:catalog` so the new block enters the catalog.
- **Declined:** record that section as a manual TODO in `coverage.md`; do not
  fabricate markup for it.

### 5. Build markup + media
- Emit `.context/port/<slug>/final.html`: serialized `<!-- wp:pediment/… -->`
  block markup realizing the mapping, carrying the client's real copy. Match the
  attribute-JSON shape and inner HTML each block expects (verify against a
  known-good page if unsure so the markup parses cleanly).
- Download every referenced image into `.context/port/<slug>/media/`. Write
  `media-manifest.md`: `filename → source URL → where used`. Reference images in
  `final.html` by the local `media/<filename>` path. If a download fails, log it
  in the manifest and keep the source URL in the markup as a fallback (never drop
  silently).

### 6. Verify (staged)
- **Coverage (always):** write `.context/port/<slug>/coverage.md` mapping each
  `inventory.md` item → its block/composition in `final.html` (or an explicit
  declined-section TODO). Flag anything unplaced. Report the gaps to the user.
- **Parse-validation (only if new blocks were authored):** insert `final.html`
  into wp-env (or run `parse_blocks`) and confirm no invalid-block errors and
  that the new blocks are registered and build cleanly.
- **Render + side-by-side (closing):** create a draft page from `final.html` in
  wp-env, screenshot the rendered result, and write `review.html` placing the
  source full-page screenshot beside the rendered Pediment page. Judge *content
  present + hierarchy sane*, NOT pixel match.

## Hand-off
Tell the user the three manual steps: review `review.html` and `coverage.md`;
paste `final.html` into a new page (editor → Options → Code editor → paste);
bulk-import `media/` into the media library (references then resolve).

## Out of scope (v1)
Whole-site crawl; header/footer/nav/branding (those are template parts +
theme.json); writing to a live site; pixel-faithful cloning; auth-gated or
JS-only-rendered pages (stop and report if the page won't render publicly).
````

- [ ] **Step 4: Validate the skill frontmatter and referenced commands**

Run: `head -5 .claude/skills/port-page/SKILL.md`
Expected: valid YAML frontmatter with `name: port-page` and a `description:`.

Run: `npm run blocks:catalog`
Expected: succeeds (confirms the command the skill calls in step 2 exists and works).

- [ ] **Step 5: Confirm distribution wiring**

Run: `grep -E '^\.claude$' .distignore && grep -E '^/\.context/$' .gitignore`
Expected: both lines present (skill excluded from zip; working area git-ignored).

- [ ] **Step 6: Commit**

```bash
git add .claude/skills/port-page/SKILL.md .gitignore .distignore
git commit -m "feat: add /port-page Elementor→Pediment skill

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Manual end-to-end validation

**Files:** none (validation only).

**Interfaces:**
- Consumes: the full pipeline from Tasks 1–3.

- [ ] **Step 1: Run the skill against a real Elementor page**

Invoke `/port-page <url>` against an actual Elementor client page (or any
public Elementor page). Let it run through the pipeline, approving/declining any
new-block proposals at the gate.

- [ ] **Step 2: Inspect the artifacts**

Confirm under `.context/port/<slug>/`: `inventory.md`, `mapping.md`,
`final.html`, `media/` + `media-manifest.md`, `coverage.md`, `review.html` all
exist. Open `coverage.md` — every inventory section is placed or has an explicit
TODO. Open `review.html` — content and hierarchy from the source are present in
the rendered Pediment page.

- [ ] **Step 3: Paste-test the markup**

In wp-env, create a new page, switch to the Code editor, paste `final.html`,
switch back to the visual editor. Expected: blocks resolve with no
"invalid/unrecognized block" warnings.

No automated test drives the live browser+model pipeline — consistent with the
existing live-AI e2e being manual-only and gated. This task's pass criterion is
the developer's inspection of `coverage.md` and `review.html`.

---

## Self-Review

**Spec coverage:**
- Skill `/port-page` in `.claude/skills/` → Task 3. ✓
- Front-end-only source capture (HTML + screenshots) → Task 3 step 1. ✓
- Serialized-markup file output → Task 3 step 5. ✓
- One page per run; whole-site out of scope → Task 3 (skill scope section). ✓
- Strict block ladder → Task 3 step 3. ✓
- New-block gate (only mandatory stop) → Task 3 step 4. ✓
- Media download + manifest → Task 3 step 5. ✓
- Staged verification (coverage / parse / side-by-side) → Task 3 step 6. ✓
- Committed catalog refreshed live + fail-loud on drift → Task 1 (generator), Task 2 (commit), Task 3 step 2 (drift stop). ✓
- "Use when" notes human-curated + preserved across regen → Task 1 (`parsePreservedNotes`), Task 2 steps 4–5. ✓
- wp-env required; clear stop if down → Task 1 (`gatherParentRecords` error), Task 3 preconditions. ✓
- Distribution: skill excluded from zip, working area gitignored → Task 3 steps 1–2. ✓
- Testing: generator unit-tested w/ fixtures incl. note preservation; pipeline manual e2e → Task 1, Task 4. ✓

**Placeholder scan:** No `TBD`/`TODO`/"implement later" in plan steps. (The `_(add guidance)_` marker and `coverage.md` TODOs are intentional *runtime* artifacts the human fills, not plan gaps.) All code steps show complete code.

**Type consistency:** `buildCatalog(records, existingDoc)`, `extractWrapperClass(renderPhp)`, `parsePreservedNotes(existingDoc)` and the `{ source, blockJson, renderPhp }` record shape are used identically in the generator, its tests, and the CLI gather functions. The `NOTE_MARKER` constant and the `**Use when:**` line format match between `buildCatalog` (emit) and `parsePreservedNotes` (parse).
