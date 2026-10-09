<?php

namespace Assistant\Module\Track\Extension\Similarity;

use PHPUnit\Framework\TestCase;

final class SimilarityParametersFormTest extends TestCase
{
    /** @dataProvider dataGetMode */
    public function testGetMode(array $queryParams, array $cookieParams, RelatedTracksMode $expected): void
    {
        $method = new \ReflectionMethod(SimilarityParametersForm::class, 'getMode');

        self::assertSame($expected, $method->invoke(null, $queryParams, $cookieParams));
    }

    public function dataGetMode(): array
    {
        $mode = SimilarityParametersForm::NAME_MODE;
        $cookie = SimilarityParametersForm::COOKIE_MODE;

        return [
            'domyślnie planowanie' => [ [], [], RelatedTracksMode::PLANNING ],
            'z adresu' => [ [ $mode => 'lift' ], [], RelatedTracksMode::LIFT ],
            'z ciasteczka' => [ [], [ $cookie => 'cool-down' ], RelatedTracksMode::COOL_DOWN ],
            'adres przed ciasteczkiem' => [ [ $mode => 'planning' ], [ $cookie => 'lift' ], RelatedTracksMode::PLANNING ],
            'nieznany tryb' => [ [ $mode => 'unknown' ], [], RelatedTracksMode::PLANNING ],
            'tablica zamiast wartości' => [ [ $mode => [ 'lift' ] ], [], RelatedTracksMode::PLANNING ],
        ];
    }
}
