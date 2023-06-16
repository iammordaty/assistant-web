/* global $ */

function formatSeconds(ss) {
	var result = '',
		s,
		m,
		h,
		d;

	s = Math.floor(ss % 60);
	m = Math.floor((ss % 3600) / 60);
	h = Math.floor((ss % 86400) / 3600);
	d = Math.floor((ss % 2592000) / 86400);

	if (d > 0) {
		result += d + ':';
	}

	if (h > 0) {
		result += (h < 10 ? '0' : '') + h + ':';
	}

	result += (m < 10 ? '0' : '') + m + ':';
	result += (s < 10 ? '0' : '') + s;

	return (ss < 0 ? '-' : '') + result;
}

$(function () {
	const $wave = $('#wave-container');

	const wavesurfer = WaveSurfer.create({
		container: document.querySelector('#wave'),
		cursorColor: 'rgb(248, 250, 252)',
		height: $wave[0].offsetHeight,
		progressColor: '#666',
		waveColor: '#999',
	});

	const $trackPlayPause = $('[data-role="track:play-pause"]');

	wavesurfer.on('ready', function () {
		$('#wave-progress').fadeOut('fast', function () {
			$(this).remove();
		});

		$trackPlayPause.fadeTo('fast', 1).addClass('cursor-pointer');

		$('#wave-container').css('visibility', 'visible').hide().fadeIn('slow');
	});

	window.wavesurfer = wavesurfer;

	wavesurfer.on('seek', function () {
		if (!wavesurfer.isPlaying()) {
			wavesurfer.play();
		}
	});

	let wavesurferPlayInterval = null;

	wavesurfer.on('play', function () {
		var $currentTimeIndicator = $('#wave-time-current-time'),
			trackDuration = wavesurfer.getDuration(),
			prefix = '',
			time;

		$trackPlayPause.addClass('active');

		wavesurferPlayInterval = setInterval(
			function () {
				if ($currentTimeIndicator.data('time-mode') === 'elapsed') {
					prefix = '';
					time = wavesurfer.getCurrentTime();
				} else {
					prefix = '-';
					time = trackDuration - wavesurfer.getCurrentTime();
				}

				$('#wave-time-current-time').html(prefix + formatSeconds(time));
			},
			500
		);
	});

	wavesurfer.on('pause', function () {
		$trackPlayPause.removeClass('active');

		clearInterval(wavesurferPlayInterval);
	});

	wavesurfer.on('finish', function () {
		$trackPlayPause.removeClass('active');

		clearInterval(wavesurferPlayInterval);
	});

	wavesurfer.on('error', function () {
		$('#wave-progress').fadeOut('fast', function () {
			$(this).remove();
		});

		$('#wave').html('<p class="lead text-center text-muted">Wystąpił błąd podczas ładowania fali dźwiękowej</p>');
		$('#wave-container').css('visibility', 'visible').hide().fadeIn('slow');

		setTimeout(function () {
			$('#wave-container').slideUp('fast', function () {
				$(this).remove();
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
		if (e.target !== document.body) {
			return;
		}

		if (e.which === 32) {
			e.preventDefault();

			wavesurfer.playPause();
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

	$container.find('[data-role="similar-tracks:filter"]').on('click keyup change', function (e) {
		e.stopPropagation();
		e.preventDefault();

		if (e.which === 27) {
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

	$form.find('input').on('keyup change', function ()  {
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
});
