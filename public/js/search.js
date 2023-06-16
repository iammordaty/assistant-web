/* global $ */

$(function () {
	// w znacznej mierze to jest to samo co w track.js, więc w przyszłości
	// można pokusić się o połączeniu obu rozwiązań

	let debounceTimerId;

	const debounce = (func, delay) => {
		clearTimeout(debounceTimerId)

		debounceTimerId = setTimeout(func, delay)
	}

	const reloadTracks = ($list, $form, $sort) => {
		$list.fadeTo(350, 0.60);

		const data = new URLSearchParams($form.serialize());
		data.set('sort', $sort.val());

		$.ajax({
			url: $form.attr('action'),
			data: data.toString(),
			dataType: 'html',
			cache: false,
			success: response => $list
				.stop(true, false, true)
				.html(response)
				.fadeTo(100, 1)
		});
	};

	const $container = $('[data-role="track-search:container"]');
	const $list = $('[data-role="track-search:list"]');

	if ($list.length === 0) {
		// pierwsze wyszukiwanie wykonywane jest za pomocą zwykłego GET,
		// więc nie ma potrzeby podpinania eventów

		return;
	}

	const $form = $container.find('[data-role="track-search:form"]');

	$form.data('previous-state', $form.serialize());

	$form.find('input').on('keyup change', function () {
		const currentFormState = $form.serialize();
		const previousFormState = $form.data('previous-state');

		if (currentFormState === previousFormState) {
			return;
		}

		$form.data('previous-state', currentFormState);

		debounce(() => reloadTracks($list, $form, $container.find('[data-role="track-search:list-sort"]')), 400);
	});

	$container.on('change', function (e) {
		const $input = $(e.originalEvent.target);
		const isSort = $input.data('role') === 'track-search:list-sort';

		if (isSort) {
			reloadTracks($list, $form, $container.find('[data-role="track-search:list-sort"]'));
		}
	});
});
