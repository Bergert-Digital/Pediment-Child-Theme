<?php
/**
 * Storage + encryption + resolution for the theme update token.
 *
 * The token (a GitHub fine-grained PAT) authenticates Plugin Update Checker
 * against a private releases repo. It is stored encrypted at rest in wp_options
 * and only decrypted in memory at update-check time.
 *
 * @package PedimentChild
 */

declare(strict_types=1);

namespace PedimentChild;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UpdateToken {
	/** wp_options key holding the encrypted token. */
	public const OPTION = 'pediment_child_update_token';

	/** wp-config constant / env var name for a plaintext token override. */
	public const CONSTANT = 'PEDIMENT_CHILD_UPDATE_TOKEN';

	/** wp-config constant overriding the encryption key material. */
	public const SECRET_CONSTANT = 'PEDIMENT_CHILD_UPDATE_SECRET';

	/**
	 * Choose encryption key material: an explicit override, else the WP salts.
	 */
	public static function keyMaterial( string $override, string $salt1, string $salt2 ): string {
		return '' !== $override ? $override : $salt1 . $salt2;
	}

	/**
	 * Derive a 32-byte secretbox key from arbitrary key material.
	 */
	public static function deriveKey( string $material ): string {
		return sodium_crypto_generichash( $material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/**
	 * Encrypt a plaintext token for storage: base64( nonce . ciphertext ).
	 */
	public static function encrypt( string $plain, ?string $key = null ): string {
		$key   = $key ?? self::activeKey();
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$box   = sodium_crypto_secretbox( $plain, $nonce, $key );
		return base64_encode( $nonce . $box );
	}

	/**
	 * Decrypt a stored token. Returns '' on any failure (tamper, rotated salts).
	 */
	public static function decrypt( string $stored, ?string $key = null ): string {
		$key = $key ?? self::activeKey();
		$raw = base64_decode( $stored, true );
		$min = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;
		if ( false === $raw || strlen( $raw ) < $min ) {
			return '';
		}
		$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$box   = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain = sodium_crypto_secretbox_open( $box, $nonce, $key );
		return false === $plain ? '' : $plain;
	}

	/**
	 * Pure precedence resolver: constant → env → option → none.
	 *
	 * @return array{token:string,source:string}
	 */
	public static function resolveFrom( ?string $constant, ?string $env, string $optionToken ): array {
		if ( null !== $constant && '' !== $constant ) {
			return array( 'token' => $constant, 'source' => 'constant' );
		}
		if ( null !== $env && '' !== $env ) {
			return array( 'token' => $env, 'source' => 'env' );
		}
		if ( '' !== $optionToken ) {
			return array( 'token' => $optionToken, 'source' => 'option' );
		}
		return array( 'token' => '', 'source' => 'none' );
	}

	/**
	 * The stored token, decrypted, or '' when unset/undecryptable.
	 */
	public static function storedToken(): string {
		$stored = (string) get_option( self::OPTION, '' );
		return '' === $stored ? '' : self::decrypt( $stored );
	}

	/**
	 * Encrypt and persist a token. False if libsodium is unavailable or empty.
	 */
	public static function store( string $pat ): bool {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return false;
		}
		$pat = trim( $pat );
		if ( '' === $pat ) {
			return false;
		}
		return update_option( self::OPTION, self::encrypt( $pat ), false );
	}

	/**
	 * Delete the stored token.
	 */
	public static function remove(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Resolve the effective token from all sources, most trusted first.
	 *
	 * @return array{token:string,source:string}
	 */
	public static function resolve(): array {
		$constant = defined( self::CONSTANT ) ? (string) constant( self::CONSTANT ) : null;
		$env      = getenv( self::CONSTANT );
		$env      = false === $env ? null : (string) $env;
		return self::resolveFrom( $constant, $env, self::storedToken() );
	}

	/**
	 * Whether any source yields a usable token.
	 */
	public static function isConfigured(): bool {
		return '' !== self::resolve()['token'];
	}

	/**
	 * Resolve the live encryption key from wp-config secrets.
	 */
	private static function activeKey(): string {
		$override = defined( self::SECRET_CONSTANT ) ? (string) constant( self::SECRET_CONSTANT ) : '';
		$salt1    = defined( 'AUTH_KEY' ) ? (string) constant( 'AUTH_KEY' ) : '';
		$salt2    = defined( 'SECRET_KEY' ) ? (string) constant( 'SECRET_KEY' ) : '';
		return self::deriveKey( self::keyMaterial( $override, $salt1, $salt2 ) );
	}
}
