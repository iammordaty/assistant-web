/* global $ */

export default function ($input, onSelect) {
    const followOnSelect = $input.data('follow-on-select');

    $input.attr('autocomplete', 'off');

    const menuClasses = [
        'typeahead',
        'dropdown-menu',
    ];

    if ($input.hasClass('dropdown-menu-right')) {
        menuClasses.push('dropdown-menu-right');
    }

    $input.typeahead({
        autoSelect: false,
        changeInputOnMove: false,
        delay: 100,
        items: 20,
        selectOnBlur: false,
        fitToElement: true,
        theme: 'bootstrap5',
        themes: {
            bootstrap5: {
                menu: `<div class="${menuClasses.join(' ')}" role="listbox"></div>`,
                // klasa d-block (display: block) to szybki fix na tabler-a,
                // który ustawiając display: flex sprawia, że źle wyświetla się podświetlony fragment
                item: '<div class="dropdown-item d-block" role="option"></div>',
                itemContentSelector: '.dropdown-item',
                headerHtml: '<h6 class="dropdown-header"></h6>',
                headerDivider: '<div class="dropdown-divider"></div>'
            },
        },
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

            $input
                .val(item.name)
                .attr('data-track', JSON.stringify(item));

            // $input.typeahead('close'); nie działa, stąd poniższy hak
            $input.trigger('blur').focus();

            if (onSelect && typeof onSelect === 'function') {
                console.log('onSelect', item);

                onSelect(item);
            }

            if (followOnSelect) {
                window.location = item.url;
            }
        },
    });
}
