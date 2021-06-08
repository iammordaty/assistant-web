/*global $*/

// yolo

var NO_SUGGESTIONS = [{ track: [], suggestions: [] }];

$(document).ready(function() {
    var rawSuggestions = $('[data-role="track-suggestions:suggestions"]').data('suggestions');
    var SUGGESTIONS = rawSuggestions.length === 0 ? NO_SUGGESTIONS : rawSuggestions;

    var $baseTrackSelector = $('[data-role="track-suggestions:base-track-selector"]');
    var $baseTrackInfo = $('[data-role="track-suggestions:base-track-info"]');

    $baseTrackSelector.on('change', function () {
        var track = SUGGESTIONS.find(el => parseInt(el.track.id) === parseInt(this.value)).track;
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

    $suggestion.each(function () {
        var $element = $(this);
        var $dropdownMenu = $element.find('.dropdown-menu');

        $dropdownMenu.css('width', $element.width() + 'px');
        $dropdownMenu.find('li a').css('padding', '6px 20px 6px 10px');

        $element.find('input').on('focus', function (e) {
            // var value = this.value;
            //
            // setTimeout(function () {
            //     if (!value && $dropdownMenu.is(':hidden')) {
            //         $element.find('.dropdown-toggle').dropdown('toggle');
            //     }
            // }, 100)
        });
    });

    $baseTrackSelector.on('change', function () {
        var suggestions = SUGGESTIONS.find(el => parseInt(el.track.id) === parseInt(this.value)).suggestions;

        console.log({suggestions, SUGGESTIONS});

        $suggestion.each(function () {
            var $element = $(this);

            console.log({
                suggestionType: $element.data('suggestion-type'),
                suggestions: suggestions[$element.data('suggestion-type')],
            })

            var suggestionType = $element.data('suggestion-type');
            var $suggestions = suggestions[suggestionType].map(suggestion => (
                '<li><a href="#" tabindex="-1">' + suggestion + '</a>')
            );

            console.log({
                $suggestions: $suggestions,
                $suggestions_l: $suggestions.length,
            })

            if ($suggestions.length === 0) {
                $element.find('button').addClass('disabled');
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
