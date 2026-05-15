# Live AI Page-Generation E2E Test — Design

**Date:** 2026-05-15
**Status:** Approved (pending written-spec review)

## Goal

A manual-only end-to-end test that drives the real AI Chat panel with the prompt
**"create a landing page for a local bike mechanic"**, lets a live Claude model
compose a full page, publishes it, and verifies the page renders cleanly for a
visitor on the front-end. The test exists primarily so the developer can run it
and inspect the generated result by hand.

## Why live (not mock)

The existing e2e suites are entirely mock-fixture driven. The mock always emits
the same generic `compose-landing.json` blocks (hero "Welcome", a CTA, a 2-item
FAQ) regardless of prompt, so it can only verify structure — never whether the
output is a plausible bike-mechanic landing page. Judging real output quality
requires the live model. The trade-off (non-determinism, token cost, CI
unsuitability) is accepted and contained by gating (see below).

## Environment

- Runs against the **child-theme wp-env at `http://localhost:8890`** — the single
  canonical test env. It mounts the `wp-starter-ai` plugin and both themes.
- That env's `.wp-env.override.json` already provides a live `ANTHROPIC_API_KEY`
  and `STARTER_AI_LOOPBACK_URL`. `STARTER_AI_MOCK` is not set and `mock_mode` is
  off, so the AI plugin uses the live Anthropic provider. **No new secret
  handling is introduced** — the test reuses the key already present in that env.
- No new wp-env is started. Never start wp-env from `wp-starter-ai` or
  `wp-starter-theme`.

## Location & gating

- New standalone spec: `wp-starter-child-theme/tests/e2e/ai-page-generation.live.spec.ts`.
- Top-level guard: `test.skip(!process.env.RUN_LIVE_AI, 'Live AI test — set RUN_LIVE_AI=1 to run')`.
  No automated runner (the default child-theme e2e run, CI) spends tokens.
- Run explicitly with: `RUN_LIVE_AI=1 npx playwright test ai-page-generation.live`
  from `wp-starter-child-theme`.
- The child-theme `tests/e2e/` directory has no `utils.ts`; the helpers needed
  (`login`, `openNewPage`, `openAIChatPanel`, `canvas`) are ported locally into
  this spec (or a small local helper) rather than importing across repos.

## Flow

1. `login()` → `openNewPage(page, 'AI Live: Bike Mechanic')` → `openAIChatPanel()`.
2. Type **"create a landing page for a local bike mechanic"** into the Composer
   textarea; click **Send**.
3. **Completion detection (the only real fragility):** wait on the chat store's
   authoritative streaming state going false — the same state `ChatPanel` reads
   via the store exported as `STORE_NAME` from `editor/chat/store`. Poll
   `wp.data.select(<store>)` for streaming → falsey, with a generous timeout
   (~120s) to absorb multi-round agentic tool use. Fail fast if the panel shows
   an error (`.starter-ai-chat__error`).
4. **Editor sanity guard:** assert the canvas now holds a substantial page —
   ≥ 4 top-level section blocks (proves a page was composed, not a single
   paragraph). This is a guard, not a content assertion.
5. **Publish** the page through the editor UI; capture the "View Page"
   permalink. **`console.log` the permalink** to the Playwright report so the
   developer can open it.
6. **Primary assertion — front-end render** (true visitor end-to-end): navigate
   to the permalink at `:8890` and assert:
   - HTTP 200 and no PHP fatal/warning/notice text in the DOM,
   - no browser console errors collected during load,
   - multiple section blocks rendered visibly (≥ 3 distinct `.starter-*`
     section wrappers, e.g. `.starter-hero` / `.starter-cta` / `.starter-faq`),
   - the page is non-trivial (rendered visible text length over a sane floor).

## Persistence (no teardown)

The published page is **intentionally left alive** — the test performs no
cleanup/deletion. Its purpose is manual inspection of the generated result, so
the page must remain reachable at its permalink after the run. Each run creates a
new page (titled with a run-distinguishing suffix if needed); accumulated test
pages are the developer's to prune manually.

## Explicitly out of scope (per product decisions)

- No separator / section-rhythm-v2 boundary assertion.
- No bike/cycle/repair keyword/content match.
- No strict block-type matrix or exact block-count equality.
- No mock-mode variant in this spec.

Quality judgment is deliberately operationalised as: *"a real, multi-section
page is composed by the live model and renders cleanly for a visitor"*, with the
human doing the subjective quality read via the persisted page.

## Implementation details to pin during planning

- Exact chat store name/selector for the streaming flag (`STORE_NAME` from
  `editor/chat/store`, and the selector name for in-flight/streaming state).
- Precise front-end section wrapper class names emitted by the starter blocks
  (`starter/hero`, `starter/cta`, `starter/faq`, …) → their `.wp-block-starter-*`
  rendered classes.
- Publish-flow selectors for the current WP 6.5 editor (Publish → confirm →
  "View Page" link).
