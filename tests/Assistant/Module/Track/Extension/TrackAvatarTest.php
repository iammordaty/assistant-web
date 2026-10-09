<?php

namespace Assistant\Module\Track\Extension;

use PHPUnit\Framework\TestCase;

/** Te same przypadki sprawdza trackInitials.test.js w module Miksów */
final class TrackAvatarTest extends TestCase
{
    public static function dataInitials(): array
    {
        return [
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
    }

    /** @dataProvider dataInitials */
    public function testInitials(array $artists, string $title, string $expected): void
    {
        self::assertSame($expected, TrackAvatar::getInitials($artists, $title));
    }
}
