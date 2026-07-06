<?php
/**
 * PHPUnit bootstrap: loads WP test harness and the child theme.
 *
 * Runs inside wp-env's tests-wordpress container.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . '/includes/functions.php';

// The active theme's slug is its directory basename. In a forked/renamed
// child theme (and in Conductor workspaces, where the dir is the workspace
// name) that is NOT literally "pediment-child-theme", so derive it from the
// repo root rather than hard-coding — matching the rest of the tooling.
$pediment_child_theme_slug = basename( dirname( __DIR__, 2 ) );

tests_add_filter(
	'muplugins_loaded',
	function () use ( $pediment_child_theme_slug ) {
		switch_theme( $pediment_child_theme_slug );
	}
);

require $_tests_dir . '/includes/bootstrap.php';
