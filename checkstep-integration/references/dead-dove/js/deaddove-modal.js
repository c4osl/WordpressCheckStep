jQuery(document).ready(function ($) {
    $('.deaddove-modal-wrapper').each(function () {
        const wrapper = $(this);
        const blurredContent = wrapper.find('.deaddove-blurred-content');
        const modal = wrapper.find('.deaddove-modal');
        const showContentButton = wrapper.find('.deaddove-show-content-btn');
        const hideContentButton = wrapper.find('.deaddove-hide-content-btn');

        // Show modal on clicking blurred content
        blurredContent.on('click', function () {
            // Reset modal position before showing
            modal.css({
                display: 'flex', // Show modal
                top: '50%',      // Center vertically
                left: '50%',     // Center horizontally
                transform: 'translate(-50%, -50%)' // Perfect centering
            });
            modal.show(); // Display the modal
        });

        // Button to show content and hide modal
        showContentButton.on('click', function () {
            modal.hide();
            blurredContent.removeClass('deaddove-blur');
        });

        // Button to hide the modal
        hideContentButton.on('click', function () {
            modal.hide();
        });
    });

    // Handle BuddyBoss media content warnings
    $('.bp-media-item').each(function () {
        const wrapper = $(this);
        const blurredContent = wrapper.find('.deaddove-blurred-content');
        const modal = wrapper.find('.deaddove-modal');
        const showContentButton = wrapper.find('.deaddove-show-content-btn');
        const hideContentButton = wrapper.find('.deaddove-hide-content-btn');

        blurredContent.on('click', function () {
            modal.css({
                display: 'flex',
                top: '50%',
                left: '50%',
                transform: 'translate(-50%, -50%)'
            });
            modal.show();
        });

        showContentButton.on('click', function () {
            modal.hide();
            blurredContent.removeClass('deaddove-blur');
        });

        hideContentButton.on('click', function () {
            modal.hide();
        });
    });
});
