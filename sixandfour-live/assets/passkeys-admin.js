/* Add and remove passkeys on the Security screen. */
( function () {
	'use strict';
	var cfg = window.SFPK || {};
	var add = document.getElementById( 'sf-pk-add' );
	var msg = document.getElementById( 'sf-pk-msg' );
	function say( t, bad ) { if ( msg ) { msg.textContent = t; msg.className = 'sf-sub' + ( bad ? ' sf-err' : '' ); } }
	if ( add ) {
		if ( ! SFPasskey.supported() ) {
			add.disabled = true;
			say( 'This browser cannot make passkeys here. Passkeys need a secure (HTTPS) address and a recent browser.', true );
		}
		add.addEventListener( 'click', async function () {
			var name = ( document.getElementById( 'sf-pk-name' ) || {} ).value || '';
			add.disabled = true;
			say( 'Waiting for your fingerprint, face or screen lock' );
			try {
				await SFPasskey.register( cfg.rest, cfg.nonce, name );
				say( 'Passkey added. Reloading' );
				window.location.reload();
			} catch ( e ) {
				say( SFPasskey.nice( e ), true );
				add.disabled = false;
			}
		} );
	}
	document.addEventListener( 'click', async function ( ev ) {
		var b = ev.target.closest( '.sf-pk-del' );
		if ( ! b ) { return; }
		b.disabled = true;
		try {
			await SFPasskey.api( cfg.rest, '/passkey/delete', { id: b.dataset.id }, cfg.nonce );
			window.location.reload();
		} catch ( e ) {
			say( e.message, true );
			b.disabled = false;
		}
	} );
}() );
