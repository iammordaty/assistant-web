/*global $*/

const initElementsActions = $container => {
    $container.find('input[type=checkbox]').prop('checked', false);

    const $actions = $container.find('[data-role="incoming-tracks:elements:actions"]');
    const $selects = $container.find('[data-role="incoming-tracks:elements:select"]');
    const $selectedCountLabel = $actions.find('[data-role="incoming-tracks:elements:selected-count"]');

    // zaznacz wszystko
    $container.find('[data-role="incoming-tracks:elements:select-all"]').on('change', function () {
        const isChecked = $(this).is(':checked');

        $selects
            .prop('checked', isChecked)
            .trigger('change');
    });

    // zaznacz za pomocą ctrl / cmd
    $container.find('[data-role="element"]').on('click', function (e) {
        const isCtrlOrCmdPressed = e.ctrlKey || e.metaKey;

        const target = e.originalEvent.target;
        const isLink = target.nodeName === 'A';
        const isCellWithSelect = target.nodeName === 'TD' && $(target).find('input[type=checkbox]').length === 1;

        if (isLink || (!isCtrlOrCmdPressed && !isCellWithSelect)) {
            return;
        }

        // tu trzeba jeszcze sprawdzić czy nie jest to kliknięcie w link z controlem (otwórz w nowej karcie)
        // i jeśli tak to też return

        const $row = $(this);
        const isElementSelected = $row.find('input[type=checkbox]').is(':checked');

        $row
            .find('[data-role="incoming-tracks:elements:select"]')
            .prop('checked', !isElementSelected)
            .trigger('change');
    });

    // zaktualizuj licznik i stan
    $container.find('[data-role="incoming-tracks:elements:select"]').on('change', function () {
        const selectedCount = $container.find('[data-role="incoming-tracks:elements:select"]:checked').length;
        const hasSelection = selectedCount > 0;
        const elementsType = $container.data('elements-type') === 'directory' ? 'katalogów' : 'utworów';

        $selectedCountLabel.text(`Zaznaczonych ${elementsType}: ${selectedCount}`);

        const elements = $container
            .find('[data-role="incoming-tracks:elements:select"]:checked')
            .map((i, element) => $(element).parents('[data-role="element" ]').data('element-pathname'))
            .get();

        $actions
            .toggleClass('d-none', !hasSelection)
            .toggleClass('d-flex', hasSelection)
            .data('context', elements);
    });

    $actions.find('[data-role="incoming-tracks:elements:remove"]').on('click', function () {
        const elements = $actions.data('context');

        showRemoveModal(elements);
    });

    $actions.find('[data-role="incoming-tracks:elements:rename"]').on('click', function () {
        const elements = $actions.data('context');

        showRenameModal(elements);
    });

    $container.find('[data-role="incoming-tracks:rename"]').on('click', function () {
        const elements = [ $(this).parents('[data-role="element"]').data('context') ];

        showRenameModal(elements);
    });
};

const showRenameModal = elements => {
    const $modal = $('#modal-rename');
    const myModal = new bootstrap.Modal($modal);

    $modal.on('show.bs.modal', function () {
        $modal.find('input[type="radio"]:first').prop('checked', true);
        $modal.find('input[type=checkbox]').prop('checked', false);

        $modal.find('input[name="elements"]').val(JSON.stringify(elements));
    });

    myModal.show();
};

const showRemoveModal = elements => {
    const $modal = $('#modal-remove');
    const myModal = new bootstrap.Modal($modal);

    $modal.on('show.bs.modal', function () {
        return $modal.find('input[name="elements"]').val(JSON.stringify(elements));
    });

    myModal.show();
};

$(() => {
    const $containers = $('[data-role="incoming-tracks:elements-container"]');

    $containers.each((i, container) => initElementsActions($(container)));
});

// --

$('[data-role="incoming-tracks:calculate-audio-data"]').on('click', function () {
    var pathname = $(this).parents('[data-role="element"]').data('element-pathname');

    $.post('/common/task/calculate-audio-data', {
        pathname: pathname
    });
});
