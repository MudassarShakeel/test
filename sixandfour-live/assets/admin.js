/* Six & Four Live admin: Media Library pickers. */
( function ( $ ) {
	'use strict';

	function frame( title, onSelect ) {
		var f = wp.media( { title: title, button: { text: 'Use this picture' }, library: { type: 'image' }, multiple: false } );
		f.on( 'select', function () {
			onSelect( f.state().get( 'selection' ).first().toJSON() );
		} );
		f.open();
	}

	// Teams: choose or clear a team picture.
	$( document ).on( 'click', '.sf-pick', function () {
		var row = $( this ).closest( '.sf-team' );
		frame( $( this ).data( 'title' ) || 'Choose a picture', function ( a ) {
			row.find( '.sf-logo-id' ).val( a.id );
			var url = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
			row.find( '.sf-pic' ).html( '<img class="sf-logo-prev" alt="" src="' + url + '">' );
			row.find( '.sf-l span' ).text( 'Your picture (save to keep it)' );
		} );
	} );
	$( document ).on( 'click', '.sf-clear', function () {
		var row = $( this ).closest( '.sf-team' );
		row.find( '.sf-logo-id' ).val( '' );
		row.find( '.sf-l span' ).text( 'Picture removed (save to keep it)' );
	} );

	// Manual post: pick from the Media Library or preview an uploaded file.
	$( '#sf-media' ).on( 'click', function () {
		frame( 'Choose a picture to post', function ( a ) {
			$( '#sf-att' ).val( a.id );
			$( '#sf-image' ).val( '' );
			$( '#sf-preview' ).attr( 'src', a.url ).prop( 'hidden', false );
		} );
	} );
	$( '#sf-image' ).on( 'change', function () {
		var file = this.files && this.files[ 0 ];
		if ( ! file ) {
			return;
		}
		$( '#sf-att' ).val( '' );
		var reader = new FileReader();
		reader.onload = function ( e ) {
			$( '#sf-preview' ).attr( 'src', e.target.result ).prop( 'hidden', false );
		};
		reader.readAsDataURL( file );
	} );
} )( jQuery );
