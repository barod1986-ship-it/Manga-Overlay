(() => {
    'use strict';

    document.documentElement.classList.add('mol-js');

    const toggle = document.querySelector('[data-mol-filter-toggle]');
    const drawer = document.getElementById('mol-library-filters');
    const closeControls = document.querySelectorAll('[data-mol-filter-close]');
    const desktop = window.matchMedia('(min-width: 900px)');
    let lastFocus = null;

    if (!toggle || !drawer) {
        return;
    }

    const close = (restoreFocus = true) => {
        drawer.classList.remove('is-open');
        document.body.classList.remove('mol-filter-open');
        toggle.setAttribute('aria-expanded', 'false');
        if (restoreFocus && lastFocus instanceof HTMLElement) {
            lastFocus.focus();
        }
    };

    const open = () => {
        lastFocus = document.activeElement;
        drawer.classList.add('is-open');
        document.body.classList.add('mol-filter-open');
        toggle.setAttribute('aria-expanded', 'true');
        const firstField = drawer.querySelector('input, select, button, a[href]');
        if (firstField instanceof HTMLElement) {
            firstField.focus();
        }
    };

    toggle.addEventListener('click', () => {
        if (drawer.classList.contains('is-open')) {
            close();
        } else {
            open();
        }
    });

    closeControls.forEach((control) => {
        control.addEventListener('click', () => close());
    });

    document.addEventListener('keydown', (event) => {
        if ('Escape' === event.key && drawer.classList.contains('is-open')) {
            close();
        }
    });

    desktop.addEventListener('change', (event) => {
        if (event.matches) {
            close(false);
        }
    });
})();

