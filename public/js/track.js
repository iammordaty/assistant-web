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
	$('[data-toggle="popover"]').popover();

	var wavesurfer = Object.create(WaveSurfer),
		wavesurferPlayInterval = null;

	wavesurfer.init({
		container: document.querySelector('#wave'),
		waveColor: '#999',
		progressColor: '#666',
		height: 200,
		cursorColor: '#fff'
	});

	wavesurfer.on('ready', function () {
		$('#wave-progress').fadeOut('fast', function () {
			$(this).remove();
		});

		$('#wave-container').css('visibility','visible').hide().fadeIn('slow');
	});

	wavesurfer.on('seek', function () {
		if (wavesurfer.backend.isPaused()) {
			wavesurfer.play();
		}
	});

	wavesurfer.on('play', function () {
		var $currentTimeIndicator = $('#wave-time-current-time'),
			trackDuration = wavesurfer.getDuration(),
			prefix = '',
			time;

		$('[data-role="play-pause"]').removeClass('fa-play').addClass('fa-pause');

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
		$('[data-role="play-pause"]').removeClass('fa-pause').addClass('fa-play');

		clearInterval(wavesurferPlayInterval);
	});

	wavesurfer.on('finish', function () {
		$('[data-role="play-pause"]').removeClass('fa-pause').addClass('fa-play');

		clearInterval(wavesurferPlayInterval);
	});

	wavesurfer.on('error', function () {
		$('#wave-progress').fadeOut('fast', function () {
			$(this).remove();
		});

		$('#wave').html('<p class="lead text-center text-muted">Wystąpił błąd podczas ładowania fali dźwiękowej</p>');
		$('#wave-container').css('visibility','visible').hide().fadeIn('slow');

		setTimeout(function () {
			$('#wave-container').slideUp('fast', function () {
				$(this).remove();
			});
		}, 3000);
	});

	wavesurfer.load($('#wave-container').data('track-url'));

	$('[data-role="play-pause"]').on('click', function () {
		wavesurfer.playPause();
	});

	$('#wave-time-current-time').on('click', function () {
		$(this).data(
			'time-mode',
			$(this).data('time-mode') === 'elapsed' ? 'remaining' : 'elapsed'
		);
	});

	// -- similarity

	$('.additional-info-wrap input[type=checkbox]').on('change', function () {
		const $info = $(this).closest('.additional-info-wrap').find('.additional-info');
		const $input = $info.find('input');

		const isUnchecked = !$(this).is(':checked');

		$info.toggleClass('hide', isUnchecked);
		$input.prop('disabled', isUnchecked);
	});

	$('[data-role="similar-tracks:toggle-visibility"]').on('click', function () {
		const $icon = $(this).find('[data-role="similar-tracks:toggle-visibility-icon"]');

		$icon.toggleClass('fa-rotate-180');

		if ($icon.hasClass('fa-rotate-180')) {
			$('[data-role="similar-tracks:parameters"]').removeClass('hide');
		} else {
			$('[data-role="similar-tracks:parameters"]').addClass('hide');
		}
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
			success: response => $list
				.stop(true, false, true)
				.html(response)
				.fadeTo(100, 1)
		});
	};

	$list.on('click', '[data-role="similar-tracks:show-more"]', function () {
		$list.find('tr.hide').hide().removeClass('hide').fadeIn('fast');

		$(this).remove();
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
