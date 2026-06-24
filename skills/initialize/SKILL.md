---
name: initialize
description: Onboard this repo as a Pediment client child theme from the template. Pulls framework docs + the catalog generator + starter blocks from the upstream template, writes a client-facing AGENTS.md, wires up the block catalog, and checks the parent version. Run once per client repo.
---

# Initialize a client child theme from the Pediment template

Wire up the current repo as a per-client Pediment child theme. Pull framework docs, the
catalog generator, and starter blocks from the upstream template; install a client-facing
`AGENTS.md`; generate the first block catalog; and verify the parent version. Run this
**once** when starting (or adopting) a client repo. To refresh later, use the `update` skill.

**Upstream template:** `https://github.com/Bergert-Digital/Pediment-Child-Theme.git`,
branch `main`.

All scratch files go under `.context/initialize/` (gitignored).

---

## Preconditions (check first, stop if unmet)

1. **This is a git repo.** Run `git rev-parse --is-inside-work-tree`. If it errors, stop and
   tell the user to run `git init` (or clone the client repo) first.
2. **Network access to GitHub** (the template is public — no auth needed).

---

## Steps — execute in order

### Step 1: Add the template as a read-only remote

```bash
git remote get-url pediment-template 2>/dev/null \
  || git remote add pediment-template https://github.com/Bergert-Digital/Pediment-Child-Theme.git
git fetch pediment-template main
```

### Step 2: Pull framework docs and the catalog generator

Bring these from the template (they are framework assets, safe to take wholesale):

```bash
git checkout pediment-template/main -- \
  docs/PEDIMENT-BLOCKS.md \
  docs/STYLING.md \
  tools/blocks-catalog.mjs
```

If the client's `package.json` lacks a `blocks:catalog` script, add it:
`"blocks:catalog": "node tools/blocks-catalog.mjs"` (under `scripts`). Edit `package.json`
directly; do not reformat unrelated keys.

### Step 3: Surface starter blocks (review-and-adopt)

List the template's starter blocks and compare to the client's:

```bash
git ls-tree --name-only pediment-template/main src/blocks/
ls src/blocks/ 2>/dev/null
```

For a fresh client repo, copy the reference block(s) so the client has a worked example:

```bash
git checkout pediment-template/main -- src/blocks/promo-banner
```

**Never overwrite a block the client has already customized.** If a block name exists in
both, show the diff (`git diff pediment-template/main -- src/blocks/<name>`) and ask before
replacing.

### Step 4: Install the client-facing AGENTS.md

The template carries a client-framed AGENTS.md at `templates/downstream/AGENTS.md`. Write it
into this repo as `AGENTS.md`, **overwriting** any maintainer AGENTS.md inherited from the
template copy:

```bash
git show pediment-template/main:templates/downstream/AGENTS.md > AGENTS.md
```

Then replace the `<client>` placeholder in the first heading with the client's name (derive
from the repo/directory basename if the user doesn't say).

### Step 5: Generate the first block catalog

The catalog is per-repo (parent blocks from the running wp-env + this client's child blocks).
Requires wp-env running:

```bash
npx wp-env run cli wp option get siteurl >/dev/null 2>&1 || npm run env:start
npm run blocks:catalog
```

`tools/blocks-catalog.mjs` preserves the "Use when" notes from the `docs/PEDIMENT-BLOCKS.md`
you pulled in Step 2, so the regenerated catalog keeps the template's curated guidance while
reflecting this client's actual installed blocks.

### Step 6: Parent-version check

The newest catalog blocks (`media-text`, `slider`, `stat-grid`, `testimonial`,
`testimonial-grid`) are **parent** blocks — they only render if the installed parent is new
enough. Compare the template's parent pin to the client's:

```bash
echo "template parent pin:"; git show pediment-template/main:.wp-env.json | grep -o 'pediment/releases/download/v[0-9.]*'
echo "client parent pin:";   grep -o 'pediment/releases/download/v[0-9.]*' .wp-env.json
```

If the client's parent is older than the template's, tell the user to bump the parent pin in
`.wp-env.json` (and restart wp-env) before using the new blocks. Do not bump it silently.

### Step 7: Report

Summarize what was pulled, the AGENTS.md install, the catalog result, and any parent-version
warning. Remind the user to run the `update` skill later to stay in sync, and to commit the
changes.
