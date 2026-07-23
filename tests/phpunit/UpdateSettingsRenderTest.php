<?php

use PedimentChild\UpdateToken;

// functions.php only requires inc/settings-updates.php inside is_admin(),
// which PHPUnit's bootstrap doesn't set; guard-load it directly so this
// suite is independent (mirrors UpdateTokenTest.php / UpdateProbeTest.php).
require_once dirname( __DIR__, 2 ) . '/inc/settings-updates.php';

class UpdateSettingsRenderTest extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
	}

	private function render(): string {
		ob_start();
		pediment_child_render_updates_tab();
		return (string) ob_get_clean();
	}

	public function test_render_never_echoes_the_stored_token() {
		UpdateToken::store( 'github_pat_SUPERSECRET_value' );
		$html = $this->render();
		$this->assertStringNotContainsString( 'github_pat_SUPERSECRET_value', $html );
	}

	public function test_render_shows_configured_status_and_remove_action() {
		UpdateToken::store( 'github_pat_configured' );
		$html = $this->render();
		$this->assertStringContainsString( 'pediment_child_remove_update_token', $html );
	}

	public function test_render_shows_not_configured_state_without_warning() {
		UpdateToken::remove();
		putenv( UpdateToken::CONSTANT );
		$html = $this->render();
		$this->assertStringContainsString( 'pediment_child_save_update_token', $html );
		$this->assertStringNotContainsString( 'notice-error', $html );
	}

	public function test_render_includes_a_nonce_field() {
		$html = $this->render();
		$this->assertStringContainsString( 'name="_wpnonce"', $html );
	}
}
