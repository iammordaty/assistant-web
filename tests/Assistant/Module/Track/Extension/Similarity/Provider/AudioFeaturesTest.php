<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Extension\Similarity\DeprecationGuard;
use Assistant\Module\Track\Extension\Similarity\TrackFactory;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../DeprecationGuard.php';
require_once __DIR__ . '/../TrackFactory.php';

final class AudioFeaturesTest extends TestCase
{
    use DeprecationGuard;

    private AudioFeatures $provider;

    protected function setUp(): void
    {
        $this->provider = new AudioFeatures();
    }

    /** @dataProvider featureVectors */
    public function testSimilarityValueForFeatureVectors(
        array $baseFeatures,
        array $comparedFeatures,
        ?int $expected,
    ): void {
        $similarity = $this->provider->getSimilarityValue(
            TrackFactory::create(audioFeatures: $baseFeatures),
            TrackFactory::create(audioFeatures: $comparedFeatures),
        );

        self::assertSame($expected, $similarity);
    }

    /** Wektor nie ma reprezentacji w zapytaniu do bazy, więc dostawca nie zawęża kandydatów */
    public function testCriteriaIsAlwaysNull(): void
    {
        self::assertNull($this->provider->getCriteria(TrackFactory::create(audioFeatures: [ 'timbre' => 50 ])));
    }

    public static function featureVectors(): iterable
    {
        yield 'identyczne wektory' => [
            [ 'danceability' => 80, 'timbre' => 30 ],
            [ 'danceability' => 80, 'timbre' => 30 ],
            100,
        ];

        // średnia odległość na wymiar: (20 + 20) / 2 = 20
        yield 'równa odległość na każdym wymiarze' => [
            [ 'danceability' => 80, 'timbre' => 30 ],
            [ 'danceability' => 60, 'timbre' => 50 ],
            80,
        ];

        // średnia odległość na wymiar: (0 + 40) / 2 = 20
        yield 'odległość skupiona na jednym wymiarze' => [
            [ 'danceability' => 80, 'timbre' => 30 ],
            [ 'danceability' => 80, 'timbre' => 70 ],
            80,
        ];

        yield 'wektory skrajnie różne' => [
            [ 'danceability' => 100 ],
            [ 'danceability' => 0 ],
            0,
        ];

        // liczone są tylko wymiary obecne po obu stronach: |20 - 30| = 10
        yield 'częściowo wspólne wymiary' => [
            [ 'danceability' => 10, 'timbre' => 20 ],
            [ 'timbre' => 30, 'mood_happy' => 40 ],
            90,
        ];

        // brak wspólnych wymiarów to brak danych, a nie maksymalna odmienność
        yield 'brak wspólnych wymiarów' => [
            [ 'danceability' => 10 ],
            [ 'timbre' => 30 ],
            null,
        ];

        yield 'brak wektora utworu bazowego' => [ [], [ 'timbre' => 30 ], null ];
        yield 'brak wektora utworu porównywanego' => [ [ 'timbre' => 30 ], [], null ];
        yield 'brak obu wektorów' => [ [], [], null ];
    }
}
