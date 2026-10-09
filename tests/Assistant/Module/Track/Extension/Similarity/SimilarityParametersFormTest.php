<?php

namespace Assistant\Module\Track\Extension\Similarity;

use PHPUnit\Framework\TestCase;

final class SimilarityParametersFormTest extends TestCase
{
    /** @dataProvider dataGetMode */
    public function testGetMode(array $queryParams, RelatedTracksMode $expected): void
    {
        $method = new \ReflectionMethod(SimilarityParametersForm::class, 'getMode');

        self::assertSame($expected, $method->invoke(null, $queryParams));
    }

    public function dataGetMode(): array
    {
        $mode = SimilarityParametersForm::NAME_MODE;

        return [
            'domyślnie podobne' => [ [], RelatedTracksMode::SIMILAR ],
            'z adresu' => [ [ $mode => 'lift' ], RelatedTracksMode::LIFT ],
            'nieznany preset' => [ [ $mode => 'unknown' ], RelatedTracksMode::SIMILAR ],
            'tablica zamiast wartości' => [ [ $mode => [ 'lift' ] ], RelatedTracksMode::SIMILAR ],
        ];
    }

    public function testMusicalKeySuggestionsListHarmonicRelations(): void
    {
        $method = new \ReflectionMethod(SimilarityParametersForm::class, 'getMusicalKeySuggestions');

        $suggestions = $method->invoke(null, '8A');

        self::assertSame('To major', $suggestions['8B']);
        self::assertSame('Perfect fifth', $suggestions['9A']);
        self::assertSame([], $method->invoke(null, null));
        self::assertSame([], $method->invoke(null, 'Xm'));
    }
}
