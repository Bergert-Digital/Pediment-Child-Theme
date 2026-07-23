<?php

// functions.php only requires inc/settings-updates.php inside is_admin(),
// which PHPUnit's bootstrap doesn't set; guard-load it directly so this
// suite is independent (mirrors UpdateProbeTest.php / UpdateSettingsRenderTest.php).
require_once dirname( __DIR__, 2 ) . '/inc/settings-updates.php';

class UpdateAjaxTest extends WP_Ajax_UnitTestCase {
	public function test_test_connection_requires_valid_nonce() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$_POST['_ajax_nonce'] = 'bogus';
		$this->expectException( WPAjaxDieStopException::class );
		$this->_handleAjax( 'pediment_child_test_update_token' );
	}
}
