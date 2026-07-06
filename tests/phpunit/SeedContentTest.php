<?php
use PedimentChild\Seed\Seed;

class SeedContentTest extends WP_UnitTestCase {

	private function fixtures_dir(): string {
		return __DIR__ . '/fixtures/patterns';
	}

	public function test_seeds_a_page_from_a_pattern_file(): void {
		$result = Seed::seed_content( $this->fixtures_dir() );
		$this->assertTrue( $result['ok'] );
		$page = get_page_by_path( 'home' );
		$this->assertNotNull( $page );
		$this->assertSame( 'Test Home', $page->post_title );
		$this->assertStringContainsString( 'Seeded home.', $page->post_content );
	}

	public function test_sets_front_page_from_front_header(): void {
		Seed::seed_content( $this->fixtures_dir() );
		$page = get_page_by_path( 'home' );
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame( (int) $page->ID, (int) get_option( 'page_on_front' ) );
	}

	public function test_is_idempotent(): void {
		Seed::seed_content( $this->fixtures_dir() );
		Seed::seed_content( $this->fixtures_dir() );
		$pages = get_posts( array( 'post_type' => 'page', 'name' => 'home', 'numberposts' => -1, 'fields' => 'ids' ) );
		$this->assertCount( 1, $pages );
	}

	public function test_empty_patterns_dir_noops(): void {
		$empty  = sys_get_temp_dir() . '/pc-empty-' . uniqid();
		mkdir( $empty, 0777, true );
		$result = Seed::seed_content( $empty );
		$this->assertTrue( $result['ok'] );
	}
}
