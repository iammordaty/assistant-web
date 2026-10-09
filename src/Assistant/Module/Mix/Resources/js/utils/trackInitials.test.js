// node --test src/Assistant/Module/Mix/Resources/js/utils/trackInitials.test.js
// Te same przypadki sprawdza TrackAvatarTest.php

import assert from 'node:assert/strict';
import test from 'node:test';
import { getTrackInitials } from './trackInitials.js';

const cases = {
    'wykrzyknik w nazwie wykonawcy': [ [ 'Sash!' ], 'Encore Une Fois [Future Breeze Mix]', 'SE' ],
    'wielowyrazowy wykonawca': [ [ 'William Orbit' ], "Barber's Adagio For Strings [ATB Version]", 'WB' ],
    'przedrostek DJ': [ [ 'DJ Tiesto' ], 'Lethal Industry [Radio Edit]', 'DL' ],
    'apostrof przed cyfrą': [ [ 'Jens' ], "'99 Psycho Strings [Bervoets & De Goeij Remix]", 'J9' ],
    'kilku wykonawców': [ [ '040', 'Erica Baxter' ], 'Ibiza Dreams [Original Mix]', '0I' ],
    'jednoliterowy wyraz': [ [ 'Guy J' ], 'Alive Again [Original Mix]', 'GA' ],
    'jednowyrazowe nazwy': [ [ 'Liar' ], 'Giulia [Arapaima X-Mix]', 'LG' ],
    'znaki spoza ASCII': [ [ 'Øystein' ], 'ąkacja', 'ØĄ' ],
    'brak danych': [ [], '', '' ],
};

for (const [ name, [ artists, title, expected ] ] of Object.entries(cases)) {
    test(name, () => {
        assert.equal(getTrackInitials({ artists, title }), expected);
    });
}
