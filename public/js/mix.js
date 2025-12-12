/* global $ */

/** wygenerowane przez ai, nie przywiązywać się */
$(() => {
	const $modal = $('#modal-mix-delete');
	const $modalName = $('#modal-mix-delete-name');
	const $confirmButton = $('#modal-mix-delete-confirm');

	let currentGuid = null;
	let $currentRow = null;
	let $currentButton = null;

	$('[data-role="mix:delete"]').on('click', function () {
		const $button = $(this);

        currentGuid = $button.data('mix-guid');
		$currentRow = $button.closest('tr');
		$currentButton = $button;

		$modalName.text($button.data('mix-name'));
		$modal.modal('show');
	});

	$confirmButton.on('click', function () {
		$currentButton.prop('disabled', true);
		$currentRow.fadeTo(350, 0.60);

		$.ajax({
			url: `/mix/${currentGuid}`,
			method: 'DELETE',
			dataType: 'json',
			success: () => window.location.reload(),
			error: function (xhr) {
				const errorMessage = xhr.responseJSON?.error || 'Nieznany błąd';

                $currentButton.prop('disabled', false);
				$currentRow.fadeTo(100, 1);

				alert('Nie udało się usunąć miksu: ' + errorMessage);
			},
		});
	});
});
