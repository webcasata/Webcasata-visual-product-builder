/**
 * Webcasata Visual Product Builder — attribute term image fields.
 *
 * Powers the "Select Image" / "Remove" buttons added to WooCommerce
 * product attribute term screens (Attributes → Flavour → Mango) by
 * class-wvpb-attribute-images.php. Kept separate from admin-builder.js,
 * which only loads on Customizer post type edit screens.
 */
( function ( $, wp ) {
	'use strict';

	$( document ).on( 'click', '.wvpb-term-media-upload', function ( e ) {
		e.preventDefault();

		var targetId = $( this ).data( 'target' );
		var $hidden  = $( '#' + targetId );
		var $preview = $( '#' + targetId + '-preview' );
		var $remove  = $( this ).siblings( '.wvpb-term-media-remove' );

		var frame = wp.media( {
			title: wvpbTermImagesL10n.selectImage,
			library: { type: [ 'image/png', 'image/svg+xml' ] },
			button: { text: wvpbTermImagesL10n.useThisImage },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			$hidden.val( attachment.id );
			$preview.attr( 'src', attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url );
			$preview.closest( '.wvpb-media-preview' ).show();
			$remove.show();
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.wvpb-term-media-remove', function ( e ) {
		e.preventDefault();

		var targetId = $( this ).data( 'target' );
		$( '#' + targetId ).val( '' );
		$( '#' + targetId + '-preview' ).closest( '.wvpb-media-preview' ).hide();
		$( this ).hide();
	} );

} )( jQuery, wp );
