# Child theme.json → Pediment Reconciliation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop the child `theme.json` from masking the parent's Pediment design tokens, so child-theme sites render the locked Pediment look (Deep Cyan + Plus Jakarta Sans) by default — while leaving the child as a documented per-client override point.

**Architecture:** WordPress deep-merges a child block theme's `theme.json` over the parent's. The child currently re-declares a full legacy **indigo** palette (`accent #4F46E5`, `primary #0F172A`, …) and **system-ui** `fontFamilies`, which overrides the parent's Pediment tokens for every child-theme site — and it omits the Pediment `accent-tint` slug entirely, so parent blocks that consume `--wp--preset--color--accent-tint` get nothing. The fix is a **minimal non-masking stub**: reduce the child `theme.json` to `$schema` + `version` only (no `settings` block). With no override present, the parent's Pediment palette + `fontFamilies` (incl. the `fontFace` whose `file:./assets/fonts/…` resolve against the **parent** theme dir, where the woff2 actually live) flow through untouched. The documented "where to override per client" example moves to `README.md` (theme.json is strict JSON — it has no comments; README is the child's existing fork/override home).

**Tech Stack:** WordPress FSE child block theme, `theme.json` v2 deep-merge, `WP_Theme_JSON_Resolver::get_merged_data()`, PHPUnit (`WP_UnitTestCase`) in the child's own suite.

**Scope (child repo `/Users/jonas/Entwicklung/wp-starter-child-theme` only):** `theme.json`, `README.md`, and a new `tests/phpunit/ThemeJsonInheritsPedimentTest.php`. NOT here: any parent (`wp-starter-theme`) change — the parent Pediment port (Plans 1–7) is already complete and is the source of truth; this plan only removes the child's masking layer. No child `functions.php`/blocks/style.css change.

**Verification constraint:** The execution worktree is NOT wp-env-mounted. Per task: env-independent gates — valid JSON, `php -l`, scope diff, and a static trace of each test method against the shipped `theme.json`. Full child PHPUnit runs POST-MERGE in the **single child wp-env test base** (`:8890`/`:8891`, which mounts the child checkout + `../wp-starter-theme` parent + the AI plugin) via `npx wp-env run tests-cli … vendor/bin/phpunit` from the child repo. **Definition of done: post-merge child PHPUnit green — the new ThemeJsonInheritsPedimentTest cases prove the resolved theme palette is Pediment (accent `#0E7490`, `accent-tint` present, primary `#0A1B33`) and the body font is Plus Jakarta Sans; the existing SmokeTest/AutoLoaderTest stay green.**

---

## File Structure

| File | Responsibility | Action |
|---|---|---|
| `tests/phpunit/ThemeJsonInheritsPedimentTest.php` | Guard: child resolves to parent Pediment tokens; child theme.json declares no `settings` | Create |
| `theme.json` | Minimal non-masking stub (`$schema` + `version`) | Modify (rewrite) |
| `README.md` | Document the per-client override point (the example theme.json can't carry as a comment) | Modify (append section) |

---

### Task 1: ThemeJsonInheritsPedimentTest — the reconciliation guard

**Files:**
- Create: `tests/phpunit/ThemeJsonInheritsPedimentTest.php`

- [ ] **Step 1: Create `tests/phpunit/ThemeJsonInheritsPedimentTest.php` with EXACTLY:**

```php
<?php

/**
 * Guards that the child theme does NOT mask the parent's Pediment tokens.
 *
 * Runs in the child wp-env base (:8890/:8891), which mounts this child
 * checkout plus ../wp-starter-theme (the parent). With the child theme
 * active and no `settings` block in the child theme.json, the resolved
 * global settings must be the parent's Pediment palette/typography.
 */
class ThemeJsonInheritsPedimentTest extends WP_UnitTestCase {

	/**
	 * @return array<string,string> slug => hex, from the theme-origin palette.
	 */
	private function theme_palette() {
		$settings = WP_Theme_JSON_Resolver::get_merged_data()->get_settings();
		$palette  = isset( $settings['color']['palette']['theme'] )
			? $settings['color']['palette']['theme']
			: array();
		$by_slug = array();
		foreach ( $palette as $entry ) {
			if ( isset( $entry['slug'], $entry['color'] ) ) {
				$by_slug[ $entry['slug'] ] = $entry['color'];
			}
		}
		return $by_slug;
	}

	public function test_child_inherits_pediment_palette() {
		$by_slug = $this->theme_palette();
		$this->assertSame(
			'#0E7490',
			isset( $by_slug['accent'] ) ? $by_slug['accent'] : null,
			'Child must inherit the Pediment accent, not the legacy indigo #4F46E5.'
		);
		$this->assertSame(
			'#0A1B33',
			isset( $by_slug['primary'] ) ? $by_slug['primary'] : null,
			'Child must inherit the Pediment primary, not the legacy #0F172A.'
		);
		$this->assertArrayHasKey(
			'accent-tint',
			$by_slug,
			'The Pediment accent-tint slug must be inherited (the legacy child palette omitted it).'
		);
	}

	public function test_child_inherits_plus_jakarta_sans_body_font() {
		$settings = WP_Theme_JSON_Resolver::get_merged_data()->get_settings();
		$families = isset( $settings['typography']['fontFamilies']['theme'] )
			? $settings['typography']['fontFamilies']['theme']
			: array();
		$body = '';
		foreach ( $families as $family ) {
			if ( isset( $family['slug'] ) && 'body' === $family['slug'] ) {
				$body = isset( $family['fontFamily'] ) ? $family['fontFamily'] : '';
			}
		}
		$this->assertStringContainsString(
			'Plus Jakarta Sans',
			$body,
			'Child must inherit the Pediment body font, not the legacy system-ui stack.'
		);
	}

	public function test_child_theme_json_declares_no_settings_override() {
		$path = get_stylesheet_directory() . '/theme.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertIsArray( $data );
		$this->assertSame( 2, $data['version'] );
		$this->assertArrayNotHasKey(
			'settings',
			$data,
			'Child theme.json must not re-declare settings — any settings block would mask the parent Pediment tokens.'
		);
	}
}
```

- [ ] **Step 2: Verify (env-independent).**

Run: `php -l tests/phpunit/ThemeJsonInheritsPedimentTest.php`
Expected: `No syntax errors detected`

Static trace (the worktree cannot run wp-env phpunit):
- `test_child_theme_json_declares_no_settings_override` → after Task 2, `theme.json` is `{$schema, version:2}` with no `settings` ⇒ `assertArrayNotHasKey('settings', …)` passes; `version` is `2`.
- `test_child_inherits_pediment_palette` / `…_plus_jakarta_sans_body_font` → with the child declaring no `settings`, `WP_Theme_JSON_Resolver::get_merged_data()` (theme origin = parent ⊕ child) yields the parent Pediment palette/fontFamilies ⇒ accent `#0E7490`, primary `#0A1B33`, `accent-tint` present, body fontFamily contains `Plus Jakarta Sans`. These go green post-merge once Task 2 lands (expected TDD: red until the masking theme.json is replaced).

- [ ] **Step 3: Commit**

```bash
git add tests/phpunit/ThemeJsonInheritsPedimentTest.php
git commit -m "test(theme-json): guard child inherits parent Pediment tokens (no masking)"
```

Then `git show --stat HEAD` — confirm only that one file is in the commit.

---

### Task 2: Minimal non-masking theme.json

**Files:**
- Modify: `theme.json`

- [ ] **Step 1: Replace the entire `theme.json` with EXACTLY:**

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 2
}
```

(No `settings` key. WordPress requires no `theme.json` in a child block theme; an empty-of-settings stub is a valid v2 file that adds zero overrides, so the parent's Pediment `color.palette` and `typography.fontFamilies` — including the `fontFace` whose `file:./assets/fonts/plus-jakarta-sans-*.woff2` resolve against the parent theme directory where those woff2 actually exist — are inherited unchanged. The legacy indigo palette + system-ui stack are gone.)

- [ ] **Step 2: Verify (env-independent).**

Run: `python3 -c "import json;d=json.load(open('theme.json'));print(sorted(d.keys()));print(d['version'])"`
Expected: `['$schema', 'version']` then `2`

Run: `python3 -c "import json;d=json.load(open('theme.json'));assert 'settings' not in d, 'settings must be absent';print('no-settings-OK')"`
Expected: `no-settings-OK`

Static trace: `ThemeJsonInheritsPedimentTest::test_child_theme_json_declares_no_settings_override` now passes (no `settings`, `version` 2); the two inheritance tests now resolve to the parent Pediment values post-merge.

- [ ] **Step 3: Commit**

```bash
git add theme.json
git commit -m "fix(theme-json): drop legacy indigo/system-ui override; inherit parent Pediment"
```

Then `git show --stat HEAD` — confirm only `theme.json` is in the commit.

---

### Task 3: README — document the per-client override point

**Files:**
- Modify: `README.md`

- [ ] **Step 1:** Read `README.md` and locate the `## First-fork rename checklist` section (it starts at the line `## First-fork rename checklist`). Immediately BEFORE that `## First-fork rename checklist` line, insert the following block, followed by one blank line:

```markdown
## Overriding the Pediment design per client

This child theme ships **no `theme.json` `settings`** on purpose: it inherits
the parent (`wp-starter-theme`) Pediment design system as-is — Deep Cyan
accent, Plus Jakarta Sans, the navy/surface palette. Child-theme sites get the
locked look with zero configuration.

To re-skin a client, add a `settings` block back to `theme.json` and override
only the tokens you need — WordPress deep-merges it over the parent, so unset
tokens keep their Pediment values. Example (`theme.json`):

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

Keep overrides minimal — every slug you redeclare stops tracking the parent.
```

(This is the documented "where to override" example the JSON file itself cannot carry as a comment. It lives in the child's existing fork/override documentation home.)

- [ ] **Step 2: Verify (env-independent).**

Run: `grep -n "## Overriding the Pediment design per client" README.md`
Expected: one match, on the line immediately above (within 2 lines of) `## First-fork rename checklist`.

Run: `python3 - <<'PY'
import re,sys
s=open('README.md').read()
i=s.find('## Overriding the Pediment design per client')
j=s.find('## First-fork rename checklist')
assert i!=-1 and j!=-1 and i<j, 'section order wrong'
# the embedded JSON example must itself be valid JSON
import json
m=re.search(r'\{\s*"\$schema".*?\n\}\n```', s[i:j], re.S)
json.loads(m.group(0).rsplit('```',1)[0])
print('README-OK')
PY`
Expected: `README-OK`

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs(readme): document per-client Pediment override point"
```

Then `git show --stat HEAD` — confirm only `README.md` is in the commit.

---

### Task 4: Final integration verification

**Files:** None modified — verification only.

- [ ] **Step 1: Scope diff is exactly the intended files.**

Run: `git diff <branch-base>..HEAD --name-only`
Expected — ONLY:
```
README.md
tests/phpunit/ThemeJsonInheritsPedimentTest.php
theme.json
```
No `functions.php`, `style.css`, `src/`, build, or any parent-repo path.

- [ ] **Step 2: theme.json is a minimal valid v2 stub.**

Run: `python3 -c "import json;d=json.load(open('theme.json'));print(d=={'\$schema':'https://schemas.wp.org/trunk/theme.json','version':2})"`
Expected: `True`

- [ ] **Step 3: Static cross-check.**

- `theme.json` has no `settings` ⇒ `ThemeJsonInheritsPedimentTest::test_child_theme_json_declares_no_settings_override` passes.
- The child declares no palette/typography ⇒ `WP_Theme_JSON_Resolver::get_merged_data()` theme origin is the parent Pediment ⇒ the two inheritance tests assert `#0E7490` / `#0A1B33` / `accent-tint` / `Plus Jakarta Sans`.
- SmokeTest (active theme = `wp-starter-child-theme`, template = `wp-starter-theme`) and AutoLoaderTest (`starter_child_register_blocks`) are untouched and unaffected.

**Post-merge (child checkout, controller — NOT a worktree step):** from `/Users/jonas/Entwicklung/wp-starter-child-theme`, run the child suite in its own wp-env base (the single test base, `:8890`/`:8891`, which mounts the child + `../wp-starter-theme` + AI plugin): `npx wp-env run tests-cli --env-cwd=wp-content/themes/wp-starter-child-theme vendor/bin/phpunit`. Expect: all green — the 3 new ThemeJsonInheritsPedimentTest cases plus the existing SmokeTest/AutoLoaderTest. (If the test-base container path differs, the established invocation for this repo is whatever `composer test` maps to inside `tests-cli`; the assertion target is the same: child resolves to Pediment.)

---

## Self-Review

**1. Spec coverage.** The locked architecture decision — "Parent = opinionated Pediment default; the child must not mask it" — plus the user-chosen reconciliation approach ("minimal non-masking stub, with the override example documented"): Task 2 reduces `theme.json` to `$schema` + `version` (zero overrides ⇒ parent Pediment inherited, fonts resolve from the parent dir); Task 1 guards it (resolved palette/typography are Pediment, and the file declares no `settings`); Task 3 gives the agency the documented per-client override point in the README (theme.json cannot hold comments). Covered.

**2. Placeholder scan.** No "TBD/TODO/handle later". Every step is a complete file or an exact insertion with exact verification commands and expected output. The README example is concrete and itself valid JSON (asserted in Task 3 Step 2).

**3. Type/contract consistency.** The test reads `WP_Theme_JSON_Resolver::get_merged_data()->get_settings()` at `['color']['palette']['theme']` / `['typography']['fontFamilies']['theme']` — the theme-origin (parent⊕child) bucket, which is exactly what a no-settings child yields from the parent. Asserted hexes (`#0E7490`, `#0A1B33`) and the `accent-tint` slug match the parent Pediment `theme.json` verbatim; the body-font assertion is substring (`Plus Jakarta Sans`) so it is robust to the trailing system-ui fallback stack. Task 4's exact-equality check on `theme.json` matches the Task 2 file byte-for-byte.
