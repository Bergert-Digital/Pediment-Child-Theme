# Fidelity-First Elementor → Pediment Migration — Design

**Date:** 2026-06-22
**Status:** Approved (pending written-spec review)
**Supersedes:** `2026-06-19-port-page-elementor-migration-design.md`

## Why this supersedes the earlier spec

The first spec assumed a port re-expresses content in *stock Pediment* blocks and
emits a portable markup file ("the look becomes Pediment"). A live end-to-end run
against `berlinerteam.de` proved that wrong on two counts:

1. **The goal is fidelity to the original**, not re-skinning into generic Pediment.
   The output must recreate the client's site — brand, layout, section treatments —
   as closely as possible, improving only where clearly better. Stock blocks
   produced a page that "looked like default Pediment" (teal accent, Plus Jakarta
   Sans) instead of the client (navy + orange + Montserrat).
2. **You cannot hit a visual target without rendering and iterating.** A
   write-once markup file has no feedback loop; it dropped the hero image, misused
   blocks, and ballooned images with nothing to catch it.

This design rebuilds the tool around **adapting the theme and blocks to recreate
the original**, **building live in wp-env**, and **gating on independently-judged
visual fidelity**.

## Goal

Migrate a client's Elementor site onto the Pediment child theme with near-pixel
structural fidelity to the original — adapting `theme.json`, block styles, and
(where needed) authoring new child blocks — improving only where clearly better.
The deliverable is the **themed child theme + live pages built in the local
wp-env**; a serialized-markup export is a byproduct for portability.

## Decisions (locked in re-brainstorm)

- **Build model:** build live in wp-env (re-skin theme → import media → create page
  → render → iterate). The themed child (theme.json, fonts, block styles/CSS, new
  blocks) plus the live pages **is** the deliverable; markup export is a byproduct.
- **Source access:** rendered public front-end + screenshots only (no creds).
- **Two phases, sharing helpers:** `/port-site` (brand/theme, once per client) and
  `/port-page` (per page, reuses the established theme).
- **Fidelity gate:** an **independent fidelity-critic subagent** scores each
  section against the rubric and the source; the builder loops applying fixes until
  every section passes (≥ 4/5 per category, no high-severity diffs, holistic
  "matches the original closely enough" = true).
- **Block adaptation ladder (bar = match the original; Pediment defaults are never
  acceptable output):** two outcomes per section —
  (1) **adapt an existing block** ONLY when its *structure* already matches the
  source, and even then it is **never used as-is** — it is adapted to the
  original's design via a child-theme block-style variant / `style.css` override;
  (2) **new child block** — the default for any distinctive section and mandatory
  when the block's structure differs from the source. **Forbidden:** CSS-patching
  a structurally-wrong block to force-fit the source (this produced white cards on
  navy). New blocks are *expected*, not exceptional; **new-block creation pauses
  for user approval**, batched once per page. Worked patterns: `site-hero`,
  `testimonial-carousel`, `teaser-row`.
- **Media:** import into the target media library, reference by attachment ID.
- **Catalog:** the existing `tools/blocks-catalog.mjs` generator +
  `docs/PEDIMENT-BLOCKS.md` stay as the block inventory source.

## Environment assumptions

- Runs in the developer's dev environment with **wp-env running** (this workspace:
  `http://localhost:8900`, remapped from the canonical 8890 via gitignored
  `.wp-env.override.json` to avoid a port clash with other workspaces). wp-env is
  required for: reading installed parent blocks (catalog), re-skinning + rendering,
  importing media, building/registering new child blocks.
- Browser automation available to load source + rendered pages and screenshot
  them. **Pediment fades sections in on scroll** — capture must wait out the
  entrance animation (≥ ~1.5s after a section enters view) or it reads as blank.
- The child theme mounts under its workspace-basename slug (e.g. `bozeman`);
  resolve the slug dynamically, never hard-code.

## Architecture

```
.claude/skills/port-site/SKILL.md      ← phase-1 pipeline (brand/theme, once)
.claude/skills/port-page/SKILL.md      ← phase-2 pipeline (per page) [rewritten]
.claude/skills/shared/visual-qa.md     ← fidelity-critic rubric (shared)
.claude/skills/shared/fidelity-critic-prompt.md  ← critic subagent dispatch template
tools/blocks-catalog.mjs               ← block inventory generator [kept]
tools/brand-extract.mjs                ← pure: source CSS/computed tokens → brand JSON
tools/theme-reskin.mjs                 ← pure: brand JSON + parent theme.json → child theme.json
docs/PEDIMENT-BLOCKS.md                ← committed block catalog [kept]
assets/fonts/                          ← downloaded client webfonts
theme.json                             ← re-skinned to the client's brand (phase 1 output)
.context/port/<slug>/                  ← gitignored per-run working area
  source.html, screenshots/, inventory.md, mapping.md, media-manifest.md,
  fidelity-qa.md (critic verdicts per round), final.html (markup export)
```

### Unit responsibilities

- **`tools/brand-extract.mjs`** — *pure core + thin capture.* Given the source
  page's CSS/computed values, produce a normalized **brand token JSON**: palette
  (primary, accent, accent-hover, accent-tint, surfaces, foreground, muted,
  border), typography (font families + weights + source URLs), radius/spacing
  scale. Pure transform is unit-tested against fixtures; the capture layer reads
  values from the rendered source (e.g. computed styles of buttons/headings/links).
- **`tools/theme-reskin.mjs`** — *pure.* Given brand JSON + the parent's
  `theme.json`, emit the child `theme.json`: fork the parent palette and
  fontFamilies **wholesale** (per the per-subtree merge rule), substituting brand
  values; emit `@font-face` referencing the downloaded fonts. Unit-tested:
  asserts the full parent palette is preserved-then-overridden (no dropped slugs)
  and font faces are wired. Webfont download is a separate side-effect step.
- **`.claude/skills/port-site/SKILL.md`** — *one job:* phase-1 pipeline — extract
  brand → download fonts → re-skin `theme.json` → global block-style overrides →
  verify brand renders. (Header/footer template parts are deferred to a later
  phase — see Out of scope.)
- **`.claude/skills/port-page/SKILL.md`** — *one job:* phase-2 pipeline (below).
- **`.claude/skills/shared/visual-qa.md`** — the rubric (already drafted).
- **`.claude/skills/shared/fidelity-critic-prompt.md`** — the critic subagent
  dispatch: inputs (built-page URL, source URL, section list, rubric), output
  (structured per-section JSON verdict). The critic captures and compares sections
  itself, independently of the builder.

## Phase 1 — `/port-site <homepage-url>`

1. **Extract brand** (`brand-extract`) from the rendered source: palette,
   typography, radius. Capture from computed styles of representative elements
   (primary button → accent; headings/body → foreground/font; bands → primary).
2. **Download webfonts** into `assets/fonts/` (consolidate variable fonts to one
   file; map weights).
3. **Re-skin `theme.json`** (`theme-reskin`): forked palette + fontFamilies with
   `@font-face`. Clear WP transients so the parse refreshes.
4. **Global block-style overrides**: child `theme.json` `styles` / `styles.css` for
   brand-specific button shape, heading scale, band treatments not expressible via
   palette alone.
5. **Verify**: render a representative page; confirm brand (accent on buttons,
   fonts, navy bands) via the fidelity critic against the source's brand.

(Header/footer template parts are **deferred to a later phase** — not in v1.)

## Phase 2 — `/port-page <url>`

Precondition: the child theme is already branded (phase 1 ran). If `theme.json`
has no `settings`, stop and tell the user to run `/port-site` first.

1. **Extract** — load URL, save `source.html` + full + per-section screenshots.
   Write `inventory.md`: ordered sections, each with purpose label, literal copy,
   image refs, **and visual treatment** (e.g. "navy band, text-left/photo-right",
   "rotating testimonial carousel", "3-up cards").
2. **Catalog refresh** — run `npm run blocks:catalog`; stop on drift; read
   `docs/PEDIMENT-BLOCKS.md`.
3. **Map (fidelity ladder)** — each section → `ADAPT <block>` (only if structure
   already matches the source — and still adapted to the original via child-theme
   overrides, never as-is) | `NEW BLOCK <name>` (default for distinctive sections;
   mandatory on structure mismatch). No CSS force-fit of a structurally-wrong
   block. Record `mapping.md` with the *visual-treatment* rationale.
4. **New-block gate** — if any `NEW BLOCK`, stop once, present all (name, why
   ladder steps 1–2 can't match the original, attributes/slots). On approval,
   scaffold per `promo-banner` convention, build, re-catalog. Declined → manual
   TODO, no fabricated markup.
5. **Build in wp-env** — import referenced media (capture attachment IDs); compose
   the page from adapted blocks using real IDs and the brand; create/update the
   page; reference images by ID.
6. **Fidelity gate loop** — dispatch the fidelity-critic subagent; apply fixes to
   changed sections; re-dispatch until all pass. Record each round in
   `fidelity-qa.md`.
7. **Export** — write `final.html` (serialized markup) as the portable byproduct.

## Error / edge handling

- **wp-env down:** stop with a clear "run `npm run env:start`" message (catalog,
  re-skin, render all need it).
- **Un-branded theme on `/port-page`:** stop, direct to `/port-site` first.
- **theme.json merge:** always fork the full parent subtree; never declare a
  partial palette/fontFamilies array (drops sibling slugs). Enforced by
  `theme-reskin` + a test.
- **Entrance animations:** wait before any capture (builder and critic).
- **Critic non-determinism:** the explicit rubric + numeric bar bound it; a
  section that fails twice on the same issue escalates to the user.
- **Image hotlink trap:** never reference source URLs in final markup — import
  first, reference by attachment ID.
- **Auth-gated / JS-only-rendered pages:** out of scope; stop and report.

## Testing

- `tools/blocks-catalog.mjs` — keep existing unit tests.
- `tools/brand-extract.mjs` — unit-test the pure normalizer against fixture
  CSS/computed-value samples (asserts correct palette/type/radius extraction).
- `tools/theme-reskin.mjs` — unit-test the transform against a fixture parent
  `theme.json` (asserts full-subtree fork, no dropped slugs, fonts wired).
- End-to-end port — manual/gated live run (browser + model + non-determinism),
  validated by the critic's `fidelity-qa.md` + developer review; consistent with
  the existing live-AI e2e being manual-only.

## Out of scope (v1)

- **Header/footer template parts** — deferred to a later phase. v1 `/port-site`
  covers palette, typography, and global block styles only; the parent's
  header/footer remain until a follow-up phase recreates the client's.
- Whole-site crawl (auto-discovering every URL) — run `/port-page` per URL.
- Auth-gated or JS-only-rendered source pages.
- Pixel-perfect raster cloning (we match structure/brand/treatment, not exact
  pixels).
- Animation/interaction parity beyond what a built Pediment block provides.

## Migration of existing committed work

- **Keep:** `tools/blocks-catalog.mjs`, its tests, `docs/PEDIMENT-BLOCKS.md`.
- **Supersede/rewrite:** `.claude/skills/port-page/SKILL.md` (old content→stock-
  blocks, markup-file model) → the phase-2 pipeline above. Add `/port-site`.
- **Promote:** the drafted `.claude/skills/port-page/visual-qa.md` rubric → shared
  critic rubric.
- The `theme.json` brand re-skin + `assets/fonts/montserrat.woff2` from the
  hands-on run are berlinerteam-specific demo artifacts and are **reverted** — the
  template child theme ships an empty `theme.json` and no bundled client fonts. A
  real client fork gets re-skinned by `/port-site`.

## Implementation details to pin during planning

- Exact computed-style selectors `brand-extract` reads for each token (primary
  button bg → accent; h1/body → font + foreground; hero band → primary).
- Variable-font handling (single file, `font-weight` range) vs discrete weights.
- The fidelity-critic's section-capture method (how it bounds each section) and its
  structured-output schema.
- wp-env media-import + page-create/update commands and slug resolution.
- Which global brand treatments need `styles.css` vs palette/typography presets.
```
