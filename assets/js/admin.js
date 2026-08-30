/*!
 * Vazir Font Plugin - Admin JavaScript
 * Version: 1.3.0
 * Refactored for modern standards and compatibility with refactored PHP code.
 */

( function( $ ) {
    'use strict';

    // Cache DOM elements
    let $fontWeights = null;
    let $previewParagraphs = null;
    let $resetButton = null;
    let l10n = window.vazirFontAdminL10n || { confirmReset: '' };

    /**
     * Show/hide preview paragraphs based on selected font weights.
     */
    const updateFontPreview = () => {
        if ( ! $fontWeights || ! $previewParagraphs ) {
            return;
        }

        const selectedWeights = $fontWeights.filter( ':checked' ).map( function() {
            return $( this ).val();
        } ).get();

        $previewParagraphs.each( function() {
            const $this = $( this );
            const weight = $this.css( 'font-weight' );

            if ( selectedWeights.includes( weight ) ) {
                $this.show();
            } else {
                $this.hide();
            }
        } );
    };

    /**
     * Reset form fields to default values.
     * Uses the same defaults as the PHP option schema.
     */
    const resetToDefaults = ( event ) => {
        if ( event ) {
            event.preventDefault();
        }

        if ( ! l10n.confirmReset || confirm( l10n.confirmReset ) ) {
            // Checkboxes: enable frontend, admin, gravity forms
            $( 'input[name="vazir_font_options[enable_frontend]"]' ).prop( 'checked', true );
            $( 'input[name="vazir_font_options[enable_admin]"]' ).prop( 'checked', true );
            $( 'input[name="vazir_font_options[enable_gravity_forms]"]' ).prop( 'checked', true );

            // Font weights: select all (default behavior)
            $( 'input[name="vazir_font_options[font_weights][]"]' ).prop( 'checked', true );

            // Exclude selectors textarea: keep in sync with VazirFontPlugin defaults.
            const defaultSelectors = [
                '.dashicons',
                '.menu-icon',
                '.menu-image',
                '[class^="dashicons-"]',
                '[class*=" dashicons-"]',
                '[class^="fa-"]',
                '[class*=" fa-"]',
                '.material-icons',
                '[data-icon]:before'
            ].join( '\n' );

            $( 'textarea[name="vazir_font_options[exclude_selectors]"]' ).val( defaultSelectors );

            // Trigger preview update
            updateFontPreview();
        }
    };

    /**
     * Optional: Disable submit button to prevent double submission.
     */
    const handleFormSubmission = ( event ) => {
        const $submitButton = $( '#submit' );
        if ( $submitButton.prop( 'disabled' ) ) {
            event.preventDefault();
            return;
        }
        $submitButton.prop( 'disabled', true ).css( 'opacity', '0.6' );
        // Re-enable after 3 seconds (fallback, but form will redirect)
        setTimeout( () => {
            $submitButton.prop( 'disabled', false ).css( 'opacity', '' );
        }, 3000 );
    };

    /**
     * Initialize admin functionality.
     */
    const init = () => {
        // Cache selectors
        $fontWeights = $( 'input[name="vazir_font_options[font_weights][]"]' );
        $previewParagraphs = $( '.vazir-font-preview__text p' );
        $resetButton = $( '.vazir-font-reset' );

        // Bind events
        $( document ).on( 'change', 'input[name="vazir_font_options[font_weights][]"]', updateFontPreview );
        $( document ).on( 'click', '.vazir-font-reset', resetToDefaults );
        $( document ).on( 'submit', 'form', handleFormSubmission );

        // Initial preview update
        updateFontPreview();
    };

    // Start when DOM is ready
    $( init );
} )( jQuery );
