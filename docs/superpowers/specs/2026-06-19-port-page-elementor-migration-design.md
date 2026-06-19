# `/port-page` — Elementor → Pediment Page Migration Command — Design

**Date:** 2026-06-19
**Status:** Approved (pending written-spec review)

## Goal

A Claude Code skill, shipped in this child theme repo, that an agency developer
invokes as `/port-page <url>` to rebuild a single existing (typically Elementor)
web page **natively in Pediment blocks**. It exists to move clients off Elementor
onto Pediment with as little manual work as possible.

The output is a reviewable **serialized-block-markup file** built from native
Pediment blocks (not a pile of core paragraph/group blocks), plus a downloaded
media set with a manifest, plus a side-by-side review artifact. The command never
writes to a live site — the developer pastes the markup into a new page via the
editor's Code editor.

## What "port" means here

This is a **content + information-architecture migration**, not a visual clone.
The client's real content (copy, images, section structure, hierarchy) is
preserved faithfully; the *look* intentionally becomes Pediment's design system,
because leaving the Elementor look behind is the point. Visual fidelity to the
source is explicitly **not** a success criterion.

## Why a skill with an explicit pipeline (not "just tell the AI")

Naive "port this page" prompts miss sections, invent block attributes the theme
doesn't have, and get tedious. The fix is a skill that enforces an ordered
pipeline with two anchors:

1. A **section inventory** captured during extraction that the final output is
   mechanically checked against — so dropped sections surface instead of passing
   silently.
2. A **live block catalog** read from the installed blocks — so mapping uses the
   theme's real block names/attributes/slots rather than the model's memory.

## Decisions (from brainstorming)

- **Source access:** rendered front-end only — public URL + screenshots. No
  credentials, works on any client URL. The agent reverse-engineers structure
  from the rendered page.
- **Output target:** a serialized-block-markup file the developer pastes into the
  editor. No live-site writes, no WP-CLI insert (automatable later).
- **Scope:** one page per run. Whole-site crawl and shared parts
  (header/footer/nav/branding) are out of scope, designed so a site-level wrapper
  could call this per-page later.
- **Block selection — strict ladder:** (1) use an existing Pediment block if one
  fits; (2) else compose from existing Pediment + core blocks; (3) only author a
  *new* child block when a section is genuinely reusable and unrepresentable by
  (1)–(2), **gated behind developer approval**.
- **Media:** download every referenced image into the run's staging folder and
  write a manifest; markup references a predictable local path; the developer
  bulk-imports to the media library once.
- **Verification (staged):** coverage self-check always; parse-validation when
  new blocks were authored; render in wp-env + side-by-side vs source as the
  closing review surface.
- **Catalog:** committed, human-readable `docs/PEDIMENT-BLOCKS.md` that the skill
  refreshes from the installed block.json files at run start and fails loudly if
  they drift.

## Environment assumptions

- Porting happens in the developer's dev environment with **wp-env running** at
  `http://localhost:8890` (the canonical env per AGENTS.md). wp-env is required
  for: reading the installed parent blocks for the catalog refresh, building any
  approved new blocks, and the render-verify step.
- The agent has **browser automation** available to load the source URL, capture
  rendered HTML, and take screenshots.
- The installed parent theme lives read-only at
  `wp-content/themes/pediment/` inside wp-env; its blocks are at
  `wp-content/themes/pediment/build/blocks/*/block.json`.

## Architecture

Three new repo additions plus the skill, and a gitignored per-run working area.

```
.claude/skills/port-page/SKILL.md   ← the ordered pipeline (the command)
tools/blocks-catalog.mjs            ← generator: installed blocks → catalog doc
docs/PEDIMENT-BLOCKS.md             ← committed, human-readable block catalog
.context/port/<slug>/               ← gitignored per-run working area
  source.html                       ← captured rendered HTML
  screenshots/full.png, sec-*.png   ← full-page + per-section screenshots
  inventory.md                      ← ordered section inventory (the contract)
  mapping.md                        ← section → chosen block (the ladder result)
  media/                            ← downloaded images
  media-manifest.md                 ← filename → source URL → usage
  final.html                        ← serialized Pediment block markup (deliverable)
  coverage.md                       ← inventory item → output block, gaps flagged
  review.html                       ← source screenshot beside rendered Pediment page
```

`package.json` gains a `blocks:catalog` script wrapping the generator.

### Unit responsibilities

- **`tools/blocks-catalog.mjs`** — *one job:* read every `block.json` under
  `wp-content/themes/pediment/build/blocks/*` and this child's
  `src/blocks/*`, and emit `docs/PEDIMENT-BLOCKS.md`. Per block it records: block
  name, title, attributes (name + type + default), inner-block slots (from
  `supports`/`allowedBlocks` where present), front-end wrapper class, and a
  hand-maintainable **"use when…"** note that the generator preserves across
  regenerations (it must not clobber human-written guidance). Pure Node, no
  TypeScript, runnable via `npm run blocks:catalog`. Run against the wp-env
  install path; fail clearly if the parent blocks aren't found (tells the
  developer to start wp-env).
- **`docs/PEDIMENT-BLOCKS.md`** — the committed, readable source of truth for the
  block inventory and when to reach for each block. Regenerated by the script;
  the "use when…" notes are human-curated.
- **`.claude/skills/port-page/SKILL.md`** — *one job:* drive the pipeline below.

## The pipeline (skill behaviour)

Per run, under `.context/port/<slug>/` (slug derived from the URL path):

1. **Extract.**
   - Load the URL in the browser; save rendered HTML to `source.html`.
   - Capture a full-page screenshot and per-section screenshots.
   - Write `inventory.md`: an ordered list of every distinct section with, for
     each: a short purpose label (hero / feature grid / testimonials / CTA / …),
     the literal copy, and the image references it uses. **This inventory is the
     contract** the final output is checked against in step 6.

2. **Catalog refresh.** Run the generator. If the installed blocks differ from the
   committed `docs/PEDIMENT-BLOCKS.md`, regenerate and **fail loudly** (the
   developer commits the refreshed catalog) so mapping never runs against a stale
   inventory. If wp-env isn't running, stop with a clear message.

3. **Map (strict ladder).** For each inventory section, choose a block:
   1. an existing Pediment block whose purpose/attributes fit;
   2. else a composition of existing Pediment + core blocks (e.g. a Pediment
      section wrapping core columns);
   3. else propose a **new child block**.
   Write `mapping.md` — a table of section → decision (block name or composition
   or "NEW BLOCK: <name>") with a one-line rationale each.

4. **New-block gate.** If `mapping.md` contains any new-block proposals, **stop**
   and present them to the developer (name, why (1)–(2) don't fit, proposed
   attributes/slots). On approval: scaffold `src/blocks/<name>/` following the
   `promo-banner` convention (block.json + render.php + edit.tsx + index.tsx +
   style.scss), using `var(--wp--preset--…)` tokens only (no color literals, per
   AGENTS.md), run `npm run build`, and re-run the catalog generator so the new
   block is in the inventory. For any proposal the developer declines, record the
   section as a **manual TODO** in `coverage.md` rather than inventing markup.

5. **Build markup + media.**
   - Emit `final.html`: serialized `<!-- wp:pediment/… -->` block markup
     realizing the mapping, with the client's real copy.
   - Download every referenced image into `media/`; write `media-manifest.md`
     (filename → source URL → where used). Markup references a predictable local
     path so the developer can bulk-import once and have references resolve.

6. **Verify (staged).**
   - **Coverage (always):** assert every `inventory.md` item maps to something in
     `final.html` (a block, a composition, or an explicit declined-section TODO).
     Write `coverage.md`; flag anything unplaced.
   - **Parse-validation (when new blocks were authored):** insert the markup into
     wp-env / run `parse_blocks` to confirm no invalid-block errors and that the
     new blocks are registered and build cleanly.
   - **Render + side-by-side (closing):** render the page in wp-env, screenshot
     it, and write `review.html` placing the source screenshot beside the rendered
     Pediment page. The check is *content present + hierarchy sane*, not pixel
     match. This is the developer's review surface before pasting into the editor.

## Human checkpoints

To honor "as little work as possible," the **only mandatory stop is the
new-block gate** (step 4). Everything else runs through to the review artifacts.
The developer's deliberate manual steps are: review `review.html`, paste
`final.html` into a new page, and bulk-import `media/`.

## Error / edge handling

- **wp-env not running:** the catalog refresh (step 2) stops with a clear
  instruction to start wp-env; the skill does not proceed against a stale catalog.
- **Catalog drift:** regenerate and fail loudly so the developer commits the
  refreshed `docs/PEDIMENT-BLOCKS.md` before mapping.
- **Image download failure:** record the failed asset in `media-manifest.md` with
  its source URL and continue; the markup keeps the source URL as a fallback
  reference so nothing is silently dropped.
- **Section the ladder can't place and the developer declines a new block:**
  logged as a manual TODO in `coverage.md` — never fabricated markup.
- **Source page behind auth / JS-only render:** out of scope for v1 (front-end
  public render only); if the page won't render publicly, stop and report.

## Testing

- **`tools/blocks-catalog.mjs`:** unit-test the parse/emit against a small fixture
  set of `block.json` files (including one child block), asserting the emitted
  markdown lists the right names/attributes/classes and **preserves** existing
  hand-written "use when…" notes across a regeneration.
- **Skill pipeline:** validated by a manual end-to-end run against a real
  Elementor client page (the developer inspects `coverage.md` and `review.html`).
  No automated test drives the live browser+model pipeline — consistent with the
  existing live-AI e2e being manual-only and gated.

## Out of scope (v1)

- Whole-site crawl and shared parts: header / footer / nav / global branding
  (those map to Pediment template parts + `theme.json`, a separate follow-up).
- Writing to a live site / WP-CLI insert (manual paste now).
- Pixel-faithful cloning of the Elementor visual design.
- Pages requiring authentication or that only render via client-side JS.

## Implementation details to pin during planning

- Exact install path of the parent blocks inside wp-env and the child
  `src/blocks` path the generator scans.
- The precise front-end wrapper classes per Pediment block (`starter-hero`,
  `starter-cta`, `starter-faq`, `starter-prose`, `starter-pull-quote`,
  `starter-stat`, `starter-contact-form`, `starter-blog-index`, …) — from the
  installed `build/blocks/*/render.php`, to record in the catalog.
- The serialized-markup conventions for each Pediment block (attribute JSON shape
  in the block comment + inner HTML), captured from a known-good page so emitted
  markup parses cleanly.
- How `final.html` references downloaded media (predictable local path scheme)
  and the bulk-import step the developer runs.
- The browser-automation calls used for full-page + per-section screenshots.
