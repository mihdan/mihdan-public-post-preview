( function( $ ) {
	'use strict';

	var wp = window.wp,
		$toggler = $( '#mppp_toggler' ),
		$link = $( '#mppp_link' );

	$toggler.on( 'click', function ( e ) {
		var $toggler = $( this );
		wp.ajax.send( 'mppp_toggle', {
			data: {
				value: $toggler.prop( 'checked' ),
				post_id: parseInt( $toggler.data( 'post-id' ) )
			},
			success: function ( response ) {

				if ( 1 === response.value ) {
					$link.text( response.link ).removeClass( 'hidden' );
				} else {
					$link.empty().addClass( 'hidden' );
				}
			}
		} );
	} );
} )( window.jQuery );

// eof;
