<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Search\Extension\Criteria\MinMaxInfo;
use Assistant\Module\Track\Extension\Similarity\DeprecationGuard;
use Assistant\Module\Track\Extension\Similarity\TrackFactory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

require_once __DIR__ . '/../DeprecationGuard.php';
require_once __DIR__ . '/../TrackFactory.php';

final class BpmTest extends TestCase
{
    use DeprecationGuard;

    private const int TOLERANCE = 5;

    private Bpm $provider;

    protected function setUp(): void
    {
        $this->provider = new Bpm([ 'tolerance' => self::TOLERANCE ]);
    }

    /** @dataProvider bpmPairs */
    public function testSimilarityValueForBpmPair(?float $baseBpm, ?float $comparedBpm, ?int $expected): void
    {
        $similarity = $this->provider->getSimilarityValue(
            TrackFactory::create(bpm: $baseBpm),
            TrackFactory::create(bpm: $comparedBpm),
        );

        self::assertSame($expected, $similarity);
    }

    public function testCriteriaIsWindowAroundBaseBpm(): void
    {
        $minMaxInfo = $this->provider->getCriteria(TrackFactory::create(bpm: 128.4));

        self::assertSame(
            [
                MinMaxInfo::GREATER_THAN_OR_EQUAL => 128.0 - self::TOLERANCE,
                MinMaxInfo::LESS_THAN_OR_EQUAL => 128.0 + self::TOLERANCE,
            ],
            $minMaxInfo->get(),
        );
    }

    /** Utwór bez tempa dawał okno [-5, 5], czyli pustą listę podobnych utworów */
    public function testCriteriaIsNullWithoutBaseBpm(): void
    {
        self::assertNull($this->provider->getCriteria(TrackFactory::create(bpm: null)));
    }

    /** Okno kryterium nie może wpuszczać utworów, którym mapa przyznaje zero punktów */
    public function testCriteriaWindowMatchesSimilarityMapRange(): void
    {
        $similarityMap = (new ReflectionProperty(Bpm::class, 'similarityMap'))->getValue($this->provider);

        self::assertSame(self::TOLERANCE, max(array_keys($similarityMap)));
    }

    public static function bpmPairs(): iterable
    {
        yield 'to samo tempo' => [ 128.0, 128.0, 100 ];
        yield 'różnica poniżej pół uderzenia' => [ 128.0, 128.4, 100 ];
        yield 'różnica jednego uderzenia' => [ 128.0, 129.0, 98 ];
        yield 'różnica dwóch uderzeń' => [ 128.0, 126.0, 93 ];
        yield 'różnica trzech uderzeń' => [ 128.0, 131.0, 70 ];
        yield 'różnica czterech uderzeń' => [ 128.0, 124.0, 60 ];
        yield 'różnica pięciu uderzeń' => [ 128.0, 133.0, 30 ];
        yield 'różnica poza mapą' => [ 128.0, 134.0, 0 ];
        yield 'tempo połówkowe nie jest rozpoznawane' => [ 128.0, 64.0, 0 ];
        // brak danych to nie zero: dostawca nie bierze wtedy udziału w wyniku
        yield 'brak tempa bazowego' => [ null, 128.0, null ];
        yield 'brak tempa porównywanego' => [ 128.0, null, null ];
        yield 'brak obu temp' => [ null, null, null ];
    }
}
