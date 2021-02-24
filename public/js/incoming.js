/*global $*/

$(document).ready(function() {
    // $.post = console.log;

    $('[data-role="incoming-tracks:calculate-audio-data"]').on('click', function (e) {
        var pathname = $(this).parents('[data-role="element"]').data('element-pathname');

        $.post('/common/task/calculate-audio-data', {
            pathname: pathname
        });
    });

    $('[data-role="incoming-tracks:move"]').on('click', function (e) {
        var pathname = $(this).parents('[data-role="element"]').data('element-pathname'),
            targetPathname = $(this).parents('[data-role="element"]').data('element-target-pathname');

        $.post('/common/task/move', {
            pathname: pathname,
            targetPathname: targetPathname,
        });
    });
});
