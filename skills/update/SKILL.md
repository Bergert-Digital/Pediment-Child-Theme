---
name: update
description: Refresh this Pediment client child theme from the upstream template. Syncs the skills themselves, pulls new framework docs and starter blocks (review-and-adopt, never overwriting the client's own blocks or their curated docs), regenerates the block catalog from the client's own copy, optionally refreshes AGENTS.md, and warns if the installed parent is older than the latest blocks require. Run anytime.
---

# Update a client child theme from the Pediment template

Pull the latest skills, framework docs, and starter blocks from the upstream template into this
client repo, without touching the client's own customizations. Run this **anytime** the
template has moved on. First-time onboarding is the `initialize` skill, not this one.

**An update may only add.** Every file this skill touches is one the client is expected to
extend, so no step here is allowed to remove client-authored content — Step 9 proves it.

**Upstream template:** `https://github.com/Bergert-Digital/Pediment-Child-Theme.git`,
branch `main`.

All scratch files go under `.context/update/` (gitignored).

---

## Preconditions (check first, stop if unmet)

1. **This repo was initialized** — `AGENTS.md` exists and references the template. If not,
   stop and tell the user to run the `initialize` skill first.
2. **Clean-ish working tree** for the paths this skill touches. If `skills/`, `docs/` or
   `src/blocks/` have uncommitted changes, warn the user before pulling so diffs stay legible
   and the Step 9 check can tell a regression from work in progress.

---

## Steps — execute in order

### Step 1: Fetch the template, then refresh the skills themselves

```bash
git remote get-url pediment-template 2>/dev/null \
  || git remote add pediment-template https://github.com/Bergert-Digital/Pediment-Child-Theme.git
git fetch pediment-template main
```

The skills in `skills/` are framework tooling, and **this procedure is one of them** — a fork
running an old copy keeps reproducing whatever bug the template already fixed. So sync them
before doing anything else:

```bash
git diff --stat HEAD pediment-template/main -- skills/
```

- **No output** → nothing to do, continue to Step 2.
- **Only `skills/<name>/…` files the client never edited** → adopt them:
  `git checkout pediment-template/main -- skills/<name>`
- **A skill the client customized** → show the diff and ask. The client's version wins unless
  they explicitly take the template's.

> **If `skills/update/SKILL.md` was among the files that changed, stop here.** You are
> currently executing the *old* copy of this procedure; the rest of these steps are stale.
> Tell the user the skill updated itself and **re-run `/update` from the top** so the new
> procedure is the one that runs. Do not continue into Step 2 on this pass.

`.claude/skills` is a symlink to `skills/` in the template, so forks pick these up either way.
If a fork has a real `.claude/skills/` directory instead of the symlink, sync that path.

### Step 2: Capture the pre-update baseline

Later steps assert that nothing was lost. Record the numbers now, before anything is touched:

```bash
mkdir -p .context/update
cp docs/PEDIMENT-BLOCKS.md .context/update/catalog.before.md 2>/dev/null || true
printf 'blocks_before=%s\nchild_before=%s\nph_before=%s\n' \
  "$(grep -c '^## ' docs/PEDIMENT-BLOCKS.md || true)" \
  "$(grep -c '^\*\*Source:\*\* child' docs/PEDIMENT-BLOCKS.md || true)" \
  "$(grep -c '_(add guidance)_' docs/PEDIMENT-BLOCKS.md || true)" \
  | tee .context/update/baseline.env
```

The baseline goes to a **file**, not shell variables — each step runs in its own shell. Step 9
sources it; Step 12 reports it.

### Step 3: Pull the catalog generator (the only safe wholesale target)

`tools/blocks-catalog.mjs` is tooling, not client-authored prose, so it can be taken wholesale:

```bash
git diff --stat HEAD pediment-template/main -- tools/blocks-catalog.mjs
git checkout pediment-template/main -- tools/blocks-catalog.mjs
```

> **Never `git checkout` the template's `docs/PEDIMENT-BLOCKS.md` or `docs/STYLING.md`.**
> Both are files the client extends, and their content is **not recoverable from the
> template**. The catalog's "Use when" notes for the client's own `<client-prefix>/*` blocks
> exist *only* in the client's copy: overwrite it and Step 7's regeneration has nothing left
> to preserve, so every curated note comes back as `_(add guidance)_` while the block *count*
> still looks correct. `docs/STYLING.md` regularly carries client-authored rules (container
> classes, `contentSize`/`wideSize`) that the template's copy does not have. Steps 4, 7 and 8
> bring in what is genuinely new without losing any of it.

### Step 4: Review-and-adopt `docs/STYLING.md`

The template's copy is a starting point, not a superset. Check whether it actually adds
anything before proposing a change:

```bash
git diff --numstat HEAD pediment-template/main -- docs/STYLING.md   # "added deleted path"
```

- **0 added** → the template's copy is a strict subset of the client's. **Skip it** and say
  so in the report.
- **Some added** → show the full diff and let the user choose:

```bash
git diff HEAD pediment-template/main -- docs/STYLING.md
```

Default to merging **only the added sections** into the client's copy by hand (Edit tool),
keeping every client-authored line. Take the template's file wholesale only if the user
explicitly asks for it after seeing the diff.

### Step 5: Review-and-adopt new starter blocks

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

### Step 6: Review-and-adopt the seeder framework

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

### Step 7: Regenerate the catalog from the client's own copy

```bash
npx wp-env run cli wp option get siteurl >/dev/null 2>&1 || npm run env:start
npm run blocks:catalog
```

The generator rewrites `docs/PEDIMENT-BLOCKS.md` from the installed parent's blocks plus this
client's `src/blocks/`, **carrying over every "Use when" note it finds in the file already
there**. Because Step 3 left that file alone, the client's curated notes survive; blocks that
are genuinely new get `_(add guidance)_`.

### Step 8: Adopt upstream notes for placeholders only

Now port the template's guidance into blocks whose local note is *still* the placeholder.
This never touches a note the client wrote.

```bash
notes_tsv () {   # <catalog file> -> "block<TAB>note"
  awk '/^## / { b = substr($0, 4); sub(/[ \t].*$/, "", b); next }
       /^\*\*Use when:\*\* / && b != "" { print b "\t" substr($0, 15); b = "" }' "$1"
}

git show pediment-template/main:docs/PEDIMENT-BLOCKS.md > .context/update/catalog.template.md
notes_tsv docs/PEDIMENT-BLOCKS.md            | LC_ALL=C sort > .context/update/notes.local.tsv
notes_tsv .context/update/catalog.template.md | LC_ALL=C sort > .context/update/notes.template.tsv

# blocks that are placeholders locally AND have a real note upstream
LC_ALL=C join -t$'\t' \
  <(grep -F '_(add guidance)_' .context/update/notes.local.tsv | cut -f1) \
  <(grep -vF '_(add guidance)_' .context/update/notes.template.tsv) \
  > .context/update/notes.adopt.tsv
cat .context/update/notes.adopt.tsv
```

Show that list to the user, then apply it:

```bash
[ -s .context/update/notes.adopt.tsv ] && awk -F'\t' '
  NR == FNR { n[$1] = $2; next }
  /^## / { b = substr($0, 4); sub(/[ \t].*$/, "", b) }
  /^\*\*Use when:\*\* _\(add guidance\)_[ \t]*$/ && (b in n) { print "**Use when:** " n[b]; next }
  { print }
' .context/update/notes.adopt.tsv docs/PEDIMENT-BLOCKS.md > .context/update/catalog.new \
  && mv .context/update/catalog.new docs/PEDIMENT-BLOCKS.md
```

(The `[ -s ... ]` guard matters: with an empty adopt list the `NR == FNR` pass would swallow
the catalog itself.)

**Never replace a note that is not the placeholder.** If the template's note for a block the
client already documented looks better, show both texts side by side and ask — the client's
version wins by default.

### Step 9: Post-condition check — fail loudly

The point of Steps 2–8 is that an update can only add. Prove it:

```bash
. .context/update/baseline.env
blocks_after=$(grep -c '^## ' docs/PEDIMENT-BLOCKS.md || true)
child_after=$(grep -c '^\*\*Source:\*\* child' docs/PEDIMENT-BLOCKS.md || true)
ph_after=$(grep -c '_(add guidance)_' docs/PEDIMENT-BLOCKS.md || true)
printf 'blocks %s -> %s | child %s -> %s | placeholders %s -> %s\n' \
  "$blocks_before" "$blocks_after" "$child_before" "$child_after" "$ph_before" "$ph_after"

[ "$ph_after"     -le "$ph_before"     ] || echo "REGRESSION: curated notes were replaced by placeholders"
[ "$child_after"  -ge "$child_before"  ] || echo "REGRESSION: client blocks disappeared from the catalog"
[ "$blocks_after" -ge "$blocks_before" ] || echo "REGRESSION: blocks disappeared from the catalog"
git diff --numstat HEAD -- docs/STYLING.md   # deletions here mean client docs were dropped
```

If any line prints `REGRESSION`, **stop**. Do not report a clean update. Restore the catalog
(`cp .context/update/catalog.before.md docs/PEDIMENT-BLOCKS.md`), tell the user exactly which
notes/blocks were lost, and diagnose before continuing. Same for `docs/STYLING.md`: any
deleted line that the client wrote is a failure, not a successful update.

A second run of this skill against an unchanged template must produce **no diff** at all.

### Step 10: Offer to refresh AGENTS.md (opt-in)

The client may have customized `AGENTS.md`, so do **not** overwrite it automatically. Show the
diff and ask:

```bash
git diff HEAD:AGENTS.md pediment-template/main:templates/downstream/AGENTS.md
```

If the user approves, write the template payload over `AGENTS.md`
(`git show pediment-template/main:templates/downstream/AGENTS.md > AGENTS.md`) and re-apply the
client's name in the first heading.

### Step 11: Parent-version check

```bash
echo "template parent pin:"; git show pediment-template/main:.wp-env.json | grep -o 'pediment/releases/download/v[0-9.]*'
echo "client parent pin:";   grep -o 'pediment/releases/download/v[0-9.]*' .wp-env.json
```

If the client's parent is older than the template's, warn that newly-documented blocks may
not render until the parent pin is bumped (and wp-env restarted). Do not bump it silently.

### Step 12: Report

Summarize, and include the numbers — a "successful" update that quietly downgraded notes is
exactly the failure this report exists to catch:

- **Catalog:** blocks before/after, client (`**Source:** child`) blocks before/after,
  `_(add guidance)_` placeholders before/after, and which blocks had a note adopted from the
  template.
- **`docs/STYLING.md`:** skipped (template adds nothing) / merged which sections / untouched.
- **Skills:** which were synced from the template, which were kept because the client had
  customized them.
- Blocks adopted or skipped, seeder framework adopted or skipped, `AGENTS.md` refreshed or
  left as-is, and any parent-version warning.

Then remind the user to review and commit.
