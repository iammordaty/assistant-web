<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Extension\Similarity\DeprecationGuard;
use Assistant\Module\Track\Extension\Similarity\TrackFactory;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../DeprecationGuard.php';
require_once __DIR__ . '/../TrackFactory.php';

final class PublisherTest extends TestCase
{
    use DeprecationGuard;

    private Publisher $provider;

    protected function setUp(): void
    {
        $this->provider = new Publisher();
    }

    /** @dataProvider publisherPairs */
    public function testSimilarityValueForPublisherPair(
        ?string $basePublisher,
        ?string $comparedPublisher,
        ?int $expected,
    ): void {
        $similarity = $this->provider->getSimilarityValue(
            TrackFactory::create(publisher: $basePublisher),
            TrackFactory::create(publisher: $comparedPublisher),
        );

        self::assertSame($expected, $similarity);
    }

    /** Wytwórnia nie może zawężać kandydatów, bo odcięłaby wszystkie utwory spoza jednej oficyny */
    public function testCriteriaIsAlwaysNull(): void
    {
        self::assertNull($this->provider->getCriteria(TrackFactory::create(publisher: 'Defected')));
    }

    public static function publisherPairs(): iterable
    {
        yield 'ta sama wytwórnia' => [ 'Defected', 'Defected', 100 ];
        yield 'inna wytwórnia' => [ 'Defected', 'Toolroom', 0 ];

        // brak danych to nie zero: dostawca nie bierze wtedy udziału w wyniku
        yield 'brak wytwórni utworu bazowego' => [ null, 'Defected', null ];
        yield 'brak wytwórni utworu porównywanego' => [ 'Defected', null, null ];
        yield 'brak obu wytwórni' => [ null, null, null ];
        yield 'pusta wytwórnia' => [ '', 'Defected', null ];
    }
}
