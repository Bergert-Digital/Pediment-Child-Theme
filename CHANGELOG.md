# Changelog

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
