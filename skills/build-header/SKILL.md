---
name: build-header
description: Build an editable site header (child parts/header.html + populated nav menu) from a client's live homepage or from a brand + spec, composed from native FSE blocks so the client can edit logo, menu, and CTA in the Site Editor. Source-driven with a spec fallback. Requires a branded theme.json (run /port-site first). Footer is out of scope.
---

# Build an editable site header

Produce a **Site-Editor-editable** header for the child theme: a child
`parts/header.html` composed from native blocks (`site-logo`, `navigation`, and an
optional CTA `button`) plus a navigation menu populated with the client's real items.
Never a `core/html` dump — see the "editable chrome" hard rule in `AGENTS.md`.

**Argument:** optional source homepage URL.
- URL given → **source-driven**: extract and match the client's real header.
- no URL → **spec-driven**: build from the brand + logo/menu/CTA the user provides.

**Resolve the theme slug dynamically:** `THEME_SLUG=$(basename "$PWD")`. Never hard-code it.

All per-run scratch files go under `.context/build-header/` (gitignored).

---

## Why this skill exists

The child inherits the parent's `parts/header.html` (a bare `<!-- wp:navigation /-->`
plus `site-logo`), and `inc/nav-seed.php` seeds an editable "Header Navigation" entity
and binds the bare nav to it. That default is already editable. This skill exists for
when the header must carry the **client's** logo, menu, layout, and CTA — the moment an
agent is tempted to hand-roll HTML. It keeps that work in native blocks.

---

## Pipeline — execute in order

### 1. Preconditions (stop on first failure)

1. **wp-env running** — `npx wp-env run cli wp option get siteurl`. If it errors, tell
   the user to run `npm run env:start` and stop.
2. **Branded theme present** — `node -e "const t=require('./theme.json'); process.exit(t.settings ? 0 : 1)"`.
   If non-zero, tell the user to run `/port-site` first and stop.
3. **Browser available** — required for source extraction and for the verification
   screenshot. If unavailable, stop and report.

### 2. Resolve inputs

**Source-driven (URL given):** load the homepage in the browser, wait for network idle,
then extract from the real header into `.context/build-header/source-header.json`:
- `logoUrl` — the `<img>` in the masthead / site-branding area (fall back to any
  logo-classed image near the top).
- `navItems` — ordered top-level menu only, as `[{label, href}]`. If the source has
  dropdown / mega menus, record them under `dropdownsFlagged: true` and list the parent
  labels — do NOT invent submenu structure; surface it to the user at hand-off.
- `cta` — a header call-to-action if present: `{label, href, isButton}`.

**Spec-driven (no URL):** ask the user for the logo (file or media-library item), the
ordered nav items (`label` + target), and an optional CTA. Brand colors/fonts already
come from `theme.json`.

### 3. Import + set the logo

```bash
LOGO_ID=$(npx wp-env run cli wp media import "<logoUrl-or-local-path>" --porcelain)
npx wp-env run cli wp option update site_logo "$LOGO_ID"
```
Never hotlink the source logo URL — always import and reference the attachment ID.

### 4. Compose the child `parts/header.html`

Read the parent's shape first:
`npx wp-env run cli cat wp-content/themes/pediment/parts/header.html`.

Fork that structure into `parts/header.html` in the child repo (create `parts/` if
absent). Keep it native — a `wp:group` with `tagName:"header"` wrapping a flex group
that holds a `brand` group (`wp:site-logo`), a **bare** `wp:navigation`, and — if there
is a CTA — a `wp:buttons`/`wp:button`. Adapt layout to the source (logo-left/nav-right
is the default; justification, CTA placement, logo width). Colors/spacing use
`var:preset|...` tokens only. Leave `wp:navigation` bare (no `ref`) — `inc/nav-seed.php`
binds it. **No `core/html`.**

Reference skeleton (adapt attributes; do not paste literally):

```html
<!-- wp:group {"tagName":"header","className":"site-header","backgroundColor":"surface","layout":{"type":"constrained"}} -->
<header class="wp-block-group site-header has-surface-background-color has-background">
  <!-- wp:group {"align":"wide","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"nowrap"}} -->
  <div class="wp-block-group alignwide">
    <!-- wp:group {"className":"brand","layout":{"type":"flex","flexWrap":"nowrap"}} -->
    <div class="wp-block-group brand"><!-- wp:site-logo {"width":150} /--></div>
    <!-- /wp:group -->
    <!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
    <div class="wp-block-group">
      <!-- wp:navigation {"overlayMenu":"mobile","layout":{"type":"flex","orientation":"horizontal","flexWrap":"nowrap"}} /-->
      <!-- wp:buttons -->
      <div class="wp-block-buttons"><!-- wp:button {"className":"is-style-fill"} -->
        <div class="wp-block-button is-style-fill"><a class="wp-block-button__link wp-element-button" href="/contact">Get started</a></div>
      <!-- /wp:button --></div>
      <!-- /wp:buttons -->
    </div>
    <!-- /wp:group -->
  </div>
  <!-- /wp:group -->
</header>
<!-- /wp:group -->
```

Drop the `wp:buttons` block entirely when there is no CTA — do not ship an empty button.

### 5. Point the nav menu at the client's real items

The menu lives in `inc/nav-seed.php` → `pediment_nav_menu_blocks()`, which returns
serialized `wp:navigation-link` markup and is the portable, re-seedable source of truth.

1. **Edit `pediment_nav_menu_blocks()`** so it returns the client's `navItems` — one
   `<!-- wp:navigation-link {"label":"…","url":"…","kind":"custom"} /-->` per item, in
   order. Keep URLs relative where possible (install-independent).
2. **Apply to the running wp-env site.** The seed is idempotent and won't overwrite an
   already-seeded entity, so update the existing entity directly for verification:
   ```bash
   NAV_ID=$(npx wp-env run cli wp post list --post_type=wp_navigation --posts_per_page=1 --field=ID --meta_key=_pediment_seeded_nav)
   # If NAV_ID is empty, the entity has not been seeded yet — call the seeder
   # directly (it is idempotent), then re-read NAV_ID. Do NOT "re-activate the
   # theme": after_switch_theme only fires on a real theme switch, so activating
   # the already-active child theme is a no-op and will not seed.
   #   npx wp-env run cli wp eval 'pediment_nav_seed_entity();'
   # Update its content to the new menu markup (pass the same markup pediment_nav_menu_blocks now returns):
   MENU=$(cat .context/build-header/menu-blocks.html)
   npx wp-env run cli wp post update "$NAV_ID" --post_content="$MENU"
   ```
   (Write the exact serialized markup you put in `pediment_nav_menu_blocks()` to
   `.context/build-header/menu-blocks.html` first, so the file and the running entity
   stay identical.)
3. Clear transients so the editor/front-end re-read: `npx wp-env run cli wp transient delete --all`.

### 6. Verify

**Source-driven:** dispatch the shared fidelity critic
(`skills/shared/fidelity-critic-prompt.md`) scoped to a single section. Fill:
- `{{BUILT_PAGE_URL}}`: `http://localhost:8900/`
- `{{SOURCE_URL}}`: the homepage URL
- `{{SECTION_LIST}}`:
  ```
  header: top site header — check logo, nav item labels/order, CTA presence + style, band background
  ```
The critic judges blind against `skills/shared/visual-qa.md`. Wait ≥1.5 s after the
header renders before any capture. On `overallPass: false`, apply the stated fixes to
`parts/header.html` / the menu, reload, re-dispatch. Loop until pass. If the same issue
survives three rounds, escalate to the user.

**Spec-driven:** no source to diff against. Open `http://localhost:8900/`, screenshot
the header to `.context/build-header/header.png`, and sanity-check: logo renders, every
nav item is present in order, CTA (if any) is styled. Report — do not run a critic against
a nonexistent target.

### 7. Editability self-check (enforce the hard rule)

- Assert **zero** `core/html`: `! grep -q "wp:html" parts/header.html` (must succeed).
- Assert the logo is `wp:site-logo` and the menu is `wp:navigation`:
  `grep -q "wp:site-logo" parts/header.html && grep -q "wp:navigation" parts/header.html`.
  A CTA, when present, is a `wp:button` — an optional element, so it is covered by the
  zero-`core/html` assertion above rather than a required grep.
- If any of logo/menu/CTA is hand-rolled HTML, the check fails — recompose from blocks
  and repeat from step 4.

### 8. Hand-off

Report to the user:
- The built `parts/header.html` and the updated `pediment_nav_menu_blocks()`.
- The live header at `http://localhost:8900/`, editable at **Appearance → Editor →
  Patterns → Template Parts → Header** (logo, menu, and CTA all editable there).
- Any dropdown/mega menus flagged in step 2 that the user must decide on.
- Footer is **out of scope** (a later `build-footer` if wanted).
- Suggest committing `parts/header.html` and `inc/nav-seed.php`.

---

## Verified rules (apply at every step)

| Rule | Detail |
|---|---|
| **No `core/html` for chrome** | Logo/nav/CTA/layout are always native blocks. `core/html` only for un-blockable third-party embeds. |
| **Child part overrides by filename** | `parts/header.html` needs no registration or `theme.json` entry. |
| **Bare nav, seeded menu** | Leave `wp:navigation` without a `ref`; `inc/nav-seed.php` binds it. Menu items live in `pediment_nav_menu_blocks()`. |
| **Editable menu, not empty** | Always populate the menu with real items — an empty nav is what pushes agents back to hand-rolled HTML. |
| **Media by attachment ID** | Logo via `wp media import --porcelain`; never hotlink the source URL. |
| **CSS token discipline** | Header colors/spacing via `var:preset|...` only. No literals. |
| **Entrance-animation delay** | Wait ≥1.5 s after the header renders before any capture. |
| **Theme slug** | `basename "$PWD"`. Never hard-code. |
| **Don't invent submenus** | Flag source dropdown/mega menus to the user; never fabricate submenu structure. |

---

## Out of scope

Footer template part; multi-level / mega-menu nav structure (flag, don't invent);
sticky/scroll JS beyond the parent's; editing the installed parent theme.
