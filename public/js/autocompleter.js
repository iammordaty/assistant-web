/*global $*/

// yolo

const initAutocompleter = $input => {
    const followOnSelect = $input.data('follow-on-select');

    $input.attr('autocomplete', 'off');

    $input.typeahead({
        autoSelect: false,
        changeInputOnMove: false,
        delay: 100,
        items: 20,
        selectOnBlur: false,
        source: (query, process) => $.ajax({
            dataType: 'json',
            url: $input.data('url') + '?query=' + query,
        }).done(response => process(response)),
        matcher: () => true,
        highlighter: function (item) {
            const regex = new RegExp('(' + this.query + ')', 'gi');

            return item.replace(regex, "<strong>$1</strong>");
        },
        getText: item => item.name,
        select: function () {
            const $activeMenuItem = this.$menu.find('.active');

            if (!$activeMenuItem.length) {
                $input.val(this.value).parents('form').submit();

                return true;
            }

            const item = $activeMenuItem.data('value');

            $input.val(item.name);

            // $input.typeahead('close'); nie działa, stąd poniższy hak
            $input.trigger('blur').focus();

            if (followOnSelect) {
                window.location = item.url;
            }
        },
    });
}

$(() => {
    const $inputs = $('[data-role="autocompleter"]');

    $inputs.each((i, input) => initAutocompleter($(input)));
});
