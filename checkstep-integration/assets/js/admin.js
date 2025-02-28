/**
 * CheckStep Integration Admin Scripts
 *
 * Handles interactive functionality on the CheckStep integration settings page.
 * Provides AJAX-based API connection testing and field visibility toggling.
 *
 * @package CheckStep_Integration
 * @subpackage Admin
 * @since 1.0.0
 */
(function($) {
    'use strict';

    // Initialize admin functionality
    $(document).ready(function() {
        // Test API connection
        $('#test-checkstep-api').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $status = $('.checkstep-status');

            $button.prop('disabled', true);
            $status.removeClass('connected not-connected')
                   .text('Testing connection...');

            // AJAX call to test API connection
            $.post(ajaxurl, {
                action: 'test_checkstep_api',
                nonce: checkstepAdmin.nonce
            }, function(response) {
                if (response.success) {
                    $status.addClass('connected')
                           .text('Connected successfully');
                } else {
                    $status.addClass('not-connected')
                           .text('Connection failed: ' + response.data.message);
                }
                $button.prop('disabled', false);
            });
        });

        // Toggle API key visibility
        $('.toggle-api-key').on('click', function(e) {
            e.preventDefault();
            var $input = $('#checkstep_api_key');
            var type = $input.attr('type');

            $input.attr('type', type === 'password' ? 'text' : 'password');
            $(this).text(type === 'password' ? 'Hide' : 'Show');
        });
    });

})(jQuery);