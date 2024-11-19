import { store, getContext, getElement } from '@wordpress/interactivity';

/* eslint-disable no-console */
console.log('action-share is here');
/* eslint-enable no-console */

const { state } = store("action-share", {
    state: {
        get isOpen() {
            const context = getContext();
            return context.isOpen;
        }
    },
    actions: {
        toggle: () => {
            const context = getContext();
            context.isOpen = !context.isOpen;
        },
        shareOnFacebook: () => {
            const context = getContext();
            const postUrl = context.postUrl;
            const facebookShareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(postUrl)}`;
            window.open(
                facebookShareUrl,
                'facebookShareWindow',
                'width=500,height=500,top=100,left=100,toolbar=0,menubar=0,scrollbars=1,resizable=1'
            );
        },
        shareOnX: () => {
            const context = getContext();
            const postUrl = context.postUrl;
            const postTitle = encodeURIComponent(context.postTitle);
            const twitterShareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(postUrl)}&text=${postTitle}`;
            window.open(
                twitterShareUrl,
                'twitterShareWindow',
                'width=500,height=500,top=100,left=100,toolbar=0,menubar=0,scrollbars=1,resizable=1'
            );
        },
        shareOnPinterest: () => {
            const context = getContext();
            const postUrl = context.postUrl;
            const postTitle = encodeURIComponent(context.postTitle);
            const postImage = encodeURIComponent(context.postImage);
            const pinterestShareUrl = `https://www.pinterest.com/pin/create/button/?url=${encodeURIComponent(postUrl)}&media=${postImage}&description=${postTitle}`;
            window.open(
                pinterestShareUrl,
                'pinterestShareWindow',
                'width=500,height=500,top=100,left=100,toolbar=0,menubar=0,scrollbars=1,resizable=1'
            );
        },
        shareOnLinkedIn: () => {
            const context = getContext();
            const postUrl = context.postUrl;
            const postTitle = encodeURIComponent(context.postTitle);
            const linkedInShareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(postUrl)}&title=${postTitle}`;
            window.open(
                linkedInShareUrl,
                'linkedInShareWindow',
                'width=500,height=500,top=100,left=100,toolbar=0,menubar=0,scrollbars=1,resizable=1'
            );
        },
        shareOnReddit: () => {
            const context = getContext();
            const postUrl = context.postUrl;
            const postTitle = encodeURIComponent(context.postTitle);
            const redditShareUrl = `https://www.reddit.com/submit?url=${encodeURIComponent(postUrl)}&title=${postTitle}`;
            window.open(
                redditShareUrl,
                'redditShareWindow',
                'width=500,height=500,top=100,left=100,toolbar=0,menubar=0,scrollbars=1,resizable=1'
            );
        },
        shareOnPocket: () => {
            const context = getContext();
            const postUrl = context.postUrl;
            const pocketShareUrl = `https://getpocket.com/save?url=${encodeURIComponent(postUrl)}`;
            window.open(
                pocketShareUrl,
                'pocketShareWindow',
                'width=500,height=500,top=100,left=100,toolbar=0,menubar=0,scrollbars=1,resizable=1'
            );
        },
        shareByEmail: () => {
            const context = getContext();
            const postUrl = context.postUrl;
            const postTitle = encodeURIComponent(context.postTitle);
            const emailShareUrl = `mailto:?subject=${postTitle}&body=${postUrl}`;
            window.location.href = emailShareUrl;
        },
        copyUrl: () => {
            const context = getContext();
            const postUrl = context.postUrl; // Get the post URL from the context
            const copyMessage = document.querySelector('.caes-hub-copy-url__tooltip');
            
            // Use modern Clipboard API to copy the URL
            navigator.clipboard.writeText(postUrl)
                .then(() => {
                    // Display success message
                    copyMessage.innerText = "Copied!";
                    copyMessage.style.display = 'block';

                    // Hide the message after 2 seconds
                    setTimeout(() => {
                        copyMessage.style.display = 'none';
                    }, 2000);
                })
                .catch(() => {
                    // Display error message if copying fails
                    copyMessage.innerText = "Failed to copy!";
                    copyMessage.style.display = 'block';

                    setTimeout(() => {
                        copyMessage.style.display = 'none';
                    }, 2000);
                });
        }
    },
    callbacks: {
        openModal: () => {
            const { ref } = getElement();
            const context = getContext();
            if (state.isOpen) {
                ref.style.opacity = '0';
                ref.style.visibility = 'visible';
                setTimeout(() => {
                    ref.showModal();
                    ref.style.opacity = '1';
                }, 10);
            } else {
                ref.style.opacity = '0';
                setTimeout(() => {
                    ref.close();
                    ref.style.visibility = 'hidden';
                }, 300);
            }
            // Make sure the modal is closed when the user hits escape
            ref.addEventListener('close', () => {
                ref.style.visibility = 'hidden';
                ref.style.opacity = '0';
                setTimeout(() => {
                    context.isOpen = false;
                }, 300);
            });
        },
        showCopyTooltip: (copyButton, copyMessage, message) => {
            copyMessage.innerText = message;
            copyMessage.style.display = 'block';
            
            // Position the tooltip above the button
            const rect = copyButton.getBoundingClientRect();
            copyMessage.style.left = `${rect.left + window.pageXOffset}px`;
            copyMessage.style.top = `${rect.top + window.pageYOffset - 30}px`; // Adjust the position above the button
            
            // Hide the tooltip after 2 seconds
            setTimeout(() => {
                copyMessage.style.display = 'none';
            }, 2000);
        }
    },
});