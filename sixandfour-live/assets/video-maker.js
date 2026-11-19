/* Cricket Sport Live: makes the result video in this browser, then uploads it so the plugin can post it.
 * Runs on the plugin's admin screens and the mobile console while the page is open (Chrome or Edge).
 * It draws each frame on a canvas, encodes H.264 with WebCodecs and builds the MP4 with mp4-muxer.
 */
( function () {
	'use strict';

	var CFG = window.SFVM || {};
	var W = 720;          // Output size. The scenes are drawn on a 1080 x 1920 grid and scaled down.
	var H = 1280;
	var FPS = 30;
	var NAVY = '#0A1B44';
	var NAVY2 = '#12306F';
	var CYAN = '#00E5FF';
	var FONT = 'InterSF, system-ui, sans-serif';

	/* ------------------------------------------------------------------ */
	/* Small helpers                                                       */
	/* ------------------------------------------------------------------ */

	function clamp( v, a, b ) { return Math.max( a, Math.min( b, v ) ); }

	/** A spring-like ease: quick rise, a little overshoot, settles at 1. x is in seconds. */
	function spring( x ) {
		if ( x <= 0 ) { return 0; }
		return 1 - Math.exp( -7 * x ) * Math.cos( 10 * x );
	}

	function lerp( a, b, t ) { return a + ( b - a ) * t; }

	function loadImage( url ) {
		return new Promise( function ( resolve ) {
			var img = new Image();
			img.crossOrigin = 'anonymous';
			img.onload = function () { resolve( img ); };
			img.onerror = function () { resolve( null ); };
			img.src = url;
		} );
	}

	function loadFonts() {
		var faces = [ [ 800, CFG.fonts.extra ], [ 700, CFG.fonts.bold ], [ 600, CFG.fonts.semi ] ].map( function ( f ) {
			var ff = new FontFace( 'InterSF', 'url(' + f[ 1 ] + ')', { weight: String( f[ 0 ] ) } );
			return ff.load().then( function () { document.fonts.add( ff ); } );
		} );
		return Promise.all( faces );
	}

	function rr( ctx, x, y, w, h, r ) {
		ctx.beginPath();
		ctx.roundRect( x, y, w, h, r );
	}

	function text( ctx, s, x, y, size, weight, color, align, spacing ) {
		ctx.font = weight + ' ' + size + 'px ' + FONT;
		ctx.fillStyle = color;
		ctx.textAlign = align || 'left';
		ctx.letterSpacing = ( spacing || 0 ) + 'px';
		ctx.fillText( s, x, y );
		ctx.letterSpacing = '0px';
	}

	function width( ctx, s, size, weight, spacing ) {
		ctx.font = weight + ' ' + size + 'px ' + FONT;
		ctx.letterSpacing = ( spacing || 0 ) + 'px';
		var w = ctx.measureText( s ).width;
		ctx.letterSpacing = '0px';
		return w;
	}

	/** The largest size, up to max, at which the text fits in maxW. */
	function fit( ctx, s, max, weight, maxW, spacing ) {
		var size = max;
		while ( size > 24 && width( ctx, s, size, weight, spacing ) > maxW ) { size -= 2; }
		return size;
	}

	/* ------------------------------------------------------------------ */
	/* Timeline                                                            */
	/* ------------------------------------------------------------------ */

	function timeline( m ) {
		var stars = Boolean( m.bat || m.bowl );
		var end = stars ? 21.5 : 17;
		var k = end / 21.5;
		return {
			end: end,
			score: 4 * k,
			stars: 11 * k,
			outro: stars ? 17 : 11 * k + 0,
			hasStars: stars,
		};
	}

	/* ------------------------------------------------------------------ */
	/* Drawing. All numbers are on the 1080 x 1920 grid.                    */
	/* ------------------------------------------------------------------ */

	function background( ctx, t ) {
		ctx.fillStyle = NAVY;
		ctx.fillRect( 0, 0, 1080, 1920 );
		var g = ctx.createRadialGradient( 540, 730, 0, 540, 730, 900 );
		g.addColorStop( 0, NAVY2 );
		g.addColorStop( 0.7, NAVY );
		ctx.fillStyle = g;
		ctx.fillRect( 0, 0, 1080, 1920 );
		ctx.save();
		ctx.globalAlpha = 0.5;
		ctx.strokeStyle = '#16295C';
		ctx.lineWidth = 1;
		var i;
		for ( i = 0; i < 10; i++ ) { ctx.beginPath(); ctx.moveTo( i * 120, 0 ); ctx.lineTo( i * 120, 1920 ); ctx.stroke(); }
		for ( i = 0; i < 17; i++ ) { ctx.beginPath(); ctx.moveTo( 0, i * 120 ); ctx.lineTo( 1080, i * 120 ); ctx.stroke(); }
		ctx.restore();
		var drift = lerp( 0, -120, clamp( t / 23, 0, 1 ) );
		ctx.save();
		ctx.globalAlpha = 0.16;
		ctx.strokeStyle = CYAN;
		ctx.lineWidth = 34;
		ctx.lineCap = 'round';
		ctx.beginPath();
		ctx.moveTo( -60, 1500 + drift * 0.3 );
		ctx.quadraticCurveTo( 520, 1180 + drift * 0.3, 1160, 420 + drift * 0.2 );
		ctx.stroke();
		ctx.restore();
	}

	function header( ctx, A, pill, local ) {
		var p = spring( local );
		ctx.save();
		ctx.globalAlpha = clamp( p, 0, 1 );
		ctx.translate( 0, ( 1 - clamp( p, 0, 1.1 ) ) * -60 );
		rr( ctx, 56, 70, 968, 150, 44 );
		ctx.fillStyle = '#0E2557';
		ctx.fill();
		ctx.lineWidth = 2;
		ctx.strokeStyle = '#1B3A82';
		ctx.stroke();
		rr( ctx, 90, 97, 96, 96, 26 );
		ctx.fillStyle = '#fff';
		ctx.fill();
		if ( A.logo ) { ctx.drawImage( A.logo, 98, 105, 80, 80 ); }
		text( ctx, 'Cricket Sport', 212, 142, 46, 800, '#fff', 'left', -0.5 );
		text( ctx, A.link, 212, 184, 27, 600, '#9EEFFF', 'left' );
		var pw = width( ctx, pill, 30, 800 ) + 60;
		rr( ctx, 990 - pw, 99, pw, 62, 31 );
		ctx.fillStyle = CYAN;
		ctx.fill();
		text( ctx, pill, 990 - pw / 2, 141, 30, 800, NAVY, 'center' );
		ctx.restore();
	}

	function headline( ctx, m, local ) {
		var sub = clamp( spring( local - 0.8 ), 0, 1 );
		ctx.save();
		ctx.globalAlpha = sub;
		text( ctx, m.tournament + '  |  ' + String( m.meta || '' ).split( ',' )[ 0 ], 70, 740 + ( 1 - sub ) * 20, 40, 800, '#8FA6DE', 'left' );
		ctx.restore();
		var words = [ m.winner, 'beat', m.loser, 'by', m.margin ];
		var size = 150;
		var x = 70;
		var y = 930;
		var gap = 26;
		words.forEach( function ( w, i ) {
			var ww = width( ctx, w, size, 800, -5 );
			if ( x + ww > 1010 ) { x = 70; y += size * 1.04; }
			var s = clamp( spring( local - ( 8 + i * 6 ) / FPS ), 0, 1 );
			ctx.save();
			ctx.globalAlpha = s;
			var hot = i === 0 || i === 4;
			text( ctx, w, x, y + ( 1 - s ) * 50, size, 800, hot ? CYAN : '#fff', 'left', -5 );
			ctx.restore();
			x += ww + gap;
		} );
	}

	function teamRow( ctx, A, t, y, local ) {
		var p = spring( local );
		var o = clamp( p, 0, 1 );
		ctx.save();
		ctx.globalAlpha = o;
		ctx.translate( ( 1 - clamp( p, 0, 1.1 ) ) * -900, 0 );
		rr( ctx, 60, y, 960, 240, 44 );
		ctx.fillStyle = t.won ? '#16398A' : '#102A66';
		ctx.fill();
		ctx.lineWidth = 3;
		ctx.strokeStyle = t.won ? CYAN : '#1F3F8F';
		ctx.stroke();
		var img = t.flag ? A.flags[ t.flag ] : null;
		if ( img ) {
			ctx.save();
			rr( ctx, 104, y + 64, 150, 112, 16 );
			ctx.clip();
			var ir = img.width / img.height;
			var dh = 112;
			var dw = dh * ir;
			if ( dw < 150 ) { dw = 150; dh = dw / ir; }
			ctx.drawImage( img, 104 + ( 150 - dw ) / 2, y + 64 + ( 112 - dh ) / 2, dw, dh );
			ctx.restore();
		} else {
			rr( ctx, 104, y + 64, 150, 112, 24 );
			ctx.fillStyle = NAVY;
			ctx.fill();
			text( ctx, t.initials || String( t.name ).slice( 0, 3 ).toUpperCase(), 179, y + 140, 52, 800, CYAN, 'center' );
		}
		var nameSize = fit( ctx, t.name, 76, 800, 330, -1.5 );
		text( ctx, t.name, 294, y + 144, nameSize, 800, '#fff', 'left', -1.5 );
		var scoreSize = fit( ctx, t.score, 112, 800, 330, -3 );
		text( ctx, t.score, 976, y + 135, scoreSize, 800, t.won ? CYAN : '#fff', 'right', -3 );
		text( ctx, t.overs, 976, y + 184, 32, 600, '#8FA6DE', 'right' );
		ctx.restore();
	}

	function resultBar( ctx, m, y, local ) {
		var p = spring( local );
		var o = clamp( p, 0, 1 );
		ctx.save();
		ctx.globalAlpha = o;
		var s = 0.85 + 0.15 * clamp( p, 0, 1.05 );
		ctx.translate( 540, y + 120 );
		ctx.scale( s, s );
		ctx.translate( -540, -( y + 120 ) );
		var h = m.toSpare ? 250 : 190;
		rr( ctx, 60, y, 960, h, 40 );
		ctx.fillStyle = CYAN;
		ctx.fill();
		var line = m.winner + ' won by ' + m.margin;
		var size = fit( ctx, line, 70, 800, 860, -2 );
		text( ctx, line, 110, y + ( m.toSpare ? 105 : 120 ), size, 800, NAVY, 'left', -2 );
		if ( m.toSpare ) { text( ctx, m.toSpare, 110, y + 190, 40, 700, '#0A3A66', 'left' ); }
		ctx.restore();
	}

	function starCard( ctx, s, y, local ) {
		var p = spring( local );
		var o = clamp( p, 0, 1 );
		ctx.save();
		ctx.globalAlpha = o;
		ctx.translate( 0, ( 1 - clamp( p, 0, 1.1 ) ) * 160 );
		rr( ctx, 60, y, 960, 520, 48 );
		ctx.fillStyle = '#102A66';
		ctx.fill();
		ctx.lineWidth = 3;
		ctx.strokeStyle = '#1F3F8F';
		ctx.stroke();
		var initials = String( s.name ).split( ' ' ).map( function ( w ) { return w.charAt( 0 ); } ).join( '' ).slice( 0, 3 );
		rr( ctx, 110, y + 44, 150, 150, 38 );
		ctx.fillStyle = CYAN;
		ctx.fill();
		text( ctx, initials, 185, y + 144, 70, 800, NAVY, 'center' );
		text( ctx, s.label, 294, y + 96, 38, 700, '#8FA6DE', 'left' );
		var nsz = fit( ctx, s.name, 70, 800, 690, -2 );
		text( ctx, s.name, 294, y + 168, nsz, 800, '#fff', 'left', -2 );
		var ssz = fit( ctx, s.stat, 210, 800, 520, -8 );
		var sw = width( ctx, s.stat, ssz, 800, -8 );
		text( ctx, s.stat, 110, y + 395, ssz, 800, CYAN, 'left', -8 );
		text( ctx, s.small, 110 + sw + 26, y + 395, 52, 700, '#C8D6FF', 'left' );
		text( ctx, s.detail, 110, y + 462, 40, 600, '#8FA6DE', 'left' );
		ctx.restore();
	}

	function outro( ctx, A, local ) {
		var p = clamp( spring( local ), 0, 1.1 );
		var q = clamp( spring( local - 0.33 ), 0, 1 );
		var r = clamp( spring( local - 0.73 ), 0, 1 );
		ctx.save();
		ctx.translate( 540, 640 );
		ctx.scale( p, p );
		if ( A.avatar ) {
			ctx.save();
			ctx.beginPath();
			ctx.arc( 0, 0, 180, 0, Math.PI * 2 );
			ctx.clip();
			ctx.drawImage( A.avatar, -180, -180, 360, 360 );
			ctx.restore();
		}
		ctx.lineWidth = 10;
		ctx.strokeStyle = 'rgba(0,229,255,0.33)';
		ctx.beginPath();
		ctx.arc( 0, 0, 185, 0, Math.PI * 2 );
		ctx.stroke();
		ctx.restore();
		ctx.save();
		ctx.globalAlpha = q;
		text( ctx, 'Cricket Sport', 540, 1000 + ( 1 - q ) * 40, 112, 800, '#fff', 'center', -3 );
		text( ctx, 'Every result, as it happens', 540, 1075, 50, 700, '#C8D6FF', 'center' );
		ctx.restore();
		ctx.save();
		ctx.globalAlpha = r;
		var bw = width( ctx, 'Follow the Page', 50, 800 ) + 112;
		var s = 0.9 + 0.1 * r;
		ctx.translate( 540, 1180 );
		ctx.scale( s, s );
		rr( ctx, -bw / 2, -50, bw, 100, 50 );
		ctx.fillStyle = CYAN;
		ctx.fill();
		text( ctx, 'Follow the Page', 0, 17, 50, 800, NAVY, 'center' );
		ctx.restore();
		ctx.save();
		ctx.globalAlpha = r;
		text( ctx, A.link, 540, 1290, 40, 600, '#9EEFFF', 'center' );
		ctx.restore();
	}

	function frame( ctx, A, m, TL, t ) {
		ctx.save();
		ctx.scale( W / 1080, H / 1920 );
		background( ctx, t );
		var inA = t < TL.score + 0.4;
		var inB = t >= TL.score && t < ( TL.hasStars ? TL.stars : TL.outro ) + 0.4;
		var inC = TL.hasStars && t >= TL.stars && t < TL.outro + 0.4;
		var inD = t >= TL.outro;
		if ( inA ) {
			header( ctx, A, 'MATCH RESULT', t );
			headline( ctx, m, t );
		}
		if ( inB ) {
			var lb = t - TL.score;
			header( ctx, A, 'SCORES', lb );
			teamRow( ctx, A, m.teams[ 0 ], 560, lb );
			teamRow( ctx, A, m.teams[ 1 ], 830, lb - 3.2 );
			resultBar( ctx, m, 1150, lb - 5.6 );
		}
		if ( inC ) {
			var lc = t - TL.stars;
			header( ctx, A, 'STAND-OUTS', lc );
			if ( m.bat ) { starCard( ctx, m.bat, m.bowl ? 420 : 640, lc ); }
			if ( m.bowl ) { starCard( ctx, m.bowl, m.bat ? 1010 : 640, m.bat ? lc - 3 : lc ); }
		}
		if ( inD ) { outro( ctx, A, t - TL.outro ); }
		ctx.restore();
	}

	/* ------------------------------------------------------------------ */
	/* Encoding                                                            */
	/* ------------------------------------------------------------------ */

	function supported() {
		return typeof window.VideoEncoder === 'function' && typeof window.Mp4Muxer === 'object' && typeof OffscreenCanvas === 'function' && window.isSecureContext;
	}

	async function pickVideoCodec() {
		var list = [ 'avc1.64001f', 'avc1.4d001f', 'avc1.42001f' ];
		for ( var i = 0; i < list.length; i++ ) {
			var r = await VideoEncoder.isConfigSupported( { codec: list[ i ], width: W, height: H, bitrate: 2500000, framerate: FPS } );
			if ( r.supported ) { return list[ i ]; }
		}
		return null;
	}

	async function pickAudio() {
		if ( typeof window.AudioEncoder !== 'function' ) { return null; }
		var tries = [ [ 'mp4a.40.2', 'aac' ], [ 'opus', 'opus' ] ];
		for ( var i = 0; i < tries.length; i++ ) {
			var r = await AudioEncoder.isConfigSupported( { codec: tries[ i ][ 0 ], sampleRate: 44100, numberOfChannels: 2, bitrate: 128000 } );
			if ( r.supported ) { return { codec: tries[ i ][ 0 ], mux: tries[ i ][ 1 ] }; }
		}
		return null;
	}

	/** Wait until the encoder has room, without relying on timers (they are slowed in background tabs). */
	function room( enc, max ) {
		if ( enc.encodeQueueSize <= max ) { return Promise.resolve(); }
		return new Promise( function ( resolve ) {
			function done() {
				if ( enc.encodeQueueSize <= max ) { enc.removeEventListener( 'dequeue', done ); resolve(); }
			}
			enc.addEventListener( 'dequeue', done );
		} );
	}

	async function musicBuffer( seconds ) {
		var res = await fetch( CFG.music );
		var arr = await res.arrayBuffer();
		var ctx = new OfflineAudioContext( 2, 44100 * seconds, 44100 );
		var buf = await ctx.decodeAudioData( arr );
		var out = [ new Float32Array( 44100 * seconds ), new Float32Array( 44100 * seconds ) ];
		var n = Math.min( buf.length, out[ 0 ].length );
		var gain = 0.5;
		for ( var c = 0; c < 2; c++ ) {
			var src = buf.getChannelData( Math.min( c, buf.numberOfChannels - 1 ) );
			for ( var i = 0; i < n; i++ ) {
				var t = i / 44100;
				var fade = clamp( ( seconds - t ) / 2, 0, 1 ) * clamp( t / 0.5, 0, 1 );
				out[ c ][ i ] = src[ i ] * gain * fade;
			}
		}
		return out;
	}

	/** Makes the MP4. Returns a Blob. progress( 0..1 ) is called as it goes. */
	async function make( m, progress ) {
		var A = { link: m.link || 'facebook.com/cricketsportinfo', flags: {} };
		await loadFonts();
		var imgs = await Promise.all( [ loadImage( CFG.logo ), loadImage( CFG.avatar ) ].concat( ( m.teams || [] ).map( function ( t ) { return t.flag ? loadImage( CFG.flagBase + t.flag.replace( /^flags\//, '' ) ) : Promise.resolve( null ); } ) ) );
		A.logo = imgs[ 0 ];
		A.avatar = imgs[ 1 ];
		( m.teams || [] ).forEach( function ( t, i ) { if ( t.flag ) { A.flags[ t.flag ] = imgs[ 2 + i ]; } } );

		var TL = timeline( m );
		var total = Math.round( TL.end * FPS );
		var codec = await pickVideoCodec();
		if ( ! codec ) { throw new Error( 'This browser cannot make H.264 video. Use Chrome or Edge.' ); }
		var audio = m.music === false ? null : await pickAudio();

		var target = new Mp4Muxer.ArrayBufferTarget();
		var muxer = new Mp4Muxer.Muxer( {
			target: target,
			video: { codec: 'avc', width: W, height: H },
			audio: audio ? { codec: audio.mux, numberOfChannels: 2, sampleRate: 44100 } : undefined,
			fastStart: 'in-memory',
		} );
		var err = null;
		var venc = new VideoEncoder( {
			output: function ( chunk, meta ) { muxer.addVideoChunk( chunk, meta ); },
			error: function ( e ) { err = e; },
		} );
		venc.configure( { codec: codec, width: W, height: H, bitrate: 2500000, framerate: FPS, latencyMode: 'quality' } );

		var aenc = null;
		if ( audio ) {
			aenc = new AudioEncoder( {
				output: function ( chunk, meta ) { muxer.addAudioChunk( chunk, meta ); },
				error: function ( e ) { err = e; },
			} );
			aenc.configure( { codec: audio.codec, sampleRate: 44100, numberOfChannels: 2, bitrate: 128000 } );
			try {
				var pcm = await musicBuffer( TL.end );
				var step = 4096;
				for ( var s = 0; s < pcm[ 0 ].length; s += step ) {
					var n = Math.min( step, pcm[ 0 ].length - s );
					var data = new Float32Array( n * 2 );
					data.set( pcm[ 0 ].subarray( s, s + n ), 0 );
					data.set( pcm[ 1 ].subarray( s, s + n ), n );
					var ad = new AudioData( { format: 'f32-planar', sampleRate: 44100, numberOfFrames: n, numberOfChannels: 2, timestamp: Math.round( s / 44100 * 1e6 ), data: data } );
					aenc.encode( ad );
					ad.close();
					await room( aenc, 40 );
				}
			} catch ( e ) {
				// No music is better than no video.
				aenc = null;
				audio = null;
			}
		}

		var canvas = new OffscreenCanvas( W, H );
		var ctx = canvas.getContext( '2d' );
		for ( var i = 0; i < total; i++ ) {
			if ( err ) { throw err; }
			frame( ctx, A, m, TL, i / FPS );
			var vf = new VideoFrame( canvas, { timestamp: Math.round( i * 1e6 / FPS ), duration: Math.round( 1e6 / FPS ) } );
			venc.encode( vf, { keyFrame: i % 60 === 0 } );
			vf.close();
			await room( venc, 6 );
			if ( progress && i % 10 === 0 ) { progress( i / total ); }
		}
		await venc.flush();
		if ( aenc ) { await aenc.flush(); }
		if ( err ) { throw err; }
		muxer.finalize();
		if ( progress ) { progress( 1 ); }
		return new Blob( [ target.buffer ], { type: 'video/mp4' } );
	}

	window.SFVideo = { make: make, supported: supported, timeline: timeline };

	/* ------------------------------------------------------------------ */
	/* The background worker: asks the plugin for work, makes it, uploads   */
	/* ------------------------------------------------------------------ */

	if ( ! CFG.enabled ) { return; }

	var chip = null;
	function say( msg, tone ) {
		if ( ! chip ) {
			chip = document.createElement( 'div' );
			chip.id = 'sf-vm';
			chip.setAttribute( 'role', 'status' );
			chip.style.cssText = 'position:fixed;right:12px;bottom:12px;z-index:100000;max-width:min(86vw,360px);padding:10px 14px;border-radius:14px;font:600 13px/1.35 system-ui,sans-serif;background:#0A1B44;color:#fff;box-shadow:0 6px 24px rgba(10,27,68,.3)';
			document.body.appendChild( chip );
		}
		chip.textContent = msg;
		chip.style.background = tone === 'bad' ? '#8f1d31' : ( tone === 'ok' ? '#0b7a5a' : '#0A1B44' );
	}

	function api( path, opts ) {
		opts = opts || {};
		opts.headers = Object.assign( { 'X-WP-Nonce': CFG.nonce }, opts.headers || {} );
		opts.credentials = 'same-origin';
		return fetch( CFG.rest + path, opts ).then( function ( r ) {
			return r.json().catch( function () { return {}; } ).then( function ( j ) {
				if ( ! r.ok ) { throw new Error( j.message || ( 'Error ' + r.status ) ); }
				return j;
			} );
		} );
	}

	var busy = false;
	var wake = null;

	async function work() {
		if ( busy ) { return; }
		if ( ! supported() ) {
			say( 'Video maker: this browser cannot make video here. Open the page in Chrome or Edge over https.', 'bad' );
			return;
		}
		busy = true;
		try {
			var next = await api( '/video/next' );
			if ( ! next.job ) {
				say( 'Video maker ready. Keep this page open and the video is made when a match ends.' );
				return;
			}
			var job = next.job;
			say( 'Making the video for ' + job.payload.winner + ' v ' + job.payload.loser + ' ... 0%' );
			try { if ( navigator.wakeLock ) { wake = await navigator.wakeLock.request( 'screen' ); } } catch ( e ) { wake = null; }
			var blob = await make( job.payload, function ( p ) { say( 'Making the video for ' + job.payload.winner + ' v ' + job.payload.loser + ' ... ' + Math.round( p * 100 ) + '%' ); } );
			say( 'Sending the video ...' );
			var fd = new FormData();
			fd.append( 'id', job.id );
			fd.append( 'file', blob, 'result.mp4' );
			await api( '/video/upload', { method: 'POST', body: fd } );
			say( 'Video made and sent. It is posted to Facebook shortly.', 'ok' );
		} catch ( e ) {
			say( 'Video maker: ' + e.message, 'bad' );
			try { await api( '/video/fail', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify( { id: ( typeof job !== 'undefined' && job ) ? job.id : 0, message: e.message } ) } ); } catch ( e2 ) { /* ignore */ }
		} finally {
			if ( wake ) { try { wake.release(); } catch ( e3 ) { /* ignore */ } wake = null; }
			busy = false;
		}
	}

	window.SFVideoMaker = { work: work };
	setTimeout( work, 1500 );
	setInterval( work, 30000 );
	document.addEventListener( 'visibilitychange', function () { if ( document.visibilityState === 'visible' ) { work(); } } );
}() );
