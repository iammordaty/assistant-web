$(function () {
    $('[data-toggle="popover"]').popover();

    $('[data-element="log-viewer"').each(function () {
        var $viewer = $(this),
            log = $viewer.data('log'),
            mtime = $viewer.data('mtime'),
            lines = $viewer.data('maxlines');

        setInterval(function () {
            $.get('/common/log/ajax', { log: log, lines: lines }, function (response) {
                var $newViewer = $(response),
                    newMtime = $newViewer.data('mtime');

                if (newMtime > mtime) {
                    $viewer.html($newViewer);

                    $('[data-toggle="popover"]').popover();
                }
            });
        }, 2000);
    });
});