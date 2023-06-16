/*global $*/

// yolo

$(document).ready(function() {
    var SUGGESTIONS = $('[data-role="track-suggestions:suggestions"]').data('suggestions');

    console.log('Hello!', SUGGESTIONS);

    var $baseTrackSelector = $('[data-role="track-suggestions:base-track-selector"]');
    var $baseTrackInfo = $('[data-role="track-suggestions:base-track-info"]');

    $baseTrackSelector.on('change', function () {
        var track = (SUGGESTIONS.find(el => parseInt(el.track.id) === parseInt(this.value)) || {}).track;

        if (!track) {
            return;
        }

        var releaseDate = new Date(track.releaseDate);
        var month = releaseDate.getMonth() + 1;

        $baseTrackInfo.find('[data-role="track"]').text(track.name);
        $baseTrackInfo.find('[data-role="track-url"]').attr('href', track.url);
        $baseTrackInfo.find('[data-role="year"]').text([
            releaseDate.getDate(),
            month < 10 ? '0' + month : month,
            releaseDate.getFullYear()
        ].join('.'))
        $baseTrackInfo.find('[data-role="release"]').text(track.release.name);
        $baseTrackInfo.find('[data-role="release-url"]').attr('href', track.release.url);
        $baseTrackInfo.find('[data-role="label"]').text(track.release.label);
    });

    // --

    var $suggestion = $('[data-role="track-suggestions:suggestion"]');

    $baseTrackSelector.on('change', function () {
        var suggestions = (SUGGESTIONS.find(el => parseInt(el.track.id) === parseInt(this.value)) || {}).suggestions;

        $suggestion.each(function () {
            var $element = $(this);
            var $dropdownMenu = $element.find('.dropdown-menu');

            $element.find('input').on('keyup', e => setTimeout(() => {
                if (!$dropdownMenu.is(':hidden')) {
                    return;
                }

                const keyCode = (e.keyCode ? e.keyCode : e.which);
                const input = $element.find('input')[0];
                const value = input.value;

                if (keyCode === 40 || (keyCode === 9 && !value && document.activeElement === input)) {
                    $element.find('.dropdown-toggle').dropdown('toggle');
                }
            }, 10));
        });

        console.log('Track changed.', suggestions);

        $suggestion.each(function () {
            var $element = $(this);

            if (!suggestions) {
                $element.find('button').addClass('d-none');

                return;
            }

            var suggestionType = $element.data('suggestion-type');
            var $suggestions = suggestions[suggestionType].map(suggestion => (
                '<a class="dropdown-item" href="#" tabindex="-1">' + suggestion + '</a>')
            );

            if ($suggestions.length === 0) {
                $element.find('button').addClass('d-none');
            }

            // @todo: Odmienić liczebnik. Na luzie, bo obecny select jest niewygodny i trzeba sugestie ugryźć inaczej.
            $element.find('input').attr('placeholder', '(podpowiedzi: ' + $suggestions.length + ')');

            $element.find('.dropdown-menu').html($suggestions);

            // słabo, że to jest tutaj, powinno wyżej (oddzielnie), podobnie jak csski
            $element.find('.dropdown-menu a').on('click', function (e) {
                e.preventDefault();

                $element.find('input').val(this.innerText);
            });
        });
    });

    // --

    $baseTrackSelector.trigger('change'); // yolo
});
