<?php

class NavSeedTest extends WP_UnitTestCase {
	public function test_seeds_nav_entity_idempotently(): void {
		$a = pediment_nav_seed_entity();
		$b = pediment_nav_seed_entity();
		$this->assertGreaterThan( 0, $a );
		$this->assertSame( $a, $b );
		$this->assertSame( '1', get_post_meta( $a, PEDIMENT_NAV_MARKER, true ) );
	}
}
