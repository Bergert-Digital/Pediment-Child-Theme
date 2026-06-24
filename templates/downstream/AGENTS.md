# AGENTS.md — <client> child theme

Agent instructions for this **client child theme**. User-level or tool-specific agent
instructions and explicit user requests take precedence over this file.

> This repo was created from the **Pediment child-theme template**
> (`Bergert-Digital/Pediment-Child-Theme`). This file was installed by the template's
> `initialize` skill. Refresh framework docs and starter blocks with the **`update`** skill.

## What this repo is

The **per-client child theme** installed on this client's WordPress site. It sits on the
installed Pediment parent theme (framework defaults, read-only) and overrides what this
client needs:

```
WordPress + parent theme (pediment, installed)   ← framework defaults, READ-ONLY
                       + this child theme (active) ← this client's overrides — your surface
                       + pediment-ai plugin (optional)
```

**Everything you change for this client lives in this repo.** You build the client's pages,
own its `theme.json`, and add client-specific blocks under `src/blocks/`. You do **not**
edit the installed parent.

## Where to make changes

| Scope | Layer | How |
|---|---|---|
| Per-page / per-template content | **DB** | Site Editor (Appearance → Editor) |
| This client's design override (color, type scale, etc.) | **Child** | `theme.json` in this repo |
| Site-specific CSS rule | **Child** | `theme.json` `styles.css`, or a stylesheet enqueued from `functions.php` |
| New client-specific block | **Child** | `src/blocks/<block>/` |
| Framework default (every Pediment site should get it) | **Upstream** | File an issue/PR against the template or `bergert/pediment` |

## Building pages

- **Picking a block?** Read [docs/PEDIMENT-BLOCKS.md](./docs/PEDIMENT-BLOCKS.md) — the
  catalog of every available block (parent + this client's child blocks), each with a
  "Use when" note. Regenerate it with `npm run blocks:catalog` (requires wp-env running).
- **Styling / "which layer owns this?"** Read [docs/STYLING.md](./docs/STYLING.md).
- **Authoring a block editor:** match the parent library's UX — visible text edits in the
  canvas (`RichText`); media/URLs/config in the sidebar (`InspectorControls`); repeating
  items via native `InnerBlocks`. See the template's authoring guidance.

## Keeping in sync with the template

Run the **`update`** skill to pull new framework docs and starter blocks from the template.
It diffs against your local copies, never overwrites this client's own blocks or
`theme.json`, regenerates your catalog, and warns if your installed parent is older than the
version the latest blocks require.

## Verifying work

1. `composer lint` (PHP) · `npm run lint:js` (JS/TS). No color literals in custom block CSS —
   use `var(--wp--preset--…)` tokens.
2. PHPUnit / Playwright as configured in this repo.
3. Visual / CSS changes — DevTools → Computed is the authoritative check.
