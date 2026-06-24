# AGENTS.md — pediment-child-theme

Project-level agent instructions for this child theme. User-level `~/.claude/CLAUDE.md`
and explicit user requests take precedence over this file.

## Two repos, two roles

This file ships in two very different kinds of repo. Know which one you are in:

- **The template — `Bergert-Digital/Pediment-Child-Theme` (this repo when you cloned the
  template). The upstream source of truth.** You improve the reusable agency starter here:
  starter blocks, docs, and tooling. **Nothing in the template is installed on a client
  site.** `src/blocks/` holds *reference / starter* blocks meant to be copied and adapted
  downstream — not a specific client's production blocks.
- **A client theme — a downstream repo created from this template.** *That* repo is the
  per-client child theme that gets installed on the client's WordPress site. There you
  build the client's pages, own its `theme.json`, and add client-specific blocks. A client
  repo refreshes framework docs and starter blocks from the template with the **`update`**
  skill, and is first wired up with the **`initialize`** skill.

**If you are reading this in the template repo, you are in the first role — you are not
building a specific client's site.**

### The stack underneath both

Both roles sit on the same stack. A **child theme** of the
[Pediment](https://github.com/bergert/pediment) FSE block theme. The parent ships as an
installed WordPress theme (zip) — you do **not** edit it. The optional
[`pediment-ai`](https://github.com/bergert/pediment-ai) plugin is installed separately.

```
WordPress + parent theme (pediment, installed)        ← framework defaults, READ-ONLY
                       + child theme (active)          ← overrides; the client repo's surface
                       + pediment-ai plugin (optional)
```

The parent is a dependency, like a JavaScript package — you read it for reference and
override what you need from the child. To inspect parent defaults, read the installed files
in your local WordPress at `wp-content/themes/pediment/theme.json` (and
`…/assets/css/theme.css`, `…/build/blocks/`). Or browse the source on GitHub:
<https://github.com/bergert/pediment>.

## Hard rules

- **Don't modify the installed parent theme.** Treat `wp-content/themes/pediment/` as
  read-only software. If a framework default needs changing, file an issue or PR against
  `bergert/pediment` upstream — not part of your day-to-day workflow.
- **Extend Brand Settings via filters**, not by patching parent files. Use the
  `pediment_brand_fields` and `pediment_brand_sections` filters in this child's
  `functions.php`.
- **Stick to WordPress standards.** Prefer official APIs (hooks, filters, block APIs)
  over custom solutions.
- **No color literals in custom block CSS.** Use `var(--wp--preset--…)` tokens declared
  in `theme.json`.
- **`theme.json` merge is per-subtree, not per-slug.** A declared `palette` /
  `fontFamilies` array **replaces** the parent's wholesale — slugs you omit disappear
  from the site (including `accent-tint`). When you fork a subtree, copy the parent's
  full version (from the installed `wp-content/themes/pediment/theme.json`) and change
  only the leaves you mean to. See [README.md](./README.md) for the exact rules.
- **Don't validate for scenarios that can't happen.** Delete unreachable defensive code
  rather than "polishing" it.

## Where to make changes

Two docs orient you before you touch anything:

- **Building a page, or picking which block to use?** Read
  [docs/PEDIMENT-BLOCKS.md](./docs/PEDIMENT-BLOCKS.md) — the generated catalog of every
  available block (parent + child), each with a "Use when" note and its attributes. It's
  the source of truth for *what blocks exist*; an agent that skips it hand-rolls markup
  instead of composing Pediment blocks. Regenerate it after adding or changing a block:
  `npm run blocks:catalog`.
- **Styling, or "which layer owns this?"** Read [docs/STYLING.md](./docs/STYLING.md) —
  the decision tree. Short version:

| Scope | Layer | How |
|---|---|---|
| Per-page / per-template content | **DB** | Site Editor (Appearance → Editor) |
| Per-client design override (color, type scale, etc.) | **Child** | `theme.json` in this repo |
| Site-specific CSS rule | **Child** | `theme.json` `styles.css`, or a stylesheet enqueued from `functions.php` |
| New client-specific block | **Child** | `src/blocks/<block>/` (worked example: `src/blocks/promo-banner/`) |
| Framework default (every Pediment site should get it) | **Upstream** | File an issue/PR against `bergert/pediment` on GitHub |
| Bug in a Pediment-shipped block | **Upstream** | Same |

> **Template vs. client:** the **Child** rows above describe work done in a **client repo**
> (per-client `theme.json`, the client's own `src/blocks/`). In the **template** you are
> instead authoring *reusable starter* blocks and the docs/tooling that client repos pull
> via the `update` skill — not one client's production content.

## Authoring a block editor

Match the parent library's editing UX — don't invent your own. The whole Pediment
catalog follows one split, and so must child blocks. Reference implementations:
`src/blocks/promo-banner/` (single block) and any parent `*-grid` (collection).

- **Visible text → edit in the canvas** with `RichText` (headings, body, quotes, labels).
  Rule of thumb: if the client reads it on the page, they edit it on the page.
- **Everything else → the sidebar** (`InspectorControls` + `PanelBody`): media
  (`MediaUpload`/`MediaPlaceholder`), URLs/links (`TextControl`), and layout/config
  (`ToggleControl`/`SelectControl`). Don't put media pickers or URL fields inline.
- **Repeating items → native `InnerBlocks`**, never hand-rolled add/remove buttons.
  Use `useInnerBlocksProps` with `allowedBlocks` + `template` + `templateLock: false`,
  and make each item its own child block (as `testimonial-grid` → `testimonial`,
  `stat-grid` → `stat`, `steps` → `step` do). You get the `+` appender, drag-reorder,
  per-item selection, and Site-Editor styling for free — a custom repeater loses all of it.

## Environment

- **Local dev: wp-env at `localhost:8890`**, started from this directory. See
  [README.md](./README.md) for setup. `.wp-env.json` ships zip-URL pins for the parent
  and plugin (the auto-zipballs of each repo's latest tag); `wp-env` downloads those on
  `env:start`. Both upstream repos are public — no auth required.
- **Dev mode vs. publish mode:** `npm run env:dev` mounts the sibling `../pediment` /
  `../pediment-ai` working copies; `npm run env:publish` reverts to the committed release-zip
  pins; `npm run env:mode` reports the active mode. These only toggle `themes`/`plugins` in
  the gitignored `.wp-env.override.json`, so the committed `.wp-env.json` stays push-ready by
  definition. Restart with `env:start` after switching. See README's "Dev mode vs. publish
  mode" section. CI does the override trick automatically.
- **Do not** start a separate wp-env from anywhere else for this site — that creates a
  second, mis-configured instance.
- **Keeping refs current:** `npm run check:wpenv-deps` verifies `.wp-env.json` is pinned
  to the latest upstream tag. A scheduled workflow opens a bump PR weekly; you can also
  run the check manually before opening any PR.
- PHP 8.1+, WordPress 6.9+. `@wordpress/scripts` build (`npm run build` → `build/blocks/`).

## Verifying work

1. `composer lint` (PHP) · `npm run lint:js` (JS/TS). No color literals in custom block
   CSS — use `var(--wp--preset--…)` tokens (this is a manual rule; there's no linter for it).
2. PHPUnit:
   `npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment-child-theme vendor/bin/phpunit`
3. Playwright: `npm run e2e`
4. **Visual / CSS changes** — DevTools → Computed is the only authoritative check. Curling
   the page and grepping inline `<style>` blocks tells you whether a rule was emitted, not
   whether it wins the cascade. After a `theme.json` edit, run
   `wp transient delete --all` (or bump `Version:` in `style.css`) so WP re-parses.

No success claims without running the relevant command (or, for visual work, viewing the
DevTools computed value) and seeing it pass.

## Commits & pushes

Conventional commits, imperative, ≤60-char summary, stage files by name (never
`git add -A`), include the `Co-Authored-By` trailer. `git push` and any `gh` remote action
require explicit user go-ahead — show the exact command and stop.
