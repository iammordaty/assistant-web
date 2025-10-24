/* global $ */

const initAutoRefresh = $view => {
    const maxLines = $view.data('lines') || 50;

    setInterval(function () {
        const latestEntryDate = $view.find('[data-role="log:log-entry"]:first').data('log-entry-date');

        $.get($view.data('auto-refresh-url'), {
            maxLines,
            fromDate: latestEntryDate,
        }, response => {
            const $entries = $(response).find('[data-role="log:log-entry"]');

            if ($entries.length === 0) {
                return;
            }

            $view.prepend($entries)

            initPopovers($view[0]);
        });
    }, 2000);
};

$(() => {
    $('[data-element="log:log-view"]').each((i, logView) => {
        const $view = $(logView);

        if ($view.data('auto-refresh')) {
            initAutoRefresh($view);
        }
    });
});
