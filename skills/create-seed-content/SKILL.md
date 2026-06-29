---
name: create-seed-content
description: Freeze a page built live in wp-env (typically the draft just produced by /port-page) into a committed patterns/<slug>.php + assets/img/ images, externalizing media through pediment_child_media_id() so the page is portable across installs. Pairs with the Tools → "Seed content" button, which re-materializes what this skill writes.
---

# Freeze a built page into a seedable pattern

This is the downstream complement to `/port-page`. Where `/port-page` *imports*
images and references them by live attachment ID, this skill does the **inverse**:
it pulls a page that already exists live in wp-env and freezes it into committed
repo files (`patterns/<slug>.php` + `assets/img/*`), rewriting every attachment-ID
reference into a filename-based `pediment_child_media_id( 'file.jpg' )` call so the
same markup re-materializes on any install.

**This skill does NO fidelity, visual-QA, or design work.** It only freezes what
`/port-page` already built. If a section looks wrong, fix it in `/port-page` and
re-run this — never patch fidelity here.

The skill *creates* the committed files; the **Tools → "Seed content"** button
(and `wp pediment-child seed`) *applies* them onto an install. They are a pair.

**Argument:** a built page's slug or post-ID. Default: the page just produced by
`/port-page` (the most recent draft page in wp-env). Pass `--all` to freeze every
draft page in wp-env, one pattern file each.

**Resolve the theme slug dynamically** from the workspace directory name
(`basename $(pwd)`) — never hard-code it. Per-run scratch goes under
`.context/seed/<slug>/` (gitignored); only `patterns/<slug>.php` and the copied
`assets/img/*` are committed artifacts.

---

## Preconditions

Check both before doing anything. STOP (with the stated message) on the first
failure.

1. **wp-env running** — run `npx wp-env run cli wp option get siteurl`. If this
   errors or exits non-zero, tell the user: "wp-env is not running — start it with
   `npm run env:start` then re-run `/create-seed-content`." Stop.

2. **Target page exists** — resolve the argument to a post ID:
   - If a numeric post-ID was given, verify it:
     `npx wp-env run cli wp post get <id> --field=post_status`.
   - If a slug was given, resolve it:
     `npx wp-env run cli wp post list --post_type=page --name=<slug> --field=ID`.
   - With no argument, default to the most recent draft page:
     `npx wp-env run cli wp post list --post_type=page --post_status=draft --orderby=date --order=DESC --posts_per_page=1 --field=ID`.
   - With `--all`, list every draft page and loop the pipeline below over each.

   If no page resolves, tell the user the page was not found in wp-env and stop.

Resolve the theme slug once: `THEME=$(basename $(pwd))`.

---

## Pipeline — execute in order (per page)

### 1. Pull the live page

```bash
npx wp-env run cli wp post get <id> --field=post_content   # the block markup
npx wp-env run cli wp post get <id> --field=post_title     # the title
npx wp-env run cli wp post get <id> --field=post_name      # the slug
```

Save the raw markup to `.context/seed/<slug>/raw.html`.

Determine whether this page is the **front page**:

```bash
npx wp-env run cli wp option get show_on_front   # 'page' when a static front page is set
npx wp-env run cli wp option get page_on_front   # the front-page post ID
```

The page is the front page when `show_on_front` is `page` **and** `page_on_front`
equals this page's ID.

### 2. Externalize media (the inverse of /port-page's "import → reference by ID")

Scan the pulled markup for every attachment-ID reference. Two shapes occur:

- block attribute JSON: `"mediaId":N`
- core image / cover block JSON: `"id":N`

For **each** referenced attachment ID `N`:

1. Resolve its source filename and on-disk file from wp-env's uploads:

   ```bash
   # original filename (e.g. hero.jpg) — basename of the attached file:
   npx wp-env run cli wp eval "echo basename( get_attached_file( <N> ) );"
   # absolute path inside the container:
   npx wp-env run cli wp eval "echo get_attached_file( <N> );"
   ```

2. Copy that file out of the container into `assets/img/<filename>` (so it becomes
   a committed asset). Use `wp-env`'s mounted uploads path on the host, or
   `wp media` / a container copy, landing the file at `assets/img/<filename>`.
   If a different attachment already mapped to that basename, disambiguate the
   filename (e.g. `hero-2.jpg`) and use the disambiguated name in the rewrite.

3. Rewrite the reference in the markup so it resolves by filename at render time:

   ```
   "mediaId":123   →   "mediaId":<?php echo (int) pediment_child_media_id( 'hero.jpg' ); ?>
   "id":123        →   "id":<?php echo (int) pediment_child_media_id( 'hero.jpg' ); ?>
   ```

   Use the EXACT helper name `pediment_child_media_id` and always cast `(int)`.

4. **Leftover hardcoded upload URLs** — after rewriting the ID attributes, scan
   for any raw upload URL still embedded in the markup (e.g. a `<img src="…/wp-content/uploads/…">`
   inside a core block, or a `url` attribute pointing at `/wp-content/uploads/`).
   Do NOT silently ship these — they will 404 on another install. Record each one
   in the run report as a **manual TODO** for the user to resolve (re-point it at a
   `pediment_child_media_id()` call, or rebuild that section in `/port-page`).

### 3. Write `patterns/<slug>.php`

Write the rewritten markup to `patterns/<slug>.php`, prefixed with a WordPress
block-pattern header. The seeder (`inc/seed.php`) reads `Title` and `Front Page`
from this header and derives the page slug from the filename.

```php
<?php
/**
 * Title: <Page Title>
 * Slug: <theme>/<slug>
 * Categories: <comma-separated categories>
 * Front Page: yes
 */
?>
<!-- the rewritten block markup with pediment_child_media_id() calls -->
```

- `Slug` is `<theme>/<slug>` where `<theme>` is `basename $(pwd)`.
- Include the `Front Page: yes` header line **only** when step 1 determined this
  page is the front page; omit it otherwise.
- `Categories` is a sensible pattern category (e.g. `pages`); keep it present.

Because the file is a PHP pattern, the `<?php echo (int) pediment_child_media_id(
'…' ); ?>` calls execute when `inc/seed.php` `include`s it during a seed run,
baking real attachment IDs into the saved page content.

### 4. Verify the round-trip

Run the seeder and confirm every externalized image resolves:

```bash
npx wp-env run cli wp pediment-child seed
```

Then confirm no `pediment_child_media_id()` call returns 0 (which would mean an
image did not import and the block renders imageless). For each filename you wrote,
check:

```bash
npx wp-env run cli wp eval "echo pediment_child_media_id( 'hero.jpg' );"
```

A non-zero result means the file imported and resolves. Report any filename that
resolves to 0 (the image is missing from `assets/img/` or failed to import).

### 5. Report

Report to the user:

- **Files written** — `patterns/<slug>.php` (one per page under `--all`).
- **Images copied** — each filename now in `assets/img/`.
- **Manual-TODO URLs** — every leftover hardcoded upload URL from step 2.4 that
  was NOT externalized, with its location in the markup.
- **Round-trip result** — any `pediment_child_media_id()` that returned 0.
- **Reminder**: "Commit these (`patterns/<slug>.php` and the new `assets/img/*`),
  then on the live site click **Tools → Seed content** (or run
  `wp pediment-child seed`) to materialize the page."

---

## Out of scope

Any fidelity, visual-QA, or design work — that all lives in `/port-page`. This
skill only freezes a page `/port-page` already built into committed, portable
seed files. It does not write to a live remote site (it prepares the committed
files; the **Tools → "Seed content"** button does the live apply) and does not
build new blocks or alter markup beyond externalizing media references.
