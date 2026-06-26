<?php
use PedimentChild\Seed\Demo;

class SeedDemoTest extends WP_UnitTestCase {
	public function test_seed_demo_creates_showcase_pages(): void {
		$r = Demo::seed_demo();
		$this->assertTrue( $r['ok'] );
		foreach ( array( 'home', 'about', 'contact', 'blog', 'mega-demo' ) as $slug ) {
			$this->assertNotNull( get_page_by_path( $slug ), "missing demo page: {$slug}" );
		}
		$this->assertSame( (int) get_page_by_path( 'home' )->ID, (int) get_option( 'page_on_front' ) );
	}
}
