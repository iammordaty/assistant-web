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

        var releaseDate = new Date(track.release.date);
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

                const key = (e.key ? e.key : e.which);
                const input = $element.find('input')[0];
                const value = input.value;

                if (key === 'ArrowDown' || (key === 'Tab' && !value && document.activeElement === input)) {
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

// -- podgląd nazwy pliku

$(function () {
    const $rename = $('[data-role="track-edit:rename"]');

    if ($rename.length === 0) {
        return;
    }

    const $form = $rename.closest('form');
    const $formats = $rename.find('[data-role="track-edit:rename-format"]');
    const $target = $rename.find('[data-role="track-edit:rename-target"]');
    const $preview = $rename.find('[data-role="track-edit:rename-preview"]');

    const manualChoice = $rename.data('manual-choice');
    const previewUrl = $rename.data('preview-url');

    const isManual = () => $formats.filter(':checked').val() === manualChoice;

    /** AI: duplikat wzorca debounce z public/js/track.js - świadomy, do scalenia przy refaktorze */
    let debounceTimerId;

    const debounce = (func, delay) => {
        clearTimeout(debounceTimerId);

        debounceTimerId = setTimeout(func, delay);
    };

    // podgląd liczy backend (ten sam kod, który wykona zapis), żeby podstawienie pól i sanityzacja
    // nazwy nie musiały być powtórzone tutaj
    const reloadPreview = () => {
        $.ajax({
            url: previewUrl,
            method: 'POST',
            data: $form.serializeArray(),
            dataType: 'json',
        }).done(response => {
            if (response.error) {
                $preview.text(response.error).addClass('text-danger');

                return;
            }

            $preview.removeClass('text-danger');

            // w trybie ręcznym zapisana zostanie wpisana nazwa, więc to ona jest podglądem;
            // poza nim pole podąża za podglądem, żeby po przełączeniu było od czego zacząć
            if (isManual()) {
                $preview.text($target.val());

                return;
            }

            $target.val(response.target);
            $preview.text(response.target);
        });
    };

    const syncManualState = () => {
        $target.prop('disabled', !isManual());
    };

    $formats.on('change', function () {
        syncManualState();

        if (isManual()) {
            $target.trigger('focus');

            return;
        }

        reloadPreview();
    });

    $('[data-role="track-edit:field"]').on('input', () => debounce(reloadPreview, 250));

    $target.on('input', function () {
        if (isManual()) {
            $preview.text(this.value).removeClass('text-danger');
        }
    });

    syncManualState();
    reloadPreview();
});
