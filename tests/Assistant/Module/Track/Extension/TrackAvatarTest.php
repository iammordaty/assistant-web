<?php

namespace Assistant\Module\Track\Extension;

use PHPUnit\Framework\TestCase;

/** Te same przypadki sprawdza trackInitials.test.js w module Miksów */
final class TrackAvatarTest extends TestCase
{
    /** @dataProvider dataGetInitials */
    public function testGetInitials(array $artists, string $title, string $expected): void
    {
        self::assertSame($expected, TrackAvatar::getInitials($artists, $title));
    }

    public function dataGetInitials(): array
    {
        return [
            'wykrzyknik w nazwie wykonawcy' => [ [ 'Sash!' ], 'Encore Une Fois [Future Breeze Mix]', 'SE' ],
            'wielowyrazowy wykonawca' => [ [ 'William Orbit' ], "Barber's Adagio For Strings [ATB Version]", 'WB' ],
            'przedrostek DJ' => [ [ 'DJ Tiesto' ], 'Lethal Industry [Radio Edit]', 'DL' ],
            'apostrof przed cyfrą' => [ [ 'Jens' ], "'99 Psycho Strings [Bervoets & De Goeij Remix]", 'J9' ],
            'kilku wykonawców' => [ [ '040', 'Erica Baxter' ], 'Ibiza Dreams [Original Mix]', '0I' ],
            'jednoliterowy wyraz' => [ [ 'Guy J' ], 'Alive Again [Original Mix]', 'GA' ],
            'jednowyrazowe nazwy' => [ [ 'Liar' ], 'Giulia [Arapaima X-Mix]', 'LG' ],
            'znaki spoza ASCII' => [ [ 'Øystein' ], 'ąkacja', 'ØĄ' ],
            'brak danych' => [ [], '', '' ],
        ];
    }
}
