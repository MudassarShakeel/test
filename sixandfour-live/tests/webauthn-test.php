<?php
/**
 * Unit tests for the passkey library with keys made on the fly. No WordPress needed.
 * Run: php tests/webauthn-test.php
 */
define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/class-sf-webauthn.php';

$fail = 0;
function ok( $c, $l ) {
	global $fail;
	echo ( $c ? '  PASS  ' : '  FAIL  ' ) . $l . "\n";
	if ( ! $c ) {
		$fail++;
	}
}
function throws( $fn, $label ) {
	try {
		$fn();
		ok( false, $label . ' (it did not refuse)' );
	} catch ( RuntimeException $e ) {
		ok( true, $label . ': ' . $e->getMessage() );
	}
}
// A small CBOR encoder, only for building test data.
function cb( $v ) {
	if ( is_int( $v ) ) {
		return $v >= 0 ? cbh( 0, $v ) : cbh( 1, -1 - $v );
	}
	if ( is_string( $v ) ) {
		return ( preg_match( '//u', $v ) && ! preg_match( '/[\x00-\x08\x0e-\x1f\x80-\xff]/', $v ) ) ? cbh( 3, strlen( $v ) ) . $v : cbh( 2, strlen( $v ) ) . $v;
	}
	if ( is_array( $v ) ) {
		$o = cbh( 5, count( $v ) );
		foreach ( $v as $k => $x ) {
			$o .= cb( $k ) . cb( $x );
		}
		return $o;
	}
	throw new Exception( 'cb' );
}
function cbh( $major, $n ) {
	if ( $n < 24 ) {
		return chr( ( $major << 5 ) | $n );
	}
	if ( $n < 256 ) {
		return chr( ( $major << 5 ) | 24 ) . chr( $n );
	}
	if ( $n < 65536 ) {
		return chr( ( $major << 5 ) | 25 ) . pack( 'n', $n );
	}
	return chr( ( $major << 5 ) | 26 ) . pack( 'N', $n );
}
function b64u( $b ) {
	return SF_WebAuthn::b64u_encode( $b );
}

$origin = 'https://c.mudassar.work';
$rp     = 'c.mudassar.work';

echo "1. CBOR\n";
$o = 0;
$d = SF_WebAuthn::cbor_decode( cb( array( 'fmt' => 'none', 'n' => -7, 'big' => 70000, 'b' => "\xff\x00\x01" ) ), $o );
ok( 'none' === $d['fmt'] && -7 === $d['n'] && 70000 === $d['big'] && "\xff\x00\x01" === $d['b'], 'maps, negative numbers, big numbers and bytes decode' );
throws( function () {
	SF_WebAuthn::cbor_decode( "\xa1\x63fm" );
}, 'a cut-off CBOR string is refused' );
throws( function () {
	SF_WebAuthn::cbor_decode( str_repeat( "\x81", 20 ) . "\x01" );
}, 'deeply nested CBOR is refused' );

foreach ( array( 'ES256' => array( OPENSSL_KEYTYPE_EC, -7 ), 'RS256' => array( OPENSSL_KEYTYPE_RSA, -257 ) ) as $name => $cfg ) {
	echo "2. $name registration and sign-in\n";
	$key = $cfg[0] === OPENSSL_KEYTYPE_EC
		? openssl_pkey_new( array( 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ) )
		: openssl_pkey_new( array( 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048 ) );
	$det = openssl_pkey_get_details( $key );
	if ( OPENSSL_KEYTYPE_EC === $cfg[0] ) {
		$cose = array( 1 => 2, 3 => -7, -1 => 1, -2 => $det['ec']['x'], -3 => $det['ec']['y'] );
	} else {
		$cose = array( 1 => 3, 3 => -257, -1 => $det['rsa']['n'], -2 => $det['rsa']['e'] );
	}
	$cred_id   = random_bytes( 32 );
	$challenge = b64u( random_bytes( 32 ) );
	$cdj       = json_encode( array( 'type' => 'webauthn.create', 'challenge' => $challenge, 'origin' => $origin ) );
	$authdata  = hash( 'sha256', $rp, true ) . chr( 0x45 ) . pack( 'N', 0 ) . str_repeat( "\0", 16 ) . pack( 'n', 32 ) . $cred_id . cb( $cose );
	$att       = cb( array( 'fmt' => 'none', 'attStmt' => array(), 'authData' => $authdata ) );
	$att       = str_replace( cb( array() ), "\xa0", $att );
	list( $id, $pem, $alg, $cnt ) = SF_WebAuthn::verify_registration( b64u( $cdj ), b64u( $att ), $challenge, $origin, $rp );
	ok( b64u( $cred_id ) === $id && $alg === $cfg[1] && 0 === $cnt, 'a valid passkey is accepted' );
	ok( false !== openssl_pkey_get_public( $pem ), 'the key became a valid PEM' );
	throws( function () use ( $cdj, $att, $origin, $rp ) {
		SF_WebAuthn::verify_registration( b64u( $cdj ), b64u( $att ), 'wrongchallenge', $origin, $rp );
	}, 'a wrong challenge is refused' );
	throws( function () use ( $cdj, $att, $challenge, $rp ) {
		SF_WebAuthn::verify_registration( b64u( $cdj ), b64u( $att ), $challenge, 'https://evil.example', $rp );
	}, 'a wrong web address is refused' );
	throws( function () use ( $cdj, $att, $challenge, $origin ) {
		SF_WebAuthn::verify_registration( b64u( $cdj ), b64u( $att ), $challenge, $origin, 'evil.example' );
	}, 'a passkey made for another site is refused' );
	$noUV = hash( 'sha256', $rp, true ) . chr( 0x41 ) . pack( 'N', 0 ) . str_repeat( "\0", 16 ) . pack( 'n', 32 ) . $cred_id . cb( $cose );
	throws( function () use ( $cdj, $challenge, $origin, $rp, $noUV ) {
		SF_WebAuthn::verify_registration( b64u( $cdj ), b64u( cb( array( 'fmt' => 'none', 'attStmt' => array(), 'authData' => $noUV ) ) ), $challenge, $origin, $rp );
	}, 'a passkey without fingerprint or screen lock is refused' );

	// Sign in.
	$cred   = array( 'pem' => $pem, 'alg' => $alg, 'count' => 0 );
	$ch2    = b64u( random_bytes( 32 ) );
	$cdj2   = json_encode( array( 'type' => 'webauthn.get', 'challenge' => $ch2, 'origin' => $origin ) );
	$make   = function ( $count, $flags = 0x05 ) use ( $rp, $cdj2, $key ) {
		$ad = hash( 'sha256', $rp, true ) . chr( $flags ) . pack( 'N', $count );
		openssl_sign( $ad . hash( 'sha256', $cdj2, true ), $sig, $key, OPENSSL_ALGO_SHA256 );
		return array( $ad, $sig );
	};
	list( $ad, $sig ) = $make( 1 );
	$n = SF_WebAuthn::verify_assertion( b64u( $cdj2 ), b64u( $ad ), b64u( $sig ), $cred, $ch2, $origin, $rp );
	ok( 1 === $n, 'a valid sign-in is accepted and returns the new counter' );
	throws( function () use ( $cdj2, $ad, $sig, $cred, $ch2, $origin, $rp ) {
		$bad = $sig;
		$bad[ strlen( $bad ) - 3 ] = chr( ord( $bad[ strlen( $bad ) - 3 ] ) ^ 1 );
		SF_WebAuthn::verify_assertion( b64u( $cdj2 ), b64u( $ad ), b64u( $bad ), $cred, $ch2, $origin, $rp );
	}, 'a changed signature is refused' );
	throws( function () use ( $cdj2, $ad, $sig, $cred, $origin, $rp ) {
		SF_WebAuthn::verify_assertion( b64u( $cdj2 ), b64u( $ad ), b64u( $sig ), $cred, 'other', $origin, $rp );
	}, 'a replayed sign-in with another challenge is refused' );
	throws( function () use ( $cdj2, $ad, $sig, $pem, $alg, $ch2, $origin, $rp ) {
		SF_WebAuthn::verify_assertion( b64u( $cdj2 ), b64u( $ad ), b64u( $sig ), array( 'pem' => $pem, 'alg' => $alg, 'count' => 5 ), $ch2, $origin, $rp );
	}, 'a counter that did not go up is refused as a copied passkey' );
	list( $ad0, $sig0 ) = $make( 2, 0x01 );
	throws( function () use ( $cdj2, $ad0, $sig0, $cred, $ch2, $origin, $rp ) {
		SF_WebAuthn::verify_assertion( b64u( $cdj2 ), b64u( $ad0 ), b64u( $sig0 ), $cred, $ch2, $origin, $rp );
	}, 'a sign-in without user verification is refused' );
	$other = openssl_pkey_new( array( 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ) );
	$ad3   = hash( 'sha256', $rp, true ) . chr( 5 ) . pack( 'N', 3 );
	openssl_sign( $ad3 . hash( 'sha256', $cdj2, true ), $sig3, $other, OPENSSL_ALGO_SHA256 );
	throws( function () use ( $cdj2, $ad3, $sig3, $cred, $ch2, $origin, $rp ) {
		SF_WebAuthn::verify_assertion( b64u( $cdj2 ), b64u( $ad3 ), b64u( $sig3 ), $cred, $ch2, $origin, $rp );
	}, 'a signature from a different key is refused' );
}
echo "\n", $fail ? "$fail FAILED\n" : "All checks passed\n";
exit( $fail ? 1 : 0 );
