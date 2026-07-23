<?php

class ThemeUpdaterTest extends WP_UnitTestCase {
	public function test_asset_pattern_matches_slug_zip() {
		$pattern = \PedimentChild\ThemeUpdater::assetPattern( 'acme-theme' );
		$this->assertMatchesRegularExpression( $pattern, 'acme-theme.zip' );
	}

	public function test_asset_pattern_rejects_other_zip() {
		$pattern = \PedimentChild\ThemeUpdater::assetPattern( 'acme-theme' );
		$this->assertDoesNotMatchRegularExpression( $pattern, 'pediment-child.zip' );
	}

	public function test_asset_pattern_escapes_regex_metacharacters() {
		// A dotted slug must match literally, not treat "." as "any char".
		$pattern = \PedimentChild\ThemeUpdater::assetPattern( 'acme.co' );
		$this->assertMatchesRegularExpression( $pattern, 'acme.co.zip' );
		$this->assertDoesNotMatchRegularExpression( $pattern, 'acmexco.zip' );
	}

	public function test_asset_pattern_is_anchored_at_end() {
		$pattern = \PedimentChild\ThemeUpdater::assetPattern( 'acme' );
		$this->assertDoesNotMatchRegularExpression( $pattern, 'acme.zip.bak' );
	}

	public function test_asset_pattern_is_anchored_at_start() {
		// Must not match an asset that merely ends in "<slug>.zip".
		$pattern = \PedimentChild\ThemeUpdater::assetPattern( 'acme' );
		$this->assertDoesNotMatchRegularExpression( $pattern, 'not-acme.zip' );
	}

	public function test_repo_url_is_a_github_url() {
		$url = \PedimentChild\ThemeUpdater::repoUrl();
		$this->assertStringStartsWith( 'https://github.com/', $url );
	}
}
