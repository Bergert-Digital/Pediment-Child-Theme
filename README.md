# wp-starter-child-theme

The agency starting point. A child theme of [wp-starter-theme](https://github.com/Bergert-Digital/wp-starter-theme). Fork or download as a zip, rename it, add your blocks and `theme.json` overrides, and push to your own git for per-client install.

## Install order on a fresh WordPress

WordPress has no automatic theme-dependency resolution, so order matters:

1. Upload and install the **parent**: `wp-starter-theme` zip (Appearance → Add New → Upload).
2. Upload and install **this child** theme zip.
3. **Activate the child** (`Starter Child Theme`).
4. Install the **wp-starter-ai** plugin zip any time (Plugins → Add New → Upload).

## First-fork rename checklist

Grep-replace these tokens with your client's identity before first client ship:

- `wp-starter-child-theme` → your theme slug (also rename the repo/directory)
- `Starter Child Theme` → your theme's display name (`style.css` `Theme Name`)
- `starter-child` → your text domain (in `style.css`, `functions.php`, `block.json`, `edit.tsx`, CSS classes)
- `StarterChild` → your PHP `@package` tag
- `starter_child_register_blocks` / `STARTER_CHILD_*` → your prefixed function/constant names

Then **replace or delete** `src/blocks/promo-banner/` — it's a worked example, not production content.

## Development

All three repos cloned side by side (`../wp-starter-theme`, `../wp-starter-ai`):

```bash
composer install
npm install
npm run env:start          # wp-env, mounts parent + plugin from siblings
npm run build              # build blocks
npm run e2e                # Playwright
npx wp-env run tests-wordpress --env-cwd=wp-content/themes/wp-starter-child-theme vendor/bin/phpunit
composer lint
```
