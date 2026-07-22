<?php
/**
 * Settings → Pediment Theme → Updates: configure the theme update token.
 *
 * Registers a tab into the parent's settings hub, stores the token (encrypted
 * via UpdateToken), and offers a GitHub "Test connection" probe. All token
 * logic stays in the child; the parent only renders the page shell.
 *
 * @package PedimentChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reduce a GitHub repo URL to its "owner/repo" API path.
 *
 * @param string $repo_url e.g. https://github.com/acme/site/
 * @return string e.g. acme/site
 */
function pediment_child_repo_api_path( string $repo_url ): string {
	$path  = wp_parse_url( $repo_url, PHP_URL_PATH );
	$path  = is_string( $path ) ? trim( $path, '/' ) : '';
	$parts = array_slice( array_values( array_filter( explode( '/', $path ) ) ), 0, 2 );
	return (string) preg_replace( '/\.git$/', '', implode( '/', $parts ) );
}

/**
 * Diagnose a "Test connection" probe from its HTTP results.
 *
 * @param int                 $repo_status     Status of GET /repos/{owner}/{repo}.
 * @param int                 $releases_status Status of GET .../releases/latest.
 * @param array<string,mixed> $releases_body   Decoded latest-release JSON.
 * @param string              $asset_pattern   ThemeUpdater::assetPattern() regex.
 * @return array{ok:bool,message:string}
 */
function pediment_child_parse_probe_response( int $repo_status, int $releases_status, array $releases_body, string $asset_pattern ): array {
	if ( 401 === $repo_status ) {
		return array( 'ok' => false, 'message' => __( 'Token rejected by GitHub (401). Check the token value.', 'pediment-child' ) );
	}
	if ( 403 === $repo_status ) {
		return array( 'ok' => false, 'message' => __( 'GitHub denied access (403). The token may lack Contents access or be rate-limited.', 'pediment-child' ) );
	}
	if ( 200 !== $repo_status ) {
		/* translators: %d: HTTP status code. */
		return array( 'ok' => false, 'message' => sprintf( __( 'Repository not visible with this token (HTTP %d).', 'pediment-child' ), $repo_status ) );
	}
	if ( 200 !== $releases_status ) {
		return array( 'ok' => false, 'message' => __( 'Repository visible, but no published release was found.', 'pediment-child' ) );
	}
	$assets = isset( $releases_body['assets'] ) && is_array( $releases_body['assets'] ) ? $releases_body['assets'] : array();
	$tag    = isset( $releases_body['tag_name'] ) ? (string) $releases_body['tag_name'] : '';
	foreach ( $assets as $asset ) {
		$name = is_array( $asset ) && isset( $asset['name'] ) ? (string) $asset['name'] : '';
		if ( '' !== $name && preg_match( $asset_pattern, $name ) ) {
			/* translators: 1: release tag, 2: asset file name. */
			return array( 'ok' => true, 'message' => sprintf( __( 'Success: release %1$s includes %2$s.', 'pediment-child' ), $tag, $name ) );
		}
	}
	/* translators: %s: release tag. */
	return array( 'ok' => false, 'message' => sprintf( __( 'Release %s found, but no matching theme zip asset.', 'pediment-child' ), $tag ) );
}
