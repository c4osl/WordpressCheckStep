
document.addEventListener('DOMContentLoaded', function () {
    const mediaUploadForm = document.querySelector('.buddyboss-media-upload-form');

    if (mediaUploadForm) {
        const warningTermsSelect = document.createElement('select');
        warningTermsSelect.multiple = true;
        warningTermsSelect.name = 'deaddove_warning_terms[]';

        // Fetch available terms via REST API.
        wp.apiFetch({ path: '/wp/v2/content_warning' }).then((terms) => {
            terms.forEach((term) => {
                const option = document.createElement('option');
                option.value = term.id;
                option.textContent = term.name;
                warningTermsSelect.appendChild(option);
            });
        });

        const label = document.createElement('label');
        label.textContent = 'Select Content Warning Terms:';
        label.appendChild(warningTermsSelect);

        mediaUploadForm.appendChild(label);
    }
});