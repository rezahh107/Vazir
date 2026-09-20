/*!
 * Vazir Font Plugin - settings-page interactions.
 */
( function( $ ) {
	'use strict';

	const $form = $( '#vazir-font-settings-form' );
	if ( ! $form.length ) {
		return;
	}

	const $fontWeights = $form.find( 'input[name="vazir_font_options[font_weights][]"]' );
	const $previewSamples = $( '.vazir-font-preview__sample[data-weight]' );
	const $emptyState = $( '#vazir-font-preview-empty' );

	const updateFontPreview = () => {
		const selectedWeights = new Set(
			$fontWeights.filter( ':checked' ).map( function() {
				return $( this ).val();
			} ).get()
		);

		$previewSamples.each( function() {
			const $sample = $( this );
			$sample.prop( 'hidden', ! selectedWeights.has( String( $sample.data( 'weight' ) ) ) );
		} );

		$emptyState.prop( 'hidden', selectedWeights.size > 0 );
	};

	$fontWeights.on( 'change', updateFontPreview );
	updateFontPreview();
} )( jQuery );
