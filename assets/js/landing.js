'use strict';

// Keep guidance collapsed until a visitor asks for help.
const landingHelp = document.getElementById('help');
if (landingHelp) {
    document.querySelectorAll('a[href="#help"]').forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault();
            landingHelp.open = true;
            landingHelp.querySelector('summary').focus();
            landingHelp.scrollIntoView({ block: 'start' });
        });
    });
    if (window.location.hash === '#help') landingHelp.open = true;
}
