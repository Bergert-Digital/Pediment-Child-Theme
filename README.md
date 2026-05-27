# pediment-child-theme

The agency starting point. A child theme of [Pediment](https://github.com/bergert/pediment). Fork or download as a zip, rename it, add your blocks and `theme.json` overrides, and push to your own git for per-client install.

## Install order on a fresh WordPress

WordPress has no automatic theme-dependency resolution, so order matters:

1. Upload and install the **parent**: `pediment` zip (Appearance → Add New → Upload).
2. Upload and install **this child** theme zip.
3. **Activate the child** (`Pediment Child Theme`).
4. Install the **pediment-ai** plugin zip any time (Plugins → Add New → Upload).

## Overriding the Pediment design per client

This child theme ships **no `theme.json` `settings`** on purpose: it inherits
the parent (`pediment`) Pediment design system as-is — Deep Cyan
accent, Plus Jakarta Sans, the navy/surface palette. Child-theme sites get the
locked look with zero configuration.

To re-skin a client, add a `settings` block back to `theme.json`. WordPress
merges child `theme.json` over the parent **per top-level subtree, not per
slug**: a subtree you omit entirely (e.g. no `typography` key) keeps all its
Pediment values, but any preset **array you declare — `color.palette`,
`typography.fontFamilies`, `fontSizes`, … — replaces the parent's array
wholesale**. So when you declare `palette`, copy the parent's full Pediment
palette and edit only the entries you want; slugs you leave out (including
`accent-tint`) disappear on that site. Web fonts additionally need a
`fontFace` array with `src` on the family.

Abbreviated example (`theme.json`) — in practice paste the parent's complete
`palette`/`fontFamilies` and change only the values you need:

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 2,
  "settings": {
    "color": {
      "palette": [
        { "slug": "accent",       "color": "#B91C1C", "name": "Accent" },
        { "slug": "accent-hover", "color": "#991B1B", "name": "Accent hover" }
      ]
    },
    "typography": {
      "fontFamilies": [
        { "slug": "heading", "name": "Heading", "fontFamily": "Georgia, serif" }
      ]
    }
  }
}
```

Rule of thumb: omit a subtree to keep Pediment; declare an array and you own all of it.

## First-fork rename checklist

Grep-replace these tokens with your client's identity before first client ship:

- `pediment-child-theme` → your theme slug (also rename the repo/directory)
- `Pediment Child Theme` → your theme's display name (`style.css` `Theme Name`)
- `pediment-child` → your text domain (in `style.css`, `functions.php`, `block.json`, `edit.tsx`, CSS classes)
- `PedimentChild` → your PHP `@package` tag
- `pediment_child_register_blocks` / `PEDIMENT_CHILD_*` → your prefixed function/constant names

Then **replace or delete** `src/blocks/promo-banner/` — it's a worked example, not production content.

## Development

`.wp-env.json` is configured for the **agency-dev workflow**: it points at the latest tagged release of `Bergert-Digital/pediment` (parent) and `Bergert-Digital/pediment-ai` (plugin) on GitHub. Running `npm run env:start` downloads those release zips into the container — no local clone of parent/plugin required, no auth required (both are public repos).

```bash
composer install
npm install
npm run env:start            # wp-env downloads parent + plugin from GitHub releases
npm run build                # build child blocks
npm run e2e                  # Playwright
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment-child-theme vendor/bin/phpunit
composer lint
npm run check:wpenv-deps     # verify .wp-env.json refs are at latest upstream tags
```

### Working against sibling clones of the parent / plugin

If you've cloned `../pediment` and `../pediment-ai` next to this repo and want `wp-env` to use those working copies instead of the published release tags (for parallel development across the three repos), drop a `.wp-env.override.json` next to `.wp-env.json`:

```json
{
  "themes": [".", "../pediment"],
  "plugins": ["../pediment-ai"]
}
```

The file is gitignored. `wp-env` merges it over `.wp-env.json`, so the released refs become irrelevant for your local runs. CI uses the same trick — see [`.github/workflows/ci.yml`](.github/workflows/ci.yml).

### Keeping `.wp-env.json` current

A scheduled workflow ([`.github/workflows/check-wpenv-deps.yml`](.github/workflows/check-wpenv-deps.yml)) runs every Monday, checks the upstream repos for newer tags, and opens a PR bumping the refs when they fall behind. You can also run the check manually any time:

```bash
npm run check:wpenv-deps
```
