<?php
/**
 * Encrypts the Facebook token and the cricket API key before they are saved, and scrubs them from messages.
 *
 * The key comes from your wp-config.php salts, so a copy of the database alone cannot read the secrets.
 * If you change the salts, the saved secrets cannot be read and you will be asked to enter them again.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_Secrets {

	const PREFIX = 'sf1:';

	private static function key() {
		return hash( 'sha256', wp_salt( 'auth' ) . '|sixandfour-live', true );
	}

	public static function available() {
		return function_exists( 'openssl_encrypt' ) && in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true );
	}

	public static function is_encrypted( $v ) {
		return is_string( $v ) && 0 === strpos( $v, self::PREFIX );
	}

	public static function encrypt( $plain ) {
		$plain = (string) $plain;
		if ( '' === $plain || self::is_encrypted( $plain ) || ! self::available() ) {
			return $plain;
		}
		$iv  = random_bytes( 12 );
		$tag = '';
		$c   = openssl_encrypt( $plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $c ) {
			return $plain;
		}
		return self::PREFIX . base64_encode( $iv . $tag . $c ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/** Returns the secret, or an empty string if it cannot be read. Plain text from older versions passes through. */
	public static function decrypt( $v ) {
		$v = (string) $v;
		if ( ! self::is_encrypted( $v ) ) {
			return $v;
		}
		if ( ! self::available() ) {
			return '';
		}
		$raw = base64_decode( substr( $v, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( false === $raw || strlen( $raw ) < 29 ) {
			return '';
		}
		$p = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
		return false === $p ? '' : $p;
	}

	/** Remove secrets from text that might be logged or shown. */
	public static function scrub( $text ) {
		$text = (string) $text;
		$o    = get_option( SF_Options::KEY, array() );
		foreach ( array( 'fb_token', 'api_key' ) as $k ) {
			$plain = isset( $o[ $k ] ) ? self::decrypt( $o[ $k ] ) : '';
			if ( strlen( $plain ) >= 8 ) {
				$text = str_replace( $plain, '[hidden]', $text );
			}
		}
		$text = preg_replace( '/(access_token|apikey)=[^&\s"\']+/i', '$1=[hidden]', $text );
		$text = preg_replace( '/\bEAA[A-Za-z0-9]{20,}/', '[hidden]', $text );
		return $text;
	}
}
