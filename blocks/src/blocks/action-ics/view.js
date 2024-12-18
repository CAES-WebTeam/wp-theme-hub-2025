document.addEventListener('DOMContentLoaded', function () {
    const button = document.querySelector('.caes-hub-action-ics__button');
    if (button) {
        button.addEventListener('click', function() {
            const icsUrl = button.getAttribute('data-ics-url');
            const link = document.createElement('a');
            link.href = icsUrl;
            link.download = 'event.ics';
            link.click();
        });
    }
});