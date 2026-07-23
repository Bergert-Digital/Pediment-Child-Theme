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

/**
 * Admin URL of the Updates tab (parent hub deep-link, or standalone fallback).
 */
function pediment_child_updates_url(): string {
	if ( function_exists( 'pediment_settings_page_url' ) ) {
		return pediment_settings_page_url( 'updates' );
	}
	return add_query_arg( 'page', 'pediment-child-updates', admin_url( 'options-general.php' ) );
}

/**
 * Register the Updates tab into the parent hub, or a standalone page if the
 * parent is absent or predates the hub API.
 */
add_action(
	'admin_menu',
	function () {
		if ( function_exists( 'pediment_settings_register_tab' ) ) {
			pediment_settings_register_tab( 'updates', __( 'Updates', 'pediment-child' ), 'pediment_child_render_updates_tab', 20 );
			return;
		}
		add_options_page(
			__( 'Theme Updates', 'pediment-child' ),
			__( 'Theme Updates', 'pediment-child' ),
			'manage_options',
			'pediment-child-updates',
			function () {
				echo '<div class="wrap"><h1>' . esc_html__( 'Theme Updates', 'pediment-child' ) . '</h1>';
				settings_errors( 'pediment_child_updates' );
				pediment_child_render_updates_tab();
				echo '</div>';
			}
		);
	}
);

/**
 * Render the Updates tab body. Write-only: never echoes the stored token.
 */
function pediment_child_render_updates_tab(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$resolved   = \PedimentChild\UpdateToken::resolve();
	$configured = '' !== $resolved['token'];
	$by_constant = 'constant' === $resolved['source'] || 'env' === $resolved['source'];
	?>
	<p><?php esc_html_e( 'A GitHub fine-grained personal access token with read-only Contents on this theme’s releases repository. Optional — only needed when updates come from a private repository.', 'pediment-child' ); ?></p>

	<?php if ( $by_constant ) : ?>
		<p><strong><?php esc_html_e( 'A token is defined in wp-config.php (or the environment) and takes precedence over anything saved here.', 'pediment-child' ); ?></strong></p>
	<?php elseif ( $configured ) : ?>
		<p><strong><?php esc_html_e( 'Token configured ✓', 'pediment-child' ); ?></strong></p>
	<?php else : ?>
		<p><em><?php esc_html_e( 'Not configured.', 'pediment-child' ); ?></em></p>
	<?php endif; ?>

	<?php if ( ! $by_constant ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="pediment_child_save_update_token" />
		<?php wp_nonce_field( 'pediment_child_save_update_token' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="pediment_child_update_token"><?php esc_html_e( 'Theme update token', 'pediment-child' ); ?></label></th>
				<td>
					<input type="password" class="regular-text" id="pediment_child_update_token" name="pediment_child_update_token" autocomplete="off" value=""
						placeholder="<?php echo esc_attr( $configured ? '••••••••••••••••' : 'github_pat_…' ); ?>" />
					<p class="description"><?php esc_html_e( 'Paste a new token to save or replace. The stored value is encrypted and never shown again.', 'pediment-child' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( $configured ? __( 'Replace token', 'pediment-child' ) : __( 'Save token', 'pediment-child' ) ); ?>
	</form>
	<?php endif; ?>

	<?php if ( $configured && ! $by_constant ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1em;">
			<input type="hidden" name="action" value="pediment_child_remove_update_token" />
			<?php wp_nonce_field( 'pediment_child_remove_update_token' ); ?>
			<?php submit_button( __( 'Remove token', 'pediment-child' ), 'delete', 'submit', false ); ?>
		</form>
	<?php endif; ?>

	<p style="margin-top:1em;">
		<button type="button" class="button" id="pediment-child-test-connection"><?php esc_html_e( 'Test connection', 'pediment-child' ); ?></button>
		<span id="pediment-child-test-result" style="margin-left:.5em;"></span>
	</p>
	<?php
}

/**
 * Persist errors across the admin-post redirect and bounce back to the tab.
 */
function pediment_child_updates_redirect(): void {
	set_transient( 'settings_errors', get_settings_errors(), 30 );
	wp_safe_redirect( add_query_arg( 'settings-updated', 'true', pediment_child_updates_url() ) );
	exit;
}

add_action( 'admin_post_pediment_child_save_update_token', 'pediment_child_handle_save_update_token' );
/**
 * Handle a token save/replace.
 */
function pediment_child_handle_save_update_token(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'pediment-child' ) );
	}
	check_admin_referer( 'pediment_child_save_update_token' );
	$raw = isset( $_POST['pediment_child_update_token'] ) ? sanitize_text_field( wp_unslash( $_POST['pediment_child_update_token'] ) ) : '';
	if ( '' === $raw ) {
		add_settings_error( 'pediment_child_updates', 'empty', __( 'No token entered.', 'pediment-child' ), 'error' );
	} elseif ( \PedimentChild\UpdateToken::store( $raw ) ) {
		add_settings_error( 'pediment_child_updates', 'saved', __( 'Update token saved.', 'pediment-child' ), 'success' );
	} else {
		add_settings_error( 'pediment_child_updates', 'failed', __( 'Could not store the token (encryption unavailable).', 'pediment-child' ), 'error' );
	}
	pediment_child_updates_redirect();
}

add_action( 'admin_post_pediment_child_remove_update_token', 'pediment_child_handle_remove_update_token' );
/**
 * Handle a token removal.
 */
function pediment_child_handle_remove_update_token(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'pediment-child' ) );
	}
	check_admin_referer( 'pediment_child_remove_update_token' );
	\PedimentChild\UpdateToken::remove();
	add_settings_error( 'pediment_child_updates', 'removed', __( 'Update token removed.', 'pediment-child' ), 'success' );
	pediment_child_updates_redirect();
}

add_action( 'wp_ajax_pediment_child_test_update_token', 'pediment_child_ajax_test_update_token' );
/**
 * "Test connection": probe the repo + latest release with the effective token.
 */
function pediment_child_ajax_test_update_token(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pediment-child' ) ), 403 );
	}
	check_ajax_referer( 'pediment_child_test_update_token' );

	$resolved = \PedimentChild\UpdateToken::resolve();
	if ( '' === $resolved['token'] ) {
		wp_send_json_error( array( 'message' => __( 'No token configured. Save a token first, then test.', 'pediment-child' ) ) );
	}

	$base = 'https://api.github.com/repos/' . pediment_child_repo_api_path( \PedimentChild\ThemeUpdater::repoUrl() );
	$args = array(
		'timeout' => 15,
		'headers' => array(
			'Authorization' => 'Bearer ' . $resolved['token'],
			'Accept'        => 'application/vnd.github+json',
			'User-Agent'    => 'pediment-child-theme',
		),
	);

	$repo = wp_remote_get( $base, $args );
	if ( is_wp_error( $repo ) ) {
		wp_send_json_error( array( 'message' => $repo->get_error_message() ) );
	}
	$rel        = wp_remote_get( $base . '/releases/latest', $args );
	$rel_status = is_wp_error( $rel ) ? 0 : (int) wp_remote_retrieve_response_code( $rel );
	$rel_body   = is_wp_error( $rel ) ? array() : (array) json_decode( (string) wp_remote_retrieve_body( $rel ), true );

	$result = pediment_child_parse_probe_response(
		(int) wp_remote_retrieve_response_code( $repo ),
		$rel_status,
		$rel_body,
		\PedimentChild\ThemeUpdater::assetPattern( get_stylesheet() )
	);

	if ( $result['ok'] ) {
		wp_send_json_success( $result );
	}
	wp_send_json_error( $result );
}

add_action( 'admin_enqueue_scripts', 'pediment_child_updates_enqueue' );
/**
 * Load the Test-connection script only on the settings page.
 *
 * @param string $hook Current admin page hook suffix.
 */
function pediment_child_updates_enqueue( string $hook ): void {
	if ( 'settings_page_pediment-theme' !== $hook && 'settings_page_pediment-child-updates' !== $hook ) {
		return;
	}
	wp_enqueue_script(
		'pediment-child-update-token-test',
		get_stylesheet_directory_uri() . '/assets/js/update-token-test.js',
		array(),
		PEDIMENT_CHILD_VERSION,
		true
	);
	wp_localize_script(
		'pediment-child-update-token-test',
		'pedimentChildUpdateTest',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pediment_child_test_update_token' ),
			'testing' => __( 'Testing…', 'pediment-child' ),
			'label'   => __( 'Test connection', 'pediment-child' ),
		)
	);
}
