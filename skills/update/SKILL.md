---
name: update
description: Refresh this Pediment client child theme from the upstream template. Pulls new framework docs and starter blocks (review-and-adopt, never overwriting the client's own blocks), regenerates the block catalog, optionally refreshes AGENTS.md, and warns if the installed parent is older than the latest blocks require. Run anytime.
---

# Update a client child theme from the Pediment template

Pull the latest framework docs and starter blocks from the upstream template into this client
repo, without touching the client's own customizations. Run this **anytime** the template has
moved on. First-time onboarding is the `initialize` skill, not this one.

**Upstream template:** `https://github.com/Bergert-Digital/Pediment-Child-Theme.git`,
branch `main`.

All scratch files go under `.context/update/` (gitignored).

---

## Preconditions (check first, stop if unmet)

1. **This repo was initialized** — `AGENTS.md` exists and references the template. If not,
   stop and tell the user to run the `initialize` skill first.
2. **Clean-ish working tree** for the paths this skill touches. If `docs/` or `src/blocks/`
   have uncommitted changes, warn the user before pulling so diffs stay legible.

---

## Steps — execute in order

### Step 1: Fetch the latest template

```bash
git remote get-url pediment-template 2>/dev/null \
  || git remote add pediment-template https://github.com/Bergert-Digital/Pediment-Child-Theme.git
git fetch pediment-template main
```

### Step 2: Diff and pull framework docs

Show what changed, then pull the framework docs (safe to take wholesale — the catalog's
"Use when" notes are reconciled on regeneration in Step 4):

```bash
git diff --stat HEAD pediment-template/main -- docs/PEDIMENT-BLOCKS.md docs/STYLING.md tools/blocks-catalog.mjs
git checkout pediment-template/main -- docs/PEDIMENT-BLOCKS.md docs/STYLING.md tools/blocks-catalog.mjs
```

### Step 3: Review-and-adopt new starter blocks

List template starter blocks and compare:

```bash
git ls-tree --name-only pediment-template/main src/blocks/
ls src/blocks/
```

For each template block **not** present locally, show it to the user and adopt only if they
want it:

```bash
git diff pediment-template/main -- src/blocks/<name>   # inspect
git checkout pediment-template/main -- src/blocks/<name> # adopt (only on approval)
```

**Never overwrite a block the client already has.** If a block exists in both and differs,
show the diff and ask — the client's version wins unless the user explicitly takes the
template's.

### Step 4: Review-and-adopt the seeder framework

The content-seeding framework ships as template PHP: `inc/media.php` (the
`pediment_child_media_id()` resolver), `inc/seed.php` (the `wp pediment-child seed` core +
Tools → "Seed content" button), `inc/seed-demo.php` (`wp pediment-child seed-demo` showcase),
`inc/nav-seed.php` (default-nav seeding), and the `assets/seed/` demo assets. Diff and adopt
the same way as blocks:

```bash
git diff HEAD pediment-template/main -- inc/media.php inc/seed.php inc/seed-demo.php inc/nav-seed.php assets/seed/
git checkout pediment-template/main -- inc/media.php inc/seed.php inc/seed-demo.php inc/nav-seed.php assets/seed/  # adopt (only on approval)
```

**Never clobber a client's customized `inc/seed.php` or `patterns/`.** `patterns/` is
client-owned content (frozen by the `create-seed-content` skill) — this skill never touches
it. If `inc/seed.php` differs because the client extended it, show the diff and ask; the
client's version wins unless the user explicitly takes the template's. If the client requires
the seeder, ensure `functions.php` wires up the new files (require + CLI/admin registration) —
show that diff against the template too.

### Step 5: Regenerate the catalog

```bash
npx wp-env run cli wp option get siteurl >/dev/null 2>&1 || npm run env:start
npm run blocks:catalog
```

This reflects the client's installed parent + their own blocks, preserving the curated
"Use when" notes from the doc pulled in Step 2.

### Step 6: Offer to refresh AGENTS.md (opt-in)

The client may have customized `AGENTS.md`, so do **not** overwrite it automatically. Show the
diff and ask:

```bash
git diff HEAD:AGENTS.md pediment-template/main:templates/downstream/AGENTS.md
```

If the user approves, write the template payload over `AGENTS.md`
(`git show pediment-template/main:templates/downstream/AGENTS.md > AGENTS.md`) and re-apply the
client's name in the first heading.

### Step 7: Parent-version check

```bash
echo "template parent pin:"; git show pediment-template/main:.wp-env.json | grep -o 'pediment/releases/download/v[0-9.]*'
echo "client parent pin:";   grep -o 'pediment/releases/download/v[0-9.]*' .wp-env.json
```

If the client's parent is older than the template's, warn that newly-documented blocks may
not render until the parent pin is bumped (and wp-env restarted). Do not bump it silently.

### Step 8: Report

Summarize: docs updated, blocks adopted/skipped, seeder framework adopted/skipped, catalog
regenerated, AGENTS.md refreshed or left as-is, and any parent-version warning. Remind the
user to review and commit.
