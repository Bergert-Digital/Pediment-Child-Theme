<?php
/**
 * Resolve a seeded media attachment ID by its original filename.
 *
 * Framework helper — KEEP THIS NAME STABLE across a fork rename. Committed
 * patterns call it; the /create-seed-content skill emits the call sites.
 *
 * Returns 0 when the attachment isn't imported yet (graceful — blocks that take
 * a mediaId render imageless until `wp pediment-child seed` runs).
 *
 * @package PedimentChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pediment_child_media_id' ) ) {
	function pediment_child_media_id( string $filename ): int {
		$q = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_pediment_child_seed_src',
						'value' => $filename,
					),
				),
			)
		);
		return $q->have_posts() ? (int) $q->posts[0] : 0;
	}
}
