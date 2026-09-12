<?php
/**
 * Encrypt and decrypt stored application passwords.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Credential vault using WordPress salts.
 */
class LAUNCHDEK_Credential_Vault {

	/**
	 * Encrypt a plaintext application password.
	 *
	 * @param string $plaintext Raw app password.
	 * @return string Base64-encoded ciphertext.
	 */
	public static function encrypt( $plaintext ) {
		$settings = LAUNCHDEK_Settings::get();

		if ( empty( $settings['encrypt_credentials'] ) ) {
			return base64_encode( (string) $plaintext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( (string) $plaintext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		$key    = self::get_encryption_key();
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( (string) $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $cipher ) {
			return '';
		}

		return base64_encode( $iv . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt stored ciphertext.
	 *
	 * @param string $encrypted Stored value.
	 * @return string Plaintext or empty string on failure.
	 */
	public static function decrypt( $encrypted ) {
		if ( '' === (string) $encrypted ) {
			return '';
		}

		$settings = LAUNCHDEK_Settings::get();
		$raw      = base64_decode( (string) $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $raw ) {
			return '';
		}

		if ( empty( $settings['encrypt_credentials'] ) || ! function_exists( 'openssl_decrypt' ) ) {
			return (string) $raw;
		}

		if ( strlen( $raw ) < 17 ) {
			return (string) $raw;
		}

		$iv     = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );
		$key    = self::get_encryption_key();
		$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		return false === $plain ? '' : (string) $plain;
	}

	/**
	 * Derive encryption key from WordPress salts.
	 *
	 * @return string 32-byte key.
	 */
	protected static function get_encryption_key() {
		$salt = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'launchdek' ) . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'vault' );

		return hash( 'sha256', $salt, true );
	}
}
