# Changelog

## [0.6.0](https://github.com/Bergert-Digital/Pediment-Child-Theme/compare/v0.5.1...v0.6.0) (2026-07-06)


### Features

* add pediment_child_media_id() seed-media resolver ([9136816](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/9136816b74eaeb8fcc7a90091a82c1b490fb1a88))
* add Tools → Seed content admin button ([856c1d8](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/856c1d8344575b7276317140cfe0811ada8d612e))
* add wp pediment-child seed (auto-discover patterns into pages) ([09dd2a0](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/09dd2a0dfb283fc766f3cfa9bfd11862add60efa))
* add wp pediment-child seed-demo showcase (ported from parent) ([1e7868b](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/1e7868bbf99e468e6fbe7f37a1f1f032eeeace04))
* **discover:** add client discovery interview skill ([96fe91c](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/96fe91cef81f7543f8588c633f11577a53948409))
* env:setup seeds client content when patterns/ exists, else demo ([5c69a39](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/5c69a397ca9974ecd0992da41588916f24c71e91))
* relocate default-nav seeding from parent into child theme ([3141251](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/31412510446bb50fc0c40b32415418cabd4b8c80))
* seed-to-live content workflow (seed command + Tools button + /create-seed-content) ([#32](https://github.com/Bergert-Digital/Pediment-Child-Theme/issues/32)) ([3bbbc44](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/3bbbc44b0ccce17d87a71c5a3f3e8a3aab079746))
* seed-to-live workflow, discover/build-header skills, env & e2e fixes ([#37](https://github.com/Bergert-Digital/Pediment-Child-Theme/issues/37)) ([d2d8003](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/d2d80036f3e3c47c557e09aa8be2798499ee0082))
* **skill:** add build-header for editable site headers ([4f96d43](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/4f96d4319e73b798d92ae38ca6866eacb43623fa))


### Bug Fixes

* **e2e:** normalize permalinks so REST saves resolve in CI ([0999ae3](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/0999ae39ccd21081fc363605b00fc8bed2099995))
* **e2e:** wait for server-persisted publish, not optimistic status ([b512ea6](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/b512ea69d356806c122b31395b95f8eb1714dfb3))
* **env:** allocate a free wp-env port to avoid cross-workspace collisions ([7e5b27d](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/7e5b27d104360ea20a692ef4d375cd50357bb9b2))
* **env:** allocate a free wp-env port to avoid cross-workspace collisions ([#33](https://github.com/Bergert-Digital/Pediment-Child-Theme/issues/33)) ([664bff9](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/664bff912525e64bbabcfa603c0a548676d31502))
* **env:** skip wp-env port randomization in CI ([5e7af20](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/5e7af208570d1ff991a6b625ec477ae719d867e7))
* **test:** derive child theme slug from directory basename ([2cf5823](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/2cf58236f529dbc47f0075701e6520ef1057dd78))

## [0.5.1](https://github.com/Bergert-Digital/Pediment-Child-Theme/compare/v0.5.0...v0.5.1) (2026-06-25)


### Bug Fixes

* **ci:** drop empty STARTER_THEME_PAT now that upstream repos are public ([9015adc](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/9015adc93128cdc8b86decf6305194cc0b740374))
* **ci:** drop empty STARTER_THEME_PAT now that upstream repos are public ([#29](https://github.com/Bergert-Digital/Pediment-Child-Theme/issues/29)) ([04638e1](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/04638e1b8e50ff34b42ec256d819df3fcedfd9e8))
* **ci:** drop empty STARTER_THEME_PAT now that upstream repos are public ([#30](https://github.com/Bergert-Digital/Pediment-Child-Theme/issues/30)) ([fbceac8](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/fbceac80148557670dba15ee726d76ef44eafbcb))

## [0.5.0](https://github.com/Bergert-Digital/Pediment-Child-Theme/compare/v0.4.0...v0.5.0) (2026-06-24)


### Features

* add initialize skill to onboard client repos ([abf7ef3](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/abf7ef36c38e87c9055a430c169037070ccee17b))
* add update skill to sync client repos from template ([fd6189e](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/fd6189e4e3bc0e002b3f8a20219a2db5c7315104))
* initialize/update skills for template→client docs sync ([#25](https://github.com/Bergert-Digital/Pediment-Child-Theme/issues/25)) ([7a30ae9](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/7a30ae98dbb107f255ff3366b2849b4d72ccc7b1))
* initialize/update skills to sync docs+blocks from template ([#24](https://github.com/Bergert-Digital/Pediment-Child-Theme/issues/24)) ([307571a](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/307571ad64e161d034f371b43d279493c7d9850d))

## [0.4.0](https://github.com/Bergert-Digital/Pediment-Child-Theme/compare/v0.3.2...v0.4.0) (2026-06-23)


### Features

* Elementor→Pediment porting toolchain + env/dep updates ([#17](https://github.com/Bergert-Digital/Pediment-Child-Theme/issues/17)) ([91649ad](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/91649adef96e72b2ed27c7b72d169720bea0055f))

## [0.3.2](https://github.com/Bergert-Digital/Pediment-Child-Theme/compare/v0.3.1...v0.3.2) (2026-06-19)


### Refactors

* **updates:** drop child PUC from manual update-check button ([#12](https://github.com/Bergert-Digital/Pediment-Child-Theme/issues/12)) ([ee930bb](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/ee930bbe76b614eeb33a14998dc6098a335194bb))

## [0.3.1](https://github.com/Bergert-Digital/Pediment-Child-Theme/compare/v0.3.0...v0.3.1) (2026-06-18)


### Bug Fixes

* **deps:** cut 0.3.1 release for markdown-it advisory ([88fe574](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/88fe574336d9e11d7707d93f2d869c378ff469cd))
* **deps:** cut 0.3.1 release for markdown-it advisory ([#10](https://github.com/Bergert-Digital/Pediment-Child-Theme/issues/10)) ([4a49f95](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/4a49f9577963fe01c55481e5294e782a92a2522c))

## [0.3.0](https://github.com/Bergert-Digital/Pediment-Child-Theme/compare/v0.2.1...v0.3.0) (2026-06-12)


### Features

* **theme:** add screenshot.png preview image ([d735769](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/d735769378c2755644f325f6f2f8d3b3ec781246))
* **updates:** expose child PUC checker + add screenshot ([#3](https://github.com/Bergert-Digital/Pediment-Child-Theme/issues/3)) ([b9ef93a](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/b9ef93a318c0dd50b4ac46b5be3946237644f96d))
* **updates:** expose child PUC checker via pediment_update_checkers ([a34107f](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/a34107f3e8025c2e12351270d5952ef0198e9a85))

## [0.2.1](https://github.com/Bergert-Digital/Pediment-Child-Theme/compare/v0.2.0...v0.2.1) (2026-06-11)


### Bug Fixes

* **deps:** force shell-quote &gt;=1.8.4 to clear critical advisory ([1a573ed](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/1a573ed37f7a22c5e5ea8146869f918da1007742))
* **e2e:** close 'Choose a pattern' starter modal before publishing ([02212a6](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/02212a6bfffdb478a073dacfece8445f885eade9))
* **e2e:** skip update checks in local/dev env; bump CI retries to 2 ([7801314](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/7801314a810b59e0c2da348a36a1862124faae45))

## [0.2.0](https://github.com/Bergert-Digital/Pediment-Child-Theme/compare/v0.1.0...v0.2.0) (2026-06-11)


### Features

* child bootstrap with starter_child_register_blocks loader ([9dc0206](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/9dc02063c6a9a38c67125c90d01aada8a4cec81c))
* **env:** add env:setup script for one-shot fresh-clone bootstrap ([c0a9a0a](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/c0a9a0a9d2fd36c17c375cf3c76316f82d7a8ea8))
* **env:** consume parent + plugin via GitHub release zips ([7c22af8](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/7c22af849d630f07dec1399b06fa90a8a3d607ac))
* promo-banner example block (starter-child namespace) ([aa46798](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/aa46798ea2d3b657e08ab941ff2ce429aae579be))
* theme identity (style.css, theme.json, package.json, tsconfig) ([1947f94](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/1947f94a1aa38899ee413f1070dfda1e083419b7))
* **updates:** add ThemeUpdater wiring PUC to GitHub releases ([842277e](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/842277e036ab5ddada8d5aca61a5010df44890ff))
* **updates:** bootstrap ThemeUpdater + add Update URI header ([fc94a31](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/fc94a31d58983de8938b29e6dd382b373e7cf86f))
* **wp-env:** add dev/publish mode switching commands ([278b7aa](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/278b7aa05554c374825d1bf8f982439a46db641e))


### Bug Fixes

* **e2e:** derive permalink from editor store, not a scraped UI link ([43de4fe](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/43de4fef55d61bcc61ee2ea45e787124a2e03d63))
* **e2e:** dismiss editor modals before interacting with the canvas ([7ba9c59](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/7ba9c5911cb7fb46a079bb89173649dca52efb23))
* **e2e:** wait for chat conversation before returning AI panel ([9456ce5](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/9456ce5812e505b82fa6aadebe81cf856c504763))
* **promo-banner:** named phpcs:ignore + editorStyle parity with parent ([9d91e08](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/9d91e087b6f21354fb9bd3f265e575483e8d760a))
* **theme-json:** drop legacy indigo/system-ui override; inherit parent Pediment ([654d77b](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/654d77b627623f088dac59267bec097cb9e9596f))
* **wp-env:** drop version from release-asset filename ([8cc74f4](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/8cc74f42d4b9b93a0b9bf00ce3fbc412be14abce))
* **wp-env:** switch to named release-asset URLs to dodge cache collision ([f1da304](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/f1da304e45a7fa30b2bf30376b12412e7ec273d5))


### Refactors

* rename Starter Child → Pediment Child ([62657ad](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/62657ad79e34e536a2957fc757d6013a087d6409))
* update parent-theme refs Starter Theme → Pediment ([8e5957e](https://github.com/Bergert-Digital/Pediment-Child-Theme/commit/8e5957ebd35d2bf9cf45e77e1fea7d48560e8c47))
