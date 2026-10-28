/* Passkey and email-link buttons on the sign-in page. */
( function () {
	'use strict';
	var cfg = window.SFLogin || {};
	var box = document.getElementById( 'sf-pl' );
	if ( ! box ) { return; }
	var msg = document.getElementById( 'sf-pl-msg' );
	var pk = document.getElementById( 'sf-pk-login' );
	function say( t, bad ) { msg.textContent = t; msg.className = 'sf-pl-msg' + ( bad ? ' bad' : '' ); }

	var can = window.SFPasskey && SFPasskey.supported();
	if ( ! can ) { pk.hidden = true; }
	if ( can || cfg.magic ) { box.hidden = false; }

	pk.addEventListener( 'click', async function () {
		pk.disabled = true;
		say( 'Waiting for your fingerprint, face or screen lock' );
		try {
			var res = await SFPasskey.login( cfg.rest, cfg.redirect );
			say( 'Signed in. Opening your site' );
			window.location.href = res.redirect;
		} catch ( e ) {
			say( SFPasskey.nice( e ), true );
			pk.disabled = false;
		}
	} );

	var open = document.getElementById( 'sf-ml-open' );
	var form = document.getElementById( 'sf-ml' );
	var send = document.getElementById( 'sf-ml-send' );
	if ( open && form ) {
		open.addEventListener( 'click', function () {
			form.hidden = false;
			open.hidden = true;
			document.getElementById( 'sf-ml-login' ).focus();
		} );
		send.addEventListener( 'click', async function () {
			var v = document.getElementById( 'sf-ml-login' ).value.trim();
			if ( ! v ) { say( 'Type your user name or email first.', true ); return; }
			send.disabled = true;
			try {
				var r = await SFPasskey.api( cfg.rest, '/magic/request', { login: v } );
				say( r.message );
			} catch ( e ) {
				say( e.message, true );
			}
			send.disabled = false;
		} );
	}
}() );
