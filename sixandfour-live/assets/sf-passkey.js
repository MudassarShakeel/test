/* Passkey helpers used by the sign-in page, the mobile console and the Security screen. */
window.SFPasskey = ( function () {
	'use strict';

	function fromB64( s ) {
		s = s.replace( /-/g, '+' ).replace( /_/g, '/' );
		while ( s.length % 4 ) { s += '='; }
		var b = atob( s ), a = new Uint8Array( b.length );
		for ( var i = 0; i < b.length; i++ ) { a[ i ] = b.charCodeAt( i ); }
		return a.buffer;
	}

	function toB64( buf ) {
		if ( ! buf ) { return ''; }
		var a = new Uint8Array( buf ), s = '';
		for ( var i = 0; i < a.length; i++ ) { s += String.fromCharCode( a[ i ] ); }
		return btoa( s ).replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/, '' );
	}

	function supported() {
		return !! ( window.PublicKeyCredential && navigator.credentials && window.isSecureContext );
	}

	async function api( rest, path, body, nonce ) {
		var headers = { 'Content-Type': 'application/json' };
		if ( nonce ) { headers[ 'X-WP-Nonce' ] = nonce; }
		var r = await fetch( rest + path, { method: 'POST', credentials: 'same-origin', headers: headers, body: JSON.stringify( body || {} ) } );
		var d = await r.json().catch( function () { return {}; } );
		if ( ! r.ok ) { throw new Error( d.message || 'Something went wrong. Try again.' ); }
		return d;
	}

	function nice( e ) {
		if ( e && ( e.name === 'NotAllowedError' || e.name === 'AbortError' ) ) { return 'Cancelled or timed out. Try again.'; }
		if ( e && e.name === 'InvalidStateError' ) { return 'This device already has a passkey for your account.'; }
		return ( e && e.message ) ? e.message : 'Something went wrong. Try again.';
	}

	async function login( rest, redirect ) {
		var o = await api( rest, '/passkey/login/options', {} );
		var pub = o.publicKey;
		pub.challenge = fromB64( pub.challenge );
		var cred = await navigator.credentials.get( { publicKey: pub } );
		var r = cred.response;
		return api( rest, '/passkey/login/verify', {
			id: o.id,
			redirect_to: redirect || '',
			credential: {
				id: cred.id,
				response: { clientDataJSON: toB64( r.clientDataJSON ), authenticatorData: toB64( r.authenticatorData ), signature: toB64( r.signature ), userHandle: toB64( r.userHandle ) }
			}
		} );
	}

	async function register( rest, nonce, name ) {
		var o = await api( rest, '/passkey/register/options', {}, nonce );
		var pub = o.publicKey;
		pub.challenge = fromB64( pub.challenge );
		pub.user.id = fromB64( pub.user.id );
		pub.excludeCredentials = ( pub.excludeCredentials || [] ).map( function ( c ) { return { type: c.type, id: fromB64( c.id ) }; } );
		var cred = await navigator.credentials.create( { publicKey: pub } );
		var r = cred.response;
		return api( rest, '/passkey/register/verify', { name: name || '', response: { clientDataJSON: toB64( r.clientDataJSON ), attestationObject: toB64( r.attestationObject ) } }, nonce );
	}

	return { supported: supported, api: api, login: login, register: register, nice: nice };
}() );
