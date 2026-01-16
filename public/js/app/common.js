import LiveElementInitializer from './live-element-initializer.js';

import {
    initAutocompleters,
    initAutocompleteShortcuts,
    initTooltips,
    initPopovers,
    initSortableTables,
    initScrollableBreadcrumbs,
} from './initializers.js';

export function createCommon(container) {
    const liveElementInitializer = new LiveElementInitializer(container);

    function init() {
        initTooltips(liveElementInitializer);
        initPopovers(liveElementInitializer);

        initAutocompleters(container);
        initAutocompleteShortcuts(container);
        initScrollableBreadcrumbs(container);
        initSortableTables(container);
    }

    return Object.freeze({ init });
}
