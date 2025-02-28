const { registerBlockType } = wp.blocks;
const { CheckboxControl } = wp.components;
const { useState, useEffect } = wp.element;
const { InnerBlocks } = wp.blockEditor;

registerBlockType('cw/content-warning', {
    title: 'Content Warning',
    icon: 'warning',
    category: 'design',
    attributes: {
        terms: {
            type: 'array',
            default: [],
        },
    },
    edit: function ({ attributes, setAttributes }) {
        const { terms } = attributes;
        const [availableTerms, setAvailableTerms] = useState([]);

        // Fetch available terms via REST API.
        useEffect(() => {
            wp.apiFetch({ path: '/wp/v2/content_warning' }).then((terms) => {
                setAvailableTerms(terms);
            });
        }, []);

        const termOptions = availableTerms.map((term) => ({
            value: term.id,
            label: term.name,
        }));

        const toggleTerm = (termId) => {
            setAttributes({
                terms: terms.includes(termId)
                    ? terms.filter((id) => id !== termId)
                    : [...terms, termId],
            });
        };

        return wp.element.createElement(
            'div',
            { className: 'deaddove-modal-wrapper editor-only' },
            wp.element.createElement('h4', null, 'Select Terms'),
            termOptions.map((term) =>
                wp.element.createElement(CheckboxControl, {
                    key: term.value,
                    label: term.label,
                    checked: terms.includes(term.value),
                    onChange: () => toggleTerm(term.value),
                })
            ),
            wp.element.createElement(InnerBlocks)
        );
    },
    save: () => {
        return wp.element.createElement(InnerBlocks.Content);
    },
});

// Initialize listeners on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    console.log("Initializing modal listeners...");
    initializeModalListeners();
});

function initializeModalListeners() {
    const modalWrappers = document.querySelectorAll('.deaddove-modal-wrapper');

    modalWrappers.forEach((modalWrapper) => {
        const showContentBtn = modalWrapper.querySelector('.deaddove-show-content-btn');
        const hideContentBtn = modalWrapper.querySelector('.deaddove-hide-content-btn');
        const modal = modalWrapper.querySelector('.deaddove-modal');
        const blurredContent = modalWrapper.querySelector('.deaddove-blurred-content');

        if (!showContentBtn || !hideContentBtn || !modal || !blurredContent) {
            console.error("Missing elements in modal wrapper:", { 
                showContentBtn, hideContentBtn, modal, blurredContent 
            });
            return; // Stop execution for this modalWrapper if elements are missing
        }

        console.log("Attaching event listeners...");

        showContentBtn.addEventListener('click', () => {
            console.log("Show content button clicked");
            modal.style.display = 'none'; // Hide modal
            blurredContent.style.display = 'block'; // Show blurred content
        });

        hideContentBtn.addEventListener('click', () => {
            console.log("Hide content button clicked");
            modal.style.display = 'block'; // Show modal
            blurredContent.style.display = 'none'; // Hide blurred content
        });
    });
}
