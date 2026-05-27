# Styling this site

You are working in **`pediment-child-theme`**. The site stack has three layers:

```
Site Editor edits  (DB: wp_global_styles, wp_template_part, …)   ← per-site, highest precedence
        ▲
This child theme  (pediment-child-theme)                          ← per-client overrides
        ▲
Parent theme      (installed at wp-content/themes/pediment/)      ← framework defaults, read-only
```

The rule: **defaults come from code, overrides come from the database.** If the Site
Editor has no edit for a given property, the theme's value wins. If the child sets
nothing, the parent's value wins. If neither, WordPress core's default wins.

## A note on "the parent"

The parent theme (`pediment`) is **installed software**, not source code in this repo.
You can browse its files at:

- **Locally:** `wp-content/themes/pediment/` in your WordPress install (e.g. inside
  `wp-env`). Read `theme.json`, `assets/css/theme.css`, `build/blocks/<block>/style-index.css`
  to understand what the parent provides as defaults.
- **Upstream source:** <https://github.com/bergert/pediment>.

You **don't edit** the installed parent theme files — they'll be overwritten on parent
update. To change a framework default, file a PR upstream. To override it on **this**
site, do so from this child repo.

## Decide where to make a change

| Scope of the change | Layer | Where to edit |
|---|---|---|
| Per-page / per-template content (hero copy, menu items, swap a block) | **DB** | Site Editor (Appearance → Editor) |
| Per-site design override (this client wants a green accent) | **Child** | `theme.json` in this repo |
| New site-specific CSS rule | **Child** | `theme.json` `styles.css`, or a stylesheet enqueued from `functions.php` |
| Framework default (should ship to every Pediment site) | **Upstream** | File a PR against `bergert/pediment` on GitHub |
| New block / bug in a Pediment block | **Upstream** | Same |

When in doubt for a design tweak, **default to the child theme.** Reach upstream only when
the change is genuinely framework-level.

## How theme.json merging works between parent and child

WordPress merges child `theme.json` over parent **per top-level subtree, not per slug**:

- A subtree you **omit** (e.g. you don't declare `settings.color`) inherits parent values
  entirely.
- An array you **declare** (e.g. `settings.color.palette`) **replaces** the parent's
  array wholesale — slugs you don't list disappear from the site (including ones the
  parent ships like `accent-tint`).
- Same for `styles` subtrees: if you set `styles.elements.button`, the parent's button
  element styles are wiped for that element.

So when you fork a subtree, **copy the parent's full version and edit only what you mean
to change.** Read the parent's authoritative `theme.json` from your local install:
`wp-content/themes/pediment/theme.json`.

## Common edits

### Change a color preset for this site

Add a `settings.color.palette` array to this repo's `theme.json`. Copy the parent's
palette from `wp-content/themes/pediment/theme.json` and modify the hex you want. Don't
drop unused slugs unless you mean to.

### Change a default block style for this site (e.g. button radius, nav font weight)

Add `styles.blocks.<block>.*` to this repo's `theme.json`. The path matches the parent's;
you only need to repeat the leaves you want to change.

### Change a global element default (h1 size, link color)

Same as block style — add `styles.elements.<element>.*` to child `theme.json`.

### Add raw CSS

Add a `styles.css` string to child `theme.json` — emitted verbatim into
`global-styles-inline-css`. This is your escape hatch for things `theme.json` can't
express (e.g. `backdrop-filter`).

Avoid normal CSS files unless you have a real reason — they bypass the Site Editor
override path. If you do use a CSS file in this repo's `assets/`, enqueue it via
`functions.php`.

### Per-page tweaks

Use the Site Editor. Edits are saved to the DB and win over both parent and child. They
survive theme updates.

## The one tricky case: navigation item color

WordPress core ships (you can see it in `wp-content/wp-includes/blocks/navigation/style.css`):

```css
.wp-block-navigation .wp-block-navigation-item__content.wp-block-navigation-item__content {
  color: inherit;
}
```

Specificity `(0,3,0)`. This intentionally beats anything in `theme.json` (which is wrapped
in `:where()` at `(0,1,0)` via the `:root` prefix). The effect: **nav items inherit color
from the `.wp-block-navigation` wrapper**, not from any `elements.link.color.text` setting.

Practical consequences:

- **To change the idle nav color:** set `styles.blocks.core/navigation.color.text`
  (color on the wrapper). Items inherit. Works from the parent's `theme.json`, your
  child's `theme.json`, and the Site Editor (Styles → Blocks → Navigation → Color →
  Text) — all standard override flow.
- **To change the nav hover or active-page color:** there's no wrapper-color equivalent
  for `:hover` or `[aria-current=page]`. The parent ships doubled-class rules in its
  `styles.css` to set those. **They're not Site-Editor-overridable.** To customise for a
  client, override in this child's `theme.json` `styles.css` with the same doubled-class
  form, e.g.:

  ```css
  .wp-block-navigation a.wp-block-navigation-item__content.wp-block-navigation-item__content:hover {
    color: var(--wp--preset--color--accent);
  }
  ```

  The child's `styles.css` is emitted after the parent's, so an equal-specificity rule
  in the child wins by source order.

## Verifying a change

Curling the page and grepping inline `<style>` blocks tells you *"is the rule emitted?"*
but not *"does it win?"* Only **DevTools → Computed** answers the cascade question:

1. Open the page, right-click the element, **Inspect**.
2. **Styles** panel → **Computed** tab → filter for the property (e.g. `color`).
3. The resolved value, and the winning selector below it, is the truth.

If your edit doesn't seem to take effect, the answer is almost always "another rule has
higher specificity" or "WordPress cached the parsed theme.json." For the latter:

```
wp transient delete --all
```

(or bump the child's `Version:` in `style.css`).

## Built-in Pediment blocks

Visual defaults for `pediment/hero`, `pediment/cta`, `pediment/feature`, etc. live in the
parent at `wp-content/themes/pediment/build/blocks/<block>/style-index.css`. To customise
these for one site, prefer:

1. Block-style variants the parent exposes (declared in each block's `block.json`), or
2. A CSS rule in your child `theme.json` `styles.css` targeting the block's wrapper class
   (`.starter-hero`, `.starter-cta`, etc.).

Editing the parent's SCSS source affects *every* Pediment site and is an upstream change.

## Cascade summary (for reference)

```
Browser computed style
  ▲
  ├── wp_global_styles (DB) ........... Site Editor edits — highest precedence among "user" layers
  │
  ├── Child theme.json (this repo)
  │     ├── styles.css ............... verbatim, normal specificity, after parent
  │     ├── styles.{elements,blocks}.* ... wrapped in :where(), specificity (0,1,0)
  │
  ├── Parent theme.json (installed)
  │     ├── styles.css ............... verbatim
  │     ├── styles.blocks.X.css ...... NOTE: core/navigation does not currently emit this — parent uses styles.css for nav-scoped rules
  │     ├── styles.{elements,blocks}.* ... wrapped in :where()
  │
  ├── Parent assets/css/theme.css ..... layout glue only (flex, backdrop-filter)
  ├── Parent built block styles (build/blocks/*/style-index.css)
  │
  └── WordPress core defaults
```
