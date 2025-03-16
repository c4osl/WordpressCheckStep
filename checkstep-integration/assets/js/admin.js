/**
 * CheckStep Integration Admin Scripts
 */
(function($) {
    'use strict';

    // Initialize admin functionality
    $(document).ready(function() {
        // Toggle password field visibility
        $('.toggle-field-visibility').on('click', function(e) {
            e.preventDefault();
            var $input = $(this).prev('input');
            var type = $input.attr('type');

            $input.attr('type', type === 'password' ? 'text' : 'password');
            $(this).text(type === 'password' ? 
                CheckStepAdmin.i18n.hideText : 
                CheckStepAdmin.i18n.showText
            );
        });

        // Handle queue item actions
        $('.queue-action-button').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var itemId = $button.data('item-id');
            var action = $button.data('action');

            // Disable button during processing
            $button.prop('disabled', true);

            // Send AJAX request to process queue item
            $.post(CheckStepAdmin.ajaxurl, {
                action: 'process_checkstep_queue_item',
                nonce: CheckStepAdmin.nonce,
                item_id: itemId,
                action_type: action
            }, function(response) {
                if (response.success) {
                    // Reload table row or entire page
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            }).always(function() {
                $button.prop('disabled', false);
            });
        });

        // Update queue status
        function updateQueueStatus() {
            $.post(CheckStepAdmin.ajaxurl, {
                action: 'get_checkstep_queue_status',
                nonce: CheckStepAdmin.nonce
            }, function(response) {
                if (response.success) {
                    $('.queue-status-count').text(response.data.pending_count);
                    $('.queue-last-processed').text(response.data.last_processed);
                }
            });
        }

        // Update queue status every minute if the tab is visible
        if ($('.queue-status').length) {
            updateQueueStatus();
            setInterval(function() {
                if (document.visibilityState === 'visible') {
                    updateQueueStatus();
                }
            }, 60000);
        }

        // Handle filter form submission
        $('#filter-queue-form').on('submit', function(e) {
            // Let the form submit normally - no need for AJAX
            return true;
        });

        // Save settings via AJAX
        $('#checkstep-settings-form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $submit = $form.find(':submit');
            var $notice = $('.settings-updated');

            $submit.prop('disabled', true);
            $notice.removeClass('notice-success notice-error').hide();

            $.post(CheckStepAdmin.ajaxurl, $form.serialize(), function(response) {
                if (response.success) {
                    $notice.addClass('notice-success')
                           .text(CheckStepAdmin.i18n.settingsSaved)
                           .show();
                } else {
                    $notice.addClass('notice-error')
                           .text(CheckStepAdmin.i18n.settingsError + ': ' + response.data.message)
                           .show();
                }
            }).always(function() {
                $submit.prop('disabled', false);
            });
        });

        // Test API connection button
        $('#test-checkstep-connection').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $status = $('.checkstep-status');

            $button.prop('disabled', true);
            $status.removeClass('connected not-connected')
                   .text(CheckStepAdmin.i18n.testingConnection);

            // Make AJAX call to test connection
            $.post(CheckStepAdmin.ajaxurl, {
                action: 'test_checkstep_connection',
                nonce: CheckStepAdmin.nonce,
                api_key: $('#checkstep_api_key').val(),
                webhook_secret: $('#checkstep_webhook_secret').val()
            }, function(response) {
                if (response.success) {
                    $status.addClass('connected')
                           .text(CheckStepAdmin.i18n.connectionSuccess);
                } else {
                    $status.addClass('not-connected')
                           .text(CheckStepAdmin.i18n.connectionFailed + ': ' + response.data.message);
                }
            }).always(function() {
                $button.prop('disabled', false);
            });
        });
    });

})(jQuery);