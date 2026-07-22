# pediment-child-theme

The agency starting point. A child theme of [Pediment](https://github.com/bergert/pediment). Fork or download as a zip, rename it, add your blocks and `theme.json` overrides, and push to your own git for per-client install.

## Starting a client theme from this template

This repo is the **template** — the agency starting point, not a theme installed on any
site. When you spin up a client child theme from it, run the **`initialize`** skill first in
the new repo: it pulls the framework docs and the block-catalog generator from the template,
installs a client-facing `AGENTS.md`, generates the block catalog, and checks the parent
version. Later, run the **`update`** skill to pull new framework docs and starter blocks as
the template evolves. The canonical skills live in `skills/` and ship with the template;
`.claude/skills` is a compatibility symlink for Claude Code discovery.

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

Set your client's identity before first client ship:

- `Text Domain` in `style.css` → your slug. **This is the single source of truth
  for the release:** the built zip is named `<slug>.zip`, it installs into a
  `<slug>/` folder, and `inc/ThemeUpdater.php` derives its update slug + release-asset
  regex from `get_stylesheet()` — all from this one value, no other edits.
- `REPO_URL` in `inc/ThemeUpdater.php` → your client's GitHub repo (PUC needs the
  real repo; this is the one release setting that can't be auto-derived).
- `Pediment Child Theme` → your theme's display name (`style.css` `Theme Name`).
- `pediment-child` → also your text-domain string in `functions.php`, `block.json`,
  `edit.tsx`, and CSS classes (keep it equal to the `Text Domain` slug above).
- `PedimentChild` → your PHP `@package` tag.
- `pediment_child_register_blocks` / `PEDIMENT_CHILD_*` → your prefixed function/constant names.
- `PEDIMENT_CHILD_UPDATE_TOKEN` (constant / env var) and `pediment_child_update_token`
  (option) → your prefixed update-token names. The optional
  `PEDIMENT_CHILD_UPDATE_SECRET` constant (encryption-key override) renames the same way.
- (Optional) `package-name` in `release-please-config.json` → your name, for release-note titles.

Then **replace or delete** `src/blocks/promo-banner/` — it's a worked example, not production content.

## Configuring the update token (private release repos)

Client forks are public by default and need no token. If a fork's releases repo
is **private**, the update checker must authenticate or WordPress silently finds
no updates. Provide a GitHub fine-grained PAT (read-only **Contents** on the
fork's repo) one of two ways:

- **wp-config constant (most secure).** Define `PEDIMENT_CHILD_UPDATE_TOKEN` in
  `wp-config.php`. The token never touches the database.
- **Settings screen (self-serve).** Settings → Pediment Theme → **Updates** →
  paste the token. It is encrypted at rest with `sodium_crypto_secretbox` (keyed
  off the site's `AUTH_KEY`/`SECURE_AUTH_KEY`, or a `PEDIMENT_CHILD_UPDATE_SECRET`
  override) and never shown again. Rotating those salts invalidates a stored
  token — just re-enter it.

Resolution precedence is **constant → environment variable → stored option →
none**. Unset everywhere means no authentication and no fatal — updates are
simply absent, exactly as for an unauthenticated public repo. On multisite, a
`wp-config.php` constant is network-wide and wins over any per-site option.
Use **Test connection** on the Updates tab to confirm the token can see the repo
and its latest `<slug>.zip` release asset.

## Development

`.wp-env.json` is configured for the **agency-dev workflow**: it points at the latest tagged release of `Bergert-Digital/pediment` (parent) and `Bergert-Digital/pediment-ai` (plugin) on GitHub. Running `npm run env:start` downloads those release zips into the container — no local clone of parent/plugin required, no auth required (both are public repos).

```bash
composer install
npm install
npm run env:setup            # boots wp-env, activates this child, seeds demo content
npm run build                # build child blocks
npm run e2e                  # Playwright
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/pediment-child-theme vendor/bin/phpunit
composer lint
npm run check:wpenv-deps     # verify .wp-env.json refs are at latest upstream tags
```

### Dev mode vs. publish mode

The committed `.wp-env.json` always pins the published release zips (**publish mode**) — that's the push-ready config and the one CI's currency check validates. For parallel development across the three repos, switch to **dev mode**, which mounts the sibling working copies (`../pediment`, `../pediment-ai`) instead:

```bash
npm run env:dev          # mount sibling working copies (fast local iteration)
npm run env:publish      # back to the committed release-zip pins
npm run env:mode         # report which mode is active
npm run env:start        # restart to apply (required after switching)
```

These commands only toggle `themes`/`plugins` in `.wp-env.override.json` (gitignored; other keys like `ANTHROPIC_API_KEY` are preserved). Because the dev paths live only in the override, **the committed `.wp-env.json` can never accidentally pick up local paths — every push is publish-ready by default.** `wp-env` fully replaces the base `themes`/`plugins` arrays with the override's. CI uses the same trick — see [`.github/workflows/ci.yml`](.github/workflows/ci.yml).

### Keeping `.wp-env.json` current

A scheduled workflow ([`.github/workflows/check-wpenv-deps.yml`](.github/workflows/check-wpenv-deps.yml)) runs every Monday, checks the upstream repos for newer tags, and opens a PR bumping the refs when they fall behind. You can also run the check manually any time:

```bash
npm run check:wpenv-deps
```
