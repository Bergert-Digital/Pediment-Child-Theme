---
name: discover
description: Interview the user about a client's site intent — faithful port vs. facelift vs. redesign, across structure, brand, and content — and write a committed docs/brief.md that steers port-site and port-page. Run once per client, after initialize, before port-site.
---

# Discover a client's site intent

Capture *what kind of port this is* before any brand or page work begins. The headline
question is fidelity: should the new site be a **faithful port**, a **facelift**, or a
**redesign**? That answer changes how `port-site` and `port-page` behave, so it must be
explicit and committed, not left in the user's head.

Run this **once** per client repo, right after `initialize` and before `port-site`. It is
safe to re-run when the client's direction changes.

The output is a single committed file: `docs/brief.md`.

---

## Preconditions (check first, stop if unmet)

1. **This is a git repo.** Run `git rev-parse --is-inside-work-tree`. If it errors, stop and
   tell the user to run the `initialize` skill first.
2. **If `docs/brief.md` already exists**, show its current contents and ask whether to
   **update** it or **start fresh**. Do not silently overwrite.

---

## Steps — execute in order

### Step 1: Interview — one question at a time

Ask these **one at a time** (wait for each answer before asking the next). Prefer offering
the multiple-choice options shown so the user can answer quickly, but accept free text.

1. **Source site URL** — the site being ported from.
2. **Pages in scope** — homepage only / key pages / full site (and which, if "key pages").
3. **Fidelity — structure/layout:** *faithful* (replicate section-for-section) / *facelift*
   (keep the shape, tidy it) / *redesign* (free to re-compose).
4. **Fidelity — visual brand** (color, type, spacing): *match* the source / *refresh* it /
   *new brand* entirely.
5. **Fidelity — content/copy:** *verbatim* / *light edits* / *rewrite*.
6. **References or inspiration** sites (optional — relevant for facelift/redesign).
7. **Hard constraints** — must-keep elements, things to drop, deadline.

For any optional question the user skips, record "Not specified".

### Step 2: Write `docs/brief.md`

Create `docs/` if it does not exist. Write the brief using this exact structure, filling
each field from the answers. Derive `<client>` from the repo/directory basename (ask if
unclear).

```markdown
# Client brief — <client>

- **Source site:** <url>
- **Pages in scope:** <...>

## Fidelity intent
- **Structure/layout:** <faithful | facelift | redesign> — <notes>
- **Visual brand:** <match | refresh | new> — <notes>
- **Content/copy:** <verbatim | light edits | rewrite> — <notes>

## References / inspiration
<...>

## Constraints
<must-keep / drop / deadline>
```

### Step 3: Report

Summarize the captured intent in one short paragraph (lead with the fidelity choices),
remind the user to **commit `docs/brief.md`**, and point to `/port-site` as the next step.
