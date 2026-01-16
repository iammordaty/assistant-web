import initAutocompleter from '../modules/autocomplete.js';

const initAutocompleters = container => {
    container
        .querySelectorAll('[data-role="autocompleter"]')
        .forEach(el => initAutocompleter(el));
}

const initAutocompleteShortcuts = (container) => {
    const headerAutocompleter = container.querySelector('header [data-role="autocompleter"]');

    container.addEventListener('keydown', event => {
        if (event.key !== '/') {
            return;
        }

        const active = document.activeElement;

        if (active instanceof HTMLInputElement || active instanceof HTMLTextAreaElement) {
            return;
        }

        event.preventDefault();

        const dashboardAutocompleter = container.querySelector('[data-role="track-search:form"] input');
        const target = dashboardAutocompleter ?? headerAutocompleter;

        target?.focus();
    });
}

const initTooltips = liveElementInitializer => {
    liveElementInitializer .init(
        '[data-bs-toggle="tooltip"]',
        el => new bootstrap.Tooltip(el)
    );
}

const initPopovers = (liveElementInitializer) => {
    liveElementInitializer.init(
        '[data-bs-toggle="popover"]',
        el => new bootstrap.Popover(el)
    );
}

const initSortableTables = (container) => {
    container
        .querySelectorAll('[data-sortable="true"]')
        .forEach(table => {
            const $table = $(table);

            $table.tablesorter();
            $table.find('th').addClass('cursor-pointer');
        });
}

const initScrollableBreadcrumbs = (container) => {
    container
        .querySelectorAll('[data-element="scrollable-breadcrumb"]')
        .forEach(el => {
            const update = () => el.dataset.hasOverflow = String(el.scrollWidth > el.clientWidth);

            update();

            new ResizeObserver(update).observe(el);
        });
}

export {
    initAutocompleters,
    initAutocompleteShortcuts,
    initTooltips,
    initPopovers,
    initSortableTables,
    initScrollableBreadcrumbs
};
