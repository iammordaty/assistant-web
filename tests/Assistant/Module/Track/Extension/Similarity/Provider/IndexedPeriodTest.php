<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Search\Extension\Criteria\MinMaxInfo;
use Assistant\Module\Track\Extension\Similarity\SimilarityTestTrack;
use DateTime;
use PHPUnit\Framework\TestCase;

// AI: testy nie mają własnego autoloadera, a fabryka utworu jest współdzielona przez kilka testów modułu
require_once __DIR__ . '/../SimilarityTestTrack.php';

final class IndexedPeriodTest extends TestCase
{
    private const array PARAMETERS = [ 'tolerance' => 2, 'max_tolerance' => 4, 'min_tracks' => 30 ];

    /** @dataProvider dataSimilarityValue */
    public function testSimilarityValueDependsOnMonthDistance(string $baseDate, string $comparedDate, int $expected): void
    {
        $provider = new IndexedPeriod(self::PARAMETERS);

        $similarityValue = $provider->getSimilarityValue(
            SimilarityTestTrack::create(indexedDate: new DateTime($baseDate)),
            SimilarityTestTrack::create(indexedDate: new DateTime($comparedDate)),
        );

        self::assertSame($expected, $similarityValue);
    }

    public function dataSimilarityValue(): array
    {
        return [
            'ten sam miesiąc, inny dzień' => [ '2026-04-01', '2026-04-30', 100 ],
            'sąsiedni miesiąc' => [ '2026-04-15', '2026-05-02', 95 ],
            'przez granicę roku' => [ '2026-01-10', '2025-12-20', 95 ],
            'dwa miesiące' => [ '2026-04-15', '2026-02-15', 85 ],
            'poza mapą' => [ '2026-04-15', '2025-04-15', 40 ],
        ];
    }

    public function testCriteriaCoverWholeMonthsOfWindow(): void
    {
        $provider = new IndexedPeriod(self::PARAMETERS);

        $criteria = $provider->getCriteriaWithin(SimilarityTestTrack::create(indexedDate: new DateTime('2026-04-15 13:45')), 2);

        $range = $criteria->get();

        self::assertEquals(new DateTime('2026-02-01 00:00'), $range[MinMaxInfo::GREATER_THAN_OR_EQUAL]);
        self::assertEquals(new DateTime('2026-07-01 00:00'), $range[MinMaxInfo::LESS_THAN]);
    }

    public function testTolerancesGrowUpToMaximum(): void
    {
        $provider = new IndexedPeriod(self::PARAMETERS);

        self::assertSame([ 2, 3, 4 ], $provider->getTolerances());
    }
}
