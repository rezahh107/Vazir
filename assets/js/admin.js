/*!
 * Vazir Font Plugin - Admin JavaScript
 * Version: 1.1.0
 */

(function($) {
    'use strict';

    window.VazirFontAdmin = {
        init: function() {
            this.bindEvents();
            this.initFontPreview();
        },

        bindEvents: function() {
            $(document).on('change', 'input[name="vazir_font_options[font_weights][]"]', this.handleWeightChange);
            $(document).on('change', 'input[name^="vazir_font_options[enable_"]', this.handleEnableChange);
            $(document).on('input', 'textarea[name="vazir_font_options[exclude_selectors]"]', this.handleExcludeSelectorChange);
            $(document).on('click', '#submit', this.handleFormSubmission);
            $(document).on('click', '.vazir-font-reset', this.resetToDefaults);
        },

        handleWeightChange: function() {
            var $preview = $('.font-preview-text p');
            var selectedWeights = $('input[name="vazir_font_options[font_weights][]"]:checked').map(function() {
                return $(this).val();
            }).get();

            $preview.each(function() {
                var $this = $(this);
                var weight = $this.css('font-weight');
                
                if (selectedWeights.indexOf(weight) === -1) {
                    $this.hide();
                } else {
                    $this.show();
                }
            });
        },

        handleEnableChange: function() {
            // You can add logic here to show/hide sections based on enable status
        },

        handleFormSubmission: function(e) {
            // Add any pre-submission validation here
        },

        resetToDefaults: function(e) {
            e.preventDefault();
            
            var l10n = window.vazirFontAdminL10n;

            if (!l10n || !l10n.confirmReset) {
                return;
            }

            var message = l10n.confirmReset;

            if (confirm(message)) {
                $('input[name="vazir_font_options[enable_frontend]"]').prop('checked', true);
                $('input[name="vazir_font_options[enable_admin]"]').prop('checked', true);
                $('input[name="vazir_font_options[enable_gravity_forms]"]').prop('checked', true);
                
                $('input[name="vazir_font_options[font_weights][]"]').prop('checked', true);
                
                $('textarea[name="vazir_font_options[exclude_selectors]"]').val(
                    ".dashicons\n.dashicons-before:before\n[class*=\"dashicons\"]:before\n.wp-menu-image\ni.fa\n[class*=\"icon-\"]:before\n.material-icons\n[data-icon]:before"
                );
            }
        },

        initFontPreview: function() {
            var $preview = $('.font-preview-text');
            
            if ($preview.length) {
                this.handleWeightChange();
            }
        }
    };

    // Initialize
    $(document).ready(function() {
        VazirFontAdmin.init();
    });
})(jQuery);
