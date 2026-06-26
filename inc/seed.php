<?php
/**
 * Pediment child content seeder: materialize committed patterns + assets onto
 * any install, via `wp pediment-child seed` and Tools → "Seed content".
 *
 * @package PedimentChild
 */

namespace PedimentChild\Seed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Idempotent seeder for the client's committed patterns + assets.
 */
class Seed {

	const SRC_META = '_pediment_child_seed_src';

	/**
	 * Register the `wp pediment-child seed` CLI command.
	 */
	public static function register_cli(): void {
		\WP_CLI::add_command( 'pediment-child seed', array( __CLASS__, 'cli_seed_content' ) );
	}

	/**
	 * CLI entry point: seed content and report the log.
	 */
	public static function cli_seed_content(): void {
		$r = self::seed_content();
		foreach ( $r['log'] as $line ) {
			\WP_CLI::log( $line );
		}
		\WP_CLI::success( $r['summary'] );
	}

	/**
	 * Seed the client's committed patterns + assets. Idempotent.
	 *
	 * @param string|null $patterns_dir Defaults to the theme's patterns/ dir.
	 * @return array{ok:bool,summary:string,log:string[]}
	 */
	public static function seed_content( ?string $patterns_dir = null ): array {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$dir = $patterns_dir ? $patterns_dir : get_theme_file_path( 'patterns' );
		$log = self::import_images();
		$log = array_merge( $log, self::set_logo_if_present() );

		$pages = self::discover_pages( $dir );
		list( $ids, $page_log ) = self::upsert_pages_from( $pages );
		$log                    = array_merge( $log, $page_log );

		return array(
			'ok'      => true,
			'summary' => 'Pediment child content seeded.',
			'log'     => $log,
		);
	}

	/**
	 * Discover committed pattern files and render them into a page map.
	 *
	 * @param string $dir Patterns directory.
	 * @return array<string,array{title:string,content:string,front:bool}>
	 */
	private static function discover_pages( string $dir ): array {
		$pages = array();
		foreach ( glob( rtrim( $dir, '/' ) . '/*.php' ) ?: array() as $file ) {
			$slug    = basename( $file, '.php' );
			$headers = get_file_data(
				$file,
				array(
					'title' => 'Title',
					'front' => 'Front Page',
				)
			);
			ob_start();
			include $file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			$content        = ob_get_clean();
			$pages[ $slug ] = array(
				'title'   => $headers['title'] ? $headers['title'] : ucfirst( $slug ),
				'content' => $content,
				'front'   => ( 'home' === $slug ) || ( 'yes' === strtolower( trim( $headers['front'] ) ) ),
			);
		}
		return $pages;
	}

	/**
	 * Upsert each slug => {title,content,front} as a published Page; set the
	 * front page + pretty permalinks. Reused by seed_content() and seed-demo.
	 *
	 * @param array<string,array{title:string,content:string,front:bool}> $pages Page map.
	 * @return array{0:array<string,int>,1:string[]}
	 */
	public static function upsert_pages_from( array $pages ): array {
		$ids = array();
		$log = array();
		foreach ( $pages as $slug => $info ) {
			$existing = get_page_by_path( $slug );
			$postarr  = array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $info['title'],
				'post_name'    => $slug,
				'post_content' => $info['content'],
			);
			if ( $existing ) {
				$postarr['ID'] = $existing->ID;
				$id            = wp_update_post( $postarr, true );
			} else {
				$id = wp_insert_post( $postarr, true );
			}
			if ( is_wp_error( $id ) ) {
				$log[] = "  WARN upsert '{$slug}': " . $id->get_error_message();
				continue;
			}
			$ids[ $slug ] = (int) $id;
			$log[]        = '  ' . ( $existing ? 'updated' : 'created' ) . " page: {$slug} (#{$id})";
		}

		$front = '';
		foreach ( $pages as $slug => $info ) {
			if ( ! empty( $info['front'] ) ) {
				$front = $slug;
				break;
			}
		}
		if ( $front && ! empty( $ids[ $front ] ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $ids[ $front ] );
			$log[] = "Front page set to {$front} (#{$ids[ $front ]}).";
		}
		if ( '' === get_option( 'permalink_structure' ) ) {
			update_option( 'permalink_structure', '/%postname%/' );
			flush_rewrite_rules( true );
		}
		return array( $ids, $log );
	}

	/**
	 * Import every image from assets/img/ into the Media Library.
	 * Skips files that already have an attachment tagged with SRC_META.
	 *
	 * @return string[] Log lines.
	 */
	private static function import_images(): array {
		$log     = array();
		$img_dir = get_theme_file_path( 'assets/img' );
		$files   = array_merge(
			glob( $img_dir . '/*.jpg' ) ?: array(),
			glob( $img_dir . '/*.jpeg' ) ?: array(),
			glob( $img_dir . '/*.png' ) ?: array(),
			glob( $img_dir . '/*.gif' ) ?: array(),
			glob( $img_dir . '/*.webp' ) ?: array()
		);

		if ( empty( $files ) ) {
			$log[] = 'Warning: no image files found in ' . $img_dir;
			return $log;
		}

		foreach ( $files as $file ) {
			$basename = basename( $file );

			// Idempotency check: skip if already imported.
			if ( self::find_attachment_by_src( $basename ) ) {
				$log[] = "  skip (already imported): {$basename}";
				continue;
			}

			$id = self::sideload_image( $file, $basename );
			if ( is_wp_error( $id ) ) {
				$log[] = "  Warning: failed to import {$basename}: " . $id->get_error_message();
				continue;
			}

			update_post_meta( $id, self::SRC_META, $basename );
			$log[] = "  imported: {$basename} (attachment #{$id})";
		}

		return $log;
	}

	/**
	 * Sideload a local file into the WP Media Library.
	 *
	 * @param string $file     Absolute path to the source file.
	 * @param string $basename Original filename (used for the title / alt).
	 * @return int|\WP_Error Attachment ID or error.
	 */
	private static function sideload_image( string $file, string $basename ) {
		// Build a $_FILES-style array.
		$mime = mime_content_type( $file );
		$tmp  = wp_tempnam( $basename );

		if ( ! copy( $file, $tmp ) ) {
			return new \WP_Error( 'copy_failed', "Could not copy {$basename} to temp dir." );
		}

		$file_array = array(
			'name'     => $basename,
			'type'     => $mime ? $mime : 'application/octet-stream',
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => filesize( $file ),
		);

		// 0 = not attached to any post (global media).
		$id = media_handle_sideload( $file_array, 0, pathinfo( $basename, PATHINFO_FILENAME ) );

		// media_handle_sideload removes the tmp file on success/failure.
		return $id;
	}

	/**
	 * Return attachment ID tagged with the given basename, or 0.
	 *
	 * @param string $basename Original filename used as the SRC_META meta value.
	 * @return int Attachment ID, or 0 if not found.
	 */
	private static function find_attachment_by_src( string $basename ): int {
		$q = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::SRC_META,
						'value' => $basename,
					),
				),
			)
		);
		return $q->have_posts() ? (int) $q->posts[0] : 0;
	}

	/**
	 * Set the custom logo theme mod to a seeded logo attachment, if one exists.
	 *
	 * No-ops cleanly (empty log) when no assets/img/logo.* file is present.
	 *
	 * @return string[] Log lines.
	 */
	private static function set_logo_if_present(): array {
		$img_dir = get_theme_file_path( 'assets/img' );
		$matches = glob( $img_dir . '/logo.*' ) ?: array();
		if ( empty( $matches ) ) {
			return array();
		}

		$basename = basename( $matches[0] );
		$logo_id  = self::find_attachment_by_src( $basename );
		if ( $logo_id ) {
			set_theme_mod( 'custom_logo', $logo_id );
			return array( "Custom logo set to attachment #{$logo_id}." );
		}
		return array( 'Warning: logo file present but attachment not found — custom_logo not set.' );
	}
}
