<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Track\Extension\Similarity\SimilarityTestTrack;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Similarity/SimilarityTestTrack.php';

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

    /** @dataProvider dataFromTrack */
    public function testFromTrack(?string $genre, ?float $bpm, ?string $initialKey, string $color, int $tint, ?string $scale): void
    {
        $avatar = TrackAvatar::fromTrack(SimilarityTestTrack::create(bpm: $bpm, initialKey: $initialKey, genre: $genre));

        self::assertSame([ $color, $tint, $scale ], [ $avatar->color, $avatar->tint, $avatar->scale ]);
    }

    public function dataFromTrack(): array
    {
        return [
            'wolny house w molu' => [ 'House', 98.1, '5A', 'blue', 5, 'A' ],
            'progressive house w dur' => [ 'Progressive House', 124.0, '8B', 'cyan', 12, 'B' ],
            'techno na granicy progu' => [ 'Techno', 130.0, '11A', 'purple', 22, 'A' ],
            'szybkie techno' => [ 'Hard Techno', 150.0, '5A', 'purple', 36, 'A' ],
            'nieznany gatunek' => [ 'Ambient', 90.0, '1B', 'secondary', 5, 'B' ],
            'brak danych' => [ null, null, null, 'secondary', 5, null ],
            'tonacja spoza notacji Camelot' => [ 'Electro House', 128.0, 'Am', 'orange', 12, null ],
        ];
    }

    public function testFromGuid(): void
    {
        $avatar = TrackAvatar::fromGuid('kaufmann-fluppe');

        self::assertSame([ 'KA', 'secondary', null ], [ $avatar->initials, $avatar->color, $avatar->scale ]);
    }
}
