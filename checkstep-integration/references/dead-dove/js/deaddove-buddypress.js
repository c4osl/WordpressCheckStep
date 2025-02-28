document.addEventListener('DOMContentLoaded', function () {
    const moreActionsButton = document.querySelector('.buddypress-users-blog-more-actions');
    const tagsField = document.querySelector('.buddypress-users-blog-tags');

    if (moreActionsButton && tagsField) {
        moreActionsButton.addEventListener('click', function () {
            const warningTermsContainer = document.createElement('div');
            warningTermsContainer.classList.add('deaddove-warning-terms-container');

            const warningTermsLabel = document.createElement('label');
            warningTermsLabel.textContent = 'Select Content Warning Terms:';
            warningTermsContainer.appendChild(warningTermsLabel);

            // Fetch available terms via REST API.
            wp.apiFetch({ path: '/wp/v2/content_warning' }).then((terms) => {
                terms.forEach((term) => {
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.name = 'deaddove_warning_terms[]';
                    checkbox.value = term.id;

                    const label = document.createElement('label');
                    label.textContent = term.name;
                    label.appendChild(checkbox);

                    warningTermsContainer.appendChild(label);
                });
            });

            tagsField.parentNode.insertBefore(warningTermsContainer, tagsField.nextSibling);
        });
    }
});
