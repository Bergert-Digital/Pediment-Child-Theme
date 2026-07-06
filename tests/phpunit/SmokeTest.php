<?php

class SmokeTest extends WP_UnitTestCase {
	public function test_wordpress_is_loaded() {
		$this->assertTrue( function_exists( 'wp_get_theme' ) );
	}

	public function test_child_theme_is_active() {
		// Slug is the directory basename (fork/rename- and workspace-safe),
		// not a hard-coded "pediment-child-theme".
		$this->assertSame( basename( dirname( __DIR__, 2 ) ), wp_get_theme()->get_stylesheet() );
	}

	public function test_parent_template_is_pediment() {
		$this->assertSame( 'pediment', wp_get_theme()->get_template() );
	}
}
