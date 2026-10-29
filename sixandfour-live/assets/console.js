/* Cricket Sport mobile console. */
( function () {
	'use strict';
	var root = document.getElementById( 'sfc' );
	var C = window.SFC;
	if ( ! root || ! C ) { return; }
	var ICON = {"house": "<path d=\"M224,120v96a8,8,0,0,1-8,8H160a8,8,0,0,1-8-8V164a4,4,0,0,0-4-4H108a4,4,0,0,0-4,4v52a8,8,0,0,1-8,8H40a8,8,0,0,1-8-8V120a16,16,0,0,1,4.69-11.31l80-80a16,16,0,0,1,22.62,0l80,80A16,16,0,0,1,224,120Z\"/>", "tray": "<path d=\"M208,32H48A16,16,0,0,0,32,48V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V48A16,16,0,0,0,208,32Zm0,176H48V168H76.69L96,187.32A15.89,15.89,0,0,0,107.31,192h41.38A15.86,15.86,0,0,0,160,187.31L179.31,168H208v40Z\"/>", "plus-circle": "<path d=\"M128,24A104,104,0,1,0,232,128,104.13,104.13,0,0,0,128,24Zm40,112H136v32a8,8,0,0,1-16,0V136H88a8,8,0,0,1,0-16h32V88a8,8,0,0,1,16,0v32h32a8,8,0,0,1,0,16Z\"/>", "sliders-horizontal": "<path d=\"M32,80a8,8,0,0,1,8-8H77.17a28,28,0,0,1,53.66,0H216a8,8,0,0,1,0,16H130.83a28,28,0,0,1-53.66,0H40A8,8,0,0,1,32,80Zm184,88H194.83a28,28,0,0,0-53.66,0H40a8,8,0,0,0,0,16H141.17a28,28,0,0,0,53.66,0H216a8,8,0,0,0,0-16Z\"/>", "camera": "<path d=\"M208,56H180.28L166.65,35.56A8,8,0,0,0,160,32H96a8,8,0,0,0-6.65,3.56L75.71,56H48A24,24,0,0,0,24,80V192a24,24,0,0,0,24,24H208a24,24,0,0,0,24-24V80A24,24,0,0,0,208,56Zm-44,76a36,36,0,1,1-36-36A36,36,0,0,1,164,132Z\"/>", "arrow-clockwise": "<path d=\"M240,56v48a8,8,0,0,1-8,8H184a8,8,0,0,1-5.66-13.66l17-17-10.55-9.65-.25-.24a80,80,0,1,0-1.67,114.78,8,8,0,1,1,11,11.63A95.44,95.44,0,0,1,128,224h-1.32A96,96,0,1,1,195.75,60l10.93,10L226.34,50.3A8,8,0,0,1,240,56Z\"/>", "pause": "<path d=\"M216,48V208a16,16,0,0,1-16,16H160a16,16,0,0,1-16-16V48a16,16,0,0,1,16-16h40A16,16,0,0,1,216,48ZM96,32H56A16,16,0,0,0,40,48V208a16,16,0,0,0,16,16H96a16,16,0,0,0,16-16V48A16,16,0,0,0,96,32Z\"/>", "play": "<path d=\"M240,128a15.74,15.74,0,0,1-7.6,13.51L88.32,229.65a16,16,0,0,1-16.2.3A15.86,15.86,0,0,1,64,216.13V39.87a15.86,15.86,0,0,1,8.12-13.82,16,16,0,0,1,16.2.3L232.4,114.49A15.74,15.74,0,0,1,240,128Z\"/>", "fingerprint": "<path d=\"M126.42,24C70.73,24.85,25.21,70.09,24,125.81a103.53,103.53,0,0,0,13.52,53.54,4,4,0,0,0,7.1-.3,119.35,119.35,0,0,0,11.37-51A71.77,71.77,0,0,1,83,71.83a8,8,0,1,1,9.86,12.61A55.82,55.82,0,0,0,72,128.07a135.28,135.28,0,0,1-18.45,68.35,4,4,0,0,0,.61,4.85c2,2,4.09,4,6.25,5.82a4,4,0,0,0,6-1A151.18,151.18,0,0,0,85,158.49a8,8,0,1,1,15.68,3.19,167.33,167.33,0,0,1-21.07,53.64,4,4,0,0,0,1.6,5.63c2.47,1.25,5,2.41,7.57,3.47a4,4,0,0,0,5-1.61A183,183,0,0,0,120,128.28a8.16,8.16,0,0,1,7.44-8.21,8,8,0,0,1,8.56,8,198.94,198.94,0,0,1-25.21,97.16,4,4,0,0,0,2.95,5.92q4.55.63,9.21.86a4,4,0,0,0,3.67-2.1A214.88,214.88,0,0,0,152,128.8c.05-13.25-10.3-24.49-23.54-24.74A24,24,0,0,0,104,128a8.1,8.1,0,0,1-7.29,8,8,8,0,0,1-8.71-8,40,40,0,0,1,40.42-40c22,.23,39.68,19.17,39.57,41.16a231.37,231.37,0,0,1-20.52,94.57,4,4,0,0,0,4.62,5.51,103.49,103.49,0,0,0,10.26-3,4,4,0,0,0,2.35-2.22,243.76,243.76,0,0,0,11.48-34,8,8,0,1,1,15.5,4q-1.12,4.37-2.4,8.7a4,4,0,0,0,6.46,4.17A104,104,0,0,0,126.42,24ZM198,161.08a8,8,0,0,1-7.92,7,8.39,8.39,0,0,1-1-.06,8,8,0,0,1-6.95-8.93,252.57,252.57,0,0,0,1.92-31,56.08,56.08,0,0,0-56-56,56.78,56.78,0,0,0-7,.43,8,8,0,0,1-2-15.89,72.1,72.1,0,0,1,81,71.49A266.93,266.93,0,0,1,198,161.08Z\"/>", "envelope": "<path d=\"M224,48H32a8,8,0,0,0-8,8V192a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V56A8,8,0,0,0,224,48Zm-8,144H40V74.19l82.59,75.71a8,8,0,0,0,10.82,0L216,74.19V192Z\"/>", "sign-out": "<path d=\"M120,216a8,8,0,0,1-8,8H48a8,8,0,0,1-8-8V40a8,8,0,0,1,8-8h64a8,8,0,0,1,0,16H56V208h56A8,8,0,0,1,120,216Zm109.66-93.66-40-40A8,8,0,0,0,176,88v32H112a8,8,0,0,0,0,16h64v32a8,8,0,0,0,13.66,5.66l40-40A8,8,0,0,0,229.66,122.34Z\"/>", "arrow-square-out": "<path d=\"M192,136v72a16,16,0,0,1-16,16H48a16,16,0,0,1-16-16V80A16,16,0,0,1,48,64h72a8,8,0,0,1,0,16H48V208H176V136a8,8,0,0,1,16,0Zm32-96a8,8,0,0,0-8-8H152a8,8,0,0,0-5.66,13.66L172.69,72l-42.35,42.34a8,8,0,0,0,11.32,11.32L184,83.31l26.34,26.35A8,8,0,0,0,224,104Z\"/>"};
	var MARK = '<svg class="logo" viewBox="0 0 512 512" aria-hidden="true"><defs>  <linearGradient id="arcm" x1="40" y1="440" x2="450" y2="140" gradientUnits="userSpaceOnUse">    <stop offset="0" stop-color="#00A8C6"/><stop offset=".62" stop-color="#00E5FF"/><stop offset="1" stop-color="#00E5FF" stop-opacity=".0"/>  </linearGradient></defs><g transform="rotate(-30 256 256)">  <rect x="241" y="34" width="30" height="130" rx="12" fill="#0A1B44"/>  <rect x="241" y="82" width="30" height="20" fill="#00E5FF"/>  <path d="M218 172 L294 172 L294 416 L276 454 L236 454 L218 416 Z" fill="#0A1B44"/>  <rect x="249" y="198" width="14" height="196" rx="7" fill="#2B3A66"/></g><path d="M44 430 Q250 350 446 142" fill="none" stroke="url(#arcm)" stroke-width="30" stroke-linecap="round"/><path d="M62 424 Q252 350 436 150" fill="none" stroke="#FFFFFF" stroke-opacity=".9" stroke-width="7" stroke-linecap="round" style="mix-blend-mode:normal"/><circle cx="446" cy="142" r="23" fill="#00E5FF"/></svg>';
	function ic( n ) { return '<svg viewBox="0 0 256 256" aria-hidden="true" fill="currentColor">' + ( ICON[ n ] || '' ) + '</svg>'; }
	function esc( s ) { return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ]; } ); }

	var S = null;          // The latest state from the server.
	var tab = 'home';
	var busy = false;
	var picked = null;     // File chosen on the Post tab.
	var draft = { caption: '', q: false, wm: true, pos: 'br', when: 'now', at: '' };
	var timer = null;

	if ( C.isAdmin ) { document.documentElement.classList.add( 'sfc-lock' ); }

	async function api( method, path, body, isForm ) {
		var opt = { method: method, credentials: 'same-origin', headers: { 'X-WP-Nonce': C.nonce } };
		if ( body ) {
			if ( isForm ) { opt.body = body; } else { opt.headers[ 'Content-Type' ] = 'application/json'; opt.body = JSON.stringify( body ); }
		}
		var r;
		try { r = await fetch( C.rest + path, opt ); } catch ( e ) { throw new Error( 'No connection. Check your signal and try again.' ); }
		var d = await r.json().catch( function () { return {}; } );
		if ( ! r.ok ) {
			var err = new Error( d.message || 'Something went wrong.' );
			err.status = r.status;
			if ( r.status === 401 || r.status === 403 ) { err.message = 'Your session ended. Reload the page to sign in again.'; }
			throw err;
		}
		return d;
	}

	var toastEl, toastT;
	function toast( msg, bad ) {
		if ( ! toastEl ) { return; }
		toastEl.textContent = msg;
		toastEl.className = 'sfc-toast' + ( bad ? ' bad' : '' );
		toastEl.hidden = false;
		clearTimeout( toastT );
		toastT = setTimeout( function () { toastEl.hidden = true; }, 3200 );
	}

	async function act( btn, fn ) {
		if ( busy ) { return; }
		busy = true;
		if ( btn ) { btn.disabled = true; }
		try { await fn(); } catch ( e ) { toast( e.message, true ); }
		busy = false;
		if ( btn && document.body.contains( btn ) ) { btn.disabled = false; }
	}

	function apply( d ) {
		if ( d && d.state ) { S = d.state; draw(); }
		if ( d && d.message ) { toast( d.message, d.ok === false ); }
	}

	/* ---------------- guest ---------------- */
	function drawGuest() {
		var can = window.SFPasskey && SFPasskey.supported();
		root.innerHTML = '<div class="sfc-login"><div class="sfc-card">' + MARK + '<h1>Cricket Sport</h1><p class="sfc-sub" style="margin:0">Administrators only. Sign in without a password.</p>' +
			( can ? '<button class="sfc-btn primary" id="g-pk" type="button">' + ic( 'fingerprint' ) + 'Sign in with a passkey</button>' : '' ) +
			( C.magic ? '<button class="sfc-btn" id="g-ml-open" type="button">' + ic( 'envelope' ) + 'Email me a sign-in link</button><div id="g-ml" hidden><label class="l" for="g-login">Your user name or email</label><input class="sfc-field" id="g-login" autocomplete="username" autocapitalize="none"><div style="height:10px"></div><button class="sfc-btn primary" id="g-ml-send" type="button">Send the link</button></div>' : '' ) +
			'<p class="sfc-sub" id="g-msg" role="status" aria-live="polite" style="margin:0;min-height:1.4em"></p><a class="plain" href="' + esc( C.login ) + '">Use a password instead</a></div></div>';
		var say = function ( t, bad ) { var m = document.getElementById( 'g-msg' ); m.textContent = t; m.style.color = bad ? '#b3213a' : '#0b6b4f'; };
		var pk = document.getElementById( 'g-pk' );
		if ( pk ) {
			pk.onclick = async function () {
				pk.disabled = true; say( 'Waiting for your fingerprint, face or screen lock' );
				try { await SFPasskey.login( C.rest, window.location.href ); window.location.reload(); } catch ( e ) { say( SFPasskey.nice( e ), true ); pk.disabled = false; }
			};
		}
		var open = document.getElementById( 'g-ml-open' );
		if ( open ) {
			open.onclick = function () { open.hidden = true; document.getElementById( 'g-ml' ).hidden = false; document.getElementById( 'g-login' ).focus(); };
			document.getElementById( 'g-ml-send' ).onclick = async function () {
				var v = document.getElementById( 'g-login' ).value.trim();
				if ( ! v ) { say( 'Type your user name or email first.', true ); return; }
				this.disabled = true;
				try { var r = await SFPasskey.api( C.rest, '/magic/request', { login: v } ); say( r.message ); } catch ( e ) { say( e.message, true ); }
				this.disabled = false;
			};
		}
	}

	/* ---------------- views ---------------- */
	function pillFor( st ) { return { posted: 'ok', approved: 'warn', pending: 'warn', failed: 'bad', skipped: 'gray' }[ st ] || 'gray'; }

	function viewHome() {
		var fb = S.fb.ok ? '<span class="sfc-dot"></span>Connected' : '<span class="sfc-dot bad"></span>Check';
		var mode = S.paused ? 'Paused' : ( S.mode === 'auto' ? 'Auto-post' : 'Ask me first' );
		var pending = S.queue.filter( function ( p ) { return p.status === 'pending'; } ).length;
		var hb = ( S.health || [] ).map( function ( x ) { return '<div class="sfc-card sfc-alert ' + x.level + '"><b>' + esc( x.title ) + '</b><div class="sfc-sub">' + esc( x.text ) + '</div></div>'; } ).join( '' );
		var h = '<h2>Today</h2>' + hb + '<div class="sfc-grid">' +
			'<div class="sfc-card"><h3>Facebook</h3><div class="sfc-big">' + fb + '</div><div class="sfc-sub">' + esc( S.fb.fans ? S.fb.fans.toLocaleString() + ' followers' : ( S.fb.message || 'Not tested' ) ) + '</div></div>' +
			'<div class="sfc-card"><h3>Posting</h3><div class="sfc-big">' + esc( mode ) + '</div><div class="sfc-sub">Next check ' + esc( S.next ) + '</div></div>' +
			'<div class="sfc-card"><h3>Cricket data</h3><div class="sfc-big">' + S.api.hits + ' <span class="sfc-sub" style="font-weight:500">of ' + S.api.limit + '</span></div><div class="sfc-meter"><i style="width:' + Math.min( 100, S.api.limit ? Math.round( S.api.hits * 100 / S.api.limit ) : 0 ) + '%"></i></div></div>' +
			'<div class="sfc-card"><h3>Waiting</h3><div class="sfc-big">' + pending + '</div><div class="sfc-sub">' + ( pending ? 'for your approval' : 'nothing to approve' ) + '</div></div></div>' +
			'<div class="sfc-row2"><button class="sfc-btn primary" id="a-check" type="button">' + ic( 'arrow-clockwise' ) + 'Check now</button>' +
			'<button class="sfc-btn" id="a-pause" type="button">' + ( S.paused ? ic( 'play' ) + 'Resume' : ic( 'pause' ) + 'Pause' ) + '</button></div>';
		if ( ! S.ready ) { h += '<div class="sfc-card"><b>Finish setup</b><p class="sfc-sub" style="margin:4px 0 12px">Add the Facebook Page, its token and the cricket key.</p><a class="sfc-btn sm" href="' + esc( C.admin ) + '&tab=setup">Open Setup</a></div>'; }
		h += '<h2>Matches</h2><div class="sfc-list">';
		if ( ! S.matches.length ) { h += '<div class="sfc-li"><div class="m"><b>No matches yet</b><span>Press Check now. The list updates on every check.</span></div></div>'; }
		S.matches.forEach( function ( m ) {
			var cls = { 'Live card': 'ok', 'In scoreboard': 'gray', Upcoming: 'warn', Done: 'gray' }[ m.plan ] || 'gray';
			h += '<div class="sfc-li"><span class="t">' + esc( m.time ) + '</span><div class="m"><b>' + esc( m.teams ) + '</b><span>' + esc( m.series || m.status ) + '</span></div><span class="sfc-pill ' + cls + '">' + esc( m.plan ) + '</span></div>';
		} );
		h += '</div>' + ( S.seen ? '<p class="sfc-sub" style="margin:-6px 4px 0">List from the check ' + esc( S.seen ) + '.</p>' : '' );
		h += '<h2>Recent posts</h2><div class="sfc-list">';
		if ( ! S.recent.length ) { h += '<div class="sfc-li"><div class="m"><b>Nothing yet</b></div></div>'; }
		S.recent.forEach( function ( p ) {
			h += '<div class="sfc-li">' + ( p.image ? '<img class="th" alt="" loading="lazy" src="' + esc( p.image ) + '">' : '<span class="t">' + esc( p.time ) + '</span>' ) + '<div class="m"><b>' + esc( p.type.charAt( 0 ).toUpperCase() + p.type.slice( 1 ) ) + '</b><span>' + esc( ( p.caption || '' ).split( '\n' )[ 0 ] ) + ( p.error ? ' (' + esc( p.error ) + ')' : '' ) + '</span></div><span class="sfc-pill ' + pillFor( p.status ) + '">' + esc( p.status ) + '</span></div>';
		} );
		h += '</div>';
		return h;
	}

	function viewQueue() {
		var h = '<h2>Post queue</h2>';
		if ( ! S.queue.length ) { return h + '<div class="sfc-card sfc-empty"><b>All caught up</b>New posts appear here when something needs you.</div>'; }
		S.queue.forEach( function ( p ) {
			var failed = p.status === 'failed';
			h += '<div class="sfc-post" data-id="' + p.id + '">' + ( p.image ? '<img class="full" alt="" loading="lazy" src="' + esc( p.image ) + '">' : '' ) +
				'<div class="body"><div><span class="sfc-pill ' + pillFor( p.status ) + '">' + esc( p.status ) + '</span> <span class="sfc-sub">' + esc( p.type ) + ' ' + esc( p.when || p.time ) + '</span></div>' +
				( p.error ? '<p class="sfc-err">' + esc( p.error ) + '</p>' : '' ) +
				( p.wait ? '<p class="sfc-sub"><b>Why it has not posted:</b> ' + esc( p.wait ) + '</p>' : '' ) +
				'<label class="l" for="c' + p.id + '">Caption</label><textarea class="sfc-field" id="c' + p.id + '">' + esc( p.caption ) + '</textarea>' +
				'<div class="sfc-row2"><button class="sfc-btn primary q-go" data-do="' + ( failed ? 'retry' : ( p.status === 'approved' ? 'now' : 'approve' ) ) + '" type="button">' + ( failed ? 'Try again' : ( p.status === 'approved' ? 'Post now' : 'Approve and post' ) ) + '</button><button class="sfc-btn q-skip" type="button">Skip</button></div></div></div>';
		} );
		return h;
	}

	function viewPost() {
		return '<h2>New post</h2>' +
			'<label class="sfc-pick" id="p-pick" for="p-file">' + ( picked ? '<img id="p-prev" alt="">' : ic( 'camera' ) + '<span>Add a picture</span><span class="sfc-sub">Take a photo or choose one. JPG, PNG or WebP.</span>' ) + '</label><input id="p-file" type="file" accept="image/jpeg,image/png,image/webp" style="position:absolute;width:1px;height:1px;opacity:0">' +
			'<div><label class="l" for="p-cap">Caption</label><textarea class="sfc-field" id="p-cap" placeholder="Write your caption">' + esc( draft.caption ) + '</textarea></div>' +
			'<div class="sfc-list"><div class="sfc-li"><div class="m"><b>Add a question for fans</b><span>Picks one from your list</span></div><label class="sfc-switch"><input type="checkbox" id="p-q"' + ( draft.q ? ' checked' : '' ) + ' aria-label="Add a question for fans"><span></span></label></div>' +
			'<div class="sfc-li"><div class="m"><b>Add the Cricket Sport mark</b><span>A small mark in a corner</span></div><label class="sfc-switch"><input type="checkbox" id="p-wm"' + ( draft.wm ? ' checked' : '' ) + ' aria-label="Add the mark"><span></span></label></div></div>' +
			'<div><label class="l" for="p-pos">Mark position</label><select class="sfc-field" id="p-pos">' + [ [ 'br', 'Bottom right' ], [ 'bl', 'Bottom left' ], [ 'tr', 'Top right' ], [ 'tl', 'Top left' ] ].map( function ( o ) { return '<option value="' + o[ 0 ] + '"' + ( draft.pos === o[ 0 ] ? ' selected' : '' ) + '>' + o[ 1 ] + '</option>'; } ).join( '' ) + '</select></div>' +
			'<div class="sfc-seg" role="group" aria-label="When"><button type="button" data-when="now" aria-pressed="' + ( draft.when === 'now' ) + '">Post now</button><button type="button" data-when="later" aria-pressed="' + ( draft.when === 'later' ) + '">Schedule</button></div>' +
			( draft.when === 'later' ? '<div><label class="l" for="p-at">Date and time</label><input class="sfc-field" type="datetime-local" id="p-at" value="' + esc( draft.at ) + '"></div>' : '' ) +
			'<button class="sfc-btn primary" id="p-go" type="button">' + ( draft.when === 'later' ? 'Schedule post' : 'Post to Facebook' ) + '</button>';
	}

	function viewSettings() {
		var h = '<h2>Settings</h2><div><h3>How posts go out</h3><div class="sfc-seg" role="group" aria-label="Posting mode"><button type="button" data-mode="auto" aria-pressed="' + ( S.mode === 'auto' ) + '">Auto-post</button><button type="button" data-mode="approve" aria-pressed="' + ( S.mode === 'approve' ) + '">Ask me first</button></div></div>' +
			'<div class="sfc-list"><div class="sfc-li"><div class="m"><b>Pause all posting</b><span>Stops scores and cards. Manual posts still work.</span></div><label class="sfc-switch"><input type="checkbox" id="s-pause"' + ( S.paused ? ' checked' : '' ) + ' aria-label="Pause all posting"><span></span></label></div></div>' +
			'<div><h3>What to post</h3><div class="sfc-list">';
		S.jobs.forEach( function ( j ) {
			h += '<div class="sfc-li"><div class="m"><b>' + esc( j.label ) + '</b></div><label class="sfc-switch"><input type="checkbox" class="s-job" data-job="' + esc( j.key ) + '"' + ( j.on ? ' checked' : '' ) + ' aria-label="' + esc( j.label ) + '"><span></span></label></div>';
		} );
		h += '</div></div><div><h3>Account</h3><div class="sfc-list"><div class="sfc-li"><div class="m"><b>' + esc( S.user ) + '</b><span>' + S.passkeys + ( S.passkeys === 1 ? ' passkey' : ' passkeys' ) + ' on this account</span></div></div></div></div>' +
			( window.SFPasskey && SFPasskey.supported() ? '<button class="sfc-btn" id="s-addpk" type="button">' + ic( 'fingerprint' ) + 'Add a passkey on this phone</button>' : '' ) +
			'<a class="sfc-btn" href="' + esc( C.admin ) + '">' + ic( 'arrow-square-out' ) + 'Open the full admin</a>' +
			'<a class="sfc-btn" href="' + esc( C.logout ) + '">' + ic( 'sign-out' ) + 'Sign out</a>';
		if ( S.log.length ) {
			h += '<div><h3>Activity</h3><div class="sfc-list">' + S.log.map( function ( l ) { return '<div class="sfc-li"><span class="t">' + esc( l.time ) + '</span><div class="m"><span>' + esc( l.text ) + '</span></div></div>'; } ).join( '' ) + '</div></div>';
		}
		return h + '<p class="sfc-sub" style="text-align:center;margin:8px 0 0">Cricket Sport Live ' + esc( C.version ) + ' by <a href="https://mudassar.work" rel="noopener" style="color:inherit">Mudassar</a></p>';
	}

	/* ---------------- shell ---------------- */
	function draw() {
		if ( ! S ) { return; }
		var pending = S.queue.filter( function ( p ) { return p.status === 'pending' || p.status === 'failed'; } ).length;
		if ( ! S.manual && tab === 'post' ) { tab = 'home'; }
		var body = { home: viewHome, queue: viewQueue, post: viewPost, settings: viewSettings }[ tab ]();
		var scroll = root.querySelector( '.sfc-main' );
		var keep = scroll ? scroll.scrollTop : 0;
		var tabs = [ [ 'home', 'Home', 'house' ], [ 'queue', 'Queue', 'tray' ], [ 'post', 'Post', 'plus-circle' ], [ 'settings', 'Settings', 'sliders-horizontal' ] ];
		if ( ! S.manual ) { tabs = tabs.filter( function ( t ) { return t[ 0 ] !== 'post'; } ); }
		root.innerHTML = '<header class="sfc-top">' + MARK + '<b>Cricket Sport</b><span class="sp"></span>' + ( S.paused ? '<span class="sfc-pill warn">Paused</span>' : ( S.fb.ok ? '<span class="sfc-pill ok">Live</span>' : '<span class="sfc-pill bad">Check setup</span>' ) ) + '</header>' +
			'<main class="sfc-main" id="sfc-main"><div class="sfc-wrap">' + body + '</div></main>' +
			'<nav class="sfc-tabs" aria-label="Sections">' + tabs.map( function ( t ) { return '<button type="button" data-tab="' + t[ 0 ] + '"' + ( tab === t[ 0 ] ? ' aria-current="page"' : '' ) + '>' + ic( t[ 2 ] ) + t[ 1 ] + ( t[ 0 ] === 'queue' && pending ? '<span class="badge">' + pending + '</span>' : '' ) + '</button>'; } ).join( '' ) + '</nav>' +
			'<div class="sfc-toast" id="sfc-toast" role="status" aria-live="polite" hidden></div>';
		toastEl = document.getElementById( 'sfc-toast' );
		var main = document.getElementById( 'sfc-main' );
		main.scrollTop = keep;
		bind();
	}

	function bind() {
		root.querySelectorAll( '[data-tab]' ).forEach( function ( b ) {
			b.onclick = function () { tab = b.dataset.tab; var m = document.getElementById( 'sfc-main' ); draw(); document.getElementById( 'sfc-main' ).scrollTop = 0; };
		} );
		var q = function ( id ) { return document.getElementById( id ); };
		if ( q( 'a-check' ) ) {
			q( 'a-check' ).onclick = function () { var b = this; act( b, async function () { b.textContent = 'Checking'; apply( await api( 'POST', '/console/check' ) ); } ); };
			q( 'a-pause' ).onclick = function () { var b = this; act( b, async function () { apply( await api( 'POST', '/console/pause', { pause: ! S.paused } ) ); } ); };
		}
		root.querySelectorAll( '.q-go' ).forEach( function ( b ) {
			b.onclick = function () { var card = b.closest( '.sfc-post' ); act( b, async function () { apply( await api( 'POST', '/console/queue', { id: +card.dataset.id, do: b.dataset.do, caption: card.querySelector( 'textarea' ).value } ) ); } ); };
		} );
		root.querySelectorAll( '.q-skip' ).forEach( function ( b ) {
			b.onclick = function () { var card = b.closest( '.sfc-post' ); act( b, async function () { apply( await api( 'POST', '/console/queue', { id: +card.dataset.id, do: 'skip' } ) ); } ); };
		} );
		// Post tab.
		if ( q( 'p-file' ) ) {
			var cap = q( 'p-cap' );
			cap.oninput = function () { draft.caption = cap.value; };
			q( 'p-q' ).onchange = function () { draft.q = this.checked; };
			q( 'p-wm' ).onchange = function () { draft.wm = this.checked; };
			q( 'p-pos' ).onchange = function () { draft.pos = this.value; };
			if ( picked && q( 'p-prev' ) ) { q( 'p-prev' ).src = URL.createObjectURL( picked ); }
			q( 'p-file' ).onchange = function () { picked = this.files && this.files[ 0 ] ? this.files[ 0 ] : null; draw(); };
			root.querySelectorAll( '[data-when]' ).forEach( function ( b ) { b.onclick = function () { draft.when = b.dataset.when; draw(); }; } );
			if ( q( 'p-at' ) ) { q( 'p-at' ).onchange = function () { draft.at = this.value; }; }
			q( 'p-go' ).onclick = function () {
				var b = this;
				act( b, async function () {
					var f = new FormData();
					if ( picked ) { f.append( 'image', picked ); }
					f.append( 'caption', cap.value );
					f.append( 'add_question', draft.q ? '1' : '' );
					f.append( 'watermark', draft.wm ? '1' : '' );
					f.append( 'wm_pos', draft.pos );
					f.append( 'when', draft.when );
					f.append( 'when_at', draft.at );
					b.textContent = 'Posting';
					var d = await api( 'POST', '/console/manual', f, true );
					picked = null; draft.caption = ''; draft.q = false; draft.when = 'now'; draft.at = '';
					apply( d );
				} );
			};
		}
		// Settings.
		root.querySelectorAll( '[data-mode]' ).forEach( function ( b ) { b.onclick = function () { act( b, async function () { apply( await api( 'POST', '/console/mode', { mode: b.dataset.mode } ) ); } ); }; } );
		if ( q( 's-pause' ) ) { q( 's-pause' ).onchange = function () { var b = this; act( b, async function () { apply( await api( 'POST', '/console/pause', { pause: b.checked } ) ); } ); }; }
		root.querySelectorAll( '.s-job' ).forEach( function ( c ) { c.onchange = function () { act( c, async function () { apply( await api( 'POST', '/console/job', { job: c.dataset.job, on: c.checked } ) ); } ); }; } );
		if ( q( 's-addpk' ) ) {
			q( 's-addpk' ).onclick = function () { var b = this; act( b, async function () { await SFPasskey.register( C.rest, C.nonce, 'My phone' ).catch( function ( e ) { throw new Error( SFPasskey.nice( e ) ); } ); S = ( await api( 'GET', '/console/state' ) ); draw(); toast( 'Passkey added.' ); } ); };
		}
	}

	async function load( quiet ) {
		try {
			S = await api( 'GET', '/console/state' );
			draw();
		} catch ( e ) {
			if ( ! quiet ) { root.innerHTML = '<div class="sfc-login"><div class="sfc-card"><b>' + esc( e.message ) + '</b><button class="sfc-btn primary" onclick="location.reload()">Reload</button></div></div>'; }
		}
	}

	if ( ! C.isAdmin ) { drawGuest(); return; }
	root.innerHTML = '<div class="sfc-spin" role="status" aria-label="Loading"></div>';
	load( false );
	timer = setInterval( function () {
		var typing = document.activeElement && /^(TEXTAREA|INPUT|SELECT)$/.test( document.activeElement.tagName );
		if ( document.visibilityState === 'visible' && ! busy && ! typing && tab !== 'post' ) { load( true ); }
	}, 45000 );
}() );
