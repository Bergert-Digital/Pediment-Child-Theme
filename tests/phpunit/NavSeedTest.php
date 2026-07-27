<?php

class NavSeedTest extends WP_UnitTestCase {

	/**
	 * Language filters registered by a test, removed again in tear_down.
	 *
	 * @var callable[]
	 */
	private array $language_filters = array();

	public function tear_down(): void {
		foreach ( $this->language_filters as $filter ) {
			remove_filter( 'posts_where', $filter );
		}
		$this->language_filters = array();

		parent::tear_down();
	}

	/**
	 * Hide posts from *filtered* queries only.
	 *
	 * Polylang/WPML scope queries to the language being rendered, so an entity
	 * tagged with another language is invisible to a filtered query but still
	 * there. WP_Query skips `posts_where` when `suppress_filters` is true —
	 * the same asymmetry the lookup relies on — so this reproduces the failure
	 * without installing a multilingual plugin.
	 *
	 * @param int[] $ids Posts to hide. Empty hides every post.
	 */
	private function hide_from_filtered_queries( array $ids = array() ): void {
		global $wpdb;

		$clause = empty( $ids )
			? '1=0'
			: $wpdb->posts . '.ID NOT IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')';

		$filter = static function ( $where ) use ( $clause ) {
			return $where . ' AND ' . $clause;
		};

		add_filter( 'posts_where', $filter );
		$this->language_filters[] = $filter;
	}

	/**
	 * Every navigation entity, language scoping ignored.
	 *
	 * @return int[]
	 */
	private function all_navigation_ids(): array {
		return get_posts(
			array(
				'post_type'        => 'wp_navigation',
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
	}

	private function delete_all_navigation_entities(): void {
		foreach ( $this->all_navigation_ids() as $id ) {
			wp_delete_post( $id, true );
		}
	}

	/**
	 * A second marked entity, as a multilingual site's translated menu.
	 */
	private function create_marked_entity( string $label ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => $label,
				'post_content' => '<!-- wp:navigation-link {"label":"' . $label . '","url":"/' . strtolower( $label ) . '","kind":"custom"} /-->',
			)
		);
		update_post_meta( $id, PEDIMENT_NAV_MARKER, '1' );

		return $id;
	}

	public function test_seeds_nav_entity_idempotently(): void {
		$a = pediment_nav_seed_entity();
		$b = pediment_nav_seed_entity();
		$this->assertGreaterThan( 0, $a );
		$this->assertSame( $a, $b );
		$this->assertSame( '1', get_post_meta( $a, PEDIMENT_NAV_MARKER, true ) );
	}

	public function test_finds_the_entity_when_a_language_filter_hides_it(): void {
		$id = pediment_nav_seed_entity();

		$this->hide_from_filtered_queries();

		$this->assertSame(
			$id,
			pediment_nav_find_entity_id(),
			'A language-scoped query must not cost the site its navigation'
		);
	}

	public function test_header_still_renders_its_links_when_a_language_filter_hides_the_entity(): void {
		pediment_nav_seed_entity();

		$this->hide_from_filtered_queries();

		$html = do_blocks( '<!-- wp:navigation /-->' );

		$this->assertStringContainsString( 'About', $html );
		$this->assertStringContainsString( 'Contact', $html );
	}

	public function test_prefers_the_entity_the_language_filter_leaves_visible(): void {
		$default    = pediment_nav_seed_entity();
		$translated = $this->create_marked_entity( 'Uebersetzt' );

		// Only the translated entity belongs to the language being rendered.
		$this->hide_from_filtered_queries( array( $default ) );

		$this->assertSame(
			$translated,
			pediment_nav_find_entity_id(),
			'A per-language entity must still win over the unfiltered fallback'
		);
	}

	public function test_does_not_duplicate_the_entity_when_a_language_filter_hides_it(): void {
		$id     = pediment_nav_seed_entity();
		$before = $this->all_navigation_ids();

		$this->hide_from_filtered_queries();

		$this->assertSame( $id, pediment_nav_seed_entity() );
		$this->assertSame( $before, $this->all_navigation_ids(), 'The seeder must not create a rival entity' );
	}

	public function test_binds_nothing_and_persists_nothing_when_no_entity_exists(): void {
		$this->delete_all_navigation_entities();

		$this->assertSame( 0, pediment_nav_find_entity_id() );

		$block = pediment_nav_bind_ref(
			array(
				'blockName' => 'core/navigation',
				'attrs'     => array(),
			)
		);

		$this->assertArrayNotHasKey( 'ref', $block['attrs'] );
		$this->assertSame( array(), $this->all_navigation_ids(), 'Binding must never persist a navigation entity' );
	}
}
