<?php
/**
 * Passkey (WebAuthn) checks: reads the browser's CBOR data, turns public keys into PEM, and verifies sign-ins.
 *
 * Only what this plugin needs is built: "none" attestation, ES256 and RS256 keys, user verification required.
 *
 * @package SixAndFourLive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SF_WebAuthn {

	/* ------------------------------------------------------------------ */
	/* Encoding helpers                                                    */
	/* ------------------------------------------------------------------ */

	public static function b64u_encode( $bin ) {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	public static function b64u_decode( $str ) {
		$str = strtr( (string) $str, '-_', '+/' );
		$pad = strlen( $str ) % 4;
		if ( $pad ) {
			$str .= str_repeat( '=', 4 - $pad );
		}
		$out = base64_decode( $str, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		return false === $out ? '' : $out;
	}

	/* ------------------------------------------------------------------ */
	/* CBOR                                                                */
	/* ------------------------------------------------------------------ */

	/** Decode one CBOR item. $offset moves past it. Throws on bad or oversized input. */
	public static function cbor_decode( $s, &$offset = 0, $depth = 0 ) {
		if ( $depth > 8 || $offset >= strlen( $s ) ) {
			throw new RuntimeException( 'Bad CBOR data' );
		}
		$ib    = ord( $s[ $offset++ ] );
		$major = $ib >> 5;
		$info  = $ib & 31;
		if ( 7 === $major ) {
			if ( 20 === $info ) {
				return false;
			}
			if ( 21 === $info ) {
				return true;
			}
			if ( 22 === $info || 23 === $info ) {
				return null;
			}
			throw new RuntimeException( 'Unsupported CBOR value' );
		}
		$len = self::cbor_len( $s, $offset, $info );
		switch ( $major ) {
			case 0:
				return $len;
			case 1:
				return -1 - $len;
			case 2:
			case 3:
				if ( $len > 65536 || $offset + $len > strlen( $s ) ) {
					throw new RuntimeException( 'Bad CBOR length' );
				}
				$v       = substr( $s, $offset, $len );
				$offset += $len;
				return $v;
			case 4:
				if ( $len > 64 ) {
					throw new RuntimeException( 'CBOR array too long' );
				}
				$a = array();
				for ( $i = 0; $i < $len; $i++ ) {
					$a[] = self::cbor_decode( $s, $offset, $depth + 1 );
				}
				return $a;
			case 5:
				if ( $len > 64 ) {
					throw new RuntimeException( 'CBOR map too long' );
				}
				$m = array();
				for ( $i = 0; $i < $len; $i++ ) {
					$k       = self::cbor_decode( $s, $offset, $depth + 1 );
					$m[ $k ] = self::cbor_decode( $s, $offset, $depth + 1 );
				}
				return $m;
		}
		throw new RuntimeException( 'Unsupported CBOR type' );
	}

	private static function cbor_len( $s, &$o, $info ) {
		if ( $info < 24 ) {
			return $info;
		}
		$size = array( 24 => 1, 25 => 2, 26 => 4, 27 => 8 );
		if ( ! isset( $size[ $info ] ) || $o + $size[ $info ] > strlen( $s ) ) {
			throw new RuntimeException( 'Bad CBOR length' );
		}
		$raw = substr( $s, $o, $size[ $info ] );
		$o  += $size[ $info ];
		switch ( $info ) {
			case 24:
				return ord( $raw );
			case 25:
				return unpack( 'n', $raw )[1];
			case 26:
				return unpack( 'N', $raw )[1];
			default:
				return unpack( 'J', $raw )[1];
		}
	}

	/* ------------------------------------------------------------------ */
	/* Public key to PEM                                                   */
	/* ------------------------------------------------------------------ */

	private static function der( $tag, $content ) {
		$n = strlen( $content );
		if ( $n < 128 ) {
			$len = chr( $n );
		} else {
			$b   = ltrim( pack( 'N', $n ), "\0" );
			$len = chr( 0x80 | strlen( $b ) ) . $b;
		}
		return chr( $tag ) . $len . $content;
	}

	private static function der_int( $bin ) {
		$bin = ltrim( $bin, "\0" );
		if ( '' === $bin || ord( $bin[0] ) & 0x80 ) {
			$bin = "\0" . $bin;
		}
		return self::der( 0x02, $bin );
	}

	private static function pem( $spki ) {
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $spki ), 64, "\n" ) . "-----END PUBLIC KEY-----\n"; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/** Turn a COSE key (a decoded CBOR map) into [ pem, alg ]. */
	public static function cose_to_pem( array $k ) {
		$kty = isset( $k[1] ) ? $k[1] : null;
		$alg = isset( $k[3] ) ? $k[3] : null;
		if ( 2 === $kty && -7 === $alg && isset( $k[-1], $k[-2], $k[-3] ) && 1 === $k[-1] && 32 === strlen( $k[-2] ) && 32 === strlen( $k[-3] ) ) {
			$oid  = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01" . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
			$spki = self::der( 0x30, self::der( 0x30, $oid ) . self::der( 0x03, "\0\x04" . $k[-2] . $k[-3] ) );
			return array( self::pem( $spki ), -7 );
		}
		if ( 3 === $kty && -257 === $alg && isset( $k[-1], $k[-2] ) && strlen( $k[-1] ) >= 128 ) {
			$oid  = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
			$rsa  = self::der( 0x30, self::der_int( $k[-1] ) . self::der_int( $k[-2] ) );
			$spki = self::der( 0x30, self::der( 0x30, $oid ) . self::der( 0x03, "\0" . $rsa ) );
			return array( self::pem( $spki ), -257 );
		}
		throw new RuntimeException( 'This passkey uses a key type that is not supported' );
	}

	/* ------------------------------------------------------------------ */
	/* Verification                                                        */
	/* ------------------------------------------------------------------ */

	private static function client_data( $json_b64u, $type, $challenge, $origin ) {
		$raw = self::b64u_decode( $json_b64u );
		$cd  = json_decode( $raw, true );
		if ( ! is_array( $cd ) || ! isset( $cd['type'], $cd['challenge'], $cd['origin'] ) ) {
			throw new RuntimeException( 'The browser sent unreadable data' );
		}
		if ( $cd['type'] !== $type ) {
			throw new RuntimeException( 'Wrong request type' );
		}
		if ( ! hash_equals( $challenge, (string) $cd['challenge'] ) ) {
			throw new RuntimeException( 'The sign-in request expired or does not match. Try again.' );
		}
		if ( ! hash_equals( $origin, (string) $cd['origin'] ) ) {
			throw new RuntimeException( 'This passkey belongs to a different web address' );
		}
		return $raw;
	}

	/**
	 * Check a new passkey.
	 *
	 * @return array [ credential_id (b64u), pem, alg, sign_count ]
	 */
	public static function verify_registration( $client_data_b64u, $attestation_b64u, $challenge, $origin, $rp_id ) {
		self::client_data( $client_data_b64u, 'webauthn.create', $challenge, $origin );
		$att = self::cbor_decode( self::b64u_decode( $attestation_b64u ) );
		if ( ! is_array( $att ) || empty( $att['authData'] ) || ! isset( $att['fmt'] ) ) {
			throw new RuntimeException( 'The passkey data is incomplete' );
		}
		$ad = $att['authData'];
		if ( strlen( $ad ) < 55 ) {
			throw new RuntimeException( 'The passkey data is too short' );
		}
		if ( ! hash_equals( hash( 'sha256', $rp_id, true ), substr( $ad, 0, 32 ) ) ) {
			throw new RuntimeException( 'This passkey was made for a different site' );
		}
		$flags = ord( $ad[32] );
		if ( ! ( $flags & 0x01 ) || ! ( $flags & 0x04 ) ) {
			throw new RuntimeException( 'The passkey must be unlocked with your fingerprint, face or screen lock' );
		}
		if ( ! ( $flags & 0x40 ) ) {
			throw new RuntimeException( 'No passkey was included' );
		}
		$count = unpack( 'N', substr( $ad, 33, 4 ) )[1];
		$off   = 37 + 16; // After the AAGUID.
		$idlen = unpack( 'n', substr( $ad, $off, 2 ) )[1];
		$off  += 2;
		if ( $idlen < 16 || $idlen > 1023 || $off + $idlen > strlen( $ad ) ) {
			throw new RuntimeException( 'The passkey id is not valid' );
		}
		$cred_id = substr( $ad, $off, $idlen );
		$off    += $idlen;
		$cose    = self::cbor_decode( $ad, $off );
		if ( ! is_array( $cose ) ) {
			throw new RuntimeException( 'The passkey key is not valid' );
		}
		list( $pem, $alg ) = self::cose_to_pem( $cose );
		return array( self::b64u_encode( $cred_id ), $pem, $alg, $count );
	}

	/**
	 * Check a sign-in. Returns the new sign count.
	 *
	 * @param array $cred Stored credential: pem, alg, count.
	 */
	public static function verify_assertion( $client_data_b64u, $auth_data_b64u, $sig_b64u, array $cred, $challenge, $origin, $rp_id ) {
		$cd_raw = self::client_data( $client_data_b64u, 'webauthn.get', $challenge, $origin );
		$ad     = self::b64u_decode( $auth_data_b64u );
		$sig    = self::b64u_decode( $sig_b64u );
		if ( strlen( $ad ) < 37 || '' === $sig ) {
			throw new RuntimeException( 'The sign-in data is incomplete' );
		}
		if ( ! hash_equals( hash( 'sha256', $rp_id, true ), substr( $ad, 0, 32 ) ) ) {
			throw new RuntimeException( 'This passkey was made for a different site' );
		}
		$flags = ord( $ad[32] );
		if ( ! ( $flags & 0x01 ) || ! ( $flags & 0x04 ) ) {
			throw new RuntimeException( 'The passkey must be unlocked with your fingerprint, face or screen lock' );
		}
		$count = unpack( 'N', substr( $ad, 33, 4 ) )[1];
		if ( ( $count > 0 || (int) $cred['count'] > 0 ) && $count <= (int) $cred['count'] ) {
			throw new RuntimeException( 'This passkey looks copied, so the sign-in was refused' );
		}
		$algo = -257 === (int) $cred['alg'] || -7 === (int) $cred['alg'] ? OPENSSL_ALGO_SHA256 : 0;
		if ( ! $algo ) {
			throw new RuntimeException( 'Unsupported passkey type' );
		}
		$ok = openssl_verify( $ad . hash( 'sha256', $cd_raw, true ), $sig, $cred['pem'], $algo );
		if ( 1 !== $ok ) {
			throw new RuntimeException( 'The passkey signature did not match' );
		}
		return $count;
	}
}
