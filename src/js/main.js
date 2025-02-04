// Back to top button

// Wait for page to load
document.addEventListener('DOMContentLoaded', function () {

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

});

