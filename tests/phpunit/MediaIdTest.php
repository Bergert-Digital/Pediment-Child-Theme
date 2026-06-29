<?php
// tests/phpunit/MediaIdTest.php
class MediaIdTest extends WP_UnitTestCase {

	public function test_returns_zero_when_not_seeded(): void {
		$this->assertSame( 0, pediment_child_media_id( 'nope.jpg' ) );
	}

	public function test_resolves_tagged_attachment(): void {
		$id = self::factory()->attachment->create();
		update_post_meta( $id, '_pediment_child_seed_src', 'hero.jpg' );
		$this->assertSame( $id, pediment_child_media_id( 'hero.jpg' ) );
	}
}
