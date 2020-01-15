$(document).ready(function() {
//    $.post = console.log;

    $('[data-role="incoming-tracks:clean"]').click(function (e) {
        var pathname = $(e.target).parents('[data-role="element"]').data('element-pathname');

        $.post('/common/task/clean', {
            pathname: pathname
        });
    });

    $('[data-role="incoming-tracks:calculate-audio-data"]').click(function (e) {
        var pathname = $(e.target).parents('[data-role="element"]').data('element-pathname');

        $.post('/common/task/calculate-audio-data', {
            pathname: pathname
        });
    });

    $('[data-role="incoming-tracks:move"]').click(function (e) {
        var pathname = $(e.target).parents('[data-role="element"]').data('element-pathname'),
            targetPathname = $(e.target).parents('[data-role="element"]').data('element-target-pathname');

        console.log({
            pathname: pathname,
            targetPathname: targetPathname,
        });

        $.post('/common/task/move', {
            pathname: pathname,
            targetPathname: targetPathname,
        });
    });
});
