<?php

/**
 * The release-please markers live inside the style.css header comment. WP must
 * still parse a clean semver from the Version: header with them present.
 */
class StyleHeaderTest extends WP_UnitTestCase {
	/** Path to the theme's committed style.css. */
	private function style_path(): string {
		return dirname( __DIR__, 2 ) . '/style.css';
	}

	public function test_version_header_parses_to_clean_semver() {
		$data = get_file_data( $this->style_path(), array( 'Version' => 'Version' ) );
		$this->assertMatchesRegularExpression(
			'/^\d+\.\d+\.\d+$/',
			$data['Version'],
			'style.css Version header must parse to a bare X.Y.Z with markers present'
		);
	}

	public function test_release_please_block_markers_present() {
		$css = (string) file_get_contents( $this->style_path() );
		$this->assertStringContainsString( 'x-release-please-start-version', $css );
		$this->assertStringContainsString( 'x-release-please-end-version', $css );
	}
}
