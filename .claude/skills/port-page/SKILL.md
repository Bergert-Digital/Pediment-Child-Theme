---
name: port-page
description: Rebuild an existing (Elementor) page live in wp-env using Pediment blocks, looping on an independent fidelity critic until every section matches the source. Requires a branded theme.json (run /port-site first if absent). Produces a live draft page + final.html export.
---

# Port a page to Pediment (fidelity-first, build-in-wp-env)

Rebuild ONE existing public page as native Pediment blocks, then iterate under an
independent visual fidelity critic until every section faithfully matches the source.
Content, information architecture, and visual treatment are all preserved.

**Argument:** the source page URL. Derive `<slug>` from its path
(`/about-us/` → `about-us`; homepage → `home`).

**Resolve theme slug dynamically** from the workspace directory name
(`basename $(pwd)`) — do not hard-code it.

All per-run files go under `.context/port/<slug>/` (gitignored).

---

## Pipeline — execute in order

### 1. Preconditions

Check all three before doing anything else. STOP (with the stated message) on the
first failure.

1. **wp-env running** — run `npm run env:mode`. If the output does not confirm a
   running environment, tell the user: "wp-env is not running — start it with
   `npm run env:start` then re-run `/port-page`." Stop.

2. **Branded theme present** — run:
   ```bash
   node -e "const t=require('./theme.json'); process.exit(t.settings ? 0 : 1)"
   ```
   If it exits non-zero, tell the user: "theme.json has no `settings` key — the
   theme has not been branded yet. Run `/port-site` first, then re-run
   `/port-page`." Stop.

3. **Browser available** — verify browser automation tools are accessible.
   If not, tell the user and stop.

---

### 2. Extract

Load the source URL in the browser. Under `.context/port/<slug>/`:

- **`source.html`** — save the fully rendered HTML.
- **`screenshots/full.png`** — full-page screenshot.
- **`screenshots/sec-NN.png`** — one screenshot per distinct visual band,
  top to bottom (`sec-01.png`, `sec-02.png`, …).
- **`inventory.md`** — an **ordered** list of every section. For each, record:
  - **label** (hero / stats-band / feature-grid / testimonials / pricing / cta /
    faq / contact / …) — this label is used verbatim by the fidelity critic
  - **purpose** — one sentence
  - **literal copy** — all visible text
  - **image refs** — every image URL present in this section
  - **visual treatment** — layout rhythm (full-width/split/grid), background
    colour/image, text colour, component density, any notable motion or decoration

> The inventory is the contract. Section labels must be stable — they are
> referenced in mapping.md, fidelity-qa.md, and the critic dispatch.

---

### 3. Catalog refresh

```bash
npm run blocks:catalog
```

If `git status --porcelain docs/PEDIMENT-BLOCKS.md` shows any change, the
committed catalog was stale: STOP and tell the user to review and commit the
regenerated `docs/PEDIMENT-BLOCKS.md`, then re-run `/port-page`.

Read `docs/PEDIMENT-BLOCKS.md` — this is the authoritative block inventory
(names, attributes, wrapper classes, "use when" guidance). Do not invent block
names or attributes that are not in it.

---

### 4. Map (fidelity ladder)

For each section in `inventory.md`, pick exactly one tier — in this priority order:

1. **Existing block** — a Pediment (or core) block whose purpose and attributes
   fit the section as-is.
2. **Existing block + variant/CSS** — a Pediment block that fits with a CSS class
   variant or minor inline style.
3. **NEW BLOCK** — no existing block can represent the section's visual treatment
   faithfully even with CSS tuning.

Write `.context/port/<slug>/mapping.md`: a table of
`section label → tier decision → rationale`.

The rationale MUST include the **visual treatment** from `inventory.md` and
explain why the chosen tier is the lowest sufficient one. For tier 3, explain
concretely why tiers 1–2 fall short.

---

### 5. New-block gate (only mandatory stop)

If `mapping.md` contains any `NEW BLOCK` rows, STOP and present **all proposals
at once** to the user. For each proposed block:

- Proposed name (`pediment-child/<name>`)
- Why tier 1 and tier 2 cannot match the section's visual treatment
- Proposed attributes and inner slots (referencing the `promo-banner` shape:
  `block.json` + `render.php` + `edit.tsx` + `index.tsx` + `style.scss`)

Wait for the user's decision on every proposal before proceeding.

**Approved blocks:**
1. Scaffold `src/blocks/<name>/` following `src/blocks/promo-banner/`:
   `block.json` (`name: "pediment-child/<name>"`,
   `textdomain: "pediment-child"`), `render.php`, `edit.tsx`, `index.tsx`,
   `style.scss`.
2. CSS rule: use `var(--wp--preset--…)` only — no color literals anywhere
   in the new block's styles.
3. Run `npm run build`.
4. Re-run `npm run blocks:catalog` so the new block enters the catalog
   before the build step.

**Declined blocks:**
Record the section as a coverage TODO in `.context/port/<slug>/coverage.md`.
Do not fabricate markup for it; the section is omitted from the built page.

---

### 6. Build in wp-env

#### 6a. Import media

For every image URL referenced in `inventory.md`:

```bash
wp --path=.wp-env/WordPress media import "<url>" --porcelain
```

Capture the returned attachment ID. Never hotlink source URLs in the final
markup — all `<img>` src values and block `url` attributes must reference
attachment IDs or the WordPress-hosted URL returned by `wp media import`.

Collect a map of `source URL → attachment ID` for use in markup composition.

#### 6b. Compose markup

Build the serialized `<!-- wp:… -->` block markup for the full page:

- Reference real attachment IDs (from 6a) for all images.
- Apply brand bands: use `is-style-band-navy` only for `stat`, `pull-quote`,
  and `social-links` blocks. Text bands (`section-head`, `prose`, `feature`)
  must use `surface` or `elevated` band styles — **never `is-style-band-navy`
  on a text band**.
- Match the attribute-JSON shape and inner HTML each block expects (cross-check
  against `docs/PEDIMENT-BLOCKS.md` and a known-good page if needed).

#### 6c. Create / update the page in wp-env

```bash
# Create:
wp --path=.wp-env/WordPress post create \
  --post_type=page \
  --post_title="<Page Title>" \
  --post_status=draft \
  --post_content="$(cat .context/port/<slug>/markup.html)" \
  --porcelain
# → prints the new post ID

# Activate the child theme if not already active:
wp --path=.wp-env/WordPress theme activate <theme-slug>
```

Capture the page permalink:
```bash
wp --path=.wp-env/WordPress post get <post-id> --field=guid
```

Record the live URL (e.g. `http://localhost:8900/?page_id=<id>`).

---

### 7. Fidelity gate loop

Dispatch the independent fidelity critic using the template in
`.claude/skills/shared/fidelity-critic-prompt.md`. Fill the placeholders:

- `{{BUILT_PAGE_URL}}` — the live page URL captured in step 6c
- `{{SOURCE_URL}}` — the original source URL
- `{{SECTION_LIST}}` — the ordered list of section labels from `inventory.md`,
  one per line as `<label>: <brief description>`

The critic evaluates blind against the rubric in
`.claude/skills/shared/visual-qa.md`. Do not add any pre-judgment or hints.

**Capture rules (enforced by the critic, also enforced here when re-rendering):**
- After navigating to a section, **wait ≥ 1.5 s** after it enters the viewport
  before any screenshot — Pediment fades sections in on scroll and an early
  capture appears blank or grey.

**After each critic run:**

1. Parse the JSON response. Check `overallPass`.
2. Record the round in `.context/port/<slug>/fidelity-qa.md` using the format
   from `visual-qa.md` (one row per section per round; latest round at top,
   prior rounds below `---`).
3. If `overallPass: false`, surface each failing section's `issues` array,
   apply the stated fixes to the block markup, update the page in wp-env
   (`wp post update <id> --post_content="$(cat …)"`), then re-dispatch
   the critic. Repeat until `overallPass: true`.
4. If the same section fails three rounds with the same high-severity issue and
   no fix closes the gap, escalate to the user with the issue details and proposed
   resolution before continuing.

---

### 8. Export + hand-off

Once `overallPass: true`:

1. Write `.context/port/<slug>/final.html` — the final serialized markup
   (the same content that is live in wp-env).
2. If any sections were declined at the new-block gate, ensure
   `.context/port/<slug>/coverage.md` lists each declined section as a
   manual TODO with a description of what is missing.
3. Report to the user:
   - Live URL of the built page in wp-env
   - Export path: `.context/port/<slug>/final.html`
   - Fidelity-gate summary: rounds taken, any sections that required > 1 round
   - Any declined-section TODOs from `coverage.md`

---

## Verified rules (apply at every step)

| Rule | Detail |
|---|---|
| **Entrance animation delay** | Pediment fades sections in on scroll. Always wait ≥ 1.5 s after a section enters the viewport before any screenshot or capture. An early capture appears blank or grey. |
| **`is-style-band-navy` scope** | Only valid on `stat`, `pull-quote`, and `social-links` blocks. Text bands (`section-head`, `prose`, `feature`) must use `surface` or `elevated` — never `is-style-band-navy` on a text band. |
| **Media references** | All images must be imported via `wp media import --porcelain` and referenced by their returned attachment ID. Never hotlink the original source URL in final markup. |
| **CSS token discipline** | Any new block CSS must use `var(--wp--preset--…)` only. No color literals, no hard-coded hex values. |
| **Theme slug** | Resolve dynamically: `basename $(pwd)`. Do not hard-code. |
| **Commit convention** | Conventional commit, ≤ 60-char summary, stage files by name, trailer: `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>` |

---

## Out of scope

Whole-site crawl; header / footer / nav / global branding (those are template
parts + theme.json — use `/port-site`); writing to a live remote site;
auth-gated or JS-only-rendered pages (stop and report if the page won't render
publicly).
