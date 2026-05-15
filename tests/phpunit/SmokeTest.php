<?php

class SmokeTest extends WP_UnitTestCase {
	public function test_wordpress_is_loaded() {
		$this->assertTrue( function_exists( 'wp_get_theme' ) );
	}

	public function test_child_theme_is_active() {
		$this->assertSame( 'wp-starter-child-theme', wp_get_theme()->get_stylesheet() );
	}

	public function test_parent_template_is_starter_theme() {
		$this->assertSame( 'wp-starter-theme', wp_get_theme()->get_template() );
	}
}
