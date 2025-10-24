/*global $*/

import initAutocompleter from './modules/autocomplete.js';

window.initTooltips = container => {
    const tooltipTriggerList = container.querySelectorAll('[data-bs-toggle="tooltip"]');
    [ ...tooltipTriggerList ].map(trigger => new bootstrap.Tooltip(trigger));
}

window.initPopovers = container => {
    const popoverTriggerList = container.querySelectorAll('[data-bs-toggle="popover"]');
    [ ...popoverTriggerList ].map(trigger => new bootstrap.Popover(trigger));
}

window.initSortableTables = container => {
    const tableSortableList = container.querySelectorAll('[data-sortable="true"]');
    [ ...tableSortableList ].map(table => {
        const $table = $(table);

        $table.tablesorter();
        $table.find('th').addClass('cursor-pointer');
    });
}

$(() => {
    const $inputs = $('[data-role="autocompleter"]');

    $inputs.each((i, input) => initAutocompleter($(input)));

    $(document).on('keydown', e => {
        var activeElement = document.activeElement.tagName.toLowerCase();

        if (activeElement === 'textarea' || activeElement === 'input') {
            return;
        }

        if (e.key === '/') {
            e.preventDefault();

            $('header [data-role="autocompleter"]')[0].focus();
        }
    });
});
