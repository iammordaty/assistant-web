<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Search\Extension\Criteria\MinMaxInfo;
use Assistant\Module\Track\Extension\Similarity\DeprecationGuard;
use Assistant\Module\Track\Extension\Similarity\TrackFactory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

require_once __DIR__ . '/../DeprecationGuard.php';
require_once __DIR__ . '/../TrackFactory.php';

final class YearTest extends TestCase
{
    use DeprecationGuard;

    private const int TOLERANCE = 5;

    private Year $provider;

    protected function setUp(): void
    {
        $this->provider = new Year([ 'tolerance' => self::TOLERANCE ]);
    }

    /** @dataProvider yearPairs */
    public function testSimilarityValueForYearPair(?int $baseYear, ?int $comparedYear, int $expected): void
    {
        $similarity = $this->provider->getSimilarityValue(
            TrackFactory::create(year: $baseYear),
            TrackFactory::create(year: $comparedYear),
        );

        self::assertSame($expected, $similarity);
    }

    /** Górna granica okna wynikała wcześniej z roku bieżącego, więc filtr wpuszczał utwory punktowane na zero */
    public function testCriteriaIsSymmetricWindowAroundBaseYear(): void
    {
        $minMaxInfo = $this->provider->getCriteria(TrackFactory::create(year: 2015));

        self::assertSame(
            [
                MinMaxInfo::GREATER_THAN_OR_EQUAL => 2015 - self::TOLERANCE,
                MinMaxInfo::LESS_THAN_OR_EQUAL => 2015 + self::TOLERANCE,
            ],
            $minMaxInfo->get(),
        );
    }

    public function testCriteriaIsNullWithoutBaseYear(): void
    {
        self::assertNull($this->provider->getCriteria(TrackFactory::create(year: null)));
    }

    public function testCriteriaWindowMatchesSimilarityMapRange(): void
    {
        $similarityMap = (new ReflectionProperty(Year::class, 'similarityMap'))->getValue($this->provider);

        self::assertSame(self::TOLERANCE, max(array_keys($similarityMap)));
    }

    public static function yearPairs(): iterable
    {
        yield 'ten sam rok' => [ 2024, 2024, 100 ];
        yield 'różnica roku' => [ 2024, 2023, 98 ];
        yield 'różnica dwóch lat' => [ 2024, 2026, 90 ];
        yield 'różnica trzech lat' => [ 2024, 2021, 70 ];
        yield 'różnica czterech lat' => [ 2024, 2020, 40 ];
        yield 'różnica pięciu lat' => [ 2024, 2019, 20 ];
        yield 'różnica poza mapą' => [ 2024, 2018, 0 ];
        yield 'brak roku bazowego' => [ null, 2024, 0 ];
        yield 'brak roku porównywanego' => [ 2024, null, 0 ];

        // dwa nieznane roczniki nie są tym samym rocznikiem
        yield 'brak obu roczników' => [ null, null, 0 ];
    }
}
