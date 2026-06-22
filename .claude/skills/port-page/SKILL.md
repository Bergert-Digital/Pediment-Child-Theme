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
