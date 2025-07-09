document.addEventListener('DOMContentLoaded', function() {
    const flipCards = document.querySelectorAll('.flip-card-container[data-card-id]');
    
    flipCards.forEach(function(card) {
        const cardId = card.getAttribute('data-card-id');
        const frontSide = document.getElementById(cardId + '-front');
        const backSide = document.getElementById(cardId + '-back');
        
        function setFlipState(isFlipped) {
            card.setAttribute('aria-pressed', String(isFlipped));
            
            // Update aria-hidden states
            if (frontSide) {
                frontSide.setAttribute('aria-hidden', String(isFlipped));
            }
            if (backSide) {
                backSide.setAttribute('aria-hidden', String(!isFlipped));
            }
            
            // Manage tabindex for focusable elements
            updateTabIndex(isFlipped);
        }
        
        function toggleFlip() {
            const isPressed = card.getAttribute('aria-pressed') === 'true';
            setFlipState(!isPressed);
        }
        
        function updateTabIndex(showBack) {
            // Get all focusable elements in both sides
            const frontFocusables = frontSide ? frontSide.querySelectorAll('a, button, input, textarea, select, [tabindex]:not([tabindex="-1"])') : [];
            const backFocusables = backSide ? backSide.querySelectorAll('a, button, input, textarea, select, [tabindex]:not([tabindex="-1"])') : [];
            
            // Set tabindex based on which side is visible
            frontFocusables.forEach(el => {
                el.setAttribute('tabindex', showBack ? '-1' : '0');
            });
            
            backFocusables.forEach(el => {
                el.setAttribute('tabindex', showBack ? '0' : '-1');
            });
        }
        
        // Initialize tabindex
        updateTabIndex(false);
        
        // Mouse hover handlers
        card.addEventListener('mouseenter', function() {
            setFlipState(true);
        });
        
        card.addEventListener('mouseleave', function() {
            setFlipState(false);
        });
        
        // Click handler (for mobile/touch devices)
        card.addEventListener('click', function() {
            toggleFlip();
        });
        
        // Keyboard handler
        card.addEventListener('keydown', function(event) {
            if ((event.code === 'Enter' || event.code === 'Space') && !event.repeat) {
                event.preventDefault();
                toggleFlip();
            }
        });
    });
});