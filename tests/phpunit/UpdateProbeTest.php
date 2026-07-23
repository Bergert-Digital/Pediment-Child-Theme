<?php

// functions.php only requires inc/settings-updates.php inside is_admin(),
// which PHPUnit's bootstrap doesn't set; guard-load it directly so this
// suite is independent (mirrors UpdateTokenTest.php).
require_once dirname( __DIR__, 2 ) . '/inc/settings-updates.php';

class UpdateProbeTest extends WP_UnitTestCase {
	public function test_repo_api_path_extracts_owner_repo() {
		$this->assertSame(
			'Bergert-Digital/Pediment-Child-Theme',
			pediment_child_repo_api_path( 'https://github.com/Bergert-Digital/Pediment-Child-Theme/' )
		);
	}

	public function test_repo_api_path_strips_git_suffix() {
		$this->assertSame(
			'acme/site',
			pediment_child_repo_api_path( 'https://github.com/acme/site.git' )
		);
	}

	public function test_probe_reports_bad_token() {
		$result = pediment_child_parse_probe_response( 401, 0, array(), '/^acme\.zip$/' );
		$this->assertFalse( $result['ok'] );
		$this->assertStringContainsString( '401', $result['message'] );
	}

	public function test_probe_reports_repo_not_visible() {
		$result = pediment_child_parse_probe_response( 404, 0, array(), '/^acme\.zip$/' );
		$this->assertFalse( $result['ok'] );
		$this->assertStringContainsString( '404', $result['message'] );
	}

	public function test_probe_reports_no_release() {
		$result = pediment_child_parse_probe_response( 200, 404, array(), '/^acme\.zip$/' );
		$this->assertFalse( $result['ok'] );
		$this->assertStringContainsString( 'release', $result['message'] );
	}

	public function test_probe_reports_missing_asset() {
		$body   = array( 'tag_name' => 'v1.2.0', 'assets' => array( array( 'name' => 'other.zip' ) ) );
		$result = pediment_child_parse_probe_response( 200, 200, $body, '/^acme\.zip$/' );
		$this->assertFalse( $result['ok'] );
		$this->assertStringContainsString( 'v1.2.0', $result['message'] );
	}

	public function test_probe_success_when_asset_matches() {
		$body   = array( 'tag_name' => 'v1.2.0', 'assets' => array( array( 'name' => 'acme.zip' ) ) );
		$result = pediment_child_parse_probe_response( 200, 200, $body, '/^acme\.zip$/' );
		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'acme.zip', $result['message'] );
	}
}
