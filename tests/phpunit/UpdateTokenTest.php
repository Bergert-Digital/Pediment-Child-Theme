<?php

use PedimentChild\UpdateToken;

// functions.php doesn't require inc/UpdateToken.php until Task 3 wires it
// into ThemeUpdater; guard-load it directly so this suite is independent.
require_once dirname( __DIR__, 2 ) . '/inc/UpdateToken.php';

class UpdateTokenTest extends WP_UnitTestCase {
	/** A deterministic 32-byte key for crypto round-trip tests. */
	private function testKey(): string {
		return str_repeat( 'k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	public function test_encrypt_decrypt_round_trip() {
		$plain  = 'github_pat_11ABCDEF_secretvalue';
		$stored = UpdateToken::encrypt( $plain, $this->testKey() );
		$this->assertNotSame( $plain, $stored, 'Ciphertext must not equal plaintext.' );
		$this->assertSame( $plain, UpdateToken::decrypt( $stored, $this->testKey() ) );
	}

	public function test_encrypt_uses_fresh_nonce_each_call() {
		$plain = 'same-token';
		$this->assertNotSame(
			UpdateToken::encrypt( $plain, $this->testKey() ),
			UpdateToken::encrypt( $plain, $this->testKey() ),
			'Random nonce must make two ciphertexts of the same plaintext differ.'
		);
	}

	public function test_decrypt_wrong_key_returns_empty() {
		$stored = UpdateToken::encrypt( 'secret', $this->testKey() );
		$other  = str_repeat( 'x', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		$this->assertSame( '', UpdateToken::decrypt( $stored, $other ) );
	}

	public function test_decrypt_garbage_returns_empty() {
		$this->assertSame( '', UpdateToken::decrypt( 'not-base64-or-too-short', $this->testKey() ) );
	}

	public function test_key_material_prefers_override() {
		$this->assertSame( 'override', UpdateToken::keyMaterial( 'override', 'a', 'b' ) );
		$this->assertSame( 'ab', UpdateToken::keyMaterial( '', 'a', 'b' ) );
	}

	public function test_derive_key_is_deterministic_and_correct_length() {
		$k1 = UpdateToken::deriveKey( 'material' );
		$k2 = UpdateToken::deriveKey( 'material' );
		$this->assertSame( $k1, $k2 );
		$this->assertSame( SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen( $k1 ) );
	}

	public function test_resolve_from_precedence_order() {
		$this->assertSame( 'constant', UpdateToken::resolveFrom( 'C', 'E', 'O' )['source'] );
		$this->assertSame( 'C', UpdateToken::resolveFrom( 'C', 'E', 'O' )['token'] );
		$this->assertSame( 'env', UpdateToken::resolveFrom( null, 'E', 'O' )['source'] );
		$this->assertSame( 'env', UpdateToken::resolveFrom( '', 'E', 'O' )['source'] );
		$this->assertSame( 'option', UpdateToken::resolveFrom( null, null, 'O' )['source'] );
		$this->assertSame( 'option', UpdateToken::resolveFrom( '', '', 'O' )['source'] );
		$none = UpdateToken::resolveFrom( null, null, '' );
		$this->assertSame( 'none', $none['source'] );
		$this->assertSame( '', $none['token'] );
	}

	public function test_store_and_stored_token_round_trip() {
		$this->assertTrue( UpdateToken::store( 'github_pat_stored' ) );
		$this->assertSame( 'github_pat_stored', UpdateToken::storedToken() );
	}

	public function test_store_rejects_empty() {
		$this->assertFalse( UpdateToken::store( '   ' ) );
	}

	public function test_stored_token_empty_when_option_absent() {
		delete_option( UpdateToken::OPTION );
		$this->assertSame( '', UpdateToken::storedToken() );
	}

	public function test_remove_clears_option() {
		UpdateToken::store( 'to-be-removed' );
		UpdateToken::remove();
		$this->assertSame( '', UpdateToken::storedToken() );
		$this->assertFalse( UpdateToken::isConfigured() );
	}

	public function test_resolve_env_beats_option() {
		UpdateToken::store( 'from-option' );
		putenv( UpdateToken::CONSTANT . '=from-env' );
		$resolved = UpdateToken::resolve();
		putenv( UpdateToken::CONSTANT ); // unset so later tests are clean.
		$this->assertSame( 'env', $resolved['source'] );
		$this->assertSame( 'from-env', $resolved['token'] );
	}

	public function test_resolve_falls_back_to_option() {
		putenv( UpdateToken::CONSTANT ); // ensure env unset.
		UpdateToken::store( 'only-option' );
		$resolved = UpdateToken::resolve();
		$this->assertSame( 'option', $resolved['source'] );
		$this->assertSame( 'only-option', $resolved['token'] );
		$this->assertTrue( UpdateToken::isConfigured() );
	}
}
