/* Mudassar Snippet Studio admin behaviour. No external requests. */
( function () {
	'use strict';
	var data = window.msstData || {};

	function el( tag, attrs, text ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			node.setAttribute( key, attrs[ key ] );
		} );
		if ( text ) {
			node.textContent = text;
		}
		return node;
	}

	/* Confirm dialogs and select-all. */
	document.addEventListener( 'click', function ( event ) {
		var target = event.target.closest ? event.target.closest( '[data-confirm]' ) : null;
		if ( target && ! window.confirm( ( data.i18n && data.i18n.confirm ) || 'Are you sure?' ) ) {
			event.preventDefault();
		}
		var all = event.target.closest ? event.target.closest( '[data-check-all]' ) : null;
		if ( all ) {
			document.querySelectorAll( 'input[name="ids[]"]' ).forEach( function ( box ) {
				box.checked = all.checked;
			} );
		}
	} );

	/* Copy helpers. */
	document.querySelectorAll( '.msst-copy' ).forEach( function ( node ) {
		node.style.cursor = 'pointer';
		node.addEventListener( 'click', function () {
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( node.textContent );
				node.title = ( data.i18n && data.i18n.copied ) || 'Copied';
			}
		} );
	} );

	/* Code editor + type/location switching (edit tab only). */
	var typeInputs = document.querySelectorAll( 'input[name="msst_type"]' );
	var textarea = document.getElementById( 'msst_code' );
	var locationSelect = document.getElementById( 'msst_location' );
	var paramWrap = document.getElementById( 'msst-param-wrap' );
	var editor = null;

	function currentType() {
		var checked = document.querySelector( 'input[name="msst_type"]:checked' );
		return checked ? checked.value : 'html';
	}

	function syncParam() {
		if ( paramWrap && locationSelect ) {
			paramWrap.hidden = ! /_paragraph$/.test( locationSelect.value );
		}
	}

	function syncLocations( keep ) {
		if ( ! locationSelect || ! data.locations ) {
			return;
		}
		var list = data.locations[ currentType() ] || {};
		var wanted = keep ? locationSelect.value : '';
		locationSelect.innerHTML = '';
		Object.keys( list ).forEach( function ( slug ) {
			var option = el( 'option', { value: slug }, list[ slug ] );
			if ( slug === wanted ) {
				option.selected = true;
			}
			locationSelect.appendChild( option );
		} );
		syncParam();
	}

	if ( textarea && ! textarea.readOnly && data.editor && window.wp && window.wp.codeEditor ) {
		editor = window.wp.codeEditor.initialize( textarea, data.editor );
		editor.codemirror.setOption( 'mode', data.modes[ currentType() ] );
	}
	typeInputs.forEach( function ( input ) {
		input.addEventListener( 'change', function () {
			syncLocations( false );
			if ( editor ) {
				editor.codemirror.setOption( 'mode', data.modes[ currentType() ] );
			}
		} );
	} );
	if ( locationSelect ) {
		locationSelect.addEventListener( 'change', syncParam );
		syncParam();
	}

	/* Conditional logic builder. */
	var holder = document.getElementById( 'msst-rules' );
	if ( ! holder || ! data.catalogue ) {
		return;
	}

	function fill( select, map, selected ) {
		select.innerHTML = '';
		Object.keys( map ).forEach( function ( key ) {
			var option = el( 'option', { value: key }, map[ key ] );
			if ( key === selected ) {
				option.selected = true;
			}
			select.appendChild( option );
		} );
	}

	function buildRule( gi, ri, rule ) {
		var row = el( 'div', { 'class': 'msst-rule' } );
		var name = 'msst_rules[' + gi + '][' + ri + ']';
		var type = el( 'select', { 'class': 'msst-input', name: name + '[type]' } );
		var labels = {};
		Object.keys( data.catalogue ).forEach( function ( key ) {
			labels[ key ] = data.catalogue[ key ].label;
		} );
		fill( type, labels, rule.type );
		var op = el( 'select', { 'class': 'msst-input', name: name + '[op]' } );
		var valueHolder = el( 'span', { style: 'flex:1;min-width:120px;display:flex' } );

		function renderValue( selected ) {
			var def = data.catalogue[ type.value ];
			fill( op, def.ops, rule.op );
			valueHolder.innerHTML = '';
			var input;
			if ( def.values ) {
				input = el( 'select', { 'class': 'msst-input', name: name + '[value]' } );
				fill( input, def.values, selected );
			} else {
				input = el( 'input', { 'class': 'msst-input', type: 'text', name: name + '[value]', maxlength: '200' } );
				input.value = selected || '';
			}
			valueHolder.appendChild( input );
		}
		type.addEventListener( 'change', function () {
			rule.op = '';
			renderValue( '' );
		} );
		renderValue( rule.value );

		var remove = el( 'button', { type: 'button', 'class': 'msst-btn msst-btn-outline' }, data.i18n.remove );
		remove.addEventListener( 'click', function () {
			row.remove();
			reindex();
		} );
		row.appendChild( type );
		row.appendChild( op );
		row.appendChild( valueHolder );
		row.appendChild( remove );
		return row;
	}

	function buildGroup( gi, rules ) {
		var group = el( 'div', { 'class': 'msst-group' } );
		group.appendChild( el( 'p', { 'class': 'msst-or' }, gi > 0 ? data.i18n.orLabel : '' ) );
		var list = el( 'div', { 'class': 'msst-rules-list' } );
		rules.forEach( function ( rule, ri ) {
			list.appendChild( buildRule( gi, ri, rule ) );
		} );
		group.appendChild( list );
		var add = el( 'button', { type: 'button', 'class': 'msst-btn msst-btn-outline' }, data.i18n.addRule );
		add.addEventListener( 'click', function () {
			list.appendChild( buildRule( 0, 0, { type: 'page_type', op: 'is', value: 'single' } ) );
			reindex();
		} );
		group.appendChild( add );
		return group;
	}

	/* Rename inputs after structural changes so indexes stay contiguous. */
	function reindex() {
		holder.querySelectorAll( '.msst-group' ).forEach( function ( group, gi ) {
			var label = group.querySelector( '.msst-or' );
			if ( label ) {
				label.textContent = gi > 0 ? data.i18n.orLabel : '';
			}
			group.querySelectorAll( '.msst-rule' ).forEach( function ( row, ri ) {
				row.querySelectorAll( '[name]' ).forEach( function ( field ) {
					field.name = field.name.replace( /^msst_rules\[\d+\]\[\d+\]/, 'msst_rules[' + gi + '][' + ri + ']' );
				} );
			} );
		} );
		holder.querySelectorAll( '.msst-group' ).forEach( function ( group ) {
			if ( ! group.querySelector( '.msst-rule' ) ) {
				group.remove();
			}
		} );
	}

	var groups = Array.isArray( data.rules ) ? data.rules : [];
	groups.forEach( function ( rules, gi ) {
		holder.appendChild( buildGroup( gi, rules ) );
	} );
	var addGroup = el( 'button', { type: 'button', 'class': 'msst-btn msst-btn-outline' }, data.i18n.addGroup );
	addGroup.addEventListener( 'click', function () {
		var group = buildGroup( holder.querySelectorAll( '.msst-group' ).length, [ { type: 'page_type', op: 'is', value: 'single' } ] );
		holder.insertBefore( group, addGroup );
		reindex();
	} );
	holder.appendChild( addGroup );
	reindex();
}() );
