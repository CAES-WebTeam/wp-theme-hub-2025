// Back to top button

// Wait for page to load
document.addEventListener('DOMContentLoaded', function () {

    /*** START TO TOP BUTTON */
    // Make back to top button
    const toTopButton = document.createElement('a');
    toTopButton.classList.add('caes-hub-to-top');
    toTopButton.href = '#';
    toTopButton.innerHTML = `<span>Back to top</span>`;

    // Append toTopButton to main
    const main = document.querySelector('main');
    main.appendChild(toTopButton);

    // Show or hide button if scroll position is lower than viewport height
    window.addEventListener('scroll', function () {
        if (window.scrollY > window.innerHeight / 2) {
            if (!toTopButton.classList.contains('caes-hub-to-top-visible')) {
                toTopButton.classList.add('caes-hub-to-top-visible');
            }
        } else {
            if (toTopButton.classList.contains('caes-hub-to-top-visible')) {
                toTopButton.classList.add('caes-hub-to-top-hide');
                setTimeout(() => {
                    toTopButton.classList.remove('caes-hub-to-top-visible', 'caes-hub-to-top-hide');
                }, 250); // Match the timeout to the CSS transition duration for opacity
            }
        }
    });

    // Add event listener to toTopButton
    toTopButton.addEventListener('click', function (event) {
        event.preventDefault();
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    /*** END TO TOP BUTTON */

    /*** START TABLE FIX */
    // Find tables without wp-block-table class
    const tables = document.querySelectorAll('.wp-block-post-content table:not(.wp-block-table table)');
    tables.forEach(table => {
        // Check if the table is already inside a <figure.wp-block-table>
        if (!table.closest('figure.wp-block-table')) {
            // Add wrapper div
            const wrapper = document.createElement('div');
            wrapper.classList.add('responsitable-wrapper');
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    });

});

