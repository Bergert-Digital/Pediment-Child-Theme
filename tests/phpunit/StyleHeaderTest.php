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

	public function test_functions_php_has_release_please_marker() {
		$functions_php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/functions.php' );
		$this->assertStringContainsString( 'x-release-please-version', $functions_php );
	}

	public function test_style_css_version_matches_pediment_child_version_constant() {
		$data = get_file_data( $this->style_path(), array( 'Version' => 'Version' ) );

		if ( defined( 'PEDIMENT_CHILD_VERSION' ) ) {
			$constant_version = PEDIMENT_CHILD_VERSION;
		} else {
			$functions_php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/functions.php' );
			$this->assertMatchesRegularExpression(
				'/define\(\s*\'PEDIMENT_CHILD_VERSION\',\s*\'([^\']*)\'/',
				$functions_php,
				'functions.php must define PEDIMENT_CHILD_VERSION'
			);
			preg_match( '/define\(\s*\'PEDIMENT_CHILD_VERSION\',\s*\'([^\']*)\'/', $functions_php, $matches );
			$constant_version = $matches[1];
		}

		$this->assertSame(
			$constant_version,
			$data['Version'],
			'style.css Version header must match the PEDIMENT_CHILD_VERSION constant in functions.php — the two files must be locked together after every release'
		);
	}
}
