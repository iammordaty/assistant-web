/* global $ */

import formatSeconds from './modules/format-seconds.js';
import initClassifierMetadataModal from './modules/classifier-metadata-modal.js';
import renderWaveform from './modules/render-waveform.js';
import toggleFavorite from './modules/toggle-favorite.js';

console.log('track.js loaded');

$(function () {
	const $wave = $('#wave-container');

	const wavesurfer = WaveSurfer.create({
		container: $wave[0],
		cursorWidth: 0,
		height: $wave[0].offsetHeight,
		progressColor: '#191919',
		renderFunction: renderWaveform,
	})

	const $trackPlayPause = $('[data-role="track:play-pause"]');

	wavesurfer.on('ready', function () {
		$('#wave-progress').fadeOut('fast', function () {
			$(this).remove();
		});

		$trackPlayPause.fadeTo('fast', 1).addClass('cursor-pointer');

		$('#wave-container').css('visibility', 'visible').hide().fadeIn('slow');
	});

	wavesurfer.on('interaction', function () {
		if (!wavesurfer.isPlaying()) {
			wavesurfer.play();
		}
	});

	const $currentTimeIndicator = $('#wave-time-current-time');

	wavesurfer.on('timeupdate', (currentTime) => {
		const trackDuration = wavesurfer.getDuration();
		let prefix = '';
		let time;

		if ($currentTimeIndicator.data('time-mode') === 'elapsed') {
			prefix = '';
			time = currentTime;
		} else {
			prefix = '-';
			time = trackDuration - currentTime;
		}

		$currentTimeIndicator.html(prefix + formatSeconds(time));
	});

	wavesurfer.on('play', function () {
		$trackPlayPause.addClass('active');
	});

	wavesurfer.on('pause', function () {
		$trackPlayPause.removeClass('active');
	});

	wavesurfer.on('finish', function () {
		$trackPlayPause.removeClass('active');
	});

	wavesurfer.on('error', function () {
		$('#wave-progress').fadeOut('fast', function () {
			$(this).remove();
		});

		$('#wave').html('<p class="fs-3 py-4 text-center text-muted">Wystąpił błąd podczas ładowania fali dźwiękowej</p>');
		$('#wave-container').css('visibility', 'visible').hide().fadeIn('slow');

		setTimeout(function () {
			$('#wave-container').slideUp('fast', function () {
				$(this).parent('div').remove();
			});

			$trackPlayPause.animate({ opacity: 0.2 }, 350);
		}, 3000);
	});

	wavesurfer.load($wave.data('track-url'));

	$trackPlayPause.on('click', () => wavesurfer.playPause());

	$('#wave-time-current-time').on('click', function () {
		$(this).data(
			'time-mode',
			$(this).data('time-mode') === 'elapsed' ? 'remaining' : 'elapsed'
		);
	});

	$(document).on('keydown', function (e) {
		if (e.target !== document.body || document.querySelector('.modal.show')) {
			return;
		}

		if (e.code === ' ') {
			e.preventDefault();

			wavesurfer.playPause();
		}

		if (e.key === 'e') {
			const editUrl = $('[data-role="track:container"]').data('track-edit-url');

			window.location.href = editUrl;
		}
	})

	// --

	// zapobiega odtwarzaniu kilku utworów z różnych kart jednocześnie

	const CURRENT_TRACK_KEY = 'current-track';

	wavesurfer.on('play', function () {
		const currentTrack = $wave.data('track');
		const nowPlayingTrack = window.localStorage.getItem(CURRENT_TRACK_KEY);

		if (currentTrack === nowPlayingTrack) {
			return;
		}

		window.localStorage.setItem(CURRENT_TRACK_KEY, currentTrack);

		const event = new StorageEvent('storage', {
			key: CURRENT_TRACK_KEY,
		});

		window.dispatchEvent(event);
	});

	window.addEventListener('storage', ({ key }) => {
		if (key !== CURRENT_TRACK_KEY) {
			return;
		}

		const currentTrack = $wave.data('track');
		const nowPlayingTrack = window.localStorage.getItem(CURRENT_TRACK_KEY);

		if (document.visibilityState === 'hidden' && (nowPlayingTrack && nowPlayingTrack !== currentTrack)) {
			wavesurfer.pause()
		}
	});

	// --

	$('[data-role="similar-keys:set-key"]').on('click', function () {
		const $keyInput = $form.find('[data-role="similar-tracks:parameter-input"][name="MusicalKey"]');

		if ($keyInput.length === 0) {
			return;
		}

		$keyInput.val(this.dataset.value).trigger('change');

		$('[data-role="similar-tracks:parameters-container"]').removeClass('d-none');
	});

	// --

	// zapobiega skakaniu strony przy filtrowaniu utworów lub zmianie parametrów podobieństwa
	const $page = $('.page-body');

	$page.css('min-height', $page.height());

	// -- similarity

	$('[data-role="similar-tracks:parameters"] input[type=checkbox]').on('change', function () {
		const $param = $(this).closest('[data-role="similar-tracks:parameter"]');
		const $input = $param.find('[data-role="similar-tracks:parameter-input"]');

		const isUnchecked = !$(this).is(':checked');

		$input
			.toggleClass('visible', !isUnchecked)
			.toggleClass('invisible', isUnchecked);

		$input.prop('disabled', isUnchecked);
	});

	$('[data-role="similar-tracks:toggle-visibility"]').on('click', function () {
		$(this).toggleClass('card-header-light');

		$('[data-role="similar-tracks:parameters-container"]').toggleClass('d-none');
	});

	let debounceTimerId;

	const debounce = (func, delay) => {
		clearTimeout(debounceTimerId)

		debounceTimerId = setTimeout(func, delay)
	}

	const $container = $('[data-role="similar-tracks:container"]');
	const $list = $('[data-role="similar-tracks:list"]');

	const reloadSimilarTracks = $form => {
		$list.fadeTo(350, 0.60);

		$.ajax({
			url: $form.attr('action'),
			data: $form.serializeArray(),
			dataType: 'html',
			cache: false,
			success: function (response) {
				$list
					.stop(true, false, true)
					.html(response)
					.fadeTo(100, 1);

				if ($list.find('.d-none').length > 0) {
					$container.find('[data-role="similar-tracks:footer"]').removeClass('d-none');
				}
			}
		});
	};

	$container.on('click', '[data-role="similar-tracks:show-more"]', function () {
		$list.find('tr.d-none').hide().removeClass('d-none').fadeIn('fast');

		$container.find('[data-role="similar-tracks:footer"]').addClass('d-none');
	});

	$(document).on('keydown', e => {
		const activeElementTagName = document.activeElement.tagName.toLowerCase();

		if (activeElementTagName === 'textarea' || activeElementTagName === 'input') {
			return;
		}

		if (e.key === 'k' && (e.ctrlKey || e.metaKey)) {
			e.preventDefault();

			$container.find('[data-role="similar-tracks:filter"]')[0].focus();
		}
	});

	$container.find('[data-role="similar-tracks:filter"]').on('click keyup change', function (e) {
		e.stopPropagation();
		e.preventDefault();

		if (e.key === 'Escape') {
			$(this).val('')
		}

		const values = $(this).val().toLowerCase().replaceAll(', ', ',').split(',');

		$list.find('table tr').filter(function () {
			const rowText = $(this).text().toLowerCase();
			const result = values.every(value => rowText.includes(value.trim()));

			$(this).toggle(result);
		});
	});

	const $form = $container.find('[data-role="similar-tracks:parameters"]');

	$form.data('previous-state', $form.serialize());

	$form.find('input').on('keyup change', function () {
		const currentFormState = $form.serialize();
		const previousFormState = $form.data('previous-state');

		if (currentFormState === previousFormState) {
			return;
		}

		const $input = $(this);
		const isCheckbox = $input.attr('type') === 'checkbox';

		if (!isCheckbox && !$input.val()) {
			return;
		}

		$form.data('previous-state', currentFormState);

		debounce(() => reloadSimilarTracks($form), isCheckbox ? 50 : 400);
	});

	// -- akcje

	$('[data-action="track:toggle-favorite"]').on('click', e => toggleFavorite(e.currentTarget));

	initClassifierMetadataModal();
});
