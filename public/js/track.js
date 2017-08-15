$(document).ready(function() {
	//When checkboxes/radios checked/unchecked, toggle background color
	$('.form-group').on('click','input[type=radio]',function() {
		$(this).closest('.form-group').find('.radio-inline, .radio').removeClass('checked');
		$(this).closest('.radio-inline, .radio').addClass('checked');
	});

	$('.form-group').on('click','input[type=checkbox]',function() {
		$(this).closest('.checkbox-inline, .checkbox').toggleClass('checked');
	});

	//Show additional info text box when relevant checkbox checked
	$('.additional-info-wrap input[type=checkbox]').click(function() {
		if ($(this).is(':checked')) {
			$(this).closest('.additional-info-wrap').find('.additional-info').removeClass('hide').find('input,select').removeAttr('disabled');
		} else {
			$(this).closest('.additional-info-wrap').find('.additional-info').addClass('hide').find('input,select').val('').attr('disabled','disabled');
		}
	});

	//Show additional info text box when relevant radio checked
	$('input[type=radio]').click(function() {
		$(this).closest('.form-group').find('.additional-info-wrap .additional-info').addClass('hide').find('input,select').val('').attr('disabled','disabled');

		if ($(this).closest('.additional-info-wrap').length > 0) {
			$(this).closest('.additional-info-wrap').find('.additional-info').removeClass('hide').find('input,select').removeAttr('disabled');
		}
	});
});

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

	$('[data-role="similar-tracks:show-more"]').on('click', function () {
		$('div.similar-tracks tr.hide').hide().removeClass('hide').fadeIn('fast');
		$(this).remove();
	});

	$('[data-role="play-pause"]').on('click', function () {
		wavesurfer.playPause();
	});

	$('#wave-time-current-time').on('click', function () {
		$(this).data(
			'time-mode',
			$(this).data('time-mode') === 'elapsed' ? 'remaining' : 'elapsed'
		);
	});

	$('[data-role="similar-tracks:toggle-visibility"]').on('click', function () {
		$(this).toggleClass('fa-rotate-180');

		if ($(this).hasClass('fa-rotate-180')) {
			$('[data-role="similar-tracks:parameters"]').removeClass('hide');
		} else {
			$('[data-role="similar-tracks:parameters"]').addClass('hide');
		}
	});
});
