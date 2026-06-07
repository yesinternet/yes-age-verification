jQuery( function ( $ ) {
	var frame;
	var $logoIdInput = $( '#yes-age-verification__logo-id' );
	var $logoPreview = $( '#yes-age-verification__logo-preview' );
	var $logoButton  = $( '#yes-age-verification__logo-button' );

	$logoButton.on( 'click', function ( e ) {
		e.preventDefault();
		if ( frame ) { frame.open(); return; }

		frame = wp.media( {
			title:    'Choose logo',
			button:   { text: 'Use this image' },
			multiple: false,
			library:  { type: 'image' },
		} );

		frame.on( 'select', function () {
			var attachment   = frame.state().get( 'selection' ).first().toJSON();
			var attachmentId = parseInt( attachment.id || attachment.ID, 10 );
			if ( ! attachmentId ) return;

			$logoIdInput.val( attachmentId );

			var $logoImage = $( '<img>' ).attr( {
				src:   attachment.url,
				alt:   attachment.alt || '',
				style: 'max-height:70px;width:auto;border:1px solid #dcdcde;border-radius:3px;padding:4px;background:#fafafa',
			} );
			$logoPreview.html( $logoImage ).show();

			if ( ! $( '.yes-age-verification__logo-remove' ).length ) {
				$logoButton.after(
					$( '<button>' )
						.attr( { type: 'button' } )
						.addClass( 'button yes-age-verification__logo-remove' )
						.text( 'Remove' )
				);
			}
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.yes-age-verification__logo-remove', function () {
		$logoIdInput.val( 0 );
		$logoPreview.empty().hide();
		$( this ).remove();
	} );
} );
