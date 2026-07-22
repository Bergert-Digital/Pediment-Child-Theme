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
}
