$(function () {
    $('[data-role="run-collection-analysis"]').on('click', function () {
        var $btn = $(this);

        $btn.prop('disabled', true).text('Analizowanie...');

        $.post('/common/task/run-collection-analysis', function () {
            $btn.text('Analiza uruchomiona');
        });
    });
});
