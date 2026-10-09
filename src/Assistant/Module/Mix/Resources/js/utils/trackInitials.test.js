// node --test src/Assistant/Module/Mix/Resources/js/utils/trackInitials.test.js
// Te same przypadki sprawdza TrackAvatarTest.php
import assert from 'node:assert/strict';
import test from 'node:test';
import { getTrackInitials } from './trackInitials.js';

const cases = [
    [ [ 'Sash!' ], 'Encore Une Fois [Future Breeze Mix]', 'SE' ],
    [ [ 'William Orbit' ], "Barber's Adagio For Strings [ATB Version]", 'WB' ],
    [ [ 'DJ Tiesto' ], 'Lethal Industry [Radio Edit]', 'DL' ],
    [ [ 'Jens' ], "'99 Psycho Strings [Bervoets & De Goeij Remix]", 'J9' ],
    [ [ '040', 'Erica Baxter' ], 'Ibiza Dreams [Original Mix]', '0I' ],
    [ [ 'Guy J' ], 'Alive Again [Original Mix]', 'GA' ],
    [ [ 'Liar' ], 'Giulia [Arapaima X-Mix]', 'LG' ],
    [ [ 'Øystein' ], 'ąkacja', 'ØĄ' ],
    [ [], '', '' ],
];

for (const [ artists, title, expected ] of cases) {
    test(`${artists.join(', ')} - ${title} → ${expected}`, () => {
        assert.equal(getTrackInitials(artists, title), expected);
    });
}
