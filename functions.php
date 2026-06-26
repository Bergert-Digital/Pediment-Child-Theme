<?php
/**
 * Pediment Child Theme bootstrap.
 *
 * Fork target. Pediment (parent) is read-only; your blocks,
 * theme.json overrides and child-specific PHP live here.
 *
 * @package PedimentChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PEDIMENT_CHILD_DIR' ) ) {
	define( 'PEDIMENT_CHILD_DIR', __DIR__ );
}
if ( ! defined( 'PEDIMENT_CHILD_VERSION' ) ) {
	define( 'PEDIMENT_CHILD_VERSION', '0.1.0' );
}

// One-click theme updates from GitHub Releases (no manual zip uploads).
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}
require_once __DIR__ . '/inc/ThemeUpdater.php';
\PedimentChild\ThemeUpdater::register();

require_once __DIR__ . '/inc/media.php';

/**
 * Register every block in the given directory (defaults to build/blocks).
 *
 * Named distinctly from the parent's pediment_register_blocks() — both
 * functions.php files load for a child theme, so an identical name would
 * fatal-redeclare.
 *
 * @param string|null $base_dir Directory containing block subfolders.
 */
function pediment_child_register_blocks( $base_dir = null ) {
	if ( null === $base_dir || '' === $base_dir ) {
		$base_dir = PEDIMENT_CHILD_DIR . '/build/blocks';
	}

	if ( ! is_dir( $base_dir ) ) {
		return;
	}

	$registry = WP_Block_Type_Registry::get_instance();
	foreach ( glob( $base_dir . '/*', GLOB_ONLYDIR ) as $block_dir ) {
		$manifest = $block_dir . '/block.json';
		if ( ! file_exists( $manifest ) ) {
			continue;
		}
		$meta = json_decode( file_get_contents( $manifest ), true );
		if ( is_array( $meta ) && isset( $meta['name'] ) && $registry->is_registered( $meta['name'] ) ) {
			continue;
		}
		register_block_type( $block_dir );
	}
}

add_action(
	'init',
	function () {
		pediment_child_register_blocks();
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'pediment-child',
			get_stylesheet_directory_uri() . '/style.css',
			array(),
			wp_get_theme()->get( 'Version' )
		);
	}
);
