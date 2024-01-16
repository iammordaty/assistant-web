/* global $ */

export default function (button) {
    button.querySelector('.switch-icon').classList.toggle('active');

    $.ajax({
        url: button.getAttribute('data-action-url'),
        type: 'POST',
        success: () => true,
        error: function (jqXHR, textStatus) {
            button.classList.toggle('active');

            console.log(jqXHR, textStatus);

            alert('Ups, coś poszło nie tak. Zerknij do konsoli, aby zobaczyć co się stało.')
        }
    });
}
