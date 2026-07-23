<?php
/**
 * GitHub-release auto-updates for the Pediment child theme.
 *
 * Points Plugin Update Checker at the public GitHub repo's releases so theme
 * updates arrive through wp-admin's normal one-click flow (Dashboard → Updates
 * / Appearance → Themes) instead of manual zip uploads.
 *
 * @package PedimentChild
 */

declare(strict_types=1);

namespace PedimentChild;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ThemeUpdater {
	/** Public repo whose GitHub Releases drive theme updates. */
	private const REPO_URL = 'https://github.com/Bergert-Digital/Pediment-Child-Theme/';

	/**
	 * Wire the update checker to this repo's GitHub releases.
	 */
	public static function register(): void {
		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		// Skip update checks in local/dev environments (wp-env, CI). There is no
		// point hitting the GitHub API on every admin load there, and the
		// synchronous check slows the block editor enough to flake e2e tests.
		// Real client sites default to the 'production' environment type.
		if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
			return;
		}

		// get_stylesheet(): the active theme's folder basename. On a fresh install
		// from our zip it equals the client's slug (the staged dir became this
		// folder), so slug === folder === "<slug>.zip" and WP matches the update
		// to the installed theme with no per-client edits here.
		$slug = get_stylesheet();

		$checker = PucFactory::buildUpdateChecker(
			self::REPO_URL,
			get_stylesheet_directory() . '/style.css',
			$slug
		);

		// Fallback branch for reading the version header if a release is ever absent.
		if ( method_exists( $checker, 'setBranch' ) ) {
			$checker->setBranch( 'main' );
		}

		// Install the built release asset (<slug>.zip) rather than GitHub's
		// auto-generated "Source code" zip, which has the wrong folder name and
		// ships no vendor/ autoloader.
		$api = $checker->getVcsApi();
		if ( method_exists( $api, 'enableReleaseAssets' ) ) {
			$api->enableReleaseAssets( self::assetPattern( $slug ) );
		}

		// Authenticate for private repos. Precedence: constant → env → stored
		// option → none. Unset everywhere → no call → updates simply absent
		// (same as an unauthenticated public repo), never a fatal.
		$auth = UpdateToken::resolve();
		if ( '' !== $auth['token'] && method_exists( $checker, 'setAuthentication' ) ) {
			$checker->setAuthentication( $auth['token'] );
		}
	}

	/**
	 * Build the Plugin Update Checker release-asset regex for a theme slug.
	 *
	 * The released zip is named "<slug>.zip" (see build-release-zip.yml) and the
	 * installed folder — hence get_stylesheet() — equals that slug, so slug,
	 * folder, zip name, and this pattern all agree on a fresh install.
	 *
	 * Anchored at both ends so it matches "<slug>.zip" exactly and never a
	 * longer asset that merely ends in it (e.g. "not-<slug>.zip").
	 */
	public static function assetPattern( string $slug ): string {
		return '/^' . preg_quote( $slug, '/' ) . '\.zip$/';
	}

	/**
	 * The GitHub repository URL that drives updates.
	 *
	 * Exposed so the settings "Test connection" probe hits the same repo the
	 * updater installs from.
	 */
	public static function repoUrl(): string {
		return self::REPO_URL;
	}
}
