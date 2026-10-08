/* Scripts Manager By Mudassar – admin behaviour. No external requests. */
( function () {
	'use strict';
	var data = window.msstData || {};
	var i18n = data.i18n || {};

	/* Confirm dialogs and select-all. */
	document.addEventListener( 'click', function ( event ) {
		var target = event.target.closest ? event.target.closest( '[data-confirm]' ) : null;
		if ( target && ! window.confirm( i18n.confirm || 'Are you sure?' ) ) {
			event.preventDefault();
		}
		var all = event.target.closest ? event.target.closest( '[data-check-all]' ) : null;
		if ( all ) {
			document.querySelectorAll( 'input[name="ids[]"]' ).forEach( function ( box ) {
				box.checked = all.checked;
			} );
		}
	} );

	/* Click-to-copy. */
	document.querySelectorAll( '.msst-copy' ).forEach( function ( node ) {
		node.style.cursor = 'pointer';
		node.addEventListener( 'click', function () {
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( node.textContent );
				node.title = i18n.copied || 'Copied';
			}
		} );
	} );

	/* Type-to-filter inside the page/post/category lists. */
	document.querySelectorAll( '.msst-picker-search' ).forEach( function ( input ) {
		input.addEventListener( 'input', function () {
			var term = input.value.toLowerCase();
			input.parentNode.querySelectorAll( '.msst-picklist label' ).forEach( function ( label ) {
				label.hidden = term !== '' && label.textContent.toLowerCase().indexOf( term ) === -1;
			} );
		} );
	} );

	/* Add / edit form behaviour. */
	var form = document.getElementById( 'msst-form' );
	if ( ! form ) {
		return;
	}
	var typeSelect = document.getElementById( 'msst_type' );
	var displaySelect = document.getElementById( 'msst_display_on' );
	var locationSelect = document.getElementById( 'msst_location' );
	var locationRow = document.getElementById( 'msst-row-location' );
	var textarea = document.getElementById( 'msst_code' );
	var editor = null;

	function option( value, label ) {
		var node = document.createElement( 'option' );
		node.value = value;
		node.textContent = label;
		return node;
	}

	function syncLocations() {
		var list = ( data.locations && data.locations[ typeSelect.value === 'php' ? 'php' : 'html' ] ) || {};
		var keep = locationSelect.value;
		locationSelect.innerHTML = '';
		Object.keys( list ).forEach( function ( slug ) {
			var node = option( slug, list[ slug ] );
			if ( slug === keep ) {
				node.selected = true;
			}
			locationSelect.appendChild( node );
		} );
	}

	function syncDisplay() {
		var rows = ( data.rows && data.rows[ displaySelect.value ] ) || [];
		form.querySelectorAll( '[data-row]' ).forEach( function ( row ) {
			row.hidden = rows.indexOf( row.getAttribute( 'data-row' ) ) === -1;
		} );
		locationRow.hidden = displaySelect.value === 'shortcode';
	}

	function syncType() {
		var isPhp = typeSelect.value === 'php';
		/* PHP cannot be "Shortcode Only". */
		Array.prototype.forEach.call( displaySelect.options, function ( opt ) {
			if ( opt.value === 'shortcode' ) {
				opt.disabled = isPhp;
			}
		} );
		if ( isPhp && displaySelect.value === 'shortcode' ) {
			displaySelect.value = 'site_wide';
		}
		if ( editor ) {
			editor.codemirror.setOption( 'mode', data.modes[ typeSelect.value ] || 'text/html' );
		}
	}

	if ( textarea && ! textarea.readOnly && data.editor && window.wp && window.wp.codeEditor ) {
		editor = window.wp.codeEditor.initialize( textarea, data.editor );
		editor.codemirror.setOption( 'mode', data.modes[ typeSelect.value ] || 'text/html' );
		editor.codemirror.refresh();
	}

	typeSelect.addEventListener( 'change', function () {
		var wasPhp = locationSelect.querySelector( 'option[value="everywhere"]' ) !== null;
		if ( wasPhp !== ( typeSelect.value === 'php' ) ) {
			locationSelect.innerHTML = '';
		}
		syncLocations();
		syncType();
		syncDisplay();
	} );
	displaySelect.addEventListener( 'change', syncDisplay );
	syncType();
	syncDisplay();
}() );
