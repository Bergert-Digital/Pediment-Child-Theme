<?php

/**
 * Guards that the child theme does NOT mask the parent's Pediment tokens.
 *
 * Runs in the child wp-env base (:8890/:8891), which mounts this child
 * checkout plus ../pediment (the parent). With the child theme
 * active and no `settings` block in the child theme.json, the resolved
 * global settings must be the parent's Pediment palette/typography.
 *
 * IMPORTANT (harness quirk): the WP test bootstrap primes
 * WP_Theme_JSON_Resolver's static caches before switching the theme, and
 * touching the theme object (`wp_get_theme()`) before the resolver read
 * re-poisons them in a way `clean_cached_data()` alone does not undo. So
 * every test here must `clean_cached_data()` and read the merged data
 * FIRST, and only then assert the active theme via the lightweight
 * `get_stylesheet()` string accessor. Do not introduce a `set_up()` that
 * calls `wp_get_theme()`.
 */
class ThemeJsonInheritsPedimentTest extends WP_UnitTestCase {

	/**
	 * Clean the resolver caches and return the freshly merged
	 * (parent ⊕ child) global settings. Must be the first thing a test
	 * does — before any theme-object access.
	 *
	 * @return array<string,mixed>
	 */
	private function fresh_settings() {
		WP_Theme_JSON_Resolver::clean_cached_data();
		return WP_Theme_JSON_Resolver::get_merged_data()->get_settings();
	}

	private function assert_child_theme_active() {
		$this->assertSame(
			'pediment-child-theme',
			get_stylesheet(),
			'These Pediment-inheritance guards are only meaningful with the child theme active.'
		);
	}

	/**
	 * @return array<string,string> slug => hex, from the theme-origin palette.
	 */
	private function theme_palette() {
		$settings = $this->fresh_settings();
		$this->assert_child_theme_active();
		$palette = isset( $settings['color']['palette']['theme'] )
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
		$settings = $this->fresh_settings();
		$this->assert_child_theme_active();
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
		$this->assert_child_theme_active();
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
