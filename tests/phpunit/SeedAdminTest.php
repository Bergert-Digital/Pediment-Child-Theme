<?php
use PedimentChild\Seed\Seed;

class SeedAdminTest extends WP_UnitTestCase {
	public function test_admin_methods_exist(): void {
		$this->assertTrue( method_exists( Seed::class, 'add_admin_page' ) );
		$this->assertTrue( method_exists( Seed::class, 'handle_admin_run' ) );
	}

	public function test_tools_page_registered_for_admin(): void {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );
		set_current_screen( 'dashboard' );
		Seed::add_admin_page();
		global $submenu;
		$tools = $submenu['tools.php'] ?? array();
		$slugs = array_map( fn( $i ) => $i[2], $tools );
		$this->assertContains( 'pediment-child-seed', $slugs );
	}
}
